<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Configurar charset UTF-8
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8'); // Asegurarse de que las funciones string trabajen con UTF-8

// --- Lógica para datos del Navbar (adaptada de productos.php) ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

$total_clientes_nav = 0;
$clientes_nuevos_nav = 0;
$pedidos_pendientes_nav = 0; // Se calcula pero el badge no se mostrará aquí
$facturas_pendientes_nav = 0;
$compras_pendientes_nav = 0;
$tablas_existentes_nav = [];

try {
    $pdo_nav = conectarDB();
    $pdo_nav->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt_tables_nav = $pdo_nav->query("SHOW TABLES");
    if($stmt_tables_nav){
        while ($row_table_nav = $stmt_tables_nav->fetch(PDO::FETCH_NUM)) {
            $tablas_existentes_nav[] = $row_table_nav[0];
        }
    }

    if (in_array('clientes', $tablas_existentes_nav)) {
        $stmt_cli_total_nav = $pdo_nav->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1 AND eliminado = 0");
        if($stmt_cli_total_nav) $total_clientes_nav = $stmt_cli_total_nav->fetch()['total'] ?? 0;

        $stmt_cli_nuevos_nav = $pdo_nav->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1 AND eliminado = 0");
        if($stmt_cli_nuevos_nav) $clientes_nuevos_nav = $stmt_cli_nuevos_nav->fetch()['nuevos'] ?? 0;
    }

    if (in_array('pedidos', $tablas_existentes_nav)) {
        $stmt_ped_pend_nav = $pdo_nav->query("SELECT COUNT(*) as pendientes FROM pedidos WHERE estado = 'pendiente'");
        if($stmt_ped_pend_nav) $pedidos_pendientes_nav = $stmt_ped_pend_nav->fetch()['pendientes'] ?? 0;
    }

    if (in_array('facturas', $tablas_existentes_nav)) {
        $stmt_fact_pend_nav = $pdo_nav->query("SELECT COUNT(*) as pendientes FROM facturas WHERE estado = 'pendiente'");
        if($stmt_fact_pend_nav){
            $facturas_data_nav = $stmt_fact_pend_nav->fetch();
            $facturas_pendientes_nav = $facturas_data_nav['pendientes'] ?? 0;
        }
    }

    if (in_array('compras', $tablas_existentes_nav)) {
        $stmt_compras_pend_nav = $pdo_nav->query("SELECT COUNT(*) as pendientes FROM compras WHERE estado IN ('pendiente', 'confirmada')");
        if($stmt_compras_pend_nav) $compras_pendientes_nav = $stmt_compras_pend_nav->fetch()['pendientes'] ?? 0;
    }

} catch (Exception $e) {
    error_log("Error al cargar datos para el menú en papelera_clientes.php: " . $e->getMessage());
    $total_clientes_nav = 0; $clientes_nuevos_nav = 0; $pedidos_pendientes_nav = 0; $facturas_pendientes_nav = 0; $compras_pendientes_nav = 0; $tablas_existentes_nav = [];
}
// --- FIN Lógica Navbar ---


// Paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Ordenamiento
$orden_campo = isset($_GET['orden']) ? $_GET['orden'] : 'fecha_eliminacion';
$orden_direccion = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

$campos_permitidos = ['codigo', 'nombre', 'apellido', 'empresa', 'fecha_eliminacion', 'eliminado_por'];
if (!in_array($orden_campo, $campos_permitidos)) {
    $orden_campo = 'fecha_eliminacion';
}

// Inicializar variables específicas de la página
$clientes_eliminados = [];
$total_clientes_papelera = 0; // Renombrado para evitar conflicto
$total_pages = 0;
$error_mensaje = '';
$clientes_en_papelera_footer = 0; // Para el badge del footer si se necesita

try {
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Construir consulta con filtros
    $where_conditions = ["eliminado = 1"];
    $params = [];

    if (!empty($filtro_busqueda)) {
        $where_conditions[] = "(codigo LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR empresa LIKE ? OR email LIKE ?)";
        $busqueda_param = "%{$filtro_busqueda}%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Consulta principal con paginación y ordenamiento
    $sql = "SELECT * FROM clientes WHERE {$where_clause} ORDER BY {$orden_campo} {$orden_direccion} LIMIT {$per_page} OFFSET {$offset}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes_eliminados = $stmt->fetchAll();

    // Contar total para paginación
    $count_sql = "SELECT COUNT(*) FROM clientes WHERE {$where_clause}";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_clientes_papelera = $count_stmt->fetchColumn();
    $total_pages = ceil($total_clientes_papelera / $per_page);

    // Contar total para el badge del footer si se usa
     $stmt_papelera_count_footer = $pdo->query("SELECT COUNT(*) FROM clientes WHERE eliminado = 1");
     if($stmt_papelera_count_footer) $clientes_en_papelera_footer = $stmt_papelera_count_footer->fetchColumn();


} catch (Exception $e) {
    $error_mensaje = "Error al cargar los datos: " . $e->getMessage();
    error_log($error_mensaje);
    $clientes_eliminados = [];
    $total_clientes_papelera = 0;
    $total_pages = 1;
    $clientes_en_papelera_footer = 0;
}

$pageTitle = "Papelera de Clientes - " . SISTEMA_NOMBRE;

// Funciones auxiliares
function getTipoClienteClass($tipo) {
    switch ($tipo) {
        case 'mayorista': return 'bg-primary';
        case 'minorista': return 'bg-success';
        case 'may_min': return 'bg-warning text-dark';
        default: return 'bg-secondary';
    }
}

function getTipoClienteTexto($tipo) {
    switch ($tipo) {
        case 'mayorista': return 'Mayorista';
        case 'minorista': return 'Minorista';
        case 'may_min': return 'May/Min';
        default: return 'No definido';
    }
}

function getOrdenUrl($campo) {
    global $orden_campo, $orden_direccion, $_GET;
    $nueva_direccion = ($orden_campo === $campo && $orden_direccion === 'ASC') ? 'desc' : 'asc';
    $params = $_GET;
    $params['orden'] = $campo;
    $params['dir'] = $nueva_direccion;
     unset($params['page']); // Asegurarse de resetear la página al cambiar el orden/filtro
    return '?' . http_build_query($params);
}

function getOrdenIcon($campo) {
    global $orden_campo, $orden_direccion;
    if ($orden_campo === $campo) {
        return $orden_direccion === 'ASC' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>';
    }
    return '<i class="bi bi-arrow-down-up text-muted"></i>';
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
        html, body { height: 100%; overflow: hidden; }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Estilos del Navbar (como en menu_principal.php / productos.php) */
        .navbar-custom {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-custom .navbar-brand { font-weight: bold; color: white !important; font-size: 1.1rem; }
        .navbar-custom .navbar-nav .nav-link { color: white !important; font-weight: 500; transition: all 0.3s ease; margin: 0 2px; border-radius: 5px; padding: 8px 12px !important; font-size: 0.95rem; }
        .navbar-custom .navbar-nav .nav-link:hover { background-color: rgba(255,255,255,0.1); transform: translateY(-1px); }
        .navbar-custom .navbar-nav .nav-link.active { background-color: rgba(255,255,255,0.2); font-weight: 600; }
        .navbar-custom .dropdown-menu { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; }
        .navbar-custom .dropdown-item { padding: 8px 16px; transition: all 0.2s ease; }
        .navbar-custom .dropdown-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
        /* Fin de estilos Navbar */

        .page-wrapper { display: flex; flex-direction: column; height: 100vh; }
        .navbar-custom.sticky-top { flex-shrink: 0; }

        .main-content-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        .fixed-header-content { flex-shrink: 0; padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .scrollable-content-area { flex-grow: 1; overflow-y: auto; padding: 0 20px; background-color: #f8f9fa; }
        .fixed-footer-actions { flex-shrink: 0; padding: 15px 20px; background-color: #ffffff; border-top: 1px solid #dee2e6; box-shadow: 0 -2px 5px rgba(0,0,0,0.05); }


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
        .form-control, .form-select { font-size: 0.85rem; padding: 6px 10px; height: auto; }

        .table-container-inner {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px; /* Espacio entre el header fijo y la tabla */
        }

        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 12px 8px;
            cursor: pointer;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table th:hover {
            background-color: #e9ecef;
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

        .cliente-eliminado {
            opacity: 0.7;
            background-color: #fff5f5;
        }

        .cliente-info {
            line-height: 1.2;
        }

        .cliente-nombre {
            font-weight: 600;
            color: #2c3e50;
        }

        .cliente-empresa {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .info-eliminacion {
            font-size: 0.8rem;
            color: #dc3545;
            font-style: italic;
        }

        .pagination-container {
            flex-shrink: 0;
            background: white;
            border-top: 1px solid #dee2e6;
            padding: 15px;
            margin: 0;
        }

        .alert-papelera {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
        }

        .btn-group .btn-action {
            border-radius: 0;
        }

        .btn-group .btn-action:first-child {
            border-top-left-radius: 5px;
            border-bottom-left-radius: 5px;
        }

        .btn-group .btn-action:last-child {
            border-top-right-radius: 5px;
            border-bottom-right-radius: 5px;
        }
         .quick-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .quick-action-btn { flex: 1; min-width: 180px; max-width: 220px; padding: 10px 15px; border-radius: 8px; font-size: 0.9rem; font-weight: 500; text-decoration: none; transition: all 0.2s; }
        .quick-action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .papelera-badge { position: relative; }
        .papelera-count { position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; font-weight: bold; }
         .table th .bi { margin-left: 5px; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <!-- Navbar Superior con estilo de menu_principal.php -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../menu_principal.php">
                <i class="bi bi-speedometer2"></i> Gestión Administrativa
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 0.75%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3csvg%3e');"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../../menu_principal.php">
                            <i class="bi bi-house-door"></i> Dashboard
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-box-seam"></i> Productos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../Inventario/productos.php"><i class="bi bi-list-ul"></i> Listado de Productos</a></li>
                            <li><a class="dropdown-item" href="../Inventario/producto_form.php"><i class="bi bi-plus-circle"></i> Nuevo Producto</a></li>
                            <li><a class="dropdown-item" href="../Inventario/productos_por_categoria.php"><i class="bi bi-tag"></i> Por Categoria</a></li>
                            <li><a class="dropdown-item" href="../Inventario/productos_por_lugar.php"><i class="bi bi-geo-alt"></i> Por Ubicación</a></li>
                            <li><a class="dropdown-item" href="../Inventario/productos_inactivos.php"><i class="bi bi-archive"></i> Productos Inactivos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../Inventario/reportes.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people"></i> Clientes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="clientes.php"><i class="bi bi-list-ul"></i> Listado de Clientes</a></li>
                            <li><a class="dropdown-item" href="cliente_form.php"><i class="bi bi-person-plus"></i> Nuevo Cliente</a></li>
                            <li><a class="dropdown-item" href="clientes_inactivos.php"><i class="bi bi-person-x"></i> Clientes Inactivos</a></li>
                            <li><a class="dropdown-item active" href="papelera_clientes.php"><i class="bi bi-trash"></i> Papelera</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cart"></i> Pedidos
                            <?php /* Badge de pedidos pendientes eliminado para esta página */ ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../pedidos/pedidos.php"><i class="bi bi-list-ul"></i> Ver Pedidos</a></li>
                            <li><a class="dropdown-item" href="../pedidos/pedido_form.php"><i class="bi bi-cart-plus"></i> Nuevo Pedido</a></li>
                            <li><a class="dropdown-item" href="../pedidos/pedidos_pendientes.php"><i class="bi bi-clock"></i> Pedidos Pendientes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../pedidos/reportes_pedidos.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-receipt"></i> Facturación
                             <?php if (isset($facturas_pendientes_nav) && $facturas_pendientes_nav > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $facturas_pendientes_nav; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../facturas/facturas.php"><i class="bi bi-list-ul"></i> Ver Facturas</a></li>
                            <li><a class="dropdown-item" href="../facturas/factura_form.php"><i class="bi bi-receipt"></i> Nueva Factura</a></li>
                            <li><a class="dropdown-item" href="../facturas/facturas_pendientes.php"><i class="bi bi-clock"></i> Facturas Pendientes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../facturas/reportes_facturas.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-truck"></i> Compras
                            <?php if (isset($compras_pendientes_nav) && $compras_pendientes_nav > 0): ?>
                                <span class="badge bg-info text-dark ms-1"><?php echo $compras_pendientes_nav; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../compras/compras.php"><i class="bi bi-list-ul"></i> Ver Compras</a></li>
                            <li><a class="dropdown-item" href="../compras/compra_form.php"><i class="bi bi-truck"></i> Nueva Compra</a></li>
                            <li><a class="dropdown-item" href="../compras/proveedores.php"><i class="bi bi-building"></i> Proveedores</a></li>
                            <li><a class="dropdown-item" href="../compras/recepcion_mercaderia.php"><i class="bi bi-box-arrow-in-down"></i> Recepción</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../compras/reportes_compras.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
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
                                <li><a class="dropdown-item" href="../Admin/admin_dashboard.php"><i class="bi bi-speedometer2"></i> Panel Admin</a></li>
                                <li><a class="dropdown-item" href="../Admin/usuarios.php"><i class="bi bi-people"></i> Gestión de Usuarios</a></li>
                                <li><a class="dropdown-item" href="../Admin/configuracion_sistema.php"><i class="bi bi-gear"></i> Configuración Sistema</a></li>
                                <li><a class="dropdown-item" href="../Admin/reportes_admin.php"><i class="bi bi-graph-up"></i> Reportes Admin</a></li>
                                <li><a class="dropdown-item" href="../Admin/logs_sistema.php"><i class="bi bi-journal-text"></i> Logs del Sistema</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>

                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content-wrapper">
        <div class="fixed-header-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-trash me-2"></i>Papelera de Clientes</h2>
                <div>
                    <a href="clientes.php" class="btn btn-primary me-2">
                        <i class="bi bi-arrow-left me-1"></i>Volver a Clientes
                    </a>
                    <button class="btn btn-warning" onclick="vaciarPapelera()" <?php echo $total_clientes_papelera == 0 ? 'disabled' : ''; ?>>
                        <i class="bi bi-trash3 me-1"></i>Vaciar Papelera
                    </button>
                </div>
            </div>

            <!-- Alert Info -->
            <div class="alert alert-papelera">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Papelera de Clientes:</strong> Estos clientes han sido eliminados pero pueden ser restaurados.
                Los clientes en la papelera no aparecen en el listado principal ni en reportes.
            </div>

            <!-- Search -->
            <div class="search-section">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="search-label">Buscar en papelera</label>
                        <input type="text" class="form-control" name="busqueda"
                               placeholder="Código, nombre, empresa, email..."
                               value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                    </div>

                    <div class="col-md-4">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-search me-1"></i>Buscar
                            </button>
                            <a href="papelera_clientes.php" class="btn btn-outline-secondary btn-sm flex-fill">
                                <i class="bi bi-arrow-clockwise me-1"></i>Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Content Area -->
        <div class="scrollable-content-area">
            <?php if (!empty($error_mensaje)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_mensaje); ?>
                </div>
            <?php endif; ?>

            <!-- Tabla -->
            <div class="table-container-inner">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('codigo'); ?>'">
                                    Código <?php echo getOrdenIcon('codigo'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('nombre'); ?>'">
                                    Cliente <?php echo getOrdenIcon('nombre'); ?>
                                </th>
                                <th>Tipo</th>
                                <th>Contacto</th>
                                <th>País</th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('fecha_eliminacion'); ?>'">
                                    Eliminado <?php echo getOrdenIcon('fecha_eliminacion'); ?>
                                </th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('eliminado_por'); ?>'">
                                    Eliminado por <?php echo getOrdenIcon('eliminado_por'); ?>
                                </th>
                                <th style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes_eliminados)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-trash text-muted" style="font-size: 2rem;"></i>
                                        <p class="text-muted mt-2">La papelera está vacía</p>
                                        <a href="clientes.php" class="btn btn-primary btn-sm">
                                            <i class="bi bi-arrow-left me-1"></i>Volver a Clientes
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes_eliminados as $cliente): ?>
                                    <tr class="cliente-eliminado">
                                        <td>
                                            <code class="text-danger"><?php echo htmlspecialchars($cliente['codigo']); ?></code>
                                        </td>
                                        <td>
                                            <div class="cliente-info">
                                                <div class="cliente-nombre">
                                                    <?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?>
                                                </div>
                                                <?php if (!empty($cliente['empresa'])): ?>
                                                    <div class="cliente-empresa">
                                                        <i class="bi bi-building me-1"></i>
                                                        <?php echo htmlspecialchars($cliente['empresa']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($cliente['tipo_cliente'])): ?>
                                                <span class="badge <?php echo getTipoClienteClass($cliente['tipo_cliente']); ?>">
                                                    <?php echo getTipoClienteTexto($cliente['tipo_cliente']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.8rem; color: #6c757d;">
                                                <?php if (!empty($cliente['email'])): ?>
                                                    <div><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($cliente['email']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($cliente['telefono'])): ?>
                                                    <div><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($cliente['telefono']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($cliente['pais'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($cliente['pais']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="info-eliminacion">
                                                <i class="bi bi-calendar-x me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($cliente['fecha_eliminacion'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($cliente['eliminado_por'] ?? 'Sistema'); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success btn-action"
                                                        title="Restaurar" onclick="restaurarCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars(addslashes($cliente['nombre'] . ' ' . $cliente['apellido']), ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-action"
                                                        title="Eliminar definitivamente" onclick="eliminarDefinitivamente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars(addslashes($cliente['nombre'] . ' ' . $cliente['apellido']), ENT_QUOTES, 'UTF-8'); ?>')">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Paginación de papelera">
                            <ul class="pagination pagination-sm justify-content-center mb-0">
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
                        </nav>

                        <div class="text-center mt-2">
                            <small class="text-muted">
                                Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                                (<?php echo number_format($total_clientes_papelera); ?> clientes en papelera)
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="fixed-footer-actions">
            <div class="quick-actions">
                 <a href="clientes.php" class="btn btn-primary quick-action-btn"><i class="bi bi-people me-2"></i>Ver Activos</a>
                <a href="cliente_form.php" class="btn btn-success quick-action-btn"><i class="bi bi-person-plus me-2"></i>Nuevo Cliente</a>
                <a href="papelera_clientes.php" class="btn btn-secondary quick-action-btn papelera-badge"><i class="bi bi-trash me-2"></i>Papelera Clientes <?php if($clientes_en_papelera_footer > 0):?><span class="papelera-count"><?php echo $clientes_en_papelera_footer;?></span><?php endif;?></a>
                <a href="reportes_clientes.php" class="btn btn-info quick-action-btn"><i class="bi bi-graph-up me-2"></i>Reportes Gráficos</a>
            </div>
        </div>
    </div>
</div>

    <!-- Modal de confirmación para restaurar -->
    <div class="modal fade" id="modalRestaurar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-clockwise me-2"></i>
                        Restaurar Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea restaurar al cliente <strong id="nombreClienteRestaurar"></strong>?</p>
                    <div class="alert alert-success">
                        <i class="bi bi-info-circle me-2"></i>
                        El cliente será restaurado y volverá a aparecer en el listado principal de clientes activos.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="confirmarRestaurar">
                        <i class="bi bi-arrow-clockwise me-2"></i>Restaurar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar definitivamente -->
    <div class="modal fade" id="modalEliminarDefinitivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Eliminar Definitivamente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>¡ATENCIÓN!</strong> Esta acción es irreversible.
                    </div>
                    <p>¿Está seguro que desea eliminar definitivamente al cliente <strong id="nombreClienteEliminarDef"></strong>?</p>
                    <p class="text-danger"><strong>El cliente será eliminado permanentemente de la base de datos y no se podrá recuperar.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarEliminarDefinitivo">
                        <i class="bi bi-trash3 me-2"></i>Eliminar Definitivamente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para vaciar papelera -->
    <div class="modal fade" id="modalVaciarPapelera" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bi bi-trash3 me-2"></i>
                        Vaciar Papelera
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>¡ATENCIÓN!</strong> Esta acción eliminará definitivamente todos los clientes de la papelera.
                    </div>
                    <p>¿Está seguro que desea vaciar completamente la papelera?</p>
                    <p class="text-danger"><strong>Todos los clientes en la papelera serán eliminados permanentemente.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" id="confirmarVaciarPapelera">
                        <i class="bi bi-trash3 me-2"></i>Vaciar Papelera
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let clienteIdActual = null;

        function restaurarCliente(id, nombre) {
            clienteIdActual = id;
            document.getElementById('nombreClienteRestaurar').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalRestaurar')).show();
        }

        function eliminarDefinitivamente(id, nombre) {
            clienteIdActual = id;
            document.getElementById('nombreClienteEliminarDef').textContent = nombre;
            new bootstrap.Modal(document.getElementById('modalEliminarDefinitivo')).show();
        }

        function vaciarPapelera() {
            new bootstrap.Modal(document.getElementById('modalVaciarPapelera')).show();
        }

        document.getElementById('confirmarRestaurar')?.addEventListener('click', function() {
            if (clienteIdActual) {
                gestionarPapelera('restaurar', clienteIdActual, this);
            }
        });

        document.getElementById('confirmarEliminarDefinitivo')?.addEventListener('click', function() {
            if (clienteIdActual) {
                gestionarPapelera('eliminar_definitivo', clienteIdActual, this);
            }
        });

        document.getElementById('confirmarVaciarPapelera')?.addEventListener('click', function() {
            gestionarPapelera('vaciar_papelera', null, this);
        });

        function gestionarPapelera(accion, id, btnEl) {
             const originalText = btnEl.innerHTML;
            btnEl.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Procesando...';
            btnEl.disabled = true;

            fetch('gestionar_papelera.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    accion: accion,
                    id: id
                })
            })
            .then(response => {
                 if (!response.ok) {
                     return response.json().then(eD => { throw new Error(eD.message || 'Servidor: ' + response.status); });
                 }
                 return response.json();
             })
            .then(data => {
                if (data.success) {
                    // Cerrar modal
                    const modals = ['modalRestaurar', 'modalEliminarDefinitivo', 'modalVaciarPapelera'];
                    modals.forEach(modalId => {
                        const modalElement = document.getElementById(modalId);
                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    });

                    // Mostrar mensaje de éxito
                    mostrarMensajeGlobal(data.message, 'success');

                    // Recargar página o eliminar fila
                     if (accion === 'restaurar' || accion === 'eliminar_definitivo') {
                         const row = document.getElementById('cliente-row-' + id);
                         if (row) {
                             row.style.opacity = '0.5'; // Efecto visual antes de recargar
                             setTimeout(() => window.location.reload(), 500); // Pequeña pausa antes de recargar
                         } else {
                             window.location.reload(); // Recarga si la fila no se encuentra
                         }
                     } else if (accion === 'vaciar_papelera') {
                         setTimeout(() => window.location.reload(), 500); // Recarga después de vaciar
                     }

                } else {
                    mostrarMensajeGlobal('Error: ' + (data.message || 'Ocurrió un error desconocido.'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensajeGlobal('Error al procesar la solicitud: ' + error.message, 'danger');
                 // Asegurarse de cerrar cualquier modal abierto si hay un error en la solicitud Fetch
                 const modals = ['modalRestaurar', 'modalEliminarDefinitivo', 'modalVaciarPapelera'];
                 modals.forEach(modalId => {
                     const modalElement = document.getElementById(modalId);
                     const modalInstance = bootstrap.Modal.getInstance(modalElement);
                     if (modalInstance) {
                         modalInstance.hide();
                     }
                 });
            })
            .finally(() => {
                btnEl.innerHTML = originalText;
                btnEl.disabled = false;
            });
        }

         // Función para mostrar mensajes flotantes (copiada de clientes.php)
         function mostrarMensajeGlobal(msg,tipo){
             const c=document.body,aI='ga-'+Date.now(),aH=`<div id="${aI}" class="alert alert-${tipo} alert-dismissible fade show position-fixed" style="top:70px;right:20px;z-index:1056;min-width:300px;" role="alert"><i class="bi bi-${tipo==='success'?'check-circle-fill':'exclamation-triangle-fill'} me-2"></i> ${msg} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
             c.insertAdjacentHTML('beforeend',aH);
             const aE=document.getElementById(aI);
             if(aE){
                 const bA=new bootstrap.Alert(aE);
                 setTimeout(()=>bA.close(),5000);
             }
         }
    </script>
</body>
</html>