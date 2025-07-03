<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$accion = $data['accion'] ?? '';

try {
    $pdo = conectarDB();
    
    switch ($accion) {
        case 'crear':
            echo json_encode(['success' => true, 'message' => 'Factura creada exitosamente']);
            break;
            
        case 'editar':
            echo json_encode(['success' => true, 'message' => 'Factura actualizada exitosamente']);
            break;
            
        case 'eliminar':
            echo json_encode(['success' => true, 'message' => 'Factura eliminada exitosamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>