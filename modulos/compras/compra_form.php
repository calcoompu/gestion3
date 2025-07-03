<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$pdo = conectarDB();
$id = $_GET['id'] ?? 0;
$compra = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM compras WHERE id = ?");
    $stmt->execute([$id]);
    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $compra ? 'Editar' : 'Nueva'; ?> Orden de Compra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-edit"></i> <?php echo $compra ? 'Editar' : 'Nueva'; ?> Orden de Compra</h1>
                    <a href="compras.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
                
                <form id="form-compra">
                    <input type="hidden" name="id" value="<?php echo $compra['id'] ?? ''; ?>">
                    
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> Información General</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Proveedor *</label>
                                        <select class="form-control" name="proveedor_id" required>
                                            <option value="">Seleccionar proveedor</option>
                                            <!-- Cargar proveedores dinámicamente -->
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha de Compra *</label>
                                        <input type="date" class="form-control" name="fecha_compra" 
                                               value="<?php echo $compra['fecha_compra'] ?? date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Fecha Entrega Estimada</label>
                                        <input type="date" class="form-control" name="fecha_entrega_estimada" 
                                               value="<?php echo $compra['fecha_entrega_estimada'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Estado</label>
                                        <select class="form-control" name="estado">
                                            <option value="pendiente">Pendiente</option>
                                            <option value="confirmada">Confirmada</option>
                                            <option value="parcial">Parcial</option>
                                            <option value="recibida">Recibida</option>
                                            <option value="cancelada">Cancelada</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="3"><?php echo $compra['observaciones'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="fas fa-box"></i> Productos</h5>
                        </div>
                        <div class="card-body">
                            <div id="productos-container">
                                <!-- Productos dinámicos -->
                            </div>
                            <button type="button" class="btn btn-success" onclick="agregarProducto()">
                                <i class="fas fa-plus"></i> Agregar Producto
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Orden
                        </button>
                        <a href="compras.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function agregarProducto() {
            // Implementar agregar producto
            console.log('Agregando producto...');
        }
    </script>
</body>
</html>