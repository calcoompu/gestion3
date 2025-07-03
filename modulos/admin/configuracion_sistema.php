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
    <title>Configuración del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>Configuración del Sistema</h1>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Datos de la Empresa</h5>
                    </div>
                    <div class="card-body">
                        <form id="form-empresa">
                            <div class="mb-3">
                                <label>Razón Social</label>
                                <input type="text" class="form-control" name="razon_social">
                            </div>
                            <div class="mb-3">
                                <label>CUIT</label>
                                <input type="text" class="form-control" name="cuit">
                            </div>
                            <div class="mb-3">
                                <label>Dirección</label>
                                <input type="text" class="form-control" name="direccion">
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Configuración Regional</h5>
                    </div>
                    <div class="card-body">
                        <form id="form-regional">
                            <div class="mb-3">
                                <label>País</label>
                                <select class="form-control" name="pais">
                                    <option value="Argentina">Argentina</option>
                                    <option value="Chile">Chile</option>
                                    <option value="Uruguay">Uruguay</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Moneda</label>
                                <select class="form-control" name="moneda">
                                    <option value="ARS">Peso Argentino (ARS)</option>
                                    <option value="USD">Dólar (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>