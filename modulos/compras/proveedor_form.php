<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$pdo = conectarDB();
$id = $_GET['id'] ?? 0;
$proveedor = null;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
    $stmt->execute([$id]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $proveedor ? 'Editar' : 'Nuevo'; ?> Proveedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-edit"></i> <?php echo $proveedor ? 'Editar' : 'Nuevo'; ?> Proveedor</h1>
                    <a href="proveedores.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
                
                <form id="form-proveedor">
                    <input type="hidden" name="id" value="<?php echo $proveedor['id'] ?? ''; ?>">
                    
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-building"></i> Información de la Empresa</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Razón Social *</label>
                                        <input type="text" class="form-control" name="razon_social" 
                                               value="<?php echo $proveedor['razon_social'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre Comercial</label>
                                        <input type="text" class="form-control" name="nombre_comercial" 
                                               value="<?php echo $proveedor['nombre_comercial'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">CUIT</label>
                                        <input type="text" class="form-control" name="cuit" 
                                               value="<?php echo $proveedor['cuit'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo $proveedor['email'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" name="telefono" 
                                               value="<?php echo $proveedor['telefono'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Días de Entrega</label>
                                        <input type="number" class="form-control" name="dias_entrega" 
                                               value="<?php echo $proveedor['dias_entrega'] ?? '0'; ?>" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="fas fa-map-marker-alt"></i> Dirección</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Dirección</label>
                                        <textarea class="form-control" name="direccion" rows="2"><?php echo $proveedor['direccion'] ?? ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Ciudad</label>
                                        <input type="text" class="form-control" name="ciudad" 
                                               value="<?php echo $proveedor['ciudad'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Provincia</label>
                                        <input type="text" class="form-control" name="provincia" 
                                               value="<?php echo $proveedor['provincia'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Código Postal</label>
                                        <input type="text" class="form-control" name="codigo_postal" 
                                               value="<?php echo $proveedor['codigo_postal'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5><i class="fas fa-user"></i> Contacto</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre de Contacto</label>
                                        <input type="text" class="form-control" name="contacto_nombre" 
                                               value="<?php echo $proveedor['contacto_nombre'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Teléfono de Contacto</label>
                                        <input type="text" class="form-control" name="contacto_telefono" 
                                               value="<?php echo $proveedor['contacto_telefono'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Email de Contacto</label>
                                        <input type="email" class="form-control" name="contacto_email" 
                                               value="<?php echo $proveedor['contacto_email'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">Condiciones de Pago</label>
                                        <input type="text" class="form-control" name="condiciones_pago" 
                                               value="<?php echo $proveedor['condiciones_pago'] ?? ''; ?>" 
                                               placeholder="Ej: 30 días, Contado, etc.">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Proveedor
                        </button>
                        <a href="proveedores.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>