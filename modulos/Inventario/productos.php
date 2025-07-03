<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Configurar charset UTF-8
header('Content-Type: text/html; charset=UTF-8');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$filtro_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtro_lugar = isset($_GET['lugar']) ? intval($_GET['lugar']) : 0;
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Ordenamiento
$orden_campo = isset($_GET['orden']) ? $_GET['orden'] : 'fecha_creacion';
$orden_direccion = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

$campos_permitidos = ['codigo', 'nombre', 'categoria_nombre', 'lugar_nombre', 'stock', 'stock_minimo', 'precio_venta', 'fecha_creacion'];
if (!in_array($orden_campo, $campos_permitidos)) {
    $orden_campo = 'fecha_creacion';
}

$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');


try {
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // --- Lógica para el menú (adaptada de menu_principal.php y combinada con la existente) ---
    $total_clientes = 0;
    $clientes_nuevos = 0;
    $pedidos_pendientes = 0; // Se calcula pero el badge no se mostrará aquí
    $pedidos_hoy = 0; 
    $facturas_pendientes = 0;
    $monto_pendiente = 0; 
    $ingresos_mes = 0; 
    $compras_pendientes = 0; 

    $tablas_existentes = [];
    $stmt_tables = $pdo->query("SHOW TABLES"); 
    if ($stmt_tables) {
        while ($row_table = $stmt_tables->fetch(PDO::FETCH_NUM)) { 
            $tablas_existentes[] = $row_table[0];
        }
    }
    
    if (in_array('clientes', $tablas_existentes)) {
        $stmt_cli_total = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1");
        if($stmt_cli_total) $total_clientes = $stmt_cli_total->fetch()['total'] ?? 0;
        
        $stmt_cli_nuevos = $pdo->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1");
        if($stmt_cli_nuevos) $clientes_nuevos = $stmt_cli_nuevos->fetch()['nuevos'] ?? 0;
    }
    
    if (in_array('pedidos', $tablas_existentes)) {
        $stmt_ped_pend = $pdo->query("SELECT COUNT(*) as pendientes FROM pedidos WHERE estado = 'pendiente'");
        if($stmt_ped_pend) $pedidos_pendientes = $stmt_ped_pend->fetch()['pendientes'] ?? 0;
        
        $stmt_ped_hoy = $pdo->query("SELECT COUNT(*) as hoy FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()");
        if($stmt_ped_hoy) $pedidos_hoy = $stmt_ped_hoy->fetch()['hoy'] ?? 0;
    }
    
    if (in_array('facturas', $tablas_existentes)) {
        $stmt_fact_pend = $pdo->query("SELECT COUNT(*) as pendientes, COALESCE(SUM(total), 0) as monto_pendiente FROM facturas WHERE estado = 'pendiente'");
        if($stmt_fact_pend) {
            $facturas_data = $stmt_fact_pend->fetch();
            $facturas_pendientes = $facturas_data['pendientes'] ?? 0;
            $monto_pendiente = $facturas_data['monto_pendiente'] ?? 0;
        }
        
        $stmt_fact_ing = $pdo->query("SELECT COALESCE(SUM(total), 0) as ingresos_mes FROM facturas WHERE MONTH(fecha_factura) = MONTH(CURDATE()) AND YEAR(fecha_factura) = YEAR(CURDATE()) AND estado = 'pagada'");
        if($stmt_fact_ing) $ingresos_mes = $stmt_fact_ing->fetch()['ingresos_mes'] ?? 0;
    }

    if (in_array('compras', $tablas_existentes)) {
        $stmt_compras_pend = $pdo->query("SELECT COUNT(*) as pendientes FROM compras WHERE estado IN ('pendiente', 'confirmada')");
        if($stmt_compras_pend) $compras_pendientes = $stmt_compras_pend->fetch()['pendientes'] ?? 0;
    }
    // --- Fin Lógica para el menú ---

    // Construir consulta base para productos
    $where_conditions = ["p.activo = 1"];
    $params = [];
    
    if ($filtro_categoria > 0) {
        $where_conditions[] = "p.categoria_id = ?";
        $params[] = $filtro_categoria;
    }
    
    if ($filtro_lugar > 0) {
        $where_conditions[] = "p.lugar_id = ?";
        $params[] = $filtro_lugar;
    }
    
    if (!empty($filtro_busqueda)) {
        $where_conditions[] = "(p.codigo LIKE ? OR p.nombre LIKE ?)";
        $busqueda_param = "%{$filtro_busqueda}%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $orden_sql = $orden_campo;
    if ($orden_campo === 'categoria_nombre') {
        $orden_sql = 'c.nombre';
    } elseif ($orden_campo === 'lugar_nombre') {
        $orden_sql = 'l.nombre';
    } else {
        $orden_sql = 'p.' . $orden_campo;
    }
    
    $sql = "SELECT p.*, c.nombre as categoria_nombre, l.nombre as lugar_nombre 
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN lugares l ON p.lugar_id = l.id 
            WHERE {$where_clause}
            ORDER BY {$orden_sql} {$orden_direccion}
            LIMIT {$per_page} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
    
    $count_sql = "SELECT COUNT(*) 
                  FROM productos p 
                  LEFT JOIN categorias c ON p.categoria_id = c.id 
                  LEFT JOIN lugares l ON p.lugar_id = l.id 
                  WHERE {$where_clause}";
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_productos = $count_stmt->fetchColumn();
    $total_pages = ceil($total_productos / $per_page);
    
    $stats_sql = "SELECT 
                    COUNT(*) as total_productos,
                    COALESCE(SUM(stock), 0) as stock_total,
                    COALESCE(SUM(precio_venta * stock), 0) as valor_total,
                    COUNT(CASE WHEN stock <= stock_minimo THEN 1 END) as productos_bajo_stock
                  FROM productos p 
                  LEFT JOIN categorias c ON p.categoria_id = c.id 
                  LEFT JOIN lugares l ON p.lugar_id = l.id 
                  WHERE {$where_clause}";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch();
    
    $categorias = $pdo->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll();
    $lugares = $pdo->query("SELECT id, nombre FROM lugares WHERE activo = 1 ORDER BY nombre")->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Error al cargar productos: " . $e->getMessage();
    $productos = [];
    $stats = ['total_productos' => 0, 'stock_total' => 0, 'valor_total' => 0, 'productos_bajo_stock' => 0];
    $categorias = [];
    $lugares = [];
    $total_pages = 1;
    
    $total_clientes = 0;
    $clientes_nuevos = 0;
    $pedidos_pendientes = 0;
    $pedidos_hoy = 0;
    $facturas_pendientes = 0;
    $monto_pendiente = 0;
    $ingresos_mes = 0;
    $compras_pendientes = 0; 
    $tablas_existentes = []; 
}

$pageTitle = "Gestión de Productos - " . SISTEMA_NOMBRE;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            overflow: hidden; 
        }

        /* Estilos del Navbar (como en menu_principal.php) */
        .navbar-custom { 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-custom .navbar-brand { 
            font-weight: bold;
            color: white !important;
            font-size: 1.1rem;
        }
        
        .navbar-custom .navbar-nav .nav-link { 
            color: white !important;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 2px;
            border-radius: 5px;
            padding: 8px 12px !important;
            font-size: 0.95rem;
        }
        
        .navbar-custom .navbar-nav .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }
        
        .navbar-custom .navbar-nav .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .navbar-custom .dropdown-menu { 
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .navbar-custom .dropdown-item {
            padding: 8px 16px;
            transition: all 0.2s ease;
        }
        
        .navbar-custom .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        /* Fin de estilos Navbar */
        
        .main-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .fixed-header {
            flex-shrink: 0;
            background-color: #f8f9fa;
            z-index: 100; 
        }
        
        .content-area {
            padding: 15px;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            padding: 0 15px 15px 15px;
        }
        
        .stats-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            margin-bottom: 15px;
            height: 80px;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card .card-body {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }
        
        .stats-icon {
            font-size: 1.8rem;
            opacity: 0.9;
        }
        
        .stats-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stats-label {
            font-size: 0.85rem;
            margin: 0;
            opacity: 0.8;
        }
        
        .card-gradient-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .card-gradient-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .card-gradient-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .card-gradient-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        
        .search-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 12px 15px;
            margin-bottom: 20px;
        }
        
        .search-label {
            font-weight: bold;
            font-size: 0.85rem;
            margin-bottom: 3px;
            color: #2c3e50;
            display: block;
        }
        
        .form-control, .form-select {
            font-size: 0.85rem;
            padding: 6px 10px;
            height: auto;
        }
        
        .btn-sm-custom {
            font-size: 0.8rem;
            padding: 6px 12px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .table-header {
            flex-shrink: 0;
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background: white;
        }
        
        .table-wrapper {
            flex: 1;
            overflow-y: auto;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 12px 8px;
        }
        
        .table td {
            padding: 10px 8px;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .btn-action {
            padding: 4px 8px;
            margin: 0 1px;
            border-radius: 5px;
            font-size: 0.8rem;
        }
        
        .badge-categoria {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .pagination-container {
            flex-shrink: 0;
            background: white;
            border-top: 1px solid #dee2e6;
            padding: 15px;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Navbar Superior con estilo de menu_principal.php -->
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
                            <li><a class="dropdown-item" href="reportes.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
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
                            <?php /* Badge de pedidos pendientes eliminado para esta página */ ?>
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

    <div class="main-container">
        <!-- Fixed Header Section -->
        <div class="fixed-header">
            <!-- Header -->
            <div class="content-area">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2><i class="bi bi-box-seam me-2"></i>Gestión de Productos</h2>
                    <div>
                        <a href="producto_form.php" class="btn btn-primary me-2">
                            <i class="bi bi-plus-circle me-1"></i>Nuevo Producto
                        </a>
                        <a href="productos_inactivos.php" class="btn btn-outline-danger me-2">
                            <i class="bi bi-archive me-1"></i>Ver Inactivos
                        </a>
                        <button class="btn btn-success" onclick="exportarExcel()">
                            <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="card stats-card card-gradient-1">
                            <div class="card-body">
                                <div>
                                    <h3 class="stats-number"><?php echo number_format($stats['total_productos']); ?></h3>
                                    <p class="stats-label">Total Productos</p>
                                </div>
                                <i class="bi bi-box-seam stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stats-card card-gradient-2">
                            <div class="card-body">
                                <div>
                                    <h3 class="stats-number"><?php echo number_format($stats['productos_bajo_stock']); ?></h3>
                                    <p class="stats-label">Stock Bajo</p>
                                </div>
                                <i class="bi bi-exclamation-triangle stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stats-card card-gradient-3">
                            <div class="card-body">
                                <div>
                                    <h3 class="stats-number">$<?php echo number_format($stats['valor_total'], 2); ?></h3>
                                    <p class="stats-label">Valor Total</p>
                                </div>
                                <i class="bi bi-currency-dollar stats-icon"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card stats-card card-gradient-4">
                            <div class="card-body">
                                <div>
                                    <h3 class="stats-number"><?php echo number_format($stats['stock_total']); ?></h3>
                                    <p class="stats-label">Total de Unidades</p>
                                </div>
                                <i class="bi bi-boxes stats-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="search-section">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="search-label">Buscar</label>
                            <input type="text" class="form-control" name="busqueda" 
                                   placeholder="Código, nombre..." 
                                   value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="search-label">Categoría</label>
                            <select class="form-select" name="categoria">
                                <option value="0">Todas</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" 
                                            <?php echo $filtro_categoria == $categoria['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="search-label">Ubicación</label>
                            <select class="form-select" name="lugar">
                                <option value="0">Todas</option>
                                <?php foreach ($lugares as $lugar): ?>
                                    <option value="<?php echo $lugar['id']; ?>" 
                                            <?php echo $filtro_lugar == $lugar['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lugar['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm-custom flex-fill">
                                    <i class="bi bi-search me-1"></i>Filtrar
                                </button>
                                <a href="productos.php" class="btn btn-outline-secondary btn-sm-custom flex-fill">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Scrollable Content Section -->
        <div class="scrollable-content">
            <!-- Products Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Lista de Productos</h5>
                        <span class="badge bg-primary"><?php echo number_format($total_productos); ?> productos</span>
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table class="table table-hover mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th width="120">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'codigo', 'dir' => $orden_campo === 'codigo' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Código <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'nombre', 'dir' => $orden_campo === 'nombre' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Producto <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="120">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'categoria_nombre', 'dir' => $orden_campo === 'categoria_nombre' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Categoría <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="120">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'lugar_nombre', 'dir' => $orden_campo === 'lugar_nombre' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Ubicación <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="80">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'stock', 'dir' => $orden_campo === 'stock' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Stock <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="90">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'stock_minimo', 'dir' => $orden_campo === 'stock_minimo' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Stock Mín. <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="100">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'precio_venta', 'dir' => $orden_campo === 'precio_venta' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Precio <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="100">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['orden' => 'fecha_creacion', 'dir' => $orden_campo === 'fecha_creacion' && $orden_direccion === 'ASC' ? 'desc' : 'asc'])); ?>" class="text-decoration-none text-dark">
                                        Fecha Alta <i class="bi bi-arrow-down-up"></i>
                                    </a>
                                </th>
                                <th width="160">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="bi bi-inbox display-4 text-muted"></i>
                                        <p class="text-muted mt-2">No se encontraron productos</p>
                                        <?php if (isset($error_message)): ?>
                                            <p class="text-danger"><?php echo htmlspecialchars($error_message); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td>
                                            <code class="text-primary"><?php echo htmlspecialchars($producto['codigo']); ?></code>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary badge-categoria">
                                                <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info badge-categoria">
                                                <?php echo htmlspecialchars($producto['lugar_nombre'] ?? 'Sin ubicación'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $stock_class = '';
                                            if ($producto['stock'] <= 0) {
                                                $stock_class = 'text-danger fw-bold';
                                            } elseif ($producto['stock'] <= $producto['stock_minimo']) {
                                                $stock_class = 'text-warning fw-bold';
                                            } else {
                                                $stock_class = 'text-success fw-bold';
                                            }
                                            ?>
                                            <span class="<?php echo $stock_class; ?>">
                                                <?php echo number_format($producto['stock']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?php echo number_format($producto['stock_minimo']); ?></span>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($producto['precio_venta'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="producto_detalle.php?id=<?php echo $producto['id']; ?>" 
                                                   class="btn btn-info btn-action" title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="producto_form.php?id=<?php echo $producto['id']; ?>" 
                                                   class="btn btn-warning btn-action" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button onclick="inactivarProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')" 
                                                        class="btn btn-secondary btn-action" title="Inactivar">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                                <button onclick="eliminarProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')" 
                                                        class="btn btn-danger btn-action" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Navegación de productos">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Página <?php echo $page; ?> de <?php echo $total_pages; ?> 
                                (<?php echo number_format($total_productos); ?> productos total)
                            </small>
                        </div>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para inactivar -->
    <div class="modal fade" id="modalInactivar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Inactivación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea inactivar el producto <strong id="nombreProductoInactivar"></strong>?</p>
                    <p class="text-muted">El producto se moverá a la lista de inactivos y no aparecerá en el inventario principal.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" id="confirmarInactivar">
                        <i class="bi bi-archive me-1"></i>Inactivar Producto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>¡Atención!</strong> Esta acción no se puede deshacer.
                    </div>
                    <p>¿Está seguro que desea eliminar definitivamente el producto <strong id="nombreProductoEliminar"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarEliminar">
                        <i class="bi bi-trash me-1"></i>Eliminar Definitivamente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let productoIdActual = null;
        
        function inactivarProducto(id, nombre) {
            productoIdActual = id;
            document.getElementById('nombreProductoInactivar').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalInactivar')).show();
        }
        
        function eliminarProducto(id, nombre) {
            productoIdActual = id;
            document.getElementById('nombreProductoEliminar').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalEliminar')).show();
        }
        
        document.getElementById('confirmarInactivar').addEventListener('click', function() {
            if (productoIdActual) {
                gestionarProducto('inactivar', productoIdActual);
            }
        });
        
        document.getElementById('confirmarEliminar').addEventListener('click', function() {
            if (productoIdActual) {
                gestionarProducto('eliminar', productoIdActual);
            }
        });
        
        function gestionarProducto(accion, id) {
            const btn = accion === 'inactivar' ? document.getElementById('confirmarInactivar') : document.getElementById('confirmarEliminar');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Procesando...';
            btn.disabled = true;
            
            fetch('gestionar_producto.php', { 
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    accion: accion,
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modalId = accion === 'inactivar' ? 'modalInactivar' : 'modalEliminar';
                    const modalInstance = bootstrap.Modal.getInstance(document.getElementById(modalId));
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Ocurrió un error desconocido.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud. Verifique la consola para más detalles.');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'excel');
            window.location.href = 'exportar_excel.php?' + params.toString(); 
        }
    </script>
</body>
</html>