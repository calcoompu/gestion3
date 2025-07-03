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
    
    // Verificar si la tabla tiene las columnas necesarias para papelera
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'eliminado'");
    $tiene_eliminado = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'fecha_eliminacion'");
    $tiene_fecha_eliminacion = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'eliminado_por'");
    $tiene_eliminado_por = $stmt->fetch();
    
    switch ($accion) {
        case 'inactivar':
            if (!isset($data['id'])) {
                throw new Exception('ID de cliente requerido');
            }
            
            $cliente_id = intval($data['id']);
            
            // Verificar que el cliente existe y está activo
            if ($tiene_eliminado) {
                $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE id = ? AND activo = 1 AND eliminado = 0");
            } else {
                $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE id = ? AND activo = 1");
            }
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                throw new Exception('Cliente no encontrado o ya está inactivo');
            }
            
            // Inactivar cliente
            $stmt = $pdo->prepare("UPDATE clientes SET activo = 0, fecha_modificacion = NOW() WHERE id = ?");
            $stmt->execute([$cliente_id]);
            
            $response = [
                'success' => true,
                'message' => 'Cliente "' . $cliente['nombre'] . ' ' . $cliente['apellido'] . '" inactivado correctamente'
            ];
            break;
            
        case 'eliminar_suave':
            if (!isset($data['id'])) {
                throw new Exception('ID de cliente requerido');
            }
            
            $cliente_id = intval($data['id']);
            
            // Verificar que el cliente existe
            if ($tiene_eliminado) {
                $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE id = ? AND eliminado = 0");
            } else {
                $stmt = $pdo->prepare("SELECT id, nombre, apellido FROM clientes WHERE id = ?");
            }
            $stmt->execute([$cliente_id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                throw new Exception('Cliente no encontrado');
            }
            
            // Verificar pedidos y facturas pendientes (solo si las tablas existen)
            $pedidos_pendientes = 0;
            $facturas_pendientes = 0;
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND estado IN ('pendiente', 'procesando')");
                $stmt->execute([$cliente_id]);
                $pedidos_pendientes = $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Tabla pedidos no existe, continuar
            }
            
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE cliente_id = ? AND estado IN ('pendiente', 'vencida')");
                $stmt->execute([$cliente_id]);
                $facturas_pendientes = $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Tabla facturas no existe, continuar
            }
            
            if ($pedidos_pendientes > 0 || $facturas_pendientes > 0) {
                $mensaje_error = 'No se puede eliminar. El cliente tiene ';
                if ($pedidos_pendientes > 0) {
                    $mensaje_error .= $pedidos_pendientes . ' pedido(s) pendiente(s)';
                }
                if ($pedidos_pendientes > 0 && $facturas_pendientes > 0) {
                    $mensaje_error .= ' y ';
                }
                if ($facturas_pendientes > 0) {
                    $mensaje_error .= $facturas_pendientes . ' factura(s) pendiente(s)';
                }
                throw new Exception($mensaje_error);
            }
            
            if ($tiene_eliminado) {
                // Usar sistema de papelera
                $sql = "UPDATE clientes SET eliminado = 1";
                $params = [];
                
                if ($tiene_fecha_eliminacion) {
                    $sql .= ", fecha_eliminacion = NOW()";
                }
                
                if ($tiene_eliminado_por) {
                    $sql .= ", eliminado_por = ?";
                    $params[] = $usuario_actual;
                }
                
                $sql .= ", fecha_modificacion = NOW() WHERE id = ?";
                $params[] = $cliente_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $response = [
                    'success' => true,
                    'message' => 'Cliente "' . $cliente['nombre'] . ' ' . $cliente['apellido'] . '" enviado a la papelera'
                ];
            } else {
                // Eliminación tradicional (sin papelera)
                $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
                $stmt->execute([$cliente_id]);
                
                $response = [
                    'success' => true,
                    'message' => 'Cliente "' . $cliente['nombre'] . ' ' . $cliente['apellido'] . '" eliminado correctamente'
                ];
            }
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $accion);
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Error PDO en gestionar_cliente_papelera.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en gestionar_cliente_papelera.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log("Error fatal en gestionar_cliente_papelera.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}

