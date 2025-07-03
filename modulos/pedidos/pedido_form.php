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
    <title>Formulario de Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Crear/Editar Pedido</h1>
        <form id="form-pedido">
            <div class="row">
                <div class="col-md-6">
                    <label>Cliente</label>
                    <select class="form-control" name="cliente_id" required>
                        <option value="">Seleccionar cliente</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Fecha de Entrega</label>
                    <input type="date" class="form-control" name="fecha_entrega">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">Guardar Pedido</button>
                <a href="pedidos.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>