<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// --- Lógica de la página y del Navbar unificada ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

$total_clientes = 0; $clientes_nuevos = 0; $pedidos_pendientes = 0; $facturas_pendientes = 0; $compras_pendientes = 0; $tablas_existentes = [];

// Paginación y filtros
$registros_por_pagina = 20;
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;
$filtro_busqueda = trim($_GET['busqueda'] ?? '');
$filtro_categoria = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtro_lugar = isset($_GET['lugar']) ? intval($_GET['lugar']) : 0;
$orden_campo = $_GET['orden'] ?? 'fecha_modificacion';
$orden_direccion = strtoupper($_GET['dir'] ?? 'DESC');
$campos_validos = ['codigo', 'nombre', 'categoria_nombre', 'lugar_nombre', 'stock', 'stock_minimo', 'precio_venta', 'fecha_creacion', 'fecha_modificacion'];
if (!in_array($orden_campo, $campos_validos)) $orden_campo = 'fecha_modificacion';
if (!in_array($orden_direccion, ['ASC', 'DESC'])) $orden_direccion = 'DESC';


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

    // --- Lógica de la página ---
    $where_conditions = ['p.activo = 0']; $params = [];
    if (!empty($filtro_busqueda)) {
        $where_conditions[] = "(p.codigo LIKE ? OR p.nombre LIKE ? OR p.descripcion LIKE ?)";
        $busqueda_param = "%$filtro_busqueda%";
        $params[] = $busqueda_param; $params[] = $busqueda_param; $params[] = $busqueda_param;
    }
    if ($filtro_categoria > 0) { $where_conditions[] = "p.categoria_id = ?"; $params[] = $filtro_categoria; }
    if ($filtro_lugar > 0) { $where_conditions[] = "p.lugar_id = ?"; $params[] = $filtro_lugar; }
    $where_clause = implode(' AND ', $where_conditions);

    $sql_count = "SELECT COUNT(*) FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN lugares l ON p.lugar_id = l.id WHERE $where_clause";
    $stmt_count = $pdo->prepare($sql_count); $stmt_count->execute($params);
    $total_registros = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_registros / $registros_por_pagina);

    $sql_productos = "SELECT p.*, c.nombre as categoria_nombre, l.nombre as lugar_nombre FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id LEFT JOIN lugares l ON p.lugar_id = l.id WHERE $where_clause ORDER BY $orden_campo $orden_direccion LIMIT $registros_por_pagina OFFSET $offset";
    $stmt_productos = $pdo->prepare($sql_productos); $stmt_productos->execute($params);
    $productos = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);
    
    $categorias = $pdo->query("SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    $lugares = $pdo->query("SELECT id, nombre FROM lugares WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_mensaje = "Error al cargar productos inactivos: " . $e->getMessage();
    $productos = []; $categorias = []; $lugares = []; $total_registros = 0; $total_paginas = 0;
}

$pageTitle = "Productos Inactivos - " . SISTEMA_NOMBRE;

function generarEnlaceOrden($campo, $orden_actual, $direccion_actual) {
    $nueva_direccion = ($campo === $orden_actual && $direccion_actual === 'ASC') ? 'DESC' : 'ASC';
    $params = $_GET;
    $params['orden'] = $campo;
    $params['dir'] = $nueva_direccion;
    return '?' . http_build_query($params);
}

function obtenerIconoOrden($campo, $orden_actual, $direccion_actual) {
    if ($campo !== $orden_actual) return '<i class="bi bi-arrow-down-up text-muted"></i>';
    return $direccion_actual === 'ASC' ? '<i class="bi bi-arrow-up text-primary"></i>' : '<i class="bi bi-arrow-down text-primary"></i>';
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
        body { background-color: #f8f9fa; overflow: hidden; }
        .navbar-custom { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar-custom .navbar-brand { font-weight: bold; color: white !important; font-size: 1.1rem; }
        .navbar-custom .navbar-nav .nav-link { color: white !important; font-weight: 500; transition: all 0.3s ease; margin: 0 2px; border-radius: 5px; padding: 8px 12px !important; font-size: 0.95rem; }
        .navbar-custom .navbar-nav .nav-link:hover { background-color: rgba(255,255,255,0.1); transform: translateY(-1px); }
        .navbar-custom .navbar-nav .nav-link.active { background-color: rgba(255,255,255,0.2); font-weight: 600; }
        .navbar-custom .dropdown-menu { border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; }
        .navbar-custom .dropdown-item { padding: 8px 16px; transition: all 0.2s ease; }
        .navbar-custom .dropdown-item:hover { background-color: #f8f9fa; transform: translateX(5px); }
        .main-container { height: 100vh; display: flex; flex-direction: column; }
        .fixed-header { flex-shrink: 0; background: #f8f9fa; padding: 20px; border-bottom: 1px solid #dee2e6; }
        .content-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 0 20px 20px 20px; }
        .search-section { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 12px 15px; margin-bottom: 20px; }
        .search-label { font-weight: bold; font-size: 0.85rem; margin-bottom: 3px; color: #2c3e50; display: block; }
        .form-control, .form-select { font-size: 0.85rem; padding: 6px 10px; height: auto; }
        .btn-sm-custom { font-size: 0.8rem; padding: 6px 12px; }
        .table-container { background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; height: 100%; display: flex; flex-direction: column; }
        .table-wrapper { flex: 1; overflow-y: auto; }
        .table th { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; font-weight: 600; font-size: 0.9rem; padding: 12px 8px; cursor: pointer; user-select: none; position: sticky; top: 0; z-index: 10; }
        .table th:hover { background-color: #e9ecef; }
        .table td { padding: 10px 8px; vertical-align: middle; font-size: 0.9rem; }
        .btn-action { padding: 4px 8px; margin: 0 1px; border-radius: 5px; font-size: 0.8rem; }
        .pagination-container { flex-shrink: 0; border-top: 1px solid #dee2e6; padding: 15px; margin: 0; }
        .alert-inactivos { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; }
    </style>
</head>
<body>
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
                            <li><a class="dropdown-item active" href="productos_inactivos.php"><i class="bi bi-archive"></i> Productos Inactivos</a></li>
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
        <div class="fixed-header">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-archive me-2"></i>Productos Inactivos</h2>
                <div><a href="productos.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-2"></i>Volver a Productos Activos</a></div>
            </div>
            <div class="alert alert-inactivos mb-4"><i class="bi bi-info-circle me-2"></i><strong>Productos Inactivos:</strong> Estos productos han sido desactivados. Puedes reactivarlos o eliminarlos.</div>
            <?php if (isset($error_mensaje)): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_mensaje); ?></div><?php endif; ?>
            <div class="search-section">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5"><label class="search-label">Buscar</label><input type="text" class="form-control" name="busqueda" placeholder="Código, nombre..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>"></div>
                    <div class="col-md-2"><label class="search-label">Categoría</label><select class="form-select" name="categoria"><option value="0">Todas</option><?php foreach ($categorias as $categoria): ?><option value="<?php echo $categoria['id']; ?>" <?php echo $filtro_categoria == $categoria['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoria['nombre']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><label class="search-label">Ubicación</label><select class="form-select" name="lugar"><option value="0">Todas</option><?php foreach ($lugares as $lugar): ?><option value="<?php echo $lugar['id']; ?>" <?php echo $filtro_lugar == $lugar['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($lugar['nombre']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><div class="d-flex gap-1"><button type="submit" class="btn btn-primary btn-sm-custom flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button><a href="productos_inactivos.php" class="btn btn-outline-secondary btn-sm-custom flex-fill"><i class="bi bi-arrow-clockwise me-1"></i>Limpiar</a></div></div>
                </form>
            </div>
             <div class="d-flex justify-content-between align-items-center pt-2 pb-1 px-2 bg-light border-bottom">
                <h5 class="mb-0"><i class="bi bi-list me-2"></i>Lista de Productos Inactivos</h5>
                <span class="badge bg-danger fs-6"><?php echo number_format($total_registros); ?> productos</span>
            </div>
        </div>
        <div class="content-area">
            <div class="table-container">
                <div class="table-wrapper">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('codigo', $orden_campo, $orden_direccion); ?>'">Código <?php echo obtenerIconoOrden('codigo', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('nombre', $orden_campo, $orden_direccion); ?>'">Producto <?php echo obtenerIconoOrden('nombre', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('categoria_nombre', $orden_campo, $orden_direccion); ?>'">Categoría <?php echo obtenerIconoOrden('categoria_nombre', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('lugar_nombre', $orden_campo, $orden_direccion); ?>'">Ubicación <?php echo obtenerIconoOrden('lugar_nombre', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('stock', $orden_campo, $orden_direccion); ?>'">Stock <?php echo obtenerIconoOrden('stock', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('precio_venta', $orden_campo, $orden_direccion); ?>'">Precio <?php echo obtenerIconoOrden('precio_venta', $orden_campo, $orden_direccion); ?></th>
                                <th onclick="window.location.href='<?php echo generarEnlaceOrden('fecha_modificacion', $orden_campo, $orden_direccion); ?>'">Fecha Inactivación <?php echo obtenerIconoOrden('fecha_modificacion', $orden_campo, $orden_direccion); ?></th>
                                <th width="200">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr><td colspan="8" class="text-center py-4"><i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i><p class="text-muted mt-2 mb-0">No hay productos inactivos</p><?php if (!empty($filtro_busqueda) || $filtro_categoria > 0 || $filtro_lugar > 0): ?><small class="text-muted">Intenta ajustar los filtros</small><?php endif; ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr id="producto-<?php echo $producto['id']; ?>">
                                        <td><code><?php echo htmlspecialchars($producto['codigo']); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><?php if (!empty($producto['descripcion'])): ?><br><small class="text-muted"><?php echo htmlspecialchars(mb_substr($producto['descripcion'], 0, 50)); ?>...</small><?php endif; ?></td>
                                        <td><?php if ($producto['categoria_nombre']): ?><span class="badge bg-secondary"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span><?php else: ?><span class="text-muted">Sin categoría</span><?php endif; ?></td>
                                        <td><?php if ($producto['lugar_nombre']): ?><span class="badge bg-info"><?php echo htmlspecialchars($producto['lugar_nombre']); ?></span><?php else: ?><span class="text-muted">Sin ubicación</span><?php endif; ?></td>
                                        <td><span class="badge bg-<?php echo $producto['stock'] <= $producto['stock_minimo'] ? 'danger' : 'success'; ?>"><?php echo number_format($producto['stock']); ?></span></td>
                                        <td><strong>$<?php echo number_format($producto['precio_venta'], 2); ?></strong></td>
                                        <td><small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($producto['fecha_modificacion'])); ?></small></td>
                                        <td><div class="btn-group" role="group"><button type="button" class="btn btn-success btn-sm btn-action" onclick="reactivarProducto(<?php echo $producto['id']; ?>)" title="Reactivar"><i class="bi bi-arrow-clockwise"></i></button><button type="button" class="btn btn-danger btn-sm btn-action" onclick="eliminarProducto(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')" title="Eliminar"><i class="bi bi-trash"></i></button></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_paginas > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Paginación"><ul class="pagination justify-content-center mb-0"><?php if ($pagina_actual > 1): ?><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>"><i class="bi bi-chevron-left"></i></a></li><?php endif; ?><?php for ($i = max(1, $pagina_actual - 2); $i <= min($total_paginas, $pagina_actual + 2); $i++): ?><li class="page-item <?php echo $i === $pagina_actual ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>"><?php echo $i; ?></a></li><?php endfor; ?><?php if ($pagina_actual < $total_paginas): ?><li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>"><i class="bi bi-chevron-right"></i></a></li><?php endif; ?></ul></nav>
                        <div class="text-center mt-2"><small class="text-muted">Mostrando <?php echo number_format(($pagina_actual - 1) * $registros_por_pagina + 1); ?> - <?php echo number_format(min($pagina_actual * $registros_por_pagina, $total_registros)); ?> de <?php echo number_format($total_registros); ?> productos</small></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalReactivar" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-arrow-clockwise me-2"></i>Reactivar Producto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>¿Estás seguro de que deseas reactivar este producto?</p><p class="text-muted">El producto volverá al inventario principal.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-success" id="confirmarReactivar"><i class="bi bi-arrow-clockwise me-2"></i>Reactivar</button></div></div></div></div>
    <div class="modal fade" id="modalEliminar" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar Producto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><strong>¡Atención!</strong> Esta acción no se puede deshacer.</div><p>¿Seguro que deseas eliminar <strong id="nombreProductoEliminar"></strong>?</p><p class="text-muted">Los datos se perderán permanentemente.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" id="confirmarEliminar"><i class="bi bi-trash me-2"></i>Eliminar</button></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let productoIdSeleccionado = null;
        function reactivarProducto(id) { productoIdSeleccionado = id; new bootstrap.Modal(document.getElementById('modalReactivar')).show(); }
        function eliminarProducto(id, nombre) { productoIdSeleccionado = id; document.getElementById('nombreProductoEliminar').textContent = nombre; new bootstrap.Modal(document.getElementById('modalEliminar')).show(); }
        document.getElementById('confirmarReactivar').addEventListener('click', () => { if (productoIdSeleccionado) gestionarProducto('reactivar', productoIdSeleccionado); });
        document.getElementById('confirmarEliminar').addEventListener('click', () => { if (productoIdSeleccionado) gestionarProducto('eliminar', productoIdSeleccionado); });
        
        function gestionarProducto(accion, id) {
            const btn = accion === 'reactivar' ? document.getElementById('confirmarReactivar') : document.getElementById('confirmarEliminar');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Procesando...'; btn.disabled = true;
            fetch('gestionar_producto.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({accion: accion, id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modalEl = document.getElementById(accion === 'reactivar' ? 'modalReactivar' : 'modalEliminar');
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                    mostrarMensaje(data.message, 'success');
                    setTimeout(() => window.location.reload(), 1200); 
                } else { mostrarMensaje(data.message || 'Error desconocido', 'danger'); }
            })
            .catch(error => { console.error('Error:', error); mostrarMensaje('Error de conexión con el servidor.', 'danger'); })
            .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
        }
        
        function mostrarMensaje(mensaje, tipo) {
            const alertContainer = document.createElement('div');
            alertContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1056; min-width: 300px;';
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
            alertDiv.innerHTML = `<i class="bi bi-${tipo === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i>${mensaje}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            alertContainer.appendChild(alertDiv);
            document.body.appendChild(alertContainer);
            new bootstrap.Alert(alertDiv);
            setTimeout(() => { alertDiv.classList.remove('show'); setTimeout(() => alertContainer.remove(), 150); }, 5000);
        }
    </script>
</body>
</html>