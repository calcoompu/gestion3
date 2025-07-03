<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');

// --- Lógica para datos del Navbar (adaptada de productos.php) ---
$usuario_nombre = $_SESSION['nombre_usuario'] ?? 'Usuario';
$usuario_rol = $_SESSION['rol_usuario'] ?? 'inventario';
$es_administrador = ($usuario_rol === 'admin' || $usuario_rol === 'administrador');

// $total_clientes_menu ya estaba, lo mantenemos así para esta lógica específica del menú en este archivo
$total_clientes_menu = 0;
$clientes_nuevos_nav = 0; // Renombrado $clientes_nuevos para claridad
$pedidos_pendientes_nav = 0; // Renombrado $pedidos_pendientes. Se calcula pero el badge no se mostrará aquí
// $pedidos_hoy no se usa en el navbar objetivo
$facturas_pendientes_nav = 0; // Renombrado $facturas_pendientes
// $monto_pendiente no se usa en el navbar objetivo
// $ingresos_mes no se usa en el navbar objetivo
$compras_pendientes_nav = 0; // Añadido para consistencia
$tablas_existentes_nav = []; // Renombrado $tablas_existentes

try {
    $pdo_nav = conectarDB(); // Usar _nav para las variables de conexión del navbar
    $pdo_nav->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $stmt_tables_nav = $pdo_nav->query("SHOW TABLES");
    if($stmt_tables_nav){
        while ($row_table_nav = $stmt_tables_nav->fetch(PDO::FETCH_NUM)) {
            $tablas_existentes_nav[] = $row_table_nav[0];
        }
    }

    if (in_array('clientes', $tablas_existentes_nav)) {
        // La lógica original para $total_clientes_menu y $clientes_nuevos se mantiene, ya que es específica de este menú
        $stmt_total_menu_nav = $pdo_nav->query("SELECT COUNT(*) as total FROM clientes WHERE activo = 1 AND eliminado = 0");
        if($stmt_total_menu_nav) $total_clientes_menu = $stmt_total_menu_nav->fetch()['total'] ?? 0;

        $stmt_nuevos_menu_nav = $pdo_nav->query("SELECT COUNT(*) as nuevos FROM clientes WHERE DATE(fecha_creacion) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND activo = 1 AND eliminado = 0");
        if($stmt_nuevos_menu_nav) $clientes_nuevos_nav = $stmt_nuevos_menu_nav->fetch()['nuevos'] ?? 0; // Usar variable renombrada
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
    error_log("Error al cargar datos para el menú en cliente_form.php: " . $e->getMessage());
    $total_clientes_menu = 0; $clientes_nuevos_nav = 0; $pedidos_pendientes_nav = 0; $facturas_pendientes_nav = 0; $compras_pendientes_nav = 0; $tablas_existentes_nav = [];
}
// --- FIN Lógica Navbar ---

$cliente_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$es_edicion = $cliente_id > 0;

$cliente_data = [
    'codigo' => '', 'nombre' => '', 'apellido' => '', 'empresa' => '',
    'email' => '', 'telefono' => '',
    'direccion' => '', 'ciudad' => '', 'provincia' => '', 'codigo_postal' => '',
    'pais' => 'Argentina',
    'tipo_identificacion' => '', 'numero_identificacion' => '',
    'tipo_cliente' => 'minorista',
    'notas' => ''
];

$errores_form = [];
$mensaje_exito_form = '';

$lista_paises_select = [
    "Argentina" => "+54", "Bolivia" => "+591", "Brasil" => "+55", "Chile" => "+56",
    "Colombia" => "+57", "Costa Rica" => "+506", "Ecuador" => "+593",
    "El Salvador" => "+503", "España" => "+34", "Estados Unidos" => "+1",
    "Guatemala" => "+502", "Honduras" => "+504", "México" => "+52",
    "Nicaragua" => "+505", "Panamá" => "+507", "Paraguay" => "+595",
    "Perú" => "+51", "República Dominicana" => "+1", "Uruguay" => "+598",
    "Venezuela" => "+58", "Otro" => ""
];
$paises_ordenados_para_select = array_keys($lista_paises_select);
sort($paises_ordenados_para_select);

$tipos_cliente_form = [
    'mayorista' => 'Mayorista',
    'minorista' => 'Minorista',
    'may_min' => 'Mayorista/Minorista'
];

$tipos_documento = [
    "DNI", "NIE", "CIF", "CUIT", "RUC", "RFC", "CNPJ", "CPF", "NIT", "CI", "CEDULA", "DUI", "RTN", "RNC", "RIF", "PASAPORTE"
];

try {
    $pdo_form = conectarDB();
    $pdo_form->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    if ($es_edicion) {
        $stmt_load = $pdo_form->prepare("SELECT * FROM clientes WHERE id = :id AND eliminado = 0"); // Añadido eliminado = 0
        $stmt_load->bindParam(':id', $cliente_id, PDO::PARAM_INT);
        $stmt_load->execute();
        $cliente_db = $stmt_load->fetch(PDO::FETCH_ASSOC);
        if (!$cliente_db) throw new Exception("Cliente no encontrado o eliminado.");
        $cliente_data = array_merge($cliente_data, $cliente_db);

        foreach ($lista_paises_select as $nombre_pais_iter => $codigo_tel_iter) {
            if (!empty($codigo_tel_iter) && strpos($cliente_data['telefono'], $codigo_tel_iter) === 0) {
                $cliente_data['telefono_cod_pais_actual'] = $codigo_tel_iter;
                $cliente_data['telefono_numero_actual'] = substr($cliente_data['telefono'], strlen($codigo_tel_iter));
                break;
            }
        }
        if (!isset($cliente_data['telefono_numero_actual'])) {
            $cliente_data['telefono_numero_actual'] = $cliente_data['telefono'];
        }
    } else {
        $sql_code = "SELECT codigo FROM clientes WHERE codigo LIKE 'CLIE-%' ORDER BY CAST(SUBSTRING(codigo, 6) AS UNSIGNED) DESC, codigo DESC LIMIT 1";
        $stmt_code = $pdo_form->query($sql_code);
        $ultimo_codigo_cliente = $stmt_code->fetchColumn();
        $numero_cliente = $ultimo_codigo_cliente ? intval(substr($ultimo_codigo_cliente, 5)) + 1 : 1;
        $cliente_data['codigo'] = 'CLIE-' . str_pad($numero_cliente, 7, '0', STR_PAD_LEFT);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cliente_data['codigo'] = trim($_POST['codigo'] ?? $cliente_data['codigo']);
        $cliente_data['nombre'] = trim($_POST['nombre'] ?? '');
        $cliente_data['apellido'] = trim($_POST['apellido'] ?? '');
        $cliente_data['empresa'] = trim($_POST['empresa'] ?? '');
        $cliente_data['email'] = trim($_POST['email'] ?? '');
        $telefono_cod_pais_post = $_POST['telefono_cod_pais'] ?? '';
        $telefono_numero_post = trim($_POST['telefono_numero'] ?? '');
        $cliente_data['telefono'] = (!empty($telefono_cod_pais_post) ? $telefono_cod_pais_post : '') . $telefono_numero_post;
        $cliente_data['direccion'] = trim($_POST['direccion'] ?? '');
        $cliente_data['ciudad'] = trim($_POST['ciudad'] ?? '');
        $cliente_data['provincia'] = trim($_POST['provincia'] ?? '');
        $cliente_data['codigo_postal'] = trim($_POST['codigo_postal'] ?? '');
        $cliente_data['pais'] = $_POST['pais'] ?? $cliente_data['pais'];
        $cliente_data['tipo_identificacion'] = trim($_POST['tipo_identificacion'] ?? '');
        $cliente_data['numero_identificacion'] = trim($_POST['numero_identificacion'] ?? '');
        $cliente_data['tipo_cliente'] = $_POST['tipo_cliente'] ?? $cliente_data['tipo_cliente'];
        $cliente_data['notas'] = trim($_POST['notas'] ?? '');

        if (empty($cliente_data['nombre'])) $errores_form[] = "El nombre es obligatorio.";
        if (empty($cliente_data['apellido'])) $errores_form[] = "El apellido es obligatorio.";
        if (!empty($cliente_data['email']) && !filter_var($cliente_data['email'], FILTER_VALIDATE_EMAIL)) {
            $errores_form[] = "El formato del email no es válido.";
        }

        if (empty($errores_form)) {
            if ($es_edicion) {
                $sql_save = "UPDATE clientes SET nombre = :nombre, apellido = :apellido, empresa = :empresa, email = :email, telefono = :telefono, direccion = :direccion, ciudad = :ciudad, provincia = :provincia, codigo_postal = :codigo_postal, pais = :pais, tipo_identificacion = :tipo_identificacion, numero_identificacion = :numero_identificacion, tipo_cliente = :tipo_cliente, notas = :notas, fecha_modificacion = NOW() WHERE id = :id";
            } else {
                $sql_save = "INSERT INTO clientes (codigo, nombre, apellido, empresa, email, telefono, direccion, ciudad, provincia, codigo_postal, pais, tipo_identificacion, numero_identificacion, tipo_cliente, notas, activo, eliminado, fecha_creacion) VALUES (:codigo, :nombre, :apellido, :empresa, :email, :telefono, :direccion, :ciudad, :provincia, :codigo_postal, :pais, :tipo_identificacion, :numero_identificacion, :tipo_cliente, :notas, 1, 0, NOW())"; // Añadido eliminado = 0
            }
            $stmt_save = $pdo_form->prepare($sql_save);
            $params_to_save = [
                ':nombre' => $cliente_data['nombre'], ':apellido' => $cliente_data['apellido'], ':empresa' => $cliente_data['empresa'],
                ':email' => $cliente_data['email'], ':telefono' => $cliente_data['telefono'],
                ':direccion' => $cliente_data['direccion'], ':ciudad' => $cliente_data['ciudad'], ':provincia' => $cliente_data['provincia'],
                ':codigo_postal' => $cliente_data['codigo_postal'], ':pais' => $cliente_data['pais'],
                ':tipo_identificacion' => $cliente_data['tipo_identificacion'], ':numero_identificacion' => $cliente_data['numero_identificacion'],
                ':tipo_cliente' => $cliente_data['tipo_cliente'], ':notas' => $cliente_data['notas']
            ];
            if ($es_edicion) {
                $params_to_save[':id'] = $cliente_id;
            } else {
                $params_to_save[':codigo'] = $cliente_data['codigo'];
            }
            $stmt_save->execute($params_to_save);
            $mensaje_exito_form = "Cliente " . ($es_edicion ? "actualizado" : "creado") . " correctamente.";
            if (!$es_edicion) {
                $new_cliente_id = $pdo_form->lastInsertId();
                // Redirigir a la edición del nuevo cliente para evitar reenvío de formulario
                // header("Location: cliente_form.php?id=" . $new_cliente_id . "&nuevo=1");
                // exit;
            } else {
                 // Si es edición y fue exitoso, recargar los datos del cliente para mostrar la info actualizada
                $stmt_load->execute();
                $cliente_db = $stmt_load->fetch(PDO::FETCH_ASSOC);
                if ($cliente_db) $cliente_data = array_merge($cliente_data, $cliente_db);
            }
        }
        $cliente_data['telefono_cod_pais_actual'] = $telefono_cod_pais_post;
        $cliente_data['telefono_numero_actual'] = $telefono_numero_post;
    }

} catch (PDOException $e) {
    $errores_form[] = "Error de base de datos: " . $e->getMessage();
    error_log("PDOException en cliente_form.php: " . $e->getMessage());
} catch (Exception $e) {
    $errores_form[] = "Error del sistema: " . $e->getMessage();
    error_log("Exception en cliente_form.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $es_edicion ? 'Editar' : 'Nuevo'; ?> Cliente - <?php echo htmlspecialchars(SISTEMA_NOMBRE); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    html, body { height: 100%; margin: 0; padding: 0; }
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

    .form-container { max-width: 900px; margin: 30px auto; background-color: #fff; padding: 0; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    .form-header { background-color: #0d6efd; color: white; padding: 20px; border-top-left-radius: 10px; border-top-right-radius: 10px; }
    .form-header h2 { margin: 0; font-size: 1.75rem; }
    .form-body { padding: 25px; }
    .form-label { font-weight: 600; }
    .btn-guardar-cliente { background-color: #0d6efd; border-color: #0d6efd; }
    .btn-guardar-cliente:hover { background-color: #0b5ed7; border-color: #0a58ca; }
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
                            <li><a class="dropdown-item active" href="cliente_form.php"><i class="bi bi-person-plus"></i> Nuevo Cliente</a></li>
                            <li><a class="dropdown-item" href="clientes_inactivos.php"><i class="bi bi-person-x"></i> Clientes Inactivos</a></li>
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
                            <li><a class="dropdown-item" href="../Compras/reportes_compras.php"><i class="bi bi-graph-up"></i> Reportes</a></li>
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

    <div class="container form-container">
    <div class="form-header">
    <div class="d-flex justify-content-between align-items-center">
    <h2><i class="bi bi-<?php echo $es_edicion ? 'pencil-square' : 'person-fill-gear'; ?> me-2"></i><?php echo $es_edicion ? 'Editar' : 'Nuevo'; ?> Cliente</h2>
    <a href="clientes.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver al Listado</a>
    </div>
    </div>

    <div class="form-body">
    <?php if (!empty($errores_form)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Errores Encontrados:</h5>
    <ul class="mb-0">
    <?php foreach ($errores_form as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($mensaje_exito_form)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($mensaje_exito_form); ?>
    <?php if (!$es_edicion && isset($new_cliente_id)): ?>
    <a href="cliente_form.php?id=<?php echo $new_cliente_id; ?>" class="alert-link ms-2">Ver/Editar cliente creado</a>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <form method="POST" id="formCliente">
    <h5 class="mb-3">Datos Personales / Empresa</h5>
    <div class="row g-3">
    <div class="col-md-3">
    <label for="codigo" class="form-label">Código *</label>
    <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo htmlspecialchars($cliente_data['codigo']); ?>" <?php echo $es_edicion ? '' : 'readonly'; ?> required>
    </div>
    <div class="col-md-5">
    <label for="nombre" class="form-label">Nombre(s) *</label>
    <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($cliente_data['nombre']); ?>" required>
    </div>
    <div class="col-md-4">
    <label for="apellido" class="form-label">Apellido(s) *</label>
    <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars($cliente_data['apellido']); ?>" required>
    </div>
    <div class="col-md-12">
    <label for="empresa" class="form-label">Nombre de Empresa (Opcional)</label>
    <input type="text" class="form-control" id="empresa" name="empresa" value="<?php echo htmlspecialchars($cliente_data['empresa']); ?>">
    </div>
    </div>

    <hr class="my-4">
    <h5 class="mb-3">Datos de Contacto</h5>
    <div class="row g-3">
    <div class="col-md-6">
    <label for="email" class="form-label">Email</label>
    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($cliente_data['email']); ?>">
    </div>
    <div class="col-md-6">
    <label for="telefono_numero" class="form-label">Teléfono</label>
    <div class="input-group">
    <select class="form-select" name="telefono_cod_pais" id="telefono_cod_pais" style="max-width: 120px;">
    <?php
    $codigoSeleccionado = $cliente_data['telefono_cod_pais_actual'] ?? '+54';
    foreach ($lista_paises_select as $nombrePais => $codigoTel) {
        if (!empty($codigoTel)) {
            echo "<option value=\"".htmlspecialchars($codigoTel)."\" ".($codigoTel == $codigoSeleccionado ? 'selected' : '').">".htmlspecialchars($nombrePais. ' ('.$codigoTel.')')."</option>";
        }
    }
    ?>
    </select>
    <input type="text" class="form-control" id="telefono_numero" name="telefono_numero" placeholder="Número" value="<?php echo htmlspecialchars($cliente_data['telefono_numero_actual'] ?? ''); ?>">
    </div>
    </div>
    </div>

    <hr class="my-4">
    <h5 class="mb-3">Dirección</h5>
    <div class="row g-3">
    <div class="col-12">
    <label for="direccion" class="form-label">Dirección (Calle y Número)</label>
    <input type="text" class="form-control" id="direccion" name="direccion" value="<?php echo htmlspecialchars($cliente_data['direccion']); ?>">
    </div>
    <div class="col-md-4">
    <label for="ciudad" class="form-label">Ciudad</label>
    <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?php echo htmlspecialchars($cliente_data['ciudad']); ?>">
    </div>
    <div class="col-md-4">
    <label for="provincia" class="form-label">Provincia / Estado</label>
    <input type="text" class="form-control" id="provincia" name="provincia" value="<?php echo htmlspecialchars($cliente_data['provincia']); ?>">
    </div>
    <div class="col-md-4">
    <label for="codigo_postal" class="form-label">Código Postal</label>
    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" value="<?php echo htmlspecialchars($cliente_data['codigo_postal']); ?>">
    </div>
    <div class="col-md-12">
    <label for="pais" class="form-label">País *</label>
    <select class="form-select" id="pais" name="pais" required>
    <?php
    foreach ($paises_ordenados_para_select as $nombrePaisIter) {
        echo "<option value=\"".htmlspecialchars($nombrePaisIter)."\" ".($cliente_data['pais'] == $nombrePaisIter ? 'selected' : '').">".htmlspecialchars($nombrePaisIter)."</option>";
    }
    ?>
    </select>
    </div>
    </div>

    <hr class="my-4">
    <h5 class="mb-3">Información Adicional</h5>
    <div class="row g-3">
    <div class="col-md-4">
    <label for="tipo_identificacion" class="form-label">Tipo Identificación</label>
    <select class="form-select" id="tipo_identificacion" name="tipo_identificacion">
    <option value="">Seleccionar...</option>
    <?php foreach($tipos_documento as $tipo): ?>
    <option value="<?php echo $tipo; ?>" <?php if($cliente_data['tipo_identificacion']==$tipo) echo 'selected'; ?>><?php echo $tipo; ?></option>
    <?php endforeach; ?>
    </select>
    </div>
    <div class="col-md-4">
    <label for="numero_identificacion" class="form-label">Nº Identificación</label>
    <input type="text" class="form-control" id="numero_identificacion" name="numero_identificacion" value="<?php echo htmlspecialchars($cliente_data['numero_identificacion']); ?>">
    </div>
    <div class="col-md-4">
    <label for="tipo_cliente" class="form-label">Tipo de Cliente *</label>
    <select class="form-select" id="tipo_cliente" name="tipo_cliente" required>
    <?php foreach($tipos_cliente_form as $key => $value): ?>
    <option value="<?php echo $key; ?>" <?php if($cliente_data['tipo_cliente'] == $key) echo 'selected'; ?>><?php echo htmlspecialchars($value); ?></option>
    <?php endforeach; ?>
    </select>
    </div>
    <div class="col-12">
    <label for="notas" class="form-label">Notas Adicionales</label>
    <textarea class="form-control" id="notas" name="notas" rows="3"><?php echo htmlspecialchars($cliente_data['notas']); ?></textarea>
    </div>
    </div>

    <hr class="my-4">
    <div class="text-center">
    <button type="submit" class="btn btn-primary btn-lg px-5 btn-guardar-cliente"><i class="bi bi-save me-2"></i><?php echo $es_edicion ? 'Actualizar' : 'Guardar'; ?> Cliente</button>
    <a href="clientes.php" class="btn btn-outline-secondary btn-lg px-4 ms-2">Cancelar</a>
    </div>
    </form>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    const selectPais = document.getElementById('pais');
    const selectCodTel = document.getElementById('telefono_cod_pais');
    const paisACodigo = <?php echo json_encode($lista_paises_select); ?>;
    if (selectPais && selectCodTel) {
        selectPais.addEventListener('change', function() {
            const paisSeleccionado = this.value;
            const codigoCorrespondiente = paisACodigo[paisSeleccionado] || '';
            let found = false;
            for (let i = 0; i < selectCodTel.options.length; i++) {
                if (selectCodTel.options[i].value === codigoCorrespondiente) {
                    selectCodTel.selectedIndex = i;
                    found = true;
                    break;
                }
            }
            // Opcional: Si el país no tiene un código predefinido, seleccionar la opción "Otro" si existe o dejar como está
             if (!found) {
                 for (let i = 0; i < selectCodTel.options.length; i++) {
                     if (selectCodTel.options[i].value === '') { // Asumiendo que "Otro" o similar tiene valor vacío
                         selectCodTel.selectedIndex = i;
                         break;
                     }
                 }
             }
        });
    }
    // Set initial value for phone code dropdown based on loaded data
    const initialPais = selectPais.value;
    const initialCodTel = paisACodigo[initialPais] || '';
    for (let i = 0; i < selectCodTel.options.length; i++) {
        if (selectCodTel.options[i].value === initialCodTel) {
            selectCodTel.selectedIndex = i;
            break;
        }
    }

    });
    </script>
</body>
</html>