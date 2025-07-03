<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$pdo = conectarDB();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recepción de Mercadería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-truck-loading"></i> Recepción de Mercadería</h1>
                    <a href="compras.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Compras
                    </a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Órdenes Pendientes de Recepción</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Código</th>
                                        <th>Proveedor</th>
                                        <th>Fecha Compra</th>
                                        <th>Entrega Estimada</th>
                                        <th>Estado</th>
                                        <th>Total</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tabla-pendientes">
                                    <!-- Contenido dinámico -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para recepción -->
    <div class="modal fade" id="modalRecepcion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-box-open"></i> Recibir Mercadería</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="form-recepcion">
                        <input type="hidden" id="compra_id" name="compra_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Fecha de Recepción</label>
                            <input type="datetime-local" class="form-control" name="fecha_recepcion" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Productos a Recibir</label>
                            <div id="productos-recepcion">
                                <!-- Productos dinámicos -->
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="3" 
                                      placeholder="Observaciones sobre la recepción..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="procesarRecepcion()">
                        <i class="fas fa-check"></i> Procesar Recepción
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            cargarPendientes();
        });
        
        function cargarPendientes() {
            // Implementar carga de órdenes pendientes
            console.log('Cargando órdenes pendientes...');
        }
        
        function abrirRecepcion(compraId) {
            // Implementar apertura de modal de recepción
            console.log('Abriendo recepción para compra:', compraId);
        }
        
        function procesarRecepcion() {
            // Implementar procesamiento de recepción
            console.log('Procesando recepción...');
        }
    </script>
</body>
</html>