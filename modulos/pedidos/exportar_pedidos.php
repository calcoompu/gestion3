<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

// Lógica para exportar pedidos a Excel/CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="pedidos.csv"');

$pdo = conectarDB();
$stmt = $pdo->query("SELECT * FROM pedidos");

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Código', 'Cliente', 'Fecha', 'Estado', 'Total']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
?>