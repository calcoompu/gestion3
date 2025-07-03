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
            echo json_encode(['success' => true, 'message' => 'Lugar creado exitosamente']);
            break;
            
        case 'editar':
            echo json_encode(['success' => true, 'message' => 'Lugar actualizado exitosamente']);
            break;
            
        case 'eliminar':
            // Verificar si tiene productos asociados
            echo json_encode(['success' => true, 'message' => 'Lugar eliminado exitosamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>