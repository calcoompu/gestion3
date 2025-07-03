<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

$pdo = conectarDB();
$stmt = $pdo->query("SELECT id, codigo, nombre, precio_venta FROM productos WHERE activo = 1");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($productos);
?>