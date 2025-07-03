<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/config.php';

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

iniciarSesionSegura();
requireLogin('../../login.php');

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
    error_log("Error al cargar datos para el menú en clientes_inactivos.php: " . $e->getMessage());
    $total_clientes_nav = 0; $clientes_nuevos_nav = 0; $pedidos_pendientes_nav = 0; $facturas_pendientes_nav = 0; $compras_pendientes_nav = 0; $tablas_existentes_nav = [];
}
// --- FIN Lógica Navbar ---


$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

$filtro_busqueda = trim($_GET['busqueda'] ?? '');
$filtro_tipo_cliente = $_GET['tipo_cliente'] ?? '';
$filtro_pais = $_GET['pais'] ?? '';
$orden_campo = $_GET['orden'] ?? 'fecha_modificacion';
$orden_direccion = $_GET['dir'] ?? 'DESC';

$campos_validos = ['codigo', 'nombre', 'apellido', 'empresa', 'tipo_cliente', 'pais', 'fecha_creacion', 'fecha_modificacion'];
if (!in_array($orden_campo, $campos_validos)) $orden_campo = 'fecha_modificacion';
$orden_direccion = strtoupper($orden_direccion) === 'ASC' ? 'ASC' : 'DESC';

$tipos_cliente_filtro = ['mayorista' => 'Mayorista', 'minorista' => 'Minorista', 'may_min' => 'Mayorista/Minorista'];
$clientes = []; $paises = []; $total_registros = 0; $total_paginas = 1; $clientes_en_papelera = 0; $error_mensaje = '';

try {
    $pdo_page = conectarDB();
    $pdo_page->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $where_conditions = ['activo = 0', 'eliminado = 0'];
    $params = [];

    if (!empty($filtro_busqueda)) {
        $where_conditions[] = "(codigo LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR empresa LIKE ? OR email LIKE ?)";
        $busqueda_param = "%$filtro_busqueda%";
        for($i=0; $i<5; $i++) $params[] = $busqueda_param;
    }
    if (!empty($filtro_tipo_cliente)) { $where_conditions[] = "tipo_cliente = ?"; $params[] = $filtro_tipo_cliente; }
    if (!empty($filtro_pais)) { $where_conditions[] = "pais = ?"; $params[] = $filtro_pais; }
    $where_clause = implode(' AND ', $where_conditions);

    $sql_count = "SELECT COUNT(*) FROM clientes WHERE $where_clause";
    $stmt_count = $pdo_page->prepare($sql_count);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = $total_registros ? ceil($total_registros / $registros_por_pagina) : 1;

    $sql_data = "SELECT * FROM clientes WHERE $where_clause ORDER BY $orden_campo $orden_direccion LIMIT $registros_por_pagina OFFSET $offset";
    $stmt_data = $pdo_page->prepare($sql_data);
    $stmt_data->execute($params);
    $clientes = $stmt_data->fetchAll();

    $sql_paises_filter = "SELECT DISTINCT pais FROM clientes WHERE pais IS NOT NULL AND pais != '' AND activo = 0 AND eliminado = 0 ORDER BY pais";
    $stmt_paises_filter = $pdo_page->query($sql_paises_filter);
    if($stmt_paises_filter) $paises = $stmt_paises_filter->fetchAll();

    $stmt_papelera_count = $pdo_page->query("SELECT COUNT(*) FROM clientes WHERE eliminado = 1");
    if($stmt_papelera_count) $clientes_en_papelera = $stmt_papelera_count->fetchColumn();

} catch (Exception $e) {
    $error_mensaje = "Error al cargar clientes inactivos: " . $e->getMessage();
}

$pageTitle = "Clientes Inactivos - " . SISTEMA_NOMBRE;

function getTipoClienteClass($tipo) { switch ($tipo) { case 'mayorista': return 'bg-primary'; case 'minorista': return 'bg-success'; case 'may_min': return 'bg-warning text-dark'; default: return 'bg-secondary'; } }
function getTipoClienteTexto($tipo) { switch ($tipo) { case 'mayorista': return 'Mayorista'; case 'minorista': return 'Minorista'; case 'may_min': return 'May/Min'; default: return 'No definido'; } }
function generarEnlaceOrden($campo, $orden_actual, $direccion_actual_func) { $nueva_direccion_func = ($campo === $orden_actual && $direccion_actual_func === 'ASC') ? 'DESC' : 'ASC'; $params_func = $_GET; $params_func['orden'] = $campo; $params_func['dir'] = $nueva_direccion_func; return '?' . http_build_query($params_func); }
function obtenerIconoOrden($campo, $orden_actual, $direccion_actual_func) { if ($campo !== $orden_actual) return '<i class="bi bi-arrow-down-up text-muted"></i>'; return $direccion_actual_func === 'ASC' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>'; }
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
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

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

        .search-section { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 12px 15px; margin-bottom: 20px; }
        .search-label { font-weight: bold; font-size: 0.85rem; margin-bottom: 3px; color: #2c3e50; display: block; }
        .form-control, .form-select { font-size: 0.85rem; padding: 6px 10px; height: auto; }
        .btn-sm-custom { font-size: 0.8rem; padding: 6px 12px; }

        .table-container-inner { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 20px; }
        .table th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; font-size: 0.9rem; padding: 12px 8px; cursor: pointer; user-select: none; position: sticky; top: 0; z-index: 10; }
        .table th:hover { background-color: #e9ecef; }
        .table td { padding: 10px 8px; vertical-align: middle; font-size: 0.9rem; }
        .btn-action { padding: 4px 8px; margin: 0 1px; border-radius: 5px; font-size: 0.8rem; }
        .badge-tipo { font-size: 0.75rem; padding: 4px 8px; }
        .cliente-info { line-height: 1.2; }
        .cliente-nombre { font-weight: 600; color: #2c3e50; }
        .cliente-empresa { font-size: 0.85rem; color: #6c757d; }
        .cliente-contacto { font-size: 0.8rem; color: #6c757d; }
        .pagination-container { padding: 15px; border-top: 1px solid #dee2e6; }
        .alert-inactivos { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; }
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
                            <li><a class="dropdown-item active" href="clientes_inactivos.php"><i class="bi bi-person-x"></i> Clientes Inactivos</a></li>
                            <li><a class="dropdown-item" href="papelera_clientes.php"><i class="bi bi-trash"></i> Papelera</a></li>
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
                <h2><i class="bi bi-person-x me-2"></i>Clientes Inactivos</h2>
                <div><a href="clientes.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Volver a Clientes Activos</a></div>
            </div>
            <div class="alert alert-inactivos mb-4"><i class="bi bi-info-circle me-2"></i><strong>Clientes Inactivos:</strong> Estos clientes han sido desactivados. Puedes reactivarlos o enviarlos a la papelera.</div>
            <?php if (!empty($error_mensaje)): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_mensaje); ?></div><?php endif; ?>
            <div class="search-section">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5"><label class="search-label">Buscar</label><input type="text" class="form-control" name="busqueda" placeholder="Código, nombre, empresa..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>"></div>
                    <div class="col-md-2"><label class="search-label">Tipo Cliente</label><select class="form-select" name="tipo_cliente"><option value="">Todos</option><?php foreach ($tipos_cliente_filtro as $key => $value): ?><option value="<?php echo htmlspecialchars($key); ?>" <?php echo $filtro_tipo_cliente == $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label class="search-label">País</label><select class="form-select" name="pais"><option value="">Todos</option><?php foreach ($paises as $pais_item): ?><option value="<?php echo htmlspecialchars($pais_item['pais']); ?>" <?php echo $filtro_pais == $pais_item['pais'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($pais_item['pais']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><div class="d-flex gap-1"><button type="submit" class="btn btn-primary btn-sm-custom flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button><a href="clientes_inactivos.php" class="btn btn-outline-secondary btn-sm-custom flex-fill"><i class="bi bi-arrow-clockwise me-1"></i>Limpiar</a></div></div>
                </form>
            </div>
             <div class="d-flex justify-content-between align-items-center pt-2 pb-1 px-2 bg-light border-bottom">
                <h5 class="mb-0"><i class="bi bi-list me-2"></i>Lista de Clientes Inactivos</h5>
                <span class="badge bg-danger fs-6"><?php echo number_format($total_registros); ?> clientes</span>
            </div>
        </div>

        <div class="scrollable-content-area">
            <div class="table-container-inner">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('codigo', $orden_campo, $orden_direccion); ?>'">Código <?php echo obtenerIconoOrden('codigo', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('nombre', $orden_campo, $orden_direccion); ?>'">Cliente <?php echo obtenerIconoOrden('nombre', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('tipo_cliente', $orden_campo, $orden_direccion); ?>'">Tipo <?php echo obtenerIconoOrden('tipo_cliente', $orden_campo, $orden_direccion); ?></th>
                                <th>Contacto</th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('pais', $orden_campo, $orden_direccion); ?>'">País <?php echo obtenerIconoOrden('pais', $orden_campo, $orden_direccion); ?></th>
                                <th>Identificación</th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('fecha_modificacion', $orden_campo, $orden_direccion); ?>'">Fecha Inactivación <?php echo obtenerIconoOrden('fecha_modificacion', $orden_campo, $orden_direccion); ?></th>
                                <th style="width: 150px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr><td colspan="8" class="text-center py-4"><i class="bi bi-inbox text-muted fs-3"></i><p class="text-muted mt-2 mb-0">No hay clientes inactivos<?php if(!empty($filtro_busqueda) || !empty($filtro_tipo_cliente) || !empty($filtro_pais)) echo ' con los filtros aplicados'; ?>.</p></td></tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr id="cliente-row-<?php echo $cliente['id']; ?>">
                                        <td><code class="text-primary"><?php echo htmlspecialchars($cliente['codigo']); ?></code></td>
                                        <td><div class="cliente-info"><div class="cliente-nombre"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></div><?php if(!empty($cliente['empresa'])):?><div class="cliente-empresa"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($cliente['empresa']);?></div><?php endif;?></div></td>
                                        <td><?php if(!empty($cliente['tipo_cliente'])):?><span class="badge badge-tipo <?php echo getTipoClienteClass($cliente['tipo_cliente']); ?>"><?php echo getTipoClienteTexto($cliente['tipo_cliente']); ?></span><?php else:?><span class="text-muted">-</span><?php endif;?></td>
                                        <td><div class="cliente-contacto"><?php if(!empty($cliente['email'])):?><div><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($cliente['email']);?></div><?php endif;?><?php if(!empty($cliente['telefono'])):?><div><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($cliente['telefono']);?></div><?php endif;?></div></td>
                                        <td><?php if(!empty($cliente['pais'])):?><span class="badge bg-info text-dark"><?php echo htmlspecialchars($cliente['pais']);?></span><?php else:?><span class="text-muted">-</span><?php endif;?></td>
                                        <td><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($cliente['fecha_modificacion']));?></small></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php $nombre_cliente_js_inactivo = htmlspecialchars(addslashes($cliente['nombre'].' '.$cliente['apellido']),ENT_QUOTES,'UTF-8');?>
                                                <button type="button" class="btn btn-success btn-action" title="Reactivar" onclick="reactivarCliente(<?php echo $cliente['id']; ?>, '<?php echo $nombre_cliente_js_inactivo;?>')"><i class="bi bi-arrow-clockwise"></i></button>
                                                <button type="button" class="btn btn-danger btn-action" title="Papelera" onclick="enviarAPapelera(<?php echo $cliente['id']; ?>, '<?php echo $nombre_cliente_js_inactivo;?>')"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Paginación"><ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php
                            $current_page_for_pagination = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1; // Usar $pagina_actual del script PHP
                            if($pagina_actual > 1):
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['pagina'=>1]));?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['pagina'=>$pagina_actual-1]));?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif;?>
                        <?php $start_loop=max(1,$pagina_actual-2); $end_loop=min($total_paginas,$pagina_actual+2); for($i=$start_loop;$i<=$end_loop;$i++):?><li class="page-item <?php if($i==$pagina_actual)echo 'active';?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['pagina'=>$i]));?>"><?php echo $i;?></a></li><?php endfor;?>
                        <?php if($pagina_actual < $total_paginas):?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['pagina'=>$pagina_actual+1]));?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['pagina'=>$total_paginas]));?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        <?php endif;?>
                    </ul></nav>
                    <div class="text-center mt-2"><small class="text-muted">Página <?php echo $pagina_actual;?> de <?php echo $total_paginas;?> (<?php echo number_format($total_registros);?> clientes inactivos)</small></div>
                </div>
                <?php endif; ?>
            </div>

        <div class="fixed-footer-actions">
            <div class="quick-actions">
                 <a href="clientes.php" class="btn btn-primary quick-action-btn"><i class="bi bi-people me-2"></i>Ver Activos</a>
                <a href="cliente_form.php" class="btn btn-success quick-action-btn"><i class="bi bi-person-plus me-2"></i>Nuevo Cliente</a>
                <a href="papelera_clientes.php" class="btn btn-secondary quick-action-btn papelera-badge"><i class="bi bi-trash me-2"></i>Papelera Clientes <?php if($clientes_en_papelera>0):?><span class="papelera-count"><?php echo $clientes_en_papelera;?></span><?php endif;?></a>
                <a href="reportes_clientes.php" class="btn btn-info quick-action-btn"><i class="bi bi-graph-up me-2"></i>Reportes Gráficos</a>
            </div>
        </div>
    </div>
</div>

    <div class="modal fade" id="modalReactivar" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-arrow-clockwise me-2"></i>Reactivar Cliente</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>¿Reactivar al cliente <strong id="nombreClienteReactivar"></strong>?</p><p class="text-muted">Volverá al listado principal de clientes activos.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-success" id="confirmarReactivar"><i class="bi bi-arrow-clockwise me-2"></i>Reactivar</button></div></div></div></div>
    <div class="modal fade" id="modalPapelera" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-trash me-2"></i>Enviar a Papelera</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>¿Enviar a la papelera al cliente <strong id="nombreClientePapelera"></strong>?</p><div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Podrá ser restaurado.</div><div id="alertaPendientesPapelera" class="alert alert-warning d-none"><i class="bi bi-exclamation-circle me-2"></i><strong>No se puede:</strong><ul id="listaPendientesPapelera" class="mb-0 mt-2"></ul></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" id="confirmarPapelera"><i class="bi bi-trash me-2"></i>Enviar</button></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let clienteIdActual=null,modalReactivarInstance=null,modalPapeleraInstance=null;
        document.addEventListener('DOMContentLoaded',function(){
            const mRI=document.getElementById('modalReactivar'); if(mRI) modalReactivarInstance=new bootstrap.Modal(mRI);
            const mPI=document.getElementById('modalPapelera'); if(mPI) modalPapeleraInstance=new bootstrap.Modal(mPI);
        });
        function reactivarCliente(id,nombre){clienteIdActual=id;document.getElementById('nombreClienteReactivar').textContent=nombre;if(modalReactivarInstance)modalReactivarInstance.show();}
        function enviarAPapelera(id,nombre){clienteIdActual=id;document.getElementById('nombreClientePapelera').textContent=nombre;
            fetch('verificar_cliente_pendientes.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({cliente_id:id})})
            .then(r=>{if(!r.ok)throw new Error('Servidor: '+r.status);return r.json();})
            .then(d=>{
                const aP=document.getElementById('alertaPendientesPapelera'),lP=document.getElementById('listaPendientesPapelera'),bE=document.getElementById('confirmarPapelera');
                aP.classList.add('d-none');lP.innerHTML='';bE.disabled=false;bE.innerHTML='<i class="bi bi-trash me-2"></i>Enviar';
                if(d.tiene_pendientes){
                    aP.classList.remove('d-none');
                    if(d.pedidos_pendientes>0)lP.innerHTML+=`<li>${d.pedidos_pendientes} pedido(s)</li>`;
                    if(d.facturas_pendientes>0)lP.innerHTML+=`<li>${d.facturas_pendientes} factura(s)</li>`;
                    bE.disabled=true;bE.innerHTML='<i class="bi bi-x-circle me-2"></i>No enviar';
                }
                if(modalPapeleraInstance)modalPapeleraInstance.show();
            }).catch(e=>{console.error('Err verificar:',e);mostrarMensajeGlobal('Err verificar pendientes: '+e.message,'danger');});
        }
        document.getElementById('confirmarReactivar')?.addEventListener('click',function(){if(clienteIdActual)gestionarCliente('reactivar',clienteIdActual,this);});
        document.getElementById('confirmarPapelera')?.addEventListener('click',function(){if(clienteIdActual&&!this.disabled)gestionarCliente('eliminar_suave',clienteIdActual,this);});

        function gestionarCliente(accion,id,btnEl){
            const oT=btnEl.innerHTML;btnEl.innerHTML='<i class="bi bi-hourglass-split me-1"></i>Proc...';btnEl.disabled=true;
            fetch('gestionar_cliente_papelera.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({accion:accion,id:id})})
            .then(r=>{if(!r.ok){return r.json().then(eD=>{throw new Error(eD.message||'Servidor: '+r.status);});}return r.json();})
            .then(d=>{
                const mI=(accion==='reactivar'&&modalReactivarInstance)||(accion==='eliminar_suave'&&modalPapeleraInstance);
                if(mI)mI.hide();
                if(d.success){
                    mostrarMensajeGlobal(d.message,'success');
                    const row=document.getElementById('cliente-row-'+id);if(row)row.style.opacity='0.5';
                    setTimeout(()=>window.location.reload(),1200);
                }else{mostrarMensajeGlobal(d.message||'Error desconocido.','danger');}
            }).catch(e=>{console.error('Err gestionar:',e);mostrarMensajeGlobal('Error: '+e.message,'danger');
                const mI=(accion==='reactivar'&&modalReactivarInstance)||(accion==='eliminar_suave'&&modalPapeleraInstance);if(mI)mI.hide();
            }).finally(()=>{btnEl.innerHTML=oT;btnEl.disabled=false;});
        }
        function mostrarMensajeGlobal(msg,tipo){const c=document.body,aI='ga-'+Date.now(),aH=`<div id="${aI}" class="alert alert-${tipo} alert-dismissible fade show position-fixed" style="top:70px;right:20px;z-index:1056;min-width:300px;" role="alert"><i class="bi bi-${tipo==='success'?'check-circle-fill':'exclamation-triangle-fill'} me-2"></i> ${msg} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;c.insertAdjacentHTML('beforeend',aH);const aE=document.getElementById(aI);if(aE){const bA=new bootstrap.Alert(aE);setTimeout(()=>bA.close(),5000);}}
    </script>
</body>
</html>