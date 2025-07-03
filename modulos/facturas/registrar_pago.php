<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$factura_id = $data['factura_id'] ?? 0;
$monto = $data['monto'] ?? 0;
$metodo_pago = $data['metodo_pago'] ?? '';

try {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("INSERT INTO factura_pagos (factura_id, monto, metodo_pago, fecha_pago, usuario_id) VALUES (?, ?, ?, CURDATE(), ?)");
    $stmt->execute([$factura_id, $monto, $metodo_pago, $_SESSION['id_usuario']]);
    
    echo json_encode(['success' => true, 'message' => 'Pago registrado exitosamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>