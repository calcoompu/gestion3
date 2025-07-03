<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$pdo = conectarDB();
$id = $_GET['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle del Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Detalle del Pedido #<?php echo $id; ?></h1>
        <div class="card">
            <div class="card-body">
                <h5>Información del Pedido</h5>
                <!-- Contenido del detalle -->
            </div>
        </div>
    </div>
</body>
</html>