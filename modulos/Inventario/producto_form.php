<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// --- Lógica para datos del Navbar Principal ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
// Definir $es_administrador como en menu_principal.php (aunque el navbar de producto_form ya tiene su propia lógica para el menú de admin)
// Esta variable $es_administrador_general se podría usar si se quisiera unificar más la lógica, pero por ahora se mantiene la del navbar copiado.
$es_administrador_general = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');


$total_clientes_menu = 0; 
$clientes_nuevos = 0;
$pedidos_pendientes = 0; // Se calcula pero el badge no se mostrará aquí
$pedidos_hoy = 0;
$facturas_pendientes = 0;
$monto_pendiente = 0;
$ingresos_mes = 0;
$compras_pendientes = 0; // Añadido para consistencia con productos.php
$tablas_existentes = [];

try {
    $pdo_menu = conectarDB(); 
    $pdo_menu->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt_tables = $pdo_menu->query("SHOW TABLES");
    if($stmt_tables){ // Verificación
        while ($row_table = $stmt_tables->fetch(PDO::FETCH_NUM)) {
            $tablas_existentes[] = $row_table[0];
        }
    }

    if (in_array('clientes', $tablas_existentes)) {
        $stmt_total_menu = $pdo_menu->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1 AND eliminado = 0");
        if($stmt_total_menu) $total_clientes_menu = $stmt_total_menu->fetch()['total'] ?? 0;
        
        $stmt_nuevos_menu = $pdo_menu->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1 AND eliminado = 0");
        if($stmt_nuevos_menu) $clientes_nuevos = $stmt_nuevos_menu->fetch()['nuevos'] ?? 0;
    }
    
    if (in_array('pedidos', $tablas_existentes)) {
        $stmt_ped_pend_menu = $pdo_menu->query("SELECT COUNT(*) as pendientes FROM pedidos WHERE estado = 'pendiente'");
        if($stmt_ped_pend_menu) $pedidos_pendientes = $stmt_ped_pend_menu->fetch()['pendientes'] ?? 0;
        
        $stmt_ped_hoy_menu = $pdo_menu->query("SELECT COUNT(*) as hoy FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()");
        if($stmt_ped_hoy_menu) $pedidos_hoy = $stmt_ped_hoy_menu->fetch()['hoy'] ?? 0;
    }
    
    if (in_array('facturas', $tablas_existentes)) {
        $stmt_fact_menu = $pdo_menu->query("SELECT COUNT(*) as pendientes, COALESCE(SUM(total), 0) as monto_pendiente FROM facturas WHERE estado = 'pendiente'");
        if($stmt_fact_menu){
            $facturas_data_menu = $stmt_fact_menu->fetch();
            $facturas_pendientes = $facturas_data_menu['pendientes'] ?? 0;
            $monto_pendiente = $facturas_data_menu['monto_pendiente'] ?? 0;
        }
        
        $stmt_ing_mes_menu = $pdo_menu->query("SELECT COALESCE(SUM(total), 0) as ingresos_mes FROM facturas WHERE MONTH(fecha_factura) = MONTH(CURDATE()) AND YEAR(fecha_factura) = YEAR(CURDATE()) AND estado = 'pagada'");
        if($stmt_ing_mes_menu) $ingresos_mes = $stmt_ing_mes_menu->fetch()['ingresos_mes'] ?? 0;
    }
    
    // Lógica para compras (necesaria para el badge en el menú, si se usara el navbar de menu_principal)
    // El navbar actual de producto_form no tiene el menú de compras, pero se mantiene la lógica por si se unifica.
    if (in_array('compras', $tablas_existentes)) {
        $stmt_compras_pend_menu = $pdo_menu->query("SELECT COUNT(*) as pendientes FROM compras WHERE estado IN ('pendiente', 'confirmada')");
        if($stmt_compras_pend_menu) $compras_pendientes = $stmt_compras_pend_menu->fetch()['pendientes'] ?? 0;
    }


} catch (Exception $e) {
    error_log("Error al cargar datos para el menú en producto_form.php: " . $e->getMessage());
    // Valores por defecto en caso de error para todas las variables del menú
    $total_clientes_menu = 0; 
    $clientes_nuevos = 0;
    $pedidos_pendientes = 0;
    $pedidos_hoy = 0;
    $facturas_pendientes = 0;
    $monto_pendiente = 0;
    $ingresos_mes = 0;
    $compras_pendientes = 0;
    $tablas_existentes = [];
}
// --- FIN Lógica Navbar ---

$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$es_edicion = $producto_id > 0;

$producto = [
    'codigo' => '',
    'nombre' => '',
    'descripcion' => '',
    'categoria_id' => '',
    'lugar_id' => '',
    'stock' => 0,
    'stock_minimo' => 1,
    'precio_venta' => 0,
    'precio_compra' => 0,
    'imagen' => ''
];

$errores = [];
$mensaje_exito = '';

try {
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    if ($es_edicion) {
        $sql = "SELECT * FROM productos WHERE id = ? AND activo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$producto_id]);
        $producto_db = $stmt->fetch();
        
        if (!$producto_db) {
            throw new Exception('Producto no encontrado');
        }
        $producto = $producto_db;
    } else {
        $sql = "SELECT codigo FROM productos WHERE codigo LIKE 'PROD-%' ORDER BY codigo DESC LIMIT 1";
        $stmt = $pdo->query($sql);
        $ultimo = $stmt->fetchColumn();
        $numero = $ultimo ? intval(substr($ultimo, 5)) + 1 : 6;
        $producto['codigo'] = 'PROD-' . str_pad($numero, 7, '0', STR_PAD_LEFT);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $datos = [
            'codigo' => trim($_POST['codigo'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'categoria_id' => !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null,
            'lugar_id' => !empty($_POST['lugar_id']) ? intval($_POST['lugar_id']) : null,
            'stock' => max(0, intval($_POST['stock'] ?? 0)),
            'stock_minimo' => max(1, intval($_POST['stock_minimo'] ?? 1)),
            'precio_venta' => max(0, floatval($_POST['precio_venta'] ?? 0)),
            'precio_compra' => max(0, floatval($_POST['precio_compra'] ?? 0))
        ];
        
        if (empty($datos['codigo'])) $errores[] = 'El código es obligatorio';
        if (empty($datos['nombre'])) $errores[] = 'El nombre es obligatorio';
        if ($datos['precio_venta'] <= 0) $errores[] = 'El precio de venta debe ser mayor a 0';
        
        $imagen_path = $producto['imagen'];
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['imagen']['type'], $tipos_permitidos)) {
                if ($_FILES['imagen']['size'] <= 5 * 1024 * 1024) {
                    $upload_dir = '../../assets/img/productos';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                    $nombre_archivo = 'prod_' . uniqid() . '.' . strtolower($extension);
                    $ruta_completa = $upload_dir . '/' . $nombre_archivo;
                    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_completa)) {
                        $imagen_path = 'assets/img/productos/' . $nombre_archivo;
                    } else { $errores[] = 'Error al subir la imagen'; }
                } else { $errores[] = 'La imagen es demasiado grande (máximo 5MB)'; }
            } else { $errores[] = 'Tipo de imagen no permitido'; }
        }
        
        if (empty($errores)) {
            try {
                if ($es_edicion) {
                    $sql = "UPDATE productos SET 
                            codigo = ?, nombre = ?, descripcion = ?, categoria_id = ?, lugar_id = ?,
                            stock = ?, stock_minimo = ?, precio_venta = ?, precio_compra = ?, imagen = ?,
                            fecha_modificacion = NOW()
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $datos['codigo'], $datos['nombre'], $datos['descripcion'],
                        $datos['categoria_id'], $datos['lugar_id'], $datos['stock'],
                        $datos['stock_minimo'], $datos['precio_venta'], $datos['precio_compra'],
                        $imagen_path, $producto_id
                    ]);
                    $mensaje_exito = 'Producto actualizado correctamente';
                } else {
                    $sql = "INSERT INTO productos 
                            (codigo, nombre, descripcion, categoria_id, lugar_id, stock, stock_minimo, 
                             precio_venta, precio_compra, imagen, activo, fecha_creacion, fecha_modificacion)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $datos['codigo'], $datos['nombre'], $datos['descripcion'],
                        $datos['categoria_id'], $datos['lugar_id'], $datos['stock'],
                        $datos['stock_minimo'], $datos['precio_venta'], $datos['precio_compra'],
                        $imagen_path
                    ]);
                    $mensaje_exito = 'Producto creado correctamente';
                    $new_producto_id = $pdo->lastInsertId();
                }
                $producto = array_merge($producto, $datos);
                $producto['imagen'] = $imagen_path;
            } catch (Exception $e) {
                $errores[] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
    
    $sql_categorias = "SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre";
    $stmt_cat = $pdo->query($sql_categorias); // Renombrada variable para evitar conflicto
    $categorias = $stmt_cat ? $stmt_cat->fetchAll() : []; // Verificación
    
    $sql_lugares = "SELECT id, nombre FROM lugares WHERE activo = 1 ORDER BY nombre";
    $stmt_lug = $pdo->query($sql_lugares); // Renombrada variable para evitar conflicto
    $lugares = $stmt_lug ? $stmt_lug->fetchAll() : []; // Verificación
    
} catch (Exception $e) {
    $errores[] = 'Error del sistema: ' . $e->getMessage();
    $categorias = []; // Asegurar que estén definidas en caso de error
    $lugares = [];    // Asegurar que estén definidas en caso de error
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $es_edicion ? 'Editar' : 'Nuevo'; ?> Producto - <?php echo htmlspecialchars(SISTEMA_NOMBRE); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding-top: 0 !important; }
        
        /* Estilos del Navbar (como en menu_principal.php) */
        .navbar-custom { 
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 0; /* Asegura que no haya margen inferior */
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

        .form-container { max-width: 900px; margin: 30px auto; background-color: #fff; padding: 0; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .form-header { background-color: #0d6efd; color: white; padding: 20px; border-top-left-radius: 10px; border-top-right-radius: 10px; }
        .form-header h2 { margin: 0; font-size: 1.75rem; }
        .form-body { padding: 25px; }
        .form-label { font-weight: 600; }
        .btn-guardar-producto { background-color: #0d6efd; border-color: #0d6efd; }
        .btn-guardar-producto:hover { background-color: #0b5ed7; border-color: #0a58ca; }
        .image-preview { 
            max-width: 200px; 
            max-height: 200px; 
            border: 2px dashed #0d6efd; 
            border-radius: 8px; 
            padding: 15px; 
            text-align: center; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            background: rgba(13, 110, 253, 0.05); 
        }
        .image-preview:hover { 
            border-color: #0b5ed7; 
            background: rgba(13, 110, 253, 0.1); 
        }
        .image-preview img { 
            max-width: 100%; 
            max-height: 150px; 
            border-radius: 4px; 
        }
    </style>
</head>
<body style="padding-top:0 !important; margin-top:0 !important;">
    <!-- NAVBAR PRINCIPAL con estilo de menu_principal.php -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top" style="margin-top:0 !important;">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../menu_principal.php">
                <i class="bi bi-speedometer2"></i> Gestión Administrativa <!-- Icono y texto de menu_principal.php -->
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
                    <!-- Gestión de Productos -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <!-- Clase active añadida a Productos -->
                            <i class="bi bi-box-seam"></i> Productos
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="../../modulos/Inventario/productos.php">
                                    <i class="bi bi-list-ul me-2"></i>Listado de Productos
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item active" href="../../modulos/Inventario/producto_form.php"> <!-- Clase active aquí también -->
                                    <i class="bi bi-plus-circle me-2"></i>Nuevo Producto
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../../modulos/Inventario/productos_por_categoria.php">
                                    <i class="bi bi-tags me-2"></i>Por Categoría
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../modulos/Inventario/productos_por_lugar.php">
                                    <i class="bi bi-geo-alt me-2"></i>Por Ubicación
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../modulos/Inventario/productos_inactivos.php">
                                    <i class="bi bi-archive me-2"></i>Productos Inactivos
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../../modulos/Inventario/reportes.php">
                                    <i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- Gestión de Clientes -->
                    <?php if (isset($tablas_existentes) && in_array('clientes', $tablas_existentes)): ?>
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
                    <?php endif; ?>
                    <!-- Gestión de Pedidos -->
                    <?php if (isset($tablas_existentes) && in_array('pedidos', $tablas_existentes)): ?>
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
                    <?php endif; ?>
                    <!-- Facturación -->
                     <?php if (isset($tablas_existentes) && in_array('facturas', $tablas_existentes)): ?>
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
                    <?php endif; ?>
                    <!-- Compras -->
                    <?php if (isset($tablas_existentes) && in_array('compras', $tablas_existentes)): ?>
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
                    <?php endif; ?>
                    <!-- Configuración (Solo para administradores) -->
                    <?php if ($usuario_rol === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear me-1"></i>Configuración
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="../../modulos/admin/configuracion_sistema.php">
                                    <i class="bi bi-sliders me-2"></i>Configuración General
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="../../modulos/admin/usuarios.php">
                                    <i class="bi bi-people-fill me-2"></i>Gestión de Usuarios
                                </a>
                            </li>
                             <li>
                                <a class="dropdown-item" href="../../modulos/admin/logs_sistema.php">
                                    <i class="bi bi-journal-text me-2"></i>Logs del Sistema
                                </a>
                            </li>
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
                            <?php if ($es_administrador_general): // Usar la variable general para el menú de admin aquí si se desea unificar ?>
                                <li><h6 class="dropdown-header text-danger"><i class="bi bi-shield-check"></i> Administración</h6></li>
                                <li><a class="dropdown-item" href="../../modulos/admin/admin_dashboard.php"><i class="bi bi-speedometer2"></i> Panel Admin</a></li>
                                <!-- Más items de admin si es necesario, siguiendo el patrón de menu_principal.php -->
                                <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            <li>
                                <a class="dropdown-item" href="../../logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container form-container">
        <div class="form-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-<?php echo $es_edicion ? 'pencil-square' : 'box-seam-fill'; ?> me-2"></i><?php echo $es_edicion ? 'Editar' : 'Nuevo'; ?> Producto</h2>
                <a href="productos.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver al Listado</a>
            </div>
        </div>

        <div class="form-body">
            <?php if (!empty($errores)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Errores Encontrados:</h5>
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($mensaje_exito); ?>
                <?php if (!$es_edicion && isset($new_producto_id)): ?>
                    <a href="producto_form.php?id=<?php echo $new_producto_id; ?>" class="alert-link ms-2">Ver/Editar producto creado</a>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formProducto">
                <h5 class="mb-3">Información Básica del Producto</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Código *</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($producto['codigo']); ?>" <?php echo $es_edicion ? '' : 'readonly'; ?> required>
                    </div>
                    <div class="col-md-9">
                        <label for="nombre" class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Categorización y Ubicación</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="categoria_id" class="form-label">Categoría</label>
                        <div class="input-group">
                            <select class="form-select" id="categoria_id" name="categoria_id">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" <?php echo $producto['categoria_id'] == $categoria['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" onclick="agregarCategoria()">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="lugar_id" class="form-label">Ubicación</label>
                        <div class="input-group">
                            <select class="form-select" id="lugar_id" name="lugar_id">
                                <option value="">Seleccionar ubicación</option>
                                <?php foreach ($lugares as $lugar): ?>
                                    <option value="<?php echo $lugar['id']; ?>" <?php echo $producto['lugar_id'] == $lugar['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lugar['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" onclick="agregarLugar()">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Inventario y Precios</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="stock" class="form-label">Stock Inicial/Actual *</label>
                        <input type="number" class="form-control" id="stock" name="stock" value="<?php echo $producto['stock']; ?>" min="0" required>
                    </div>
                    <div class="col-md-3">
                        <label for="stock_minimo" class="form-label">Stock Mínimo *</label>
                        <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" value="<?php echo $producto['stock_minimo']; ?>" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label for="precio_compra" class="form-label">Precio Compra *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="precio_compra" name="precio_compra" value="<?php echo $producto['precio_compra']; ?>" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="precio_venta" class="form-label">Precio Venta *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="precio_venta" name="precio_venta" value="<?php echo $producto['precio_venta']; ?>" step="0.01" min="0.01" required>
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Imagen del Producto</h5>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Imagen del Producto</label>
                        <div class="image-preview" onclick="document.getElementById('imagen').click()">
                            <?php if (!empty($producto['imagen']) && file_exists('../../' . $producto['imagen'])): ?>
                                <img src="../../<?php echo htmlspecialchars($producto['imagen']); ?>" alt="Imagen del producto">
                            <?php else: ?>
                                <i class="bi bi-image display-4 text-muted"></i>
                                <div class="small text-muted mt-2">Click para subir imagen</div>
                                <div class="small text-muted">(Máximo 5MB - JPG, PNG, GIF, WEBP)</div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="imagen" name="imagen" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    </div>
                </div>

                <hr class="my-4">
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg px-5 btn-guardar-producto"><i class="bi bi-save me-2"></i><?php echo $es_edicion ? 'Actualizar' : 'Guardar'; ?> Producto</button>
                    <a href="productos.php" class="btn btn-outline-secondary btn-lg px-4 ms-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para nueva categoría -->
    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nueva_categoria" class="form-label">Nombre de la categoría *</label>
                        <input type="text" class="form-control" id="nueva_categoria" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCategoria()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para nuevo lugar -->
    <div class="modal fade" id="modalLugar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Ubicación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nuevo_lugar" class="form-label">Nombre de la ubicación *</label>
                        <input type="text" class="form-control" id="nuevo_lugar" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarLugar()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = input.parentElement.querySelector('.image-preview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Vista previa">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function agregarCategoria() {
            const modal = new bootstrap.Modal(document.getElementById('modalCategoria'));
            modal.show();
        }

        function agregarLugar() {
            const modal = new bootstrap.Modal(document.getElementById('modalLugar'));
            modal.show();
        }

        function guardarCategoria() {
            const nombre = document.getElementById('nueva_categoria').value.trim();
            if (!nombre) {
                alert('Por favor ingrese el nombre de la categoría');
                return;
            }

            fetch('ajax_categorias.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=crear&nombre=' + encodeURIComponent(nombre)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('categoria_id');
                    const option = new Option(nombre, data.id, true, true);
                    select.add(option);
                    bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
                    document.getElementById('nueva_categoria').value = '';
                    alert('Categoría creada correctamente');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error al crear la categoría');
                console.error(error);
            });
        }

        function guardarLugar() {
            const nombre = document.getElementById('nuevo_lugar').value.trim();
            if (!nombre) {
                alert('Por favor ingrese el nombre de la ubicación');
                return;
            }

            fetch('ajax_lugares.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'accion=crear&nombre=' + encodeURIComponent(nombre)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('lugar_id');
                    const option = new Option(nombre, data.id, true, true);
                    select.add(option);
                    bootstrap.Modal.getInstance(document.getElementById('modalLugar')).hide();
                    document.getElementById('nuevo_lugar').value = '';
                    alert('Ubicación creada correctamente');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error al crear la ubicación');
                console.error(error);
            });
        }
    </script>
</body>
</html>