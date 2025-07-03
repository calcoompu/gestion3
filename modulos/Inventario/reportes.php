<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// --- Lógica de la página y del Navbar unificada ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

$total_clientes = 0; $clientes_nuevos = 0; $pedidos_pendientes = 0; $facturas_pendientes = 0; $compras_pendientes = 0; $tablas_existentes = [];

try {
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // --- Lógica para el menú (copiada de productos.php) ---
    $stmt_tables = $pdo->query("SHOW TABLES"); 
    if ($stmt_tables) { while ($row_table = $stmt_tables->fetch(PDO::FETCH_NUM)) { $tablas_existentes[] = $row_table[0]; } }
    
    if (in_array('clientes', $tablas_existentes)) {
        $stmt_cli_total = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1");
        if($stmt_cli_total) $total_clientes = $stmt_cli_total->fetch()['total'] ?? 0;
    }
    if (in_array('pedidos', $tablas_existentes)) {
        $stmt_ped_pend = $pdo->query("SELECT COUNT(*) as pendientes FROM pedidos WHERE estado = 'pendiente'");
        if($stmt_ped_pend) $pedidos_pendientes = $stmt_ped_pend->fetch()['pendientes'] ?? 0;
    }
    if (in_array('facturas', $tablas_existentes)) {
        $stmt_fact_pend = $pdo->query("SELECT COUNT(*) as pendientes FROM facturas WHERE estado = 'pendiente'");
        if($stmt_fact_pend) $facturas_pendientes = $stmt_fact_pend->fetch()['pendientes'] ?? 0;
    }
    if (in_array('compras', $tablas_existentes)) {
        $stmt_compras_pend = $pdo->query("SELECT COUNT(*) as pendientes FROM compras WHERE estado IN ('pendiente', 'confirmada')");
        if($stmt_compras_pend) $compras_pendientes = $stmt_compras_pend->fetch()['pendientes'] ?? 0;
    }
    // --- Fin Lógica para el menú ---

    // --- Lógica de la página de reportes ---
    $stats = obtenerEstadisticasInventario($pdo);
    
    $sql_cat = "SELECT c.nombre as categoria, COUNT(p.id) as cantidad, SUM(p.stock * p.precio_venta) as valor_total FROM categorias c LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1 GROUP BY c.id, c.nombre ORDER BY cantidad DESC";
    $productos_por_categoria = $pdo->query($sql_cat)->fetchAll(PDO::FETCH_ASSOC);
    
    $sql_lug = "SELECT l.nombre as lugar, COUNT(p.id) as cantidad, SUM(p.stock * p.precio_venta) as valor_total FROM lugares l LEFT JOIN productos p ON l.id = p.lugar_id AND p.activo = 1 GROUP BY l.id, l.nombre ORDER BY cantidad DESC";
    $productos_por_lugar = $pdo->query($sql_lug)->fetchAll(PDO::FETCH_ASSOC);
    
    $sql_low = "SELECT codigo, nombre, stock, stock_minimo, precio_venta FROM productos WHERE stock <= stock_minimo AND activo = 1 ORDER BY (stock - stock_minimo) ASC";
    $productos_bajo_stock = $pdo->query($sql_low)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error al generar reportes: " . $e->getMessage();
    $stats = ['total_productos' => 0, 'productos_bajo_stock' => 0, 'valor_total_inventario' => 0, 'precio_promedio' => 0];
    $productos_por_categoria = []; $productos_por_lugar = []; $productos_bajo_stock = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Inventario - <?php echo SISTEMA_NOMBRE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .navbar-custom { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom .navbar-brand { font-weight: bold; color: white !important; font-size: 1.1rem; }
        .navbar-custom .navbar-nav .nav-link { color: white !important; font-weight: 500; transition: all 0.3s ease; margin: 0 2px; border-radius: 5px; padding: 8px 12px !important; font-size: 0.95rem; }
        .navbar-custom .navbar-nav .nav-link:hover { background-color: rgba(255,255,255,0.1); transform: translateY(-1px); }
        .navbar-custom .navbar-nav .nav-link.active { background-color: rgba(255,255,255,0.2); font-weight: 600; }
        .navbar-custom .dropdown-menu { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; }
        .navbar-custom .dropdown-item { padding: 8px 16px; transition: all 0.2s ease; }
        .navbar-custom .dropdown-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
        .report-card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); margin-bottom: 1.5rem; }
        .report-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .chart-container { position: relative; height: 300px; }
        .export-btn { margin-left: 0.5rem; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../menu_principal.php">
                <i class="bi bi-speedometer2"></i> Gestión Administrativa
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 0.75%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../../menu_principal.php"> 
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> 
                            <i class="bi bi-box-seam"></i> Productos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="productos.php"><i class="bi bi-list-ul"></i> Listado de Productos</a></li>
                            <li><a class="dropdown-item" href="producto_form.php"><i class="bi bi-plus-circle"></i> Nuevo Producto</a></li>
                            <li><a class="dropdown-item" href="productos_por_categoria.php"><i class="bi bi-tag"></i> Por Categoria</a></li>
                            <li><a class="dropdown-item" href="productos_por_lugar.php"><i class="bi bi-geo-alt"></i> Por Ubicación</a></li>
                            <li><a class="dropdown-item" href="productos_inactivos.php"><i class="bi bi-archive"></i> Productos Inactivos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item active" href="reportes.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/clientes/clientes.php"><i class="bi bi-list-ul"></i> Ver Clientes</a></li>
                            <li><a class="dropdown-item" href="../../modulos/clientes/cliente_form.php"><i class="bi bi-person-plus"></i> Nuevo Cliente</a></li>
                            <li><a class="dropdown-item" href="../../modulos/clientes/clientes_inactivos.php"><i class="bi bi-person-x"></i> Clientes Inactivos</a></li>
                            <li><a class="dropdown-item" href="../../modulos/clientes/papelera_clientes.php"><i class="bi bi-trash"></i> Papelera</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cart"></i> Pedidos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/pedidos/pedidos.php"><i class="bi bi-list-ul"></i> Ver Pedidos</a></li>
                            <li><a class="dropdown-item" href="../../modulos/pedidos/pedido_form.php"><i class="bi bi-cart-plus"></i> Nuevo Pedido</a></li>
                            <li><a class="dropdown-item" href="../../modulos/pedidos/pedidos_pendientes.php"><i class="bi bi-clock"></i> Pedidos Pendientes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../modulos/pedidos/reportes_pedidos.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-receipt"></i> Facturación
                             <?php if (isset($facturas_pendientes) && $facturas_pendientes > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $facturas_pendientes; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/facturas/facturas.php"><i class="bi bi-list-ul"></i> Ver Facturas</a></li>
                            <li><a class="dropdown-item" href="../../modulos/facturas/factura_form.php"><i class="bi bi-receipt"></i> Nueva Factura</a></li>
                            <li><a class="dropdown-item" href="../../modulos/facturas/facturas_pendientes.php"><i class="bi bi-clock"></i> Facturas Pendientes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../modulos/facturas/reportes_facturas.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-truck"></i> Compras
                            <?php if (isset($compras_pendientes) && $compras_pendientes > 0): ?>
                                <span class="badge bg-info text-dark ms-1"><?php echo $compras_pendientes; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/compras/compras.php"><i class="bi bi-list-ul"></i> Ver Compras</a></li>
                            <li><a class="dropdown-item" href="../../modulos/compras/compra_form.php"><i class="bi bi-truck"></i> Nueva Compra</a></li>
                            <li><a class="dropdown-item" href="../../modulos/compras/proveedores.php"><i class="bi bi-building"></i> Proveedores</a></li>
                            <li><a class="dropdown-item" href="../../modulos/compras/recepcion_mercaderia.php"><i class="bi bi-box-arrow-in-down"></i> Recepción</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../modulos/compras/reportes_compras.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Rol: <?php echo ucfirst(htmlspecialchars($usuario_rol)); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($es_administrador): ?>
                                <li><h6 class="dropdown-header text-danger"><i class="bi bi-shield-check"></i> Administración</h6></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/admin_dashboard.php"><i class="bi bi-speedometer2"></i> Panel Admin</a></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/usuarios.php"><i class="bi bi-people"></i> Gestión de Usuarios</a></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/configuracion_sistema.php"><i class="bi bi-gear"></i> Configuración Sistema</a></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/reportes_admin.php"><i class="bi bi-graph-up"></i> Reportes Admin</a></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/logs_sistema.php"><i class="bi bi-journal-text"></i> Logs del Sistema</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes de Inventario</h2>
                <p class="text-muted">Análisis visual y detallado del estado de su inventario.</p>
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
                            $total_productos_cat = array_sum(array_column($productos_por_categoria, 'cantidad'));
                            foreach ($productos_por_categoria as $categoria): 
                                $porcentaje = $total_productos_cat > 0 ? ($categoria['cantidad'] / $total_productos_cat * 100) : 0;
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
                        <a href="productos.php?orden=stock&dir=asc" class="btn btn-outline-warning w-100">
                            <i class="bi bi-sort-numeric-down me-2"></i>Ver Bajo Stock
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
        const categorias = <?php echo json_encode($productos_por_categoria); ?>;
        const lugares = <?php echo json_encode($productos_por_lugar); ?>;
        const ctxCategorias = document.getElementById('chartCategorias').getContext('2d');
        new Chart(ctxCategorias, {
            type: 'doughnut',
            data: {
                labels: categorias.map(c => c.categoria),
                datasets: [{
                    data: categorias.map(c => c.cantidad),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
        const ctxLugares = document.getElementById('chartLugares').getContext('2d');
        new Chart(ctxLugares, {
            type: 'bar',
            data: {
                labels: lugares.map(l => l.lugar),
                datasets: [{ label: 'Cantidad de Productos', data: lugares.map(l => l.cantidad), backgroundColor: '#36A2EB' }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
        function exportarExcel() { alert('Funcionalidad de exportación a Excel en desarrollo'); }
        function exportarPDF() { alert('Funcionalidad de exportación a PDF en desarrollo'); }
    </script>
</body>
</html>