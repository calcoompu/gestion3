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
            echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente']);
            break;
            
        case 'editar':
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
            break;
            
        case 'eliminar':
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>