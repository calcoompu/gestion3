<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Configurar encoding UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// --- Lógica para datos del Navbar (adaptada de productos.php) ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

$total_clientes = 0;
$clientes_nuevos = 0;
$pedidos_pendientes = 0; 
$facturas_pendientes = 0;
$compras_pendientes = 0; 
$tablas_existentes = [];

try {
    $pdo_menu = conectarDB(); 
    $pdo_menu->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt_tables_menu = $pdo_menu->query("SHOW TABLES");
    if($stmt_tables_menu){
        while ($row_table_menu = $stmt_tables_menu->fetch(PDO::FETCH_NUM)) {
            $tablas_existentes[] = $row_table_menu[0];
        }
    }

    if (in_array('clientes', $tablas_existentes)) {
        $stmt_cli_total_menu = $pdo_menu->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1");
        if($stmt_cli_total_menu) $total_clientes = $stmt_cli_total_menu->fetch()['total'] ?? 0;
        
        $stmt_cli_nuevos_menu = $pdo_menu->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1");
        if($stmt_cli_nuevos_menu) $clientes_nuevos = $stmt_cli_nuevos_menu->fetch()['nuevos'] ?? 0;
    }
    
    if (in_array('pedidos', $tablas_existentes)) {
        $stmt_ped_pend_menu = $pdo_menu->query("SELECT COUNT(*) as pendientes FROM pedidos WHERE estado = 'pendiente'");
        if($stmt_ped_pend_menu) $pedidos_pendientes = $stmt_ped_pend_menu->fetch()['pendientes'] ?? 0;
    }
    
    if (in_array('facturas', $tablas_existentes)) {
        $stmt_fact_pend_menu = $pdo_menu->query("SELECT COUNT(*) as pendientes FROM facturas WHERE estado = 'pendiente'");
        if($stmt_fact_pend_menu){
            $facturas_data_menu = $stmt_fact_pend_menu->fetch();
            $facturas_pendientes = $facturas_data_menu['pendientes'] ?? 0;
        }
    }
    
    if (in_array('compras', $tablas_existentes)) {
        $stmt_compras_pend_menu = $pdo_menu->query("SELECT COUNT(*) as pendientes FROM compras WHERE estado IN ('pendiente', 'confirmada')");
        if($stmt_compras_pend_menu) $compras_pendientes = $stmt_compras_pend_menu->fetch()['pendientes'] ?? 0;
    }

} catch (Exception $e) {
    error_log("Error al cargar datos para el menú en productos_por_categoria.php: " . $e->getMessage());
    $total_clientes = 0; $clientes_nuevos = 0; $pedidos_pendientes = 0; $facturas_pendientes = 0; $compras_pendientes = 0; $tablas_existentes = [];
}
// --- FIN Lógica Navbar ---

try {
    $pdo_main = conectarDB(); 
    $pdo_main->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $sql_totales = "SELECT 
                        COUNT(DISTINCT c.id) as total_categorias,
                        COUNT(p.id) as total_productos,
                        COALESCE(SUM(p.stock), 0) as total_stock,
                        COALESCE(SUM(p.stock * p.precio_venta), 0) as valor_total
                    FROM categorias c
                    LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1";
    
    $stmt_main_totales = $pdo_main->query($sql_totales); 
    $totales_generales = $stmt_main_totales->fetch();
    
    $sql_sin_categoria = "SELECT COUNT(*) as productos_sin_categoria,
                                 COALESCE(SUM(stock), 0) as stock_sin_categoria,
                                 COALESCE(SUM(stock * precio_venta), 0) as valor_sin_categoria
                          FROM productos 
                          WHERE categoria_id IS NULL AND activo = 1";
    
    $stmt_main_sin_cat = $pdo_main->query($sql_sin_categoria); 
    $sin_categoria_totales = $stmt_main_sin_cat->fetch();
    
    $totales_generales['total_productos'] += $sin_categoria_totales['productos_sin_categoria'];
    $totales_generales['total_stock'] += $sin_categoria_totales['stock_sin_categoria'];
    $totales_generales['valor_total'] += $sin_categoria_totales['valor_sin_categoria'];
    
    if ($sin_categoria_totales['productos_sin_categoria'] > 0) {
        $totales_generales['total_categorias']++;
    }
    
    $sql_productos_cat = "SELECT c.id as categoria_id, c.nombre as categoria,
                   p.id as producto_id, p.nombre as producto, p.codigo,
                   p.stock, p.precio_venta, p.precio_compra,
                   (p.stock * p.precio_venta) as valor_total_producto
            FROM categorias c
            LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1
            ORDER BY c.nombre, p.nombre";
    
    $stmt_main_productos = $pdo_main->query($sql_productos_cat); 
    $resultados = $stmt_main_productos->fetchAll();
    
    $categorias_productos = [];
    $totales_categoria = [];
    
    foreach ($resultados as $row) {
        $categoria_id = $row['categoria_id'];
        $categoria_nombre = $row['categoria'];
        
        if (!isset($categorias_productos[$categoria_id])) {
            $categorias_productos[$categoria_id] = ['nombre' => $categoria_nombre, 'productos' => []];
            $totales_categoria[$categoria_id] = ['total_productos' => 0, 'total_stock' => 0, 'valor_total' => 0];
        }
        
        if ($row['producto_id']) {
            $categorias_productos[$categoria_id]['productos'][] = $row;
            $totales_categoria[$categoria_id]['total_productos']++;
            $totales_categoria[$categoria_id]['total_stock'] += $row['stock'];
            $totales_categoria[$categoria_id]['valor_total'] += $row['valor_total_producto'];
        }
    }
    
    $sql_sin_cat_lista = "SELECT id as producto_id, nombre as producto, codigo,
                   stock, precio_venta, precio_compra,
                   (stock * precio_venta) as valor_total_producto
            FROM productos 
            WHERE categoria_id IS NULL AND activo = 1
            ORDER BY nombre";
    
    $stmt_main_sin_cat_lista = $pdo_main->query($sql_sin_cat_lista); 
    $productos_sin_categoria = $stmt_main_sin_cat_lista->fetchAll();
    
} catch (Exception $e) {
    $error = "Error al cargar análisis: " . $e->getMessage();
    $categorias_productos = [];
    $productos_sin_categoria = [];
    $totales_generales = ['total_categorias' => 0, 'total_productos' => 0, 'total_stock' => 0, 'valor_total' => 0];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos por Categoría - <?php echo htmlspecialchars(SISTEMA_NOMBRE); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; flex-direction: column; height: 100vh; margin: 0; }
        .navbar-custom { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom .navbar-brand { font-weight: bold; color: white !important; font-size: 1.1rem; }
        .navbar-custom .navbar-nav .nav-link { color: white !important; font-weight: 500; transition: all 0.3s ease; margin: 0 2px; border-radius: 5px; padding: 8px 12px !important; font-size: 0.95rem; }
        .navbar-custom .navbar-nav .nav-link:hover { background-color: rgba(255,255,255,0.1); transform: translateY(-1px); }
        .navbar-custom .navbar-nav .nav-link.active { background-color: rgba(255,255,255,0.2); font-weight: 600; }
        .navbar-custom .dropdown-menu { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; }
        .navbar-custom .dropdown-item { padding: 8px 16px; transition: all 0.2s ease; }
        .navbar-custom .dropdown-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
        .page-header-section { background-color: #f8f9fa; padding: 20px 15px 15px 15px; border-bottom: 2px solid #dee2e6; flex-shrink: 0; }
        .fixed-footer { flex-shrink: 0; background-color: white; border-top: 2px solid #dee2e6; padding: 20px; }
        .scrollable-content { flex-grow: 1; overflow-y: auto; padding: 15px; }
        .summary-card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 12px; height: 130px; transition: transform 0.2s; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .summary-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: rgba(255,255,255,0.4); }
        .summary-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.2); }
        .summary-card .card-body { padding: 15px; text-align: center; }
        .stat-icon { font-size: 2.2rem; opacity: 0.9; margin-bottom: 8px; } 
        .stat-label { font-size: 0.9rem; font-weight: 600; margin: 0 0 10px 0; line-height: 1.2; } 
        .stat-number { font-size: 2rem; font-weight: 700; margin: 0; line-height: 1; } 
        .category-section { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; border-radius: 10px; }
        .category-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px 10px 0 0; padding: 15px 20px; }
        .category-totals { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 2px solid #dee2e6; padding: 15px 20px; font-size: 1.1rem; }
        .category-totals .total-item { font-weight: bold; font-size: 1.2rem; }
        .category-totals .total-value { color: #0d6efd; font-weight: 900; font-size: 1.3rem; }
        .product-row:hover { background-color: #f8f9fa; }
        .table { table-layout: fixed !important; width: 100% !important; }
        .table th:nth-child(1), .table td:nth-child(1) { width: 120px !important; }
        .table th:nth-child(3), .table td:nth-child(3) { width: 120px !important; text-align: center !important; vertical-align: middle !important; }
        .table th:nth-child(4), .table td:nth-child(4) { width: 140px !important; text-align: right !important; }
        .table th:nth-child(5), .table td:nth-child(5) { width: 140px !important; text-align: right !important; }
        .table th:nth-child(6), .table td:nth-child(6) { width: 120px !important; text-align: center !important; }
        .quantity-badge { background-color: #0d6efd !important; color: white; font-weight: bold; padding: 8px 14px; border-radius: 8px; min-width: 60px; text-align: center; display: inline-block; font-size: 0.95rem; }
        .quantity-column { width: 120px !important; text-align: center !important; vertical-align: middle !important; padding: 12px !important; }
        .no-products { color: #6c757d; font-style: italic; text-align: center; padding: 40px; font-size: 1.1rem; }
        .action-btn { border: none !important; font-weight: 600; padding: 12px 20px; border-radius: 8px; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-decoration: none; display: inline-block; width: 100%; text-align: center; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .action-btn-primary { background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%); color: white; }
        .action-btn-success { background: linear-gradient(135deg, #198754 0%, #146c43 100%); color: white; }
        .action-btn-info { background: linear-gradient(135deg, #0dcaf0 0%, #087990 100%); color: white; }
        .action-btn-warning { background: linear-gradient(135deg, #ffc107 0%, #d39e00 100%); color: #000; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../menu_principal.php"><i class="bi bi-speedometer2"></i> Gestión Administrativa</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                 <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 0.75%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="../../menu_principal.php"><i class="bi bi-house-door"></i> Dashboard</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-box-seam"></i> Productos</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="productos.php"><i class="bi bi-list-ul"></i> Listado</a></li>
                            <li><a class="dropdown-item" href="producto_form.php"><i class="bi bi-plus-circle"></i> Nuevo</a></li>
                            <li><a class="dropdown-item active" href="productos_por_categoria.php"><i class="bi bi-tag"></i> Por Categoría</a></li>
                            <li><a class="dropdown-item" href="productos_por_lugar.php"><i class="bi bi-geo-alt"></i> Por Ubicación</a></li>
                            <li><a class="dropdown-item" href="productos_inactivos.php"><i class="bi bi-archive"></i> Inactivos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="reportes.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    <?php if (isset($tablas_existentes) && in_array('clientes', $tablas_existentes)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-people"></i> Clientes</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/clientes/clientes.php">Ver Clientes</a></li>
                            <li><a class="dropdown-item" href="../../modulos/clientes/cliente_form.php">Nuevo Cliente</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($tablas_existentes) && in_array('pedidos', $tablas_existentes)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-cart"></i> Pedidos</a>
                         <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/pedidos/pedidos.php">Ver Pedidos</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                     <?php if (isset($tablas_existentes) && in_array('facturas', $tablas_existentes)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-receipt"></i> Facturación <?php if (isset($facturas_pendientes) && $facturas_pendientes > 0): ?><span class="badge bg-danger ms-1"><?php echo $facturas_pendientes; ?></span><?php endif; ?></a>
                        <ul class="dropdown-menu">
                             <li><a class="dropdown-item" href="../../modulos/facturas/facturas.php">Ver Facturas</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($tablas_existentes) && in_array('compras', $tablas_existentes)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-truck"></i> Compras <?php if (isset($compras_pendientes) && $compras_pendientes > 0): ?><span class="badge bg-info text-dark ms-1"><?php echo $compras_pendientes; ?></span><?php endif; ?></a>
                         <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/compras/compras.php">Ver Compras</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if ($es_administrador): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-gear me-1"></i>Configuración</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../../modulos/admin/configuracion_sistema.php">Config. General</a></li>
                            <li><a class="dropdown-item" href="../../modulos/admin/usuarios.php">Usuarios</a></li>
                            <li><a class="dropdown-item" href="../../modulos/admin/logs_sistema.php">Logs</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($usuario_nombre); ?></a>
                        <ul class="dropdown-menu dropdown-menu-end">
                             <li><h6 class="dropdown-header">Rol: <?php echo ucfirst(htmlspecialchars($usuario_rol)); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($es_administrador): ?>
                                <li><h6 class="dropdown-header text-danger"><i class="bi bi-shield-check"></i> Admin</h6></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/admin_dashboard.php">Panel Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="page-header-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0"><i class="bi bi-tags me-2"></i>Productos por Categoría</h2>
            <div>
                <a href="productos.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left me-1"></i>Volver</a>
                <a href="reportes.php" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes</a>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3 mb-3 mb-md-0"><div class="card summary-card bg-primary text-white"><div class="card-body"><i class="bi bi-tags stat-icon"></i><p class="stat-label">Categorías</p><div class="stat-number"><?php echo number_format($totales_generales['total_categorias']); ?></div></div></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="card summary-card bg-success text-white"><div class="card-body"><i class="bi bi-box-seam stat-icon"></i><p class="stat-label">Total Productos</p><div class="stat-number"><?php echo number_format($totales_generales['total_productos']); ?></div></div></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="card summary-card bg-info text-white"><div class="card-body"><i class="bi bi-boxes stat-icon"></i><p class="stat-label">Stock Total</p><div class="stat-number"><?php echo number_format($totales_generales['total_stock']); ?></div></div></div></div>
            <div class="col-md-3 mb-3 mb-md-0"><div class="card summary-card bg-warning text-dark"><div class="card-body"><i class="bi bi-currency-dollar stat-icon"></i><p class="stat-label">Valor Total</p><div class="stat-number"><?php echo formatCurrency($totales_generales['valor_total']); ?></div></div></div></div>
        </div>
    </div>

    <div class="scrollable-content">
        <?php if (isset($error)): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php foreach ($categorias_productos as $categoria_id => $categoria_data): ?>
            <?php if (!empty($categoria_data['productos'])): ?>
                <div class="card category-section">
                    <div class="category-header"><h4 class="mb-0"><i class="bi bi-tag me-2"></i><?php echo htmlspecialchars($categoria_data['nombre']); ?></h4></div>
                    <div class="category-totals">
                        <div class="row text-center">
                            <div class="col-md-4"><div class="total-item">Productos: <span class="total-value"><?php echo number_format($totales_categoria[$categoria_id]['total_productos']); ?></span></div></div>
                            <div class="col-md-4"><div class="total-item">Stock Total: <span class="total-value"><?php echo number_format($totales_categoria[$categoria_id]['total_stock']); ?></span></div></div>
                            <div class="col-md-4"><div class="total-item">Valor Total: <span class="total-value"><?php echo formatCurrency($totales_categoria[$categoria_id]['valor_total']); ?></span></div></div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light"><tr><th>Código</th><th>Producto</th><th class="quantity-column">Cantidad</th><th class="text-end">Valor Unitario</th><th class="text-end">Valor Total</th><th class="text-center">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($categoria_data['productos'] as $producto): ?>
                                        <tr class="product-row">
                                            <td><code><?php echo htmlspecialchars($producto['codigo'] ?: 'N/A'); ?></code></td>
                                            <td><strong><?php echo htmlspecialchars($producto['producto']); ?></strong></td>
                                            <td class="quantity-column"><span class="quantity-badge"><?php echo number_format($producto['stock']); ?></span></td>
                                            <td class="text-end"><?php echo formatCurrency($producto['precio_venta']); ?></td>
                                            <td class="text-end"><strong><?php echo formatCurrency($producto['valor_total_producto']); ?></strong></td>
                                            <td class="text-center">
                                                <a href="producto_detalle.php?id=<?php echo $producto['producto_id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver detalles"><i class="bi bi-eye"></i></a>
                                                <a href="producto_form.php?id=<?php echo $producto['producto_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!empty($productos_sin_categoria)): ?>
            <div class="card category-section">
                <div class="category-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff8f00 100%);"><h4 class="mb-0"><i class="bi bi-question-circle me-2"></i>Sin Categoría</h4></div>
                <div class="category-totals">
                    <div class="row text-center">
                        <div class="col-md-4"><div class="total-item">Productos: <span class="total-value"><?php echo number_format(count($productos_sin_categoria)); ?></span></div></div>
                        <div class="col-md-4"><div class="total-item">Stock Total: <span class="total-value"><?php echo number_format(array_sum(array_column($productos_sin_categoria, 'stock'))); ?></span></div></div>
                        <div class="col-md-4"><div class="total-item">Valor Total: <span class="total-value"><?php echo formatCurrency(array_sum(array_column($productos_sin_categoria, 'valor_total_producto'))); ?></span></div></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Código</th><th>Producto</th><th class="quantity-column">Cantidad</th><th class="text-end">Valor Unitario</th><th class="text-end">Valor Total</th><th class="text-center">Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($productos_sin_categoria as $producto): ?>
                                    <tr class="product-row">
                                        <td><code><?php echo htmlspecialchars($producto['codigo'] ?: 'N/A'); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($producto['producto']); ?></strong></td>
                                        <td class="quantity-column"><span class="quantity-badge"><?php echo number_format($producto['stock']); ?></span></td>
                                        <td class="text-end"><?php echo formatCurrency($producto['precio_venta']); ?></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($producto['valor_total_producto']); ?></strong></td>
                                        <td class="text-center">
                                            <a href="producto_detalle.php?id=<?php echo $producto['producto_id']; ?>" class="btn btn-sm btn-outline-primary" title="Ver detalles"><i class="bi bi-eye"></i></a>
                                            <a href="producto_form.php?id=<?php echo $producto['producto_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="fixed-footer">
        <h5 class="mb-3"><i class="bi bi-lightning-charge me-2"></i>Acciones Rápidas</h5>
        <div class="row">
            <div class="col-md-3 mb-2 mb-md-0"><a href="productos.php" class="action-btn action-btn-primary"><i class="bi bi-list-ul me-2"></i>Todos los Productos</a></div>
            <div class="col-md-3 mb-2 mb-md-0"><a href="producto_form.php" class="action-btn action-btn-success"><i class="bi bi-plus-circle me-2"></i>Nuevo Producto</a></div>
            <div class="col-md-3 mb-2 mb-md-0"><a href="productos_por_lugar.php" class="action-btn action-btn-info"><i class="bi bi-geo-alt me-2"></i>Por Ubicación</a></div>
            <div class="col-md-3 mb-2 mb-md-0"><a href="exportar_excel.php?tipo=categoria" class="action-btn action-btn-warning"><i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel</a></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>