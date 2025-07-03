<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Configurar charset UTF-8
header('Content-Type: text/html; charset=UTF-8');

// --- Lógica para datos del Navbar (adaptada de productos.php) ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

$total_clientes_nav = 0; // Renombrado para evitar conflicto con $total_clientes_filtrados
$clientes_nuevos_nav = 0; // Renombrado
$pedidos_pendientes_nav = 0; // Renombrado. Se calcula pero el badge no se mostrará aquí
$facturas_pendientes_nav = 0; // Renombrado
$compras_pendientes_nav = 0; // Renombrado
$tablas_existentes_nav = []; // Renombrado

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
        $stmt_cli_total_nav = $pdo_nav->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1 AND eliminado = 0"); // Condición de clientes.php original
        if($stmt_cli_total_nav) $total_clientes_nav = $stmt_cli_total_nav->fetch()['total'] ?? 0;
        
        $stmt_cli_nuevos_nav = $pdo_nav->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1 AND eliminado = 0"); // Condición de clientes.php original
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
    error_log("Error al cargar datos para el menú en clientes.php: " . $e->getMessage());
    $total_clientes_nav = 0; $clientes_nuevos_nav = 0; $pedidos_pendientes_nav = 0; $facturas_pendientes_nav = 0; $compras_pendientes_nav = 0; $tablas_existentes_nav = [];
}
// --- FIN Lógica Navbar ---


$tipos_cliente = ['mayorista' => 'Mayorista', 'minorista' => 'Minorista', 'may_min' => 'Mayorista/Minorista'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$filtro_tipo_cliente = $_GET['tipo_cliente'] ?? '';
$filtro_pais = $_GET['pais'] ?? '';
$filtro_busqueda = trim($_GET['busqueda'] ?? '');
$orden_campo = $_GET['orden'] ?? 'fecha_creacion';
$orden_direccion = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';
$campos_permitidos_orden = ['codigo', 'nombre', 'apellido', 'empresa', 'tipo_cliente', 'pais', 'fecha_creacion'];
if (!in_array($orden_campo, $campos_permitidos_orden)) $orden_campo = 'fecha_creacion';

$clientes = []; $total_clientes_filtrados = 0; $total_pages = 0;
$stats_clientes_pagina = ['total_general' => 0, 'activos' => 0, 'por_tipo_activos' => [], 'paises_activos' => []];
$paises_para_filtro_bd = []; $error_mensaje_pagina = ''; $clientes_en_papelera = 0;

try {
    $pdo_pagina = conectarDB(); // Usar una nueva conexión o la del menú si está disponible y es seguro
    $pdo_pagina->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt_total_general = $pdo_pagina->query("SELECT COUNT(*) FROM clientes WHERE eliminado = 0");
    if($stmt_total_general) $stats_clientes_pagina['total_general'] = $stmt_total_general->fetchColumn();
    
    $stmt_activos_pagina = $pdo_pagina->query("SELECT COUNT(*) FROM clientes WHERE activo = 1 AND eliminado = 0");
    if($stmt_activos_pagina) $stats_clientes_pagina['activos'] = $stmt_activos_pagina->fetchColumn();
    
    $stmt_tipos_pagina = $pdo_pagina->query("SELECT tipo_cliente, COUNT(*) as cantidad FROM clientes WHERE tipo_cliente IS NOT NULL AND activo = 1 AND eliminado = 0 GROUP BY tipo_cliente");
    if($stmt_tipos_pagina) $stats_clientes_pagina['por_tipo_activos'] = $stmt_tipos_pagina->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_paises_pagina = $pdo_pagina->query("SELECT pais, COUNT(*) as cantidad FROM clientes WHERE pais IS NOT NULL AND pais != '' AND activo = 1 AND eliminado = 0 GROUP BY pais");
    if($stmt_paises_pagina) $stats_clientes_pagina['paises_activos'] = $stmt_paises_pagina->fetchAll(PDO::FETCH_ASSOC);
    
    $where_conditions_tabla = ["activo = 1", "eliminado = 0"]; $params_tabla = [];
    if (!empty($filtro_tipo_cliente)) { $where_conditions_tabla[] = "tipo_cliente = ?"; $params_tabla[] = $filtro_tipo_cliente; }
    if (!empty($filtro_pais)) { $where_conditions_tabla[] = "pais = ?"; $params_tabla[] = $filtro_pais; }
    if (!empty($filtro_busqueda)) {
        $where_conditions_tabla[] = "(codigo LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR empresa LIKE ? OR email LIKE ?)";
        $busqueda_param_tabla = "%{$filtro_busqueda}%";
        for ($i=0; $i < 5; $i++) $params_tabla[] = $busqueda_param_tabla;
    }
    $where_clause_tabla = implode(' AND ', $where_conditions_tabla);
    
    $sql_tabla = "SELECT * FROM clientes WHERE {$where_clause_tabla} ORDER BY {$orden_campo} {$orden_direccion} LIMIT {$per_page} OFFSET {$offset}";
    $stmt_tabla = $pdo_pagina->prepare($sql_tabla); $stmt_tabla->execute($params_tabla);
    $clientes = $stmt_tabla->fetchAll(PDO::FETCH_ASSOC);
    
    $count_sql_tabla = "SELECT COUNT(*) FROM clientes WHERE {$where_clause_tabla}";
    $count_stmt_tabla = $pdo_pagina->prepare($count_sql_tabla); $count_stmt_tabla->execute($params_tabla);
    $total_clientes_filtrados = $count_stmt_tabla->fetchColumn();
    $total_pages = $total_clientes_filtrados ? ceil($total_clientes_filtrados / $per_page) : 0;
    
    $sql_paises_filtro = "SELECT DISTINCT pais FROM clientes WHERE pais IS NOT NULL AND pais != '' AND activo = 1 AND eliminado = 0 ORDER BY pais";
    $stmt_paises_filtro = $pdo_pagina->query($sql_paises_filtro);
    if($stmt_paises_filtro) $paises_para_filtro_bd = $stmt_paises_filtro->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_papelera_footer = $pdo_pagina->query("SELECT COUNT(*) FROM clientes WHERE eliminado = 1");
    if($stmt_papelera_footer) $clientes_en_papelera = $stmt_papelera_footer->fetchColumn();

} catch (Exception $e) {
    $error_mensaje_pagina = "Error al cargar datos: " . $e->getMessage(); error_log($error_mensaje_pagina);
    // Inicializar arrays y contadores a 0 en caso de error
    $clientes = []; $total_clientes_filtrados = 0; $total_pages = 0;
    $stats_clientes_pagina = ['total_general' => 0, 'activos' => 0, 'por_tipo_activos' => [], 'paises_activos' => []];
    $paises_para_filtro_bd = []; $clientes_en_papelera = 0;
}

function getTipoClienteClass($tipo) { switch ($tipo) { case 'mayorista': return 'bg-primary'; case 'minorista': return 'bg-success'; case 'may_min': return 'bg-warning text-dark'; default: return 'bg-secondary'; } }
function getTipoClienteTexto($tipo) { global $tipos_cliente; return $tipos_cliente[$tipo] ?? 'No definido'; }
function getOrdenUrl($campo_actual_func) { global $orden_campo, $orden_direccion; $nueva_direccion_func = ($orden_campo === $campo_actual_func && $orden_direccion === 'ASC') ? 'desc' : 'asc'; $params_func = $_GET; $params_func['orden'] = $campo_actual_func; $params_func['dir'] = $nueva_direccion_func; unset($params_func['page']); return htmlspecialchars('?' . http_build_query($params_func)); }
function getOrdenIcon($campo_actual_func) { global $orden_campo, $orden_direccion; if ($orden_campo === $campo_actual_func) { return $orden_direccion === 'ASC' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>'; } return '<i class="bi bi-arrow-down-up text-muted"></i>'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - <?php echo htmlspecialchars(SISTEMA_NOMBRE); ?></title>
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
        .navbar-custom.sticky-top { flex-shrink: 0; } /* Para que el navbar no se encoja */
        
        .main-content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden; 
        }
        .fixed-header-content { flex-shrink: 0; padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .scrollable-content-area { flex-grow: 1; overflow-y: auto; padding: 0 20px; background-color: #f8f9fa; }
        .fixed-footer-actions { flex-shrink: 0; padding: 15px 20px; background-color: #ffffff; border-top: 1px solid #dee2e6; box-shadow: 0 -2px 5px rgba(0,0,0,0.05); }

        .stats-card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.2s; margin-bottom: 15px; min-height: 100px; }
        .stats-card:hover { transform: translateY(-2px); }
        .stats-card .card-body { padding: 15px; display: flex; align-items: center; justify-content: space-between; height: 100%; }
        .stats-icon { font-size: 2rem; opacity: 0.9; }
        .stats-number { font-size: 1.8rem; font-weight: bold; margin: 0; }
        .stats-label { font-size: 0.9rem; margin: 0; opacity: 0.8; }
        .card-gradient-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .card-gradient-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .card-gradient-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .card-gradient-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        
        .search-section { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 15px; margin-bottom: 20px; }
        .search-label { font-weight: bold; font-size: 0.9rem; margin-bottom: 5px; color: #2c3e50; display: block; }
        .form-control, .form-select { font-size: 0.9rem; padding: 8px 12px; height: auto; }
        .btn-sm-custom { font-size: 0.9rem; padding: 8px 15px; }
        
        .table-container-inner { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 20px; }
        .table th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; font-size: 0.9rem; padding: 12px 10px; cursor: pointer; user-select: none; white-space: nowrap; position: sticky; top: 0; z-index: 10; }
        .table th:hover { background-color: #e9ecef; }
        .table td { padding: 10px; vertical-align: middle; font-size: 0.9rem; }
        .btn-action { padding: 5px 10px; margin: 0 2px; border-radius: 5px; font-size: 0.85rem; }
        .badge-tipo { font-size: 0.8rem; padding: 5px 8px; }
        .cliente-info { line-height: 1.3; }
        .cliente-nombre { font-weight: 600; color: #2c3e50; }
        .cliente-empresa { font-size: 0.85rem; color: #6c757d; }
        .cliente-contacto { font-size: 0.85rem; color: #6c757d; }
        
        .pagination-container { padding: 15px; border-top: 1px solid #dee2e6; }
        
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                 <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 30 30\'%3e%3cpath stroke=\'rgba%28255, 255, 255, 0.75%29\' stroke-linecap=\'round\' stroke-miterlimit=\'10\' stroke-width=\'2\' d=\'M4 7h22M4 15h22M4 23h22\'/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../../menu_principal.php"> <!-- Clase active eliminada de Dashboard -->
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
                    <?php if (isset($tablas_existentes_nav) && in_array('clientes', $tablas_existentes_nav)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <!-- Clase active añadida a Clientes -->
                            <i class="bi bi-people"></i> Clientes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="clientes.php"><i class="bi bi-list-ul"></i> Listado de Clientes</a></li> <!-- Clase active aquí -->
                            <li><a class="dropdown-item" href="cliente_form.php"><i class="bi bi-person-plus"></i> Nuevo Cliente</a></li>
                            <li><a class="dropdown-item" href="clientes_inactivos.php"><i class="bi bi-person-x"></i> Clientes Inactivos</a></li>
                            <li><a class="dropdown-item" href="papelera_clientes.php"><i class="bi bi-trash"></i> Papelera</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (isset($tablas_existentes_nav) && in_array('pedidos', $tablas_existentes_nav)): ?>
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
                    <?php endif; ?>
                     <?php if (isset($tablas_existentes_nav) && in_array('facturas', $tablas_existentes_nav)): ?>
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
                    <?php endif; ?>
                    <?php if (isset($tablas_existentes_nav) && in_array('compras', $tablas_existentes_nav)): ?>
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
                    <?php endif; ?>
                    <?php if ($es_administrador): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear me-1"></i>Configuración
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../Admin/configuracion_sistema.php"><i class="bi bi-sliders me-2"></i>Configuración General</a></li>
                            <li><a class="dropdown-item" href="../Admin/usuarios.php"><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</a></li>
                            <li><a class="dropdown-item" href="../Admin/logs_sistema.php"><i class="bi bi-journal-text me-2"></i>Logs del Sistema</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($usuario_nombre); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                             <li><h6 class="dropdown-header">Rol: <?php echo ucfirst(htmlspecialchars($usuario_rol)); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if ($es_administrador): ?>
                                <li><h6 class="dropdown-header text-danger"><i class="bi bi-shield-check"></i> Administración</h6></li>
                                <li><a class="dropdown-item" href="../Admin/admin_dashboard.php"><i class="bi bi-speedometer2"></i> Panel Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content-wrapper">
        <div class="fixed-header-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0"><i class="bi bi-people me-2"></i>Gestión de Clientes</h2>
                <div>
                    <a href="cliente_form.php" class="btn btn-primary me-2"><i class="bi bi-plus-circle me-1"></i>Nuevo Cliente</a>
                    <a href="clientes_inactivos.php" class="btn btn-outline-danger me-2"><i class="bi bi-person-x me-1"></i>Ver Inactivos</a>
                    <button class="btn btn-success" onclick="exportarExcel()"><i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel</button>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-lg-3 col-md-6 mb-3"><div class="card stats-card card-gradient-1 h-100"><div class="card-body"><div><h3 class="stats-number"><?php echo number_format($stats_clientes_pagina['total_general'] ?? 0); ?></h3><p class="stats-label">Total Clientes</p></div><i class="bi bi-people stats-icon"></i></div></div></div>
                <div class="col-lg-3 col-md-6 mb-3"><div class="card stats-card card-gradient-2 h-100"><div class="card-body"><div><h3 class="stats-number"><?php echo number_format($stats_clientes_pagina['activos'] ?? 0); ?></h3><p class="stats-label">Clientes Activos</p></div><i class="bi bi-person-check stats-icon"></i></div></div></div>
                <div class="col-lg-3 col-md-6 mb-3"><div class="card stats-card card-gradient-3 h-100"><div class="card-body"><div><h3 class="stats-number"><?php echo count($stats_clientes_pagina['por_tipo_activos'] ?? []); ?></h3><p class="stats-label">Tipos de Cliente</p></div><i class="bi bi-diagram-3 stats-icon"></i></div></div></div>
                <div class="col-lg-3 col-md-6 mb-3"><div class="card stats-card card-gradient-4 h-100"><div class="card-body"><div><h3 class="stats-number"><?php echo count($stats_clientes_pagina['paises_activos'] ?? []); ?></h3><p class="stats-label">Países</p></div><i class="bi bi-globe stats-icon"></i></div></div></div>
            </div>

            <div class="search-section">
                <form method="GET" action="clientes.php" class="row g-2 align-items-end">
                    <div class="col-md-5"><label for="busqueda" class="search-label">Buscar</label><input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Código, nombre, empresa, email..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>"></div>
                    <div class="col-md-2"><label for="tipo_cliente" class="search-label">Tipo Cliente</label><select class="form-select" id="tipo_cliente" name="tipo_cliente"><option value="">Todos</option><?php foreach ($tipos_cliente as $k => $v): ?><option value="<?php echo htmlspecialchars($k); ?>" <?php if($filtro_tipo_cliente==$k) echo 'selected';?>><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label for="pais" class="search-label">País</label><select class="form-select" id="pais" name="pais"><option value="">Todos</option><?php foreach ($paises_para_filtro_bd as $pd): ?><option value="<?php echo htmlspecialchars($pd['pais']); ?>" <?php if($filtro_pais==$pd['pais']) echo 'selected';?>><?php echo htmlspecialchars($pd['pais']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><div class="d-flex gap-1"><button type="submit" class="btn btn-primary btn-sm-custom flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button><a href="clientes.php" class="btn btn-outline-secondary btn-sm-custom flex-fill"><i class="bi bi-arrow-clockwise me-1"></i>Limpiar</a></div></div>
                </form>
            </div>
        </div>

        <div class="scrollable-content-area">
            <?php if (!empty($error_mensaje_pagina)): ?>
                <div class="alert alert-danger mt-3"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_mensaje_pagina); ?></div>
            <?php endif; ?>

            <div class="table-container-inner">
                <div class="table-responsive"> 
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('codigo'); ?>'">Código <?php echo getOrdenIcon('codigo'); ?></th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('nombre'); ?>'">Cliente <?php echo getOrdenIcon('nombre'); ?></th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('tipo_cliente'); ?>'">Tipo <?php echo getOrdenIcon('tipo_cliente'); ?></th>
                                <th>Contacto</th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('pais'); ?>'">País <?php echo getOrdenIcon('pais'); ?></th>
                                <th>Identificación</th>
                                <th onclick="window.location.href='<?php echo getOrdenUrl('fecha_creacion'); ?>'">Fecha Alta <?php echo getOrdenIcon('fecha_creacion'); ?></th>
                                <th style="width: 210px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr><td colspan="8" class="text-center py-4"><i class="bi bi-inbox text-muted fs-3"></i><p class="text-muted mt-2">No hay clientes activos<?php if(!empty($filtro_busqueda) || !empty($filtro_tipo_cliente) || !empty($filtro_pais)) echo ' con los filtros aplicados'; ?>.</p><?php if(empty($filtro_busqueda) && empty($filtro_tipo_cliente) && empty($filtro_pais)): ?><a href="cliente_form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Agregar primer cliente</a><?php endif; ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr id="cliente-row-<?php echo $cliente['id']; ?>">
                                        <td><code class="text-primary"><?php echo htmlspecialchars($cliente['codigo']); ?></code></td>
                                        <td><div class="cliente-info"><div class="cliente-nombre"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?></div><?php if(!empty($cliente['empresa'])):?><div class="cliente-empresa"><i class="bi bi-building me-1"></i><?php echo htmlspecialchars($cliente['empresa']);?></div><?php endif;?></div></td>
                                        <td><?php if(!empty($cliente['tipo_cliente'])):?><span class="badge badge-tipo <?php echo getTipoClienteClass($cliente['tipo_cliente']); ?>"><?php echo getTipoClienteTexto($cliente['tipo_cliente']); ?></span><?php else:?><span class="text-muted">-</span><?php endif;?></td>
                                        <td><div class="cliente-contacto"><?php if(!empty($cliente['email'])):?><div><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($cliente['email']);?></div><?php endif;?><?php if(!empty($cliente['telefono'])):?><div><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($cliente['telefono']);?></div><?php endif;?></div></td>
                                        <td><?php if(!empty($cliente['pais'])):?><span class="badge bg-info text-dark"><?php echo htmlspecialchars($cliente['pais']);?></span><?php else:?><span class="text-muted">-</span><?php endif;?></td>
                                        <td><?php if(!empty($cliente['tipo_identificacion'])&&!empty($cliente['numero_identificacion'])):?><small class="text-muted"><?php echo htmlspecialchars($cliente['tipo_identificacion']);?>:<br><?php echo htmlspecialchars($cliente['numero_identificacion']);?></small><?php else:?><span class="text-muted">-</span><?php endif;?></td>
                                        <td><small class="text-muted"><?php echo date('d/m/Y', strtotime($cliente['fecha_creacion']));?></small></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="cliente_detalle.php?id=<?php echo $cliente['id']; ?>" class="btn btn-info btn-action" title="Ver"><i class="bi bi-eye"></i></a>
                                                <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-warning btn-action" title="Editar"><i class="bi bi-pencil"></i></a>
                                                <a href="../Pedidos/pedido_form.php?cliente_id=<?php echo $cliente['id']; ?>" class="btn btn-success btn-action" title="Pedido"><i class="bi bi-cart-plus"></i></a> <!-- Corregido el path a Pedidos -->
                                                <?php $nombre_cliente_js = htmlspecialchars(addslashes($cliente['nombre'].' '.$cliente['apellido']),ENT_QUOTES,'UTF-8');?>
                                                <?php if(isset($cliente['activo'])&&$cliente['activo']==1):?>
                                                <button type="button" class="btn btn-secondary btn-action" title="Inactivar" onclick="inactivarCliente(<?php echo $cliente['id'];?>, '<?php echo $nombre_cliente_js;?>')"><i class="bi bi-archive"></i></button>
                                                <?php else:?>
                                                <button type="button" class="btn btn-outline-secondary btn-action" title="Cliente ya inactivo" disabled><i class="bi bi-archive"></i></button>
                                                <?php endif;?>
                                                <button type="button" class="btn btn-danger btn-action" title="Papelera" onclick="verificarEliminarCliente(<?php echo $cliente['id'];?>, '<?php echo $nombre_cliente_js;?>')"><i class="bi bi-trash"></i></button>
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
                    <nav aria-label="Paginación"><ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php if($page>1):?><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>1]));?>"><i class="bi bi-chevron-double-left"></i></a></li><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1]));?>"><i class="bi bi-chevron-left"></i></a></li><?php endif;?>
                        <?php $start_loop=max(1,$page-2); $end_loop=min($total_pages,$page+2); for($i=$start_loop;$i<=$end_loop;$i++):?><li class="page-item <?php if($i==$page)echo 'active';?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$i]));?>"><?php echo $i;?></a></li><?php endfor;?>
                        <?php if($page<$total_pages):?><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1]));?>"><i class="bi bi-chevron-right"></i></a></li><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$total_pages]));?>"><i class="bi bi-chevron-double-right"></i></a></li><?php endif;?>
                    </ul></nav>
                    <div class="text-center mt-2"><small class="text-muted">Página <?php echo $page;?> de <?php echo $total_pages;?> (<?php echo number_format($total_clientes_filtrados);?> clientes)</small></div>
                </div>
                <?php endif; ?>
            </div> 
        </div> 

        <div class="fixed-footer-actions">
            <div class="quick-actions">
                <a href="../pedidos/pedidos_pendientes.php" class="btn btn-warning quick-action-btn"><i class="bi bi-clock-history me-2"></i>Pedidos Pendientes</a>
                <a href="../facturacion/facturas_pendientes.php" class="btn btn-danger quick-action-btn"><i class="bi bi-receipt me-2"></i>Facturas Pendientes</a>
                <a href="reportes_clientes.php" class="btn btn-info quick-action-btn"><i class="bi bi-graph-up me-2"></i>Reportes Gráficos</a>
                <a href="clientes_frecuentes.php" class="btn btn-success quick-action-btn"><i class="bi bi-star me-2"></i>Clientes Frecuentes</a>
                <a href="papelera_clientes.php" class="btn btn-secondary quick-action-btn papelera-badge"><i class="bi bi-trash me-2"></i>Papelera Clientes <?php if($clientes_en_papelera>0):?><span class="papelera-count"><?php echo $clientes_en_papelera;?></span><?php endif;?></a>
            </div>
        </div>
    </div> 
</div> 

    <div class="modal fade" id="modalInactivar" tabindex="-1" aria-labelledby="modalInactivarLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="modalInactivarLabel"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirmar Inactivación</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>¿Está seguro que desea inactivar al cliente <strong id="nombreClienteInactivar"></strong>?</p><div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>El cliente será movido a la lista de inactivos.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-warning" id="confirmarInactivar"><i class="bi bi-archive me-2"></i>Inactivar</button></div></div></div></div>
    <div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title" id="modalEliminarLabel"><i class="bi bi-exclamation-triangle me-2"></i>Enviar a Papelera</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>¿Enviar a la papelera al cliente <strong id="nombreClienteEliminar"></strong>?</p><div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Podrá ser restaurado.</div><div id="alertaPendientes" class="alert alert-warning d-none"><i class="bi bi-exclamation-circle me-2"></i><strong>No se puede:</strong><ul id="listaPendientes" class="mb-0 mt-2"></ul></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" id="confirmarEliminar"><i class="bi bi-trash me-2"></i>Enviar</button></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let clienteIdActual=null,modalInactivarInstance=null,modalEliminarInstance=null;
        document.addEventListener('DOMContentLoaded',function(){const mIE=document.getElementById('modalInactivar');if(mIE)modalInactivarInstance=new bootstrap.Modal(mIE);const mEE=document.getElementById('modalEliminar');if(mEE)modalEliminarInstance=new bootstrap.Modal(mEE);});
        function inactivarCliente(id,nombre){clienteIdActual=id;document.getElementById('nombreClienteInactivar').textContent=nombre;if(modalInactivarInstance)modalInactivarInstance.show();}
        function verificarEliminarCliente(id,nombre){clienteIdActual=id;document.getElementById('nombreClienteEliminar').textContent=nombre;fetch('verificar_cliente_pendientes.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({cliente_id:id})}).then(r=>{if(!r.ok)throw new Error('Servidor: '+r.status);return r.json();}).then(d=>{const aP=document.getElementById('alertaPendientes'),lP=document.getElementById('listaPendientes'),bE=document.getElementById('confirmarEliminar');aP.classList.add('d-none');lP.innerHTML='';bE.disabled=false;bE.innerHTML='<i class="bi bi-trash me-2"></i>Enviar';if(d.tiene_pendientes){aP.classList.remove('d-none');if(d.pedidos_pendientes>0)lP.innerHTML+=`<li>${d.pedidos_pendientes} pedido(s)</li>`;if(d.facturas_pendientes>0)lP.innerHTML+=`<li>${d.facturas_pendientes} factura(s)</li>`;bE.disabled=true;bE.innerHTML='<i class="bi bi-x-circle me-2"></i>No enviar';}if(modalEliminarInstance)modalEliminarInstance.show();}).catch(e=>{console.error('Err verificar:',e);mostrarMensajeGlobal('Err verificar pendientes: '+e.message,'danger');});}
        document.getElementById('confirmarInactivar')?.addEventListener('click',function(){if(clienteIdActual)gestionarCliente('inactivar',clienteIdActual,this);});
        document.getElementById('confirmarEliminar')?.addEventListener('click',function(){if(clienteIdActual&&!this.disabled)gestionarCliente('eliminar_suave',clienteIdActual,this);});
        function gestionarCliente(accion,id,btnEl){const oT=btnEl.innerHTML;btnEl.innerHTML='<i class="bi bi-hourglass-split me-1"></i>Proc...';btnEl.disabled=true;fetch('gestionar_cliente_papelera.php',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({accion:accion,id:id})}).then(r=>{if(!r.ok){return r.json().then(eD=>{throw new Error(eD.message||'Servidor: '+r.status);});}return r.json();}).then(d=>{const mI=(accion==='inactivar'&&modalInactivarInstance)||(accion==='eliminar_suave'&&modalEliminarInstance);if(mI)mI.hide();if(d.success){mostrarMensajeGlobal(d.message,'success');const row=document.getElementById('cliente-row-'+id);if(row)row.style.opacity='0.5';setTimeout(()=>window.location.reload(),1200);}else{mostrarMensajeGlobal(d.message||'Error desconocido.','danger');}}).catch(e=>{console.error('Err gestionar:',e);mostrarMensajeGlobal('Error: '+e.message,'danger');const mI=(accion==='inactivar'&&modalInactivarInstance)||(accion==='eliminar_suave'&&modalEliminarInstance);if(mI)mI.hide();}).finally(()=>{btnEl.innerHTML=oT;btnEl.disabled=false;});}
        document.querySelectorAll('select[name="tipo_cliente"],select[name="pais"]').forEach(s=>{s.addEventListener('change',function(){this.closest('form').submit();});});
        function exportarExcel(){const p=new URLSearchParams(window.location.search);p.set('export','excel');window.location.href='exportar_clientes.php?'+p.toString();}
        function mostrarMensajeGlobal(msg,tipo){const c=document.body,aI='ga-'+Date.now(),aH=`<div id="${aI}" class="alert alert-${tipo} alert-dismissible fade show position-fixed" style="top:70px;right:20px;z-index:1056;min-width:300px;" role="alert"><i class="bi bi-${tipo==='success'?'check-circle-fill':'exclamation-triangle-fill'} me-2"></i> ${msg} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;c.insertAdjacentHTML('beforeend',aH);const aE=document.getElementById(aI);if(aE){const bA=new bootstrap.Alert(aE);setTimeout(()=>bA.close(),5000);}}
    </script>
</body>
</html>