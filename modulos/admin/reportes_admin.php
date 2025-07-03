<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$pdo = conectarDB();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes Administrativos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Reportes Administrativos</h1>
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Actividad de Usuarios</h5>
                        <p>Reporte de actividad y accesos</p>
                        <a href="#" class="btn btn-primary">Ver Reporte</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Auditoría del Sistema</h5>
                        <p>Registro de cambios y modificaciones</p>
                        <a href="#" class="btn btn-primary">Ver Reporte</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Estadísticas Generales</h5>
                        <p>Resumen general del sistema</p>
                        <a href="#" class="btn btn-primary">Ver Reporte</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>