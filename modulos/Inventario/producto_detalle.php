<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Configurar charset UTF-8
header('Content-Type: text/html; charset=UTF-8');

$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($producto_id <= 0) {
    header('Location: productos.php');
    exit;
}

try {
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Obtener datos del producto con categoría y lugar
    $sql = "SELECT p.*, c.nombre as categoria_nombre, l.nombre as lugar_nombre 
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN lugares l ON p.lugar_id = l.id 
            WHERE p.id = ? AND p.activo = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }
    
    // Calcular margen de ganancia
    $margen = 0;
    if ($producto['precio_compra'] > 0) {
        $margen = (($producto['precio_venta'] - $producto['precio_compra']) / $producto['precio_compra']) * 100;
    }
    
    // Valor total del stock
    $valor_total = $producto['precio_venta'] * $producto['stock'];
    
    // Estado del stock
    $estado_stock = 'normal';
    $color_stock = 'success';
    if ($producto['stock'] <= 0) {
        $estado_stock = 'sin stock';
        $color_stock = 'danger';
    } elseif ($producto['stock'] <= $producto['stock_minimo']) {
        $estado_stock = 'stock bajo';
        $color_stock = 'warning';
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Producto - <?php echo SISTEMA_NOMBRE; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .product-image {
            max-width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .info-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body class="bg-light">
    <?php if (isset($error)): ?>
        <div class="container py-4">
            <div class="alert alert-danger">
                <h4>Error</h4>
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="productos.php" class="btn btn-primary">Volver a Productos</a>
            </div>
        </div>
    <?php else: ?>
        <div class="container py-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-eye me-2"></i>
                    Detalles del Producto
                </h2>
                <div>
                    <a href="producto_form.php?id=<?php echo $producto['id']; ?>" class="btn btn-warning me-2">
                        <i class="bi bi-pencil me-2"></i>Editar
                    </a>
                    <a href="productos.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Volver
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Columna izquierda - Imagen y datos básicos -->
                <div class="col-md-6">
                    <div class="card info-card">
                        <div class="card-body text-center">
                            <?php if (!empty($producto['imagen']) && file_exists('../../' . $producto['imagen'])): ?>
                                <img src="../../<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                     class="product-image mb-3">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 300px; border-radius: 8px;">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-image display-1"></i>
                                        <p class="mt-2">Sin imagen</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <h3 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                            <p class="text-muted mb-2">
                                <code><?php echo htmlspecialchars($producto['codigo']); ?></code>
                            </p>
                            
                            <?php if (!empty($producto['descripcion'])): ?>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha - Información detallada -->
                <div class="col-md-6">
                    <!-- Estadísticas rápidas -->
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($producto['stock']); ?></div>
                                <div class="stat-label">Stock Actual</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo formatCurrency($producto['precio_venta']); ?></div>
                                <div class="stat-label">Precio Venta</div>
                            </div>
                        </div>
                    </div>

                    <!-- Información detallada -->
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Información Detallada</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Categoría:</strong></div>
                                <div class="col-sm-8">
                                    <?php if (!empty($producto['categoria_nombre'])): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($producto['categoria_nombre']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Sin categoría</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Ubicación:</strong></div>
                                <div class="col-sm-8">
                                    <?php if (!empty($producto['lugar_nombre'])): ?>
                                        <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($producto['lugar_nombre']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin ubicación</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Estado Stock:</strong></div>
                                <div class="col-sm-8">
                                    <span class="badge bg-<?php echo $color_stock; ?>"><?php echo ucfirst($estado_stock); ?></span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Stock Mínimo:</strong></div>
                                <div class="col-sm-8"><?php echo number_format($producto['stock_minimo']); ?> unidades</div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Precio Compra:</strong></div>
                                <div class="col-sm-8"><?php echo formatCurrency($producto['precio_compra']); ?></div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Margen Ganancia:</strong></div>
                                <div class="col-sm-8">
                                    <span class="badge bg-<?php echo $margen > 30 ? 'success' : ($margen > 15 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($margen, 1); ?>%
                                    </span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Valor Total Stock:</strong></div>
                                <div class="col-sm-8">
                                    <strong class="text-success"><?php echo formatCurrency($valor_total); ?></strong>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Fecha Alta:</strong></div>
                                <div class="col-sm-8">
                                    <?php echo date('d/m/Y H:i', strtotime($producto['fecha_creacion'])); ?>
                                </div>
                            </div>

                            <?php if ($producto['fecha_modificacion'] !== $producto['fecha_creacion']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-4"><strong>Última Modificación:</strong></div>
                                    <div class="col-sm-8">
                                        <?php echo date('d/m/Y H:i', strtotime($producto['fecha_modificacion'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Acciones rápidas -->
                    <div class="card info-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Acciones Rápidas</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="producto_form.php?id=<?php echo $producto['id']; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil me-2"></i>Editar Producto
                                </a>
                                
                                <?php if ($producto['stock'] <= $producto['stock_minimo']): ?>
                                    <button class="btn btn-info" onclick="alert('Funcionalidad de restock en desarrollo')">
                                        <i class="bi bi-plus-circle me-2"></i>Reabastecer Stock
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline-primary" onclick="window.print()">
                                    <i class="bi bi-printer me-2"></i>Imprimir Detalles
                                </button>
                                
                                <button class="btn btn-outline-success" onclick="compartirProducto()">
                                    <i class="bi bi-share me-2"></i>Compartir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function compartirProducto() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($producto['nombre']); ?>',
                    text: 'Producto: <?php echo htmlspecialchars($producto['nombre']); ?> - Código: <?php echo htmlspecialchars($producto['codigo']); ?>',
                    url: window.location.href
                });
            } else {
                // Fallback para navegadores que no soportan Web Share API
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Enlace copiado al portapapeles');
                });
            }
        }

        // Mejorar la experiencia de impresión
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('printing');
        });

        window.addEventListener('afterprint', function() {
            document.body.classList.remove('printing');
        });
    </script>

    <style>
        @media print {
            .btn, .card-header {
                display: none !important;
            }
            .container {
                max-width: none !important;
            }
        }
    </style>
</body>
</html>

