<?php
require_once 'config/config.php';

iniciarSesionSegura();
requireLogin();

$pageTitle = "Dashboard - " . SISTEMA_NOMBRE;
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';

// SOLUCIÓN: Obtener rol directamente de la base de datos
$usuario_rol = 'inventario'; // Valor por defecto
try {
    $pdo = conectarDB();
    if (isset($_SESSION['id_usuario'])) {
        $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1");
        $stmt->execute([$_SESSION['id_usuario']]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($resultado) {
            $usuario_rol = $resultado['rol'];
        }
    }
} catch (Exception $e) {
    // En caso de error, mantener valor por defecto
    $usuario_rol = 'inventario';
}

// Verificar si es administrador para mostrar módulo admin
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

// Obtener estadísticas de forma segura
try {
    $pdo = conectarDB();
    
    // Estadísticas básicas de productos
    $stats_productos = obtenerEstadisticasInventario($pdo);
    
    // Estadísticas adicionales con verificación de tablas
    $total_clientes = 0;
    $clientes_nuevos = 0;
    $pedidos_pendientes = 0;
    $pedidos_hoy = 0;
    $facturas_pendientes = 0;
    $monto_pendiente = 0;
    $ingresos_mes = 0;
    $compras_pendientes = 0;
    $compras_mes = 0;
    
    // Verificar tablas existentes
    $tablas_existentes = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tablas_existentes[] = $row[0];
    }
    
    // Estadísticas de clientes
    if (in_array('clientes', $tablas_existentes)) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1");
        $total_clientes = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1");
        $clientes_nuevos = $stmt->fetch()['nuevos'] ?? 0;
    }
    
    // Estadísticas de pedidos
    if (in_array('pedidos', $tablas_existentes)) {
        $stmt = $pdo->query("SELECT COUNT(*) as pendientes FROM pedidos WHERE estado = 'pendiente'");
        $pedidos_pendientes = $stmt->fetch()['pendientes'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as hoy FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()");
        $pedidos_hoy = $stmt->fetch()['hoy'] ?? 0;
    }
    
    // Estadísticas de facturas
    if (in_array('facturas', $tablas_existentes)) {
        $stmt = $pdo->query("SELECT COUNT(*) as pendientes, COALESCE(SUM(total), 0) as monto_pendiente FROM facturas WHERE estado = 'pendiente'");
        $facturas_data = $stmt->fetch();
        $facturas_pendientes = $facturas_data['pendientes'] ?? 0;
        $monto_pendiente = $facturas_data['monto_pendiente'] ?? 0;
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as ingresos_mes FROM facturas WHERE MONTH(fecha_factura) = MONTH(CURDATE()) AND YEAR(fecha_factura) = YEAR(CURDATE()) AND estado = 'pagada'");
        $ingresos_mes = $stmt->fetch()['ingresos_mes'] ?? 0;
    }
    
    // Estadísticas de compras
    if (in_array('compras', $tablas_existentes)) {
        $stmt = $pdo->query("SELECT COUNT(*) as pendientes FROM compras WHERE estado IN ('pendiente', 'confirmada')");
        $compras_pendientes = $stmt->fetch()['pendientes'] ?? 0;
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) as compras_mes FROM compras WHERE MONTH(fecha_compra) = MONTH(CURDATE()) AND YEAR(fecha_compra) = YEAR(CURDATE())");
        $compras_mes = $stmt->fetch()['compras_mes'] ?? 0;
    }
    
} catch (Exception $e) {
    // Valores por defecto en caso de error
    $stats_productos = [
        'total_productos' => 0,
        'productos_bajo_stock' => 0,
        'valor_total_inventario' => 0,
        'precio_promedio' => 0
    ];
    $total_clientes = 0;
    $clientes_nuevos = 0;
    $pedidos_pendientes = 0;
    $pedidos_hoy = 0;
    $facturas_pendientes = 0;
    $monto_pendiente = 0;
    $ingresos_mes = 0;
    $compras_pendientes = 0;
    $compras_mes = 0;
}
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
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            color: white !important;
            font-size: 1.1rem;
        }
        
        .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 2px;
            border-radius: 5px;
            padding: 8px 12px !important;
            font-size: 0.95rem;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateY(-1px);
        }
        
        .navbar-nav .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            font-weight: 600;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .dropdown-item {
            padding: 8px 16px;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .content-area {
            min-height: calc(100vh - 120px);
            padding: 20px 0;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 15px;
            text-align: center;
        }
        
        .dashboard-header h1 {
            font-size: 2.2rem;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            margin-bottom: 20px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.2rem;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .stat-detail {
            font-size: 0.8rem;
            margin-top: 10px;
            padding: 6px 10px;
            border-radius: 15px;
        }
        
        .module-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            margin-bottom: 20px;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .module-icon {
            font-size: 2.8rem;
            margin-bottom: 20px;
        }
        
        .module-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .module-description {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .btn-module {
            border-radius: 25px;
            padding: 8px 20px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .btn-module:hover {
            transform: translateY(-2px);
        }
        
        .actions-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #495057;
        }
        
        .quick-action-btn {
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 500;
            margin: 5px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
        }
        
        .admin-only {
            position: relative;
        }
        
        .admin-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .text-purple { color: #6f42c1 !important; }
        .btn-purple { 
            background-color: #6f42c1; 
            border-color: #6f42c1; 
            color: white;
        }
        .btn-purple:hover { 
            background-color: #5a32a3; 
            border-color: #5a32a3; 
            color: white;
        }
        
        /* Mejoras para móviles y tablets */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1rem;
            }
            
            .navbar-nav .nav-link {
                font-size: 0.9rem;
                padding: 6px 10px !important;
                margin: 1px;
            }
            
            .dashboard-header {
                padding: 20px 15px;
                margin-bottom: 20px;
            }
            
            .dashboard-header h1 {
                font-size: 1.8rem;
            }
            
            .dashboard-header p {
                font-size: 1rem;
            }
            
            .stat-card {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .module-card {
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .module-icon {
                font-size: 2.5rem;
            }
            
            .module-title {
                font-size: 1.1rem;
            }
            
            .actions-section {
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .quick-action-btn {
                padding: 8px 15px;
                margin: 3px;
                font-size: 0.85rem;
                width: 100%;
                margin-bottom: 8px;
            }
            
            .content-area {
                padding: 15px 0;
            }
        }
        
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 0.9rem;
            }
            
            .navbar-nav .nav-link {
                font-size: 0.85rem;
                padding: 5px 8px !important;
            }
            
            .dashboard-header h1 {
                font-size: 1.6rem;
            }
            
            .stat-number {
                font-size: 1.3rem;
            }
            
            .module-icon {
                font-size: 2.2rem;
            }
            
            .module-title {
                font-size: 1rem;
            }
            
            .module-description {
                font-size: 0.8rem;
            }
        }
        
        /* Mejoras para tablets */
        @media (min-width: 769px) and (max-width: 1024px) {
            .navbar-nav .nav-link {
                font-size: 0.9rem;
                padding: 7px 11px !important;
            }
            
            .stat-card {
                padding: 18px;
            }
            
            .module-card {
                padding: 22px;
            }
        }
        
        /* Ocultar elementos en pantallas muy pequeñas */
        @media (max-width: 480px) {
            .stat-detail {
                display: none;
            }
            
            .module-description {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Superior -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="menu_principal.php">
                <i class="bi bi-speedometer2"></i> Gestión Administrativa
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 0.75%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="menu_principal.php">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-box-seam"></i> Productos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="modulos/Inventario/productos.php"><i class="bi bi-list-ul"></i> Listado de Productos</a></li>
                            <li><a class="dropdown-item" href="modulos/Inventario/producto_form.php"><i class="bi bi-plus-circle"></i> Nuevo Producto</a></li>
                            <!-- MODIFICACION: Se agregan las opciones Por Categoria y Por Ubicacion -->
                            <li><a class="dropdown-item" href="https://sistemas-ia.com.ar/sistemadeinventario/modulos/Inventario/productos_por_categoria.php"><i class="bi bi-tag"></i> Por Categoria</a></li>
                            <li><a class="dropdown-item" href="https://sistemas-ia.com.ar/sistemadeinventario/modulos/Inventario/productos_por_lugar.php"><i class="bi bi-geo-alt"></i> Por Ubicación</a></li>
                            <li><a class="dropdown-item" href="modulos/Inventario/productos_inactivos.php"><i class="bi bi-archive"></i> Productos Inactivos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="modulos/Inventario/reportes.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="modulos/clientes/clientes.php"><i class="bi bi-list-ul"></i> Ver Clientes</a></li>
                            <li><a class="dropdown-item" href="modulos/clientes/cliente_form.php"><i class="bi bi-person-plus"></i> Nuevo Cliente</a></li>
                            <li><a class="dropdown-item" href="modulos/clientes/clientes_inactivos.php"><i class="bi bi-person-x"></i> Clientes Inactivos</a></li>
                            <li><a class="dropdown-item" href="modulos/clientes/papelera_clientes.php"><i class="bi bi-trash"></i> Papelera</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cart"></i> Pedidos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="modulos/pedidos/pedidos.php"><i class="bi bi-list-ul"></i> Ver Pedidos</a></li>
                            <li><a class="dropdown-item" href="modulos/pedidos/pedido_form.php"><i class="bi bi-cart-plus"></i> Nuevo Pedido</a></li>
                            <li><a class="dropdown-item" href="modulos/pedidos/pedidos_pendientes.php"><i class="bi bi-clock"></i> Pedidos Pendientes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="modulos/pedidos/reportes_pedidos.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-receipt"></i> Facturación
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="modulos/facturas/facturas.php"><i class="bi bi-list-ul"></i> Ver Facturas</a></li>
                            <li><a class="dropdown-item" href="modulos/facturas/factura_form.php"><i class="bi bi-receipt"></i> Nueva Factura</a></li>
                            <li><a class="dropdown-item" href="modulos/facturas/facturas_pendientes.php"><i class="bi bi-clock"></i> Facturas Pendientes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="modulos/facturas/reportes_facturas.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-truck"></i> Compras
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="modulos/compras/compras.php"><i class="bi bi-list-ul"></i> Ver Compras</a></li>
                            <li><a class="dropdown-item" href="modulos/compras/compra_form.php"><i class="bi bi-truck"></i> Nueva Compra</a></li>
                            <li><a class="dropdown-item" href="modulos/compras/proveedores.php"><i class="bi bi-building"></i> Proveedores</a></li>
                            <li><a class="dropdown-item" href="modulos/compras/recepcion_mercaderia.php"><i class="bi bi-box-arrow-in-down"></i> Recepción</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="modulos/compras/reportes_compras.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($usuario_nombre); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Rol: <?php echo ucfirst($usuario_rol); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <?php if ($es_administrador): ?>
                                <li><h6 class="dropdown-header text-danger"><i class="bi bi-shield-check"></i> Administración</h6></li>
                                <li><a class="dropdown-item" href="modulos/admin/admin_dashboard.php"><i class="bi bi-speedometer2"></i> Panel Admin</a></li>
                                <li><a class="dropdown-item" href="modulos/admin/usuarios.php"><i class="bi bi-people"></i> Gestión de Usuarios</a></li>
                                <li><a class="dropdown-item" href="modulos/admin/configuracion_sistema.php"><i class="bi bi-gear"></i> Configuración Sistema</a></li>
                                <li><a class="dropdown-item" href="modulos/admin/reportes_admin.php"><i class="bi bi-graph-up"></i> Reportes Admin</a></li>
                                <li><a class="dropdown-item" href="modulos/admin/logs_sistema.php"><i class="bi bi-journal-text"></i> Logs del Sistema</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="content-area">
        <div class="container-fluid">
            <!-- Header del Dashboard -->
            <div class="dashboard-header">
                <h1><i class="bi bi-speedometer2"></i> Dashboard Principal</h1>
                <p>Bienvenido al sistema de gestión integral InventPro</p>
            </div>

            <!-- Estadísticas -->
            <div class="row">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div class="stat-number text-primary"><?php echo number_format($stats_productos['total_productos']); ?></div>
                        <div class="stat-label">Productos Activos</div>
                        <div class="stat-detail bg-light text-muted">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo $stats_productos['productos_bajo_stock']; ?> bajo stock
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-number text-success"><?php echo number_format($total_clientes); ?></div>
                        <div class="stat-label">Clientes Activos</div>
                        <div class="stat-detail bg-light text-muted">
                            <i class="bi bi-plus-circle"></i> <?php echo $clientes_nuevos; ?> nuevos este mes
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-warning">
                            <i class="bi bi-cart"></i>
                        </div>
                        <div class="stat-number text-warning"><?php echo number_format($pedidos_pendientes); ?></div>
                        <div class="stat-label">Pedidos Pendientes</div>
                        <div class="stat-detail bg-light text-muted">
                            <i class="bi bi-calendar-day"></i> <?php echo $pedidos_hoy; ?> pedidos hoy
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-info">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div class="stat-number text-info">$<?php echo number_format($monto_pendiente, 2); ?></div>
                        <div class="stat-label">Facturas Pendientes</div>
                        <div class="stat-detail bg-light text-muted">
                            <i class="bi bi-file-earmark"></i> <?php echo $facturas_pendientes; ?> facturas
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-purple">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div class="stat-number text-purple"><?php echo number_format($compras_pendientes); ?></div>
                        <div class="stat-label">Compras Pendientes</div>
                        <div class="stat-detail bg-light text-muted">
                            <i class="bi bi-currency-dollar"></i> $<?php echo number_format($compras_mes, 0); ?> este mes
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <div class="stat-number text-success">$<?php echo number_format($ingresos_mes, 2); ?></div>
                        <div class="stat-label">Ingresos del Mes</div>
                        <div class="stat-detail bg-light text-muted">
                            <i class="bi bi-check-circle"></i> Ventas realizadas
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="actions-section">
                <h2 class="section-title"><i class="bi bi-lightning"></i> Acciones Rápidas</h2>
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="modulos/Inventario/producto_form.php" class="btn btn-primary quick-action-btn w-100">
                            <i class="bi bi-plus-circle"></i> Nuevo Producto
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="modulos/clientes/cliente_form.php" class="btn btn-success quick-action-btn w-100">
                            <i class="bi bi-person-plus"></i> Nuevo Cliente
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="modulos/pedidos/pedido_form.php" class="btn btn-warning quick-action-btn w-100">
                            <i class="bi bi-cart-plus"></i> Nuevo Pedido
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <a href="modulos/compras/compra_form.php" class="btn btn-purple quick-action-btn w-100">
                            <i class="bi bi-truck"></i> Nueva Compra
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulos Principales -->
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="module-card">
                        <div class="module-icon text-primary">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <h3 class="module-title">Gestión de Productos</h3>
                        <p class="module-description">Administra tu inventario, controla stock, precios y categorías de productos.</p>
                        <a href="modulos/Inventario/productos.php" class="btn btn-primary btn-module">Acceder</a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="module-card">
                        <div class="module-icon text-success">
                            <i class="bi bi-people"></i>
                        </div>
                        <h3 class="module-title">Gestión de Clientes</h3>
                        <p class="module-description">Administra tu base de clientes, historial de compras y datos de contacto.</p>
                        <a href="modulos/clientes/clientes.php" class="btn btn-success btn-module">Acceder</a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="module-card">
                        <div class="module-icon text-warning">
                            <i class="bi bi-cart"></i>
                        </div>
                        <h3 class="module-title">Gestión de Pedidos</h3>
                        <p class="module-description">Procesa pedidos de clientes, controla estados y tiempos de entrega.</p>
                        <a href="modulos/pedidos/pedidos.php" class="btn btn-warning btn-module">Acceder</a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="module-card">
                        <div class="module-icon text-info">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <h3 class="module-title">Facturación</h3>
                        <p class="module-description">Genera facturas, controla pagos y gestiona la contabilidad del negocio.</p>
                        <a href="modulos/facturas/facturas.php" class="btn btn-info btn-module">Acceder</a>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="module-card">
                        <div class="module-icon text-purple">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h3 class="module-title">Gestión de Compras</h3>
                        <p class="module-description">Administra proveedores, órdenes de compra y recepción de mercadería.</p>
                        <a href="modulos/compras/compras.php" class="btn btn-purple btn-module">Acceder</a>
                    </div>
                </div>
                
                <?php if ($es_administrador): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="module-card admin-only">
                        <span class="admin-badge">ADMIN</span>
                        <div class="module-icon text-danger">
                            <i class="bi bi-gear"></i>
                        </div>
                        <h3 class="module-title">Gestión de Administración</h3>
                        <p class="module-description">Panel de administración, usuarios, configuración del sistema y reportes.</p>
                        <a href="modulos/admin/admin_dashboard.php" class="btn btn-danger btn-module">Acceder</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mejorar la experiencia en móviles
        document.addEventListener('DOMContentLoaded', function() {
            // Cerrar navbar al hacer clic en un enlace (móviles)
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            const navbarCollapse = document.querySelector('.navbar-collapse');
            
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 992) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                            toggle: false
                        });
                        bsCollapse.hide();
                    }
                });
            });
            
            // Mejorar hover en dispositivos táctiles
            const cards = document.querySelectorAll('.stat-card, .module-card');
            cards.forEach(card => {
                card.addEventListener('touchstart', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>