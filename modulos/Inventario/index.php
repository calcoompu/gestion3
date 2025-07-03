<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

try {
    $pdo = conectarDB();
    $stats = obtenerEstadisticasInventario($pdo);
    
    // Productos con bajo stock
    $sql = "SELECT codigo, nombre, stock, stock_minimo 
            FROM productos 
            WHERE stock <= stock_minimo AND activo = 1 
            ORDER BY stock ASC 
            LIMIT 10";
    $stmt = $pdo->query($sql);
    $productos_bajo_stock = $stmt->fetchAll();
    
    // Productos más vendidos (simulado)
    $sql = "SELECT codigo, nombre, precio_venta, stock 
            FROM productos 
            WHERE activo = 1 
            ORDER BY nombre ASC 
            LIMIT 10";
    $stmt = $pdo->query($sql);
    $productos_populares = $stmt->fetchAll();
    
} catch (Exception $e) {
    $stats = array(
        'total_productos' => 0,
        'productos_bajo_stock' => 0,
        'valor_total_inventario' => 0,
        'precio_promedio' => 0
    );
    $productos_bajo_stock = array();
    $productos_populares = array();
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard de Inventario</h2>
        <div>
            <a href="producto_form.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Producto
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($stats['total_productos']); ?></h4>
                            <p class="mb-0">Total Productos</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-box-seam fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($stats['productos_bajo_stock']); ?></h4>
                            <p class="mb-0">Bajo Stock</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-exclamation-triangle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo formatCurrency($stats['valor_total_inventario']); ?></h4>
                            <p class="mb-0">Valor Total</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo formatCurrency($stats['precio_promedio']); ?></h4>
                            <p class="mb-0">Precio Promedio</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-graph-up fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Productos con Bajo Stock -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Productos con Bajo Stock</h5>
                    <a href="productos.php?filtro=bajo_stock" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($productos_bajo_stock)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-check-circle fs-1"></i>
                            <p class="mt-2">¡Excelente! No hay productos con bajo stock.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Stock</th>
                                        <th>Mínimo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_bajo_stock as $producto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $producto['stock']; ?></span>
                                            </td>
                                            <td><?php echo $producto['stock_minimo']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Productos Recientes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-star text-warning me-2"></i>Productos en Inventario</h5>
                    <a href="productos.php" class="btn btn-sm btn-outline-primary">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($productos_populares)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mt-2">No hay productos registrados.</p>
                            <a href="producto_form.php" class="btn btn-primary">Agregar Primer Producto</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Precio</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productos_populares as $producto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($producto['codigo']); ?></td>
                                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                            <td><?php echo formatCurrency($producto['precio_venta']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $producto['stock'] > 10 ? 'success' : ($producto['stock'] > 0 ? 'warning' : 'danger'); ?>">
                                                    <?php echo $producto['stock']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="producto_form.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-plus-circle me-2"></i>Nuevo Producto
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="productos.php" class="btn btn-outline-success w-100">
                                <i class="bi bi-list-ul me-2"></i>Ver Productos
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="reportes.php" class="btn btn-outline-info w-100">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="productos_por_categoria.php" class="btn btn-outline-warning w-100">
                                <i class="bi bi-tags me-2"></i>Por Categoría
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1rem;
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 0.75em;
}
</style>

