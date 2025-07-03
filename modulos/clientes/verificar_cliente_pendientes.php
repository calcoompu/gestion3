<?php
require_once '../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

iniciarSesionSegura();
requireLogin('../../login.php');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['cliente_id'])) {
        throw new Exception('ID de cliente requerido');
    }
    
    $cliente_id = intval($data['cliente_id']);
    $pdo = conectarDB();
    
    // Verificar pedidos pendientes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND estado IN ('pendiente', 'procesando')");
    $stmt->execute([$cliente_id]);
    $pedidos_pendientes = $stmt->fetchColumn();
    
    // Verificar facturas pendientes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE cliente_id = ? AND estado IN ('pendiente', 'vencida')");
    $stmt->execute([$cliente_id]);
    $facturas_pendientes = $stmt->fetchColumn();
    
    $tiene_pendientes = ($pedidos_pendientes > 0 || $facturas_pendientes > 0);
    
    echo json_encode([
        'success' => true,
        'tiene_pendientes' => $tiene_pendientes,
        'pedidos_pendientes' => $pedidos_pendientes,
        'facturas_pendientes' => $facturas_pendientes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

