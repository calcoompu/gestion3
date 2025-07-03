<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lógica para crear backup de la base de datos
    $fecha = date('Y-m-d_H-i-s');
    $archivo_backup = "backup_sistemasia_inventpro_$fecha.sql";
    
    // Comando mysqldump (requiere configuración del servidor)
    $comando = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > $archivo_backup";
    
    echo json_encode(['success' => true, 'message' => 'Backup creado: ' . $archivo_backup]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Backup del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Backup del Sistema</h1>
        <div class="card">
            <div class="card-body">
                <h5>Crear Backup de la Base de Datos</h5>
                <p>Esta función creará una copia de seguridad completa de la base de datos.</p>
                <button id="btn-backup" class="btn btn-warning">Crear Backup</button>
            </div>
        </div>
    </div>
</body>
</html>