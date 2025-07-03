<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$factura_id = $_GET['id'] ?? 0;

// Lógica para generar PDF de factura
// Aquí iría la implementación con TCPDF o similar

echo "PDF de factura #" . $factura_id;
?>