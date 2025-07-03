<?php
require_once '../../config/config.php';

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Inicializar sesión
iniciarSesionSegura();
requireLogin('../../login.php');

try {
    // Obtener datos de la solicitud
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos de solicitud inválidos');
    }
    
    if (!isset($data['accion'])) {
        throw new Exception('Acción requerida');
    }
    
    $accion = $data['accion'];
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $usuario_actual = $_SESSION['usuario'] ?? 'Sistema';
    
    switch ($accion) {
        case 'restaurar':
            if (!isset($data['id'])) {
                throw new Exception('ID de cliente requerido');
            }
            
            $cliente_id = intval($data['id']);
            
            // Verificar que el cliente existe y está eliminado
            $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE id = ? AND eliminado = 1");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                throw new Exception('Cliente no encontrado en la papelera');
            }
            
            // Restaurar cliente (eliminado = 0, limpiar campos de eliminación)
            $stmt = $pdo->prepare("UPDATE clientes SET eliminado = 0, fecha_eliminacion = NULL, eliminado_por = NULL, activo = 1, fecha_modificacion = NOW() WHERE id = ?");
            $stmt->execute([$cliente_id]);
            
            $response = [
                'success' => true,
                'message' => 'Cliente "' . $cliente['nombre'] . ' ' . $cliente['apellido'] . '" restaurado correctamente'
            ];
            break;
            
        case 'eliminar_definitivo':
            if (!isset($data['id'])) {
                throw new Exception('ID de cliente requerido');
            }
            
            $cliente_id = intval($data['id']);
            
            // Verificar que el cliente existe y está eliminado
            $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE id = ? AND eliminado = 1");
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                throw new Exception('Cliente no encontrado en la papelera');
            }
            
            // Verificar pedidos y facturas pendientes antes de eliminar definitivamente
            $pedidos_pendientes = 0;
            $facturas_pendientes = 0;
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND estado IN ('pendiente', 'procesando')");
                $stmt->execute([$cliente_id]);
                $pedidos_pendientes = $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Tabla no existe, continuar
            }
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE cliente_id = ? AND estado IN ('pendiente', 'vencida')");
                $stmt->execute([$cliente_id]);
                $facturas_pendientes = $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Tabla no existe, continuar
            }
            
            if ($pedidos_pendientes > 0 || $facturas_pendientes > 0) {
                throw new Exception('No se puede eliminar definitivamente. El cliente tiene ' . 
                    ($pedidos_pendientes > 0 ? $pedidos_pendientes . ' pedido(s) pendiente(s)' : '') .
                    ($pedidos_pendientes > 0 && $facturas_pendientes > 0 ? ' y ' : '') .
                    ($facturas_pendientes > 0 ? $facturas_pendientes . ' factura(s) pendiente(s)' : ''));
            }
            
            // Eliminar definitivamente
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            
            $response = [
                'success' => true,
                'message' => 'Cliente "' . $cliente['nombre'] . ' ' . $cliente['apellido'] . '" eliminado definitivamente'
            ];
            break;
            
        case 'vaciar_papelera':
            // Obtener todos los clientes en papelera
            $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE eliminado = 1");
            $stmt->execute();
            $clientes_papelera = $stmt->fetchAll();
            
            if (empty($clientes_papelera)) {
                throw new Exception('La papelera está vacía');
            }
            
            $eliminados = 0;
            $con_pendientes = 0;
            
            foreach ($clientes_papelera as $cliente) {
                // Verificar pendientes para cada cliente
                $pedidos_pendientes = 0;
                $facturas_pendientes = 0;
                
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND estado IN ('pendiente', 'procesando')");
                    $stmt->execute([$cliente['id']]);
                    $pedidos_pendientes = $stmt->fetchColumn();
                } catch (PDOException $e) {
                    // Tabla no existe, continuar
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE cliente_id = ? AND estado IN ('pendiente', 'vencida')");
                    $stmt->execute([$cliente['id']]);
                    $facturas_pendientes = $stmt->fetchColumn();
                } catch (PDOException $e) {
                    // Tabla no existe, continuar
                }
                
                if ($pedidos_pendientes == 0 && $facturas_pendientes == 0) {
                    // Eliminar definitivamente
                    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
                    $stmt->execute([$cliente['id']]);
                    $eliminados++;
                } else {
                    $con_pendientes++;
                }
            }
            
            $mensaje = "Papelera procesada: $eliminados cliente(s) eliminado(s) definitivamente";
            if ($con_pendientes > 0) {
                $mensaje .= ", $con_pendientes cliente(s) no se pudieron eliminar por tener pendientes";
            }
            
            $response = [
                'success' => true,
                'message' => $mensaje
            ];
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $accion);
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Error PDO en gestionar_papelera.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en gestionar_papelera.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log("Error fatal en gestionar_papelera.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}

