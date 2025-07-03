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
    <title>Formulario de Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Crear/Editar Usuario</h1>
        <form id="form-usuario">
            <div class="row">
                <div class="col-md-6">
                    <label>Nombre de Usuario</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="col-md-6">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label>Nombre</label>
                    <input type="text" class="form-control" name="nombre" required>
                </div>
                <div class="col-md-6">
                    <label>Apellido</label>
                    <input type="text" class="form-control" name="apellido">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <label>Rol</label>
                    <select class="form-control" name="rol" required>
                        <option value="">Seleccionar rol</option>
                        <option value="administrador">Administrador</option>
                        <option value="encargado">Encargado</option>
                        <option value="operador">Operador</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Contraseña</label>
                    <input type="password" class="form-control" name="password">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Guardar Usuario</button>
                <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>