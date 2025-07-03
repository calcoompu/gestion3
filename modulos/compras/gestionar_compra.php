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
            $codigo = generarCodigoCompra($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO compras (codigo, proveedor_id, fecha_compra, fecha_entrega_estimada, 
                                   estado, observaciones, usuario_id, total) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codigo,
                $data['proveedor_id'],
                $data['fecha_compra'],
                $data['fecha_entrega_estimada'] ?? null,
                $data['estado'] ?? 'pendiente',
                $data['observaciones'] ?? null,
                $_SESSION['id_usuario'],
                $data['total'] ?? 0
            ]);
            
            $compra_id = $pdo->lastInsertId();
            
            // Insertar detalles si existen
            if (!empty($data['productos'])) {
                foreach ($data['productos'] as $producto) {
                    $stmt = $pdo->prepare("
                        INSERT INTO compra_detalles (compra_id, producto_id, cantidad_pedida, 
                                                   precio_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $compra_id,
                        $producto['producto_id'],
                        $producto['cantidad'],
                        $producto['precio_unitario'],
                        $producto['subtotal']
                    ]);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Compra creada exitosamente', 'id' => $compra_id]);
            break;
            
        case 'editar':
            $stmt = $pdo->prepare("
                UPDATE compras 
                SET proveedor_id = ?, fecha_compra = ?, fecha_entrega_estimada = ?, 
                    estado = ?, observaciones = ?, total = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['proveedor_id'],
                $data['fecha_compra'],
                $data['fecha_entrega_estimada'] ?? null,
                $data['estado'],
                $data['observaciones'] ?? null,
                $data['total'] ?? 0,
                $data['id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Compra actualizada exitosamente']);
            break;
            
        case 'eliminar':
            // Verificar que la compra esté en estado pendiente
            $stmt = $pdo->prepare("SELECT estado FROM compras WHERE id = ?");
            $stmt->execute([$data['id']]);
            $compra = $stmt->fetch();
            
            if ($compra['estado'] !== 'pendiente') {
                echo json_encode(['success' => false, 'message' => 'Solo se pueden eliminar compras pendientes']);
                break;
            }
            
            $stmt = $pdo->prepare("UPDATE compras SET activo = 0 WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Compra eliminada exitosamente']);
            break;
            
        case 'cambiar_estado':
            $stmt = $pdo->prepare("UPDATE compras SET estado = ? WHERE id = ?");
            $stmt->execute([$data['estado'], $data['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Estado actualizado exitosamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $accion]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function generarCodigoCompra($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) + 1 as siguiente FROM compras");
    $resultado = $stmt->fetch();
    return 'COMP-' . str_pad($resultado['siguiente'], 7, '0', STR_PAD_LEFT);
}
?>