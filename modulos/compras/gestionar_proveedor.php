<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$accion = $data['accion'] ?? '';

try {
    $pdo = conectarDB();
    
    switch ($accion) {
        case 'crear':
            $codigo = generarCodigoProveedor($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO proveedores (codigo, razon_social, nombre_comercial, cuit, direccion, 
                                       ciudad, provincia, codigo_postal, telefono, email, 
                                       contacto_nombre, contacto_telefono, contacto_email, 
                                       condiciones_pago, dias_entrega, activo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $codigo,
                $data['razon_social'],
                $data['nombre_comercial'] ?? null,
                $data['cuit'] ?? null,
                $data['direccion'] ?? null,
                $data['ciudad'] ?? null,
                $data['provincia'] ?? null,
                $data['codigo_postal'] ?? null,
                $data['telefono'] ?? null,
                $data['email'] ?? null,
                $data['contacto_nombre'] ?? null,
                $data['contacto_telefono'] ?? null,
                $data['contacto_email'] ?? null,
                $data['condiciones_pago'] ?? null,
                $data['dias_entrega'] ?? 0
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor creado exitosamente']);
            break;
            
        case 'editar':
            $stmt = $pdo->prepare("
                UPDATE proveedores 
                SET razon_social = ?, nombre_comercial = ?, cuit = ?, direccion = ?, 
                    ciudad = ?, provincia = ?, codigo_postal = ?, telefono = ?, email = ?, 
                    contacto_nombre = ?, contacto_telefono = ?, contacto_email = ?, 
                    condiciones_pago = ?, dias_entrega = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['razon_social'],
                $data['nombre_comercial'] ?? null,
                $data['cuit'] ?? null,
                $data['direccion'] ?? null,
                $data['ciudad'] ?? null,
                $data['provincia'] ?? null,
                $data['codigo_postal'] ?? null,
                $data['telefono'] ?? null,
                $data['email'] ?? null,
                $data['contacto_nombre'] ?? null,
                $data['contacto_telefono'] ?? null,
                $data['contacto_email'] ?? null,
                $data['condiciones_pago'] ?? null,
                $data['dias_entrega'] ?? 0,
                $data['id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente']);
            break;
            
        case 'eliminar':
            // Verificar si tiene compras asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM compras WHERE proveedor_id = ? AND activo = 1");
            $stmt->execute([$data['id']]);
            $compras = $stmt->fetch();
            
            if ($compras['total'] > 0) {
                echo json_encode(['success' => false, 'message' => 'No se puede eliminar el proveedor porque tiene compras asociadas']);
                break;
            }
            
            $stmt = $pdo->prepare("UPDATE proveedores SET activo = 0 WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado exitosamente']);
            break;
            
        case 'activar':
            $stmt = $pdo->prepare("UPDATE proveedores SET activo = 1 WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Proveedor activado exitosamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $accion]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generarCodigoProveedor($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) + 1 as siguiente FROM proveedores");
    $resultado = $stmt->fetch();
    return 'PROV-' . str_pad($resultado['siguiente'], 7, '0', STR_PAD_LEFT);
}
?>