<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

try {
    $pdo = conectarDB();
    $stats = obtenerEstadisticasInventario($pdo);
    
    // Productos por categoría
    $sql = "SELECT c.nombre as categoria, COUNT(p.id) as cantidad, 
                   SUM(p.stock * p.precio_venta) as valor_total
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1
            GROUP BY c.id, c.nombre
            ORDER BY cantidad DESC";
    $stmt = $pdo->query($sql);
    $productos_por_categoria = $stmt->fetchAll();
    
    // Productos por ubicación
    $sql = "SELECT l.nombre as lugar, COUNT(p.id) as cantidad,
                   SUM(p.stock * p.precio_venta) as valor_total
            FROM lugares l
            LEFT JOIN productos p ON l.id = p.lugar_id AND p.activo = 1
            GROUP BY l.id, l.nombre
            ORDER BY cantidad DESC";
    $stmt = $pdo->query($sql);
    $productos_por_lugar = $stmt->fetchAll();
    
    // Productos con bajo stock
    $sql = "SELECT codigo, nombre, stock, stock_minimo, precio_venta
            FROM productos 
            WHERE stock <= stock_minimo AND activo = 1
            ORDER BY (stock - stock_minimo) ASC";
    $stmt = $pdo->query($sql);
    $productos_bajo_stock = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al generar reportes: " . $e->getMessage();
    $productos_por_categoria = [];
    $productos_por_lugar = [];
    $productos_bajo_stock = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo SISTEMA_NOMBRE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .report-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .export-btn {
            margin-left: 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes de Inventario</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../../menu_principal.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Reportes</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button class="btn btn-success export-btn" onclick="exportarExcel()">
                    <i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel
                </button>
                <button class="btn btn-danger export-btn" onclick="exportarPDF()">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Exportar PDF
                </button>
            </div>
        </div>

        <!-- Estadísticas Generales -->
        <div class="card report-card">
            <div class="card-header report-header">
                <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Resumen General</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-primary"><?php echo number_format($stats['total_productos']); ?></h3>
                            <p class="text-muted mb-0">Total Productos</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-warning"><?php echo number_format($stats['productos_bajo_stock']); ?></h3>
                            <p class="text-muted mb-0">Productos Bajo Stock</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h3 class="text-success"><?php echo formatCurrency($stats['valor_total_inventario']); ?></h3>
                            <p class="text-muted mb-0">Valor Total Inventario</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-info"><?php echo formatCurrency($stats['precio_promedio']); ?></h3>
                        <p class="text-muted mb-0">Precio Promedio</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Gráfico por Categorías -->
            <div class="col-lg-6">
                <div class="card report-card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Productos por Categoría</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartCategorias"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico por Ubicaciones -->
            <div class="col-lg-6">
                <div class="card report-card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Productos por Ubicación</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartLugares"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Productos por Categoría -->
        <div class="card report-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-table me-2"></i>Detalle por Categorías</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Cantidad Productos</th>
                                <th>Valor Total</th>
                                <th>Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_productos = array_sum(array_column($productos_por_categoria, 'cantidad'));
                            foreach ($productos_por_categoria as $categoria): 
                                $porcentaje = $total_productos > 0 ? ($categoria['cantidad'] / $total_productos * 100) : 0;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($categoria['categoria']); ?></td>
                                    <td><?php echo number_format($categoria['cantidad']); ?></td>
                                    <td><?php echo formatCurrency($categoria['valor_total'] ?? 0); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" style="width: <?php echo $porcentaje; ?>%">
                                                <?php echo number_format($porcentaje, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Productos con Bajo Stock -->
        <?php if (!empty($productos_bajo_stock)): ?>
        <div class="card report-card">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Productos con Bajo Stock</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Diferencia</th>
                                <th>Valor Afectado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_bajo_stock as $producto): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($producto['codigo']); ?></code></td>
                                    <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                    <td>
                                        <span class="badge bg-danger"><?php echo $producto['stock']; ?></span>
                                    </td>
                                    <td><?php echo $producto['stock_minimo']; ?></td>
                                    <td class="text-danger">
                                        <?php echo ($producto['stock'] - $producto['stock_minimo']); ?>
                                    </td>
                                    <td><?php echo formatCurrency($producto['precio_venta'] * $producto['stock']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Acciones Rápidas -->
        <div class="card report-card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="productos_por_categoria.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-tags me-2"></i>Análisis por Categoría
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="productos_por_lugar.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-geo-alt me-2"></i>Análisis por Ubicación
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="productos.php?filtro=bajo_stock" class="btn btn-outline-warning w-100">
                            <i class="bi bi-exclamation-triangle me-2"></i>Ver Bajo Stock
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="producto_form.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-plus-circle me-2"></i>Nuevo Producto
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Datos para gráficos
        const categorias = <?php echo json_encode($productos_por_categoria); ?>;
        const lugares = <?php echo json_encode($productos_por_lugar); ?>;

        // Gráfico de Categorías
        const ctxCategorias = document.getElementById('chartCategorias').getContext('2d');
        new Chart(ctxCategorias, {
            type: 'doughnut',
            data: {
                labels: categorias.map(c => c.categoria),
                datasets: [{
                    data: categorias.map(c => c.cantidad),
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Lugares
        const ctxLugares = document.getElementById('chartLugares').getContext('2d');
        new Chart(ctxLugares, {
            type: 'bar',
            data: {
                labels: lugares.map(l => l.lugar),
                datasets: [{
                    label: 'Cantidad de Productos',
                    data: lugares.map(l => l.cantidad),
                    backgroundColor: '#36A2EB',
                    borderColor: '#36A2EB',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Funciones de exportación
        function exportarExcel() {
            alert('Funcionalidad de exportación a Excel en desarrollo');
        }

        function exportarPDF() {
            alert('Funcionalidad de exportación a PDF en desarrollo');
        }
    </script>
</body>
</html>

