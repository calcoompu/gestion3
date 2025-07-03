<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="facturas.csv"');

$pdo = conectarDB();
$stmt = $pdo->query("SELECT * FROM facturas");

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Código', 'Cliente', 'Fecha', 'Estado', 'Total']);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
?>