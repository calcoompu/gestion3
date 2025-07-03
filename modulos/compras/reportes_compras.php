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
    <title>Reportes de Compras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-chart-bar"></i> Reportes de Compras</h1>
                    <a href="compras.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Compras
                    </a>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-calendar-alt"></i> Compras por Período</h5>
                            </div>
                            <div class="card-body">
                                <form id="form-periodo">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha Desde</label>
                                        <input type="date" class="form-control" name="fecha_desde" 
                                               value="<?php echo date('Y-m-01'); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Fecha Hasta</label>
                                        <input type="date" class="form-control" name="fecha_hasta" 
                                               value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="generarReportePeriodo()">
                                        <i class="fas fa-search"></i> Generar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-truck"></i> Análisis por Proveedor</h5>
                            </div>
                            <div class="card-body">
                                <form id="form-proveedor">
                                    <div class="mb-3">
                                        <label class="form-label">Proveedor</label>
                                        <select class="form-control" name="proveedor_id">
                                            <option value="">Todos los proveedores</option>
                                            <!-- Cargar proveedores dinámicamente -->
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Período (meses)</label>
                                        <select class="form-control" name="periodo">
                                            <option value="3">Últimos 3 meses</option>
                                            <option value="6">Últimos 6 meses</option>
                                            <option value="12">Último año</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="generarReporteProveedor()">
                                        <i class="fas fa-search"></i> Generar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-box"></i> Productos Más Comprados</h5>
                            </div>
                            <div class="card-body">
                                <form id="form-productos">
                                    <div class="mb-3">
                                        <label class="form-label">Período</label>
                                        <select class="form-control" name="periodo">
                                            <option value="30">Últimos 30 días</option>
                                            <option value="90">Últimos 3 meses</option>
                                            <option value="365">Último año</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Cantidad a mostrar</label>
                                        <select class="form-control" name="limite">
                                            <option value="10">Top 10</option>
                                            <option value="20">Top 20</option>
                                            <option value="50">Top 50</option>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="generarReporteProductos()">
                                        <i class="fas fa-search"></i> Generar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Resultados del Reporte</h5>
                    </div>
                    <div class="card-body">
                        <div id="resultado-reporte">
                            <p class="text-muted text-center">Selecciona un tipo de reporte para ver los resultados</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generarReportePeriodo() {
            console.log('Generando reporte por período...');
        }
        
        function generarReporteProveedor() {
            console.log('Generando reporte por proveedor...');
        }
        
        function generarReporteProductos() {
            console.log('Generando reporte de productos...');
        }
    </script>
</body>
</html>