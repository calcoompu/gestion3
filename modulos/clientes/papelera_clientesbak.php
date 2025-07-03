<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

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

// Inicializar variables
$clientes_eliminados = [];
$total_clientes = 0;
$total_pages = 0;
$error_mensaje = '';

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
    $total_clientes = $count_stmt->fetchColumn();
    $total_pages = ceil($total_clientes / $per_page);
    
} catch (Exception $e) {
    $error_mensaje = "Error al cargar los datos: " . $e->getMessage();
    error_log($error_mensaje);
}

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
    global $orden_campo, $orden_direccion;
    $nueva_direccion = ($orden_campo === $campo && $orden_direccion === 'ASC') ? 'desc' : 'asc';
    $params = $_GET;
    $params['orden'] = $campo;
    $params['dir'] = $nueva_direccion;
    return '?' . http_build_query($params);
}

function getOrdenIcon($campo) {
    global $orden_campo, $orden_direccion;
    if ($orden_campo === $campo) {
        return $orden_direccion === 'ASC' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<i class="bi bi-arrow-down-up text-muted"></i>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papelera de Clientes - <?php echo SISTEMA_NOMBRE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .main-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 56px;
        }
        
        .fixed-header {
            flex-shrink: 0;
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            padding: 0 20px;
        }
        
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
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../menu_principal.php">
                <i class="bi bi-trash me-2"></i>
                <?php echo htmlspecialchars(SISTEMA_NOMBRE); ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white fw-bold" href="clientes.php">
                    <i class="bi bi-arrow-left me-2"></i>
                    Volver a Clientes
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Fixed Header Section -->
        <div class="fixed-header">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="bi bi-trash me-2"></i>Papelera de Clientes</h2>
                <div>
                    <a href="clientes.php" class="btn btn-primary me-2">
                        <i class="bi bi-arrow-left me-1"></i>Volver a Clientes
                    </a>
                    <button class="btn btn-warning" onclick="vaciarPapelera()" <?php echo $total_clientes == 0 ? 'disabled' : ''; ?>>
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
        <div class="content-area">
            <?php if (!empty($error_mensaje)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_mensaje); ?>
                </div>
            <?php endif; ?>

            <!-- Tabla -->
            <div class="table-container">
                <div class="table-wrapper">
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
                                <th width="150">Acciones</th>
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
                                                        title="Restaurar" onclick="restaurarCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?>')">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-action" 
                                                        title="Eliminar definitivamente" onclick="eliminarDefinitivamente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']); ?>')">
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
                                (<?php echo number_format($total_clientes); ?> clientes en papelera)
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
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
        
        document.getElementById('confirmarRestaurar').addEventListener('click', function() {
            if (clienteIdActual) {
                gestionarPapelera('restaurar', clienteIdActual);
            }
        });
        
        document.getElementById('confirmarEliminarDefinitivo').addEventListener('click', function() {
            if (clienteIdActual) {
                gestionarPapelera('eliminar_definitivo', clienteIdActual);
            }
        });
        
        document.getElementById('confirmarVaciarPapelera').addEventListener('click', function() {
            gestionarPapelera('vaciar_papelera', null);
        });
        
        function gestionarPapelera(accion, id) {
            const btn = document.querySelector(`#confirmar${accion.charAt(0).toUpperCase() + accion.slice(1).replace('_', '')}`);
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Procesando...';
            btn.disabled = true;
            
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
            .then(response => response.json())
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
                    alert(data.message);
                    
                    // Recargar página
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al procesar la solicitud');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>

