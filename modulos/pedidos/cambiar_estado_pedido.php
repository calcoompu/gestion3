<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$pedido_id = $data['pedido_id'] ?? 0;
$nuevo_estado = $data['estado'] ?? '';

try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $pedido_id]);
    
    echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>