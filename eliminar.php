<?php
/**
 * SCRIPT ELIMINAR ARCHIVOS INNECESARIOS
 * =====================================
 * 
 * Este script elimina archivos duplicados, backups y versiones obsoletas
 * del sistema de gestión de inventario.
 * 
 * IMPORTANTE: Haz un backup completo antes de ejecutar este script
 */

// Configuración de seguridad
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Definir archivos a eliminar por categorías
$archivos_eliminar = [
    'backups' => [
        'menu_principalbak10_6.php',
        'modulos/Inventario/gestionar_productobak.php',
        'modulos/Inventario/gestionar_productobak10_61.php',
        'modulos/Inventario/producto_formbak10_61.php',
        'modulos/Inventario/productos_inactivosbak.php',
        'modulos/Inventario/productosbak.php',
        'modulos/Inventario/productosbak10_6.php',
        'modulos/Inventario/productosbak2.php',
        'modulos/Inventario/productosbak3.php'
    ],
    'duplicados' => [
        'menu_principal_FINAL.php',
        'productos(1).php',
        'productos(3).php',
        'producto_form(1).php',
        'productos_completo.php',
        'producto_form_mejorado.php',
        'modulos/Inventario/productos10_62.php',
        'modulos/Inventario/productos_inactivos_corregido.php'
    ],
    'debug' => [
        'modulos/Inventario/gestionar_producto_DEBUG.php',
        'modulos/Inventario/gestionar_producto_CORREGIDO_FINAL.php',
        'modulos/Inventario/corregir_charset.php'
    ],
    'configuraciones' => [
        'confignuevo.php',
        'configquefunciona.php',
        'config/configNUEVO.php'
    ],
    'temporales' => [
        'pasted_content.txt',
        'image.png',
        'sistema_inventario_COMPLETO_final.zip',
        'sistemadgestion.zip',
        'gestionar_producto.php',
        'productos.php',
        'productos_inactivos.php'
    ]
];

// Archivos críticos que NUNCA se deben eliminar
$archivos_criticos = [
    'index.php',
    'login.php',
    'logout.php',
    'menu_principal.php',
    '.htaccess',
    'README.md',
    'config/config.php',
    'modulos/Inventario/productos.php',
    'modulos/Inventario/productos_inactivos.php',
    'modulos/Inventario/gestionar_producto.php',
    'modulos/Inventario/producto_form.php',
    'modulos/Inventario/producto_detalle.php'
];

// Función para verificar si un archivo es crítico
function esCritico($archivo) {
    global $archivos_criticos;
    return in_array($archivo, $archivos_criticos);
}

// Función para mostrar el header HTML
function mostrarHeader() {
    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Archivos Innecesarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .container { margin-top: 20px; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-danger { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); border: none; }
        .btn-success { background: linear-gradient(135deg, #51cf66 0%, #40c057 100%); border: none; }
        .btn-warning { background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%); border: none; }
        .alert-danger { border-left: 4px solid #dc3545; }
        .alert-success { border-left: 4px solid #28a745; }
        .alert-warning { border-left: 4px solid #ffc107; }
        .file-list { max-height: 300px; overflow-y: auto; }
        .category-header { background: linear-gradient(135deg, #495057 0%, #343a40 100%); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h3 class="mb-0">
                            <i class="bi bi-trash3 me-2"></i>
                            Eliminar Archivos Innecesarios
                        </h3>
                        <small>Sistema de Gestión de Inventario - Limpieza de Archivos</small>
                    </div>
                    <div class="card-body">';
}

// Función para mostrar el footer HTML
function mostrarFooter() {
    echo '          </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
}

// Función para contar archivos existentes
function contarArchivos($categoria) {
    global $archivos_eliminar;
    $contador = 0;
    foreach ($archivos_eliminar[$categoria] as $archivo) {
        if (file_exists($archivo)) {
            $contador++;
        }
    }
    return $contador;
}

// Función para mostrar archivos de una categoría
function mostrarCategoria($categoria, $titulo, $descripcion, $icono, $color) {
    global $archivos_eliminar;
    
    $archivos_existentes = [];
    foreach ($archivos_eliminar[$categoria] as $archivo) {
        if (file_exists($archivo)) {
            $archivos_existentes[] = $archivo;
        }
    }
    
    if (empty($archivos_existentes)) {
        return;
    }
    
    echo "<div class='mb-4'>
            <div class='category-header p-3 rounded-top'>
                <h5 class='mb-1'>
                    <i class='bi bi-{$icono} me-2'></i>
                    {$titulo} (" . count($archivos_existentes) . " archivos)
                </h5>
                <small>{$descripcion}</small>
            </div>
            <div class='border border-top-0 rounded-bottom p-3 file-list'>
                <div class='row'>";
    
    foreach ($archivos_existentes as $archivo) {
        $size = file_exists($archivo) ? number_format(filesize($archivo) / 1024, 1) : '0';
        echo "<div class='col-md-6 mb-2'>
                <div class='d-flex align-items-center'>
                    <i class='bi bi-file-earmark text-{$color} me-2'></i>
                    <span class='me-auto'>{$archivo}</span>
                    <small class='text-muted'>{$size} KB</small>
                </div>
              </div>";
    }
    
    echo "    </div>
            </div>
          </div>";
}

// Función para eliminar archivos de una categoría
function eliminarCategoria($categoria) {
    global $archivos_eliminar;
    
    $eliminados = 0;
    $errores = [];
    
    foreach ($archivos_eliminar[$categoria] as $archivo) {
        if (file_exists($archivo)) {
            // Verificación de seguridad adicional
            if (esCritico($archivo)) {
                $errores[] = "ARCHIVO CRÍTICO PROTEGIDO: {$archivo}";
                continue;
            }
            
            if (unlink($archivo)) {
                $eliminados++;
                echo "<div class='alert alert-success py-2'>
                        <i class='bi bi-check-circle me-2'></i>
                        Eliminado: <code>{$archivo}</code>
                      </div>";
            } else {
                $errores[] = "Error al eliminar: {$archivo}";
            }
        }
    }
    
    return ['eliminados' => $eliminados, 'errores' => $errores];
}

// Procesar acciones
$accion = $_GET['accion'] ?? '';
$categoria = $_GET['categoria'] ?? '';

mostrarHeader();

// Mostrar advertencia de seguridad
echo '<div class="alert alert-danger">
        <h5><i class="bi bi-exclamation-triangle me-2"></i>¡ADVERTENCIA IMPORTANTE!</h5>
        <ul class="mb-0">
            <li><strong>Haz un backup completo</strong> antes de continuar</li>
            <li>Este script eliminará archivos <strong>permanentemente</strong></li>
            <li>Verifica que el sistema funcione después de cada eliminación</li>
            <li>Los archivos críticos están protegidos automáticamente</li>
        </ul>
      </div>';

if ($accion === 'eliminar' && $categoria) {
    // Eliminar categoría específica
    echo "<div class='alert alert-warning'>
            <h5><i class='bi bi-gear me-2'></i>Eliminando archivos de: " . ucfirst($categoria) . "</h5>
          </div>";
    
    $resultado = eliminarCategoria($categoria);
    
    echo "<div class='alert alert-success'>
            <h5><i class='bi bi-check-circle me-2'></i>Proceso Completado</h5>
            <p><strong>Archivos eliminados:</strong> {$resultado['eliminados']}</p>";
    
    if (!empty($resultado['errores'])) {
        echo "<p><strong>Errores:</strong></p><ul>";
        foreach ($resultado['errores'] as $error) {
            echo "<li class='text-danger'>{$error}</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
    
    echo '<div class="text-center mt-4">
            <a href="eliminar.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i>Volver al Menú Principal
            </a>
          </div>';

} elseif ($accion === 'eliminar_todo') {
    // Eliminar todas las categorías
    echo "<div class='alert alert-warning'>
            <h5><i class='bi bi-gear me-2'></i>Eliminando TODOS los archivos innecesarios...</h5>
          </div>";
    
    $total_eliminados = 0;
    $total_errores = [];
    
    foreach ($archivos_eliminar as $cat => $archivos) {
        if (contarArchivos($cat) > 0) {
            echo "<h6 class='mt-3 mb-2'>Procesando: " . ucfirst($cat) . "</h6>";
            $resultado = eliminarCategoria($cat);
            $total_eliminados += $resultado['eliminados'];
            $total_errores = array_merge($total_errores, $resultado['errores']);
        }
    }
    
    echo "<div class='alert alert-success mt-4'>
            <h5><i class='bi bi-check-circle me-2'></i>¡Limpieza Completada!</h5>
            <p><strong>Total de archivos eliminados:</strong> {$total_eliminados}</p>";
    
    if (!empty($total_errores)) {
        echo "<p><strong>Errores encontrados:</strong></p><ul>";
        foreach ($total_errores as $error) {
            echo "<li class='text-danger'>{$error}</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
    
    echo '<div class="text-center mt-4">
            <a href="eliminar.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i>Volver al Menú Principal
            </a>
            <a href="menu_principal.php" class="btn btn-success ms-2">
                <i class="bi bi-house me-2"></i>Ir al Sistema
            </a>
          </div>';

} else {
    // Mostrar menú principal
    echo '<div class="alert alert-info">
            <h5><i class="bi bi-info-circle me-2"></i>Archivos Detectados para Eliminación</h5>
            <p>Se han encontrado archivos innecesarios en tu sistema. Puedes eliminarlos por categorías o todos a la vez.</p>
          </div>';
    
    // Mostrar cada categoría
    mostrarCategoria('backups', 'Archivos de Backup', 'Copias de seguridad automáticas y manuales', 'archive', 'warning');
    mostrarCategoria('duplicados', 'Archivos Duplicados', 'Versiones duplicadas y obsoletas', 'files', 'info');
    mostrarCategoria('debug', 'Archivos de Debug', 'Archivos de desarrollo y debugging', 'bug', 'danger');
    mostrarCategoria('configuraciones', 'Configuraciones Duplicadas', 'Archivos de configuración obsoletos', 'gear', 'secondary');
    mostrarCategoria('temporales', 'Archivos Temporales', 'Archivos temporales y de prueba', 'clock', 'muted');
    
    // Calcular totales
    $total_archivos = 0;
    $total_size = 0;
    foreach ($archivos_eliminar as $categoria => $archivos) {
        foreach ($archivos as $archivo) {
            if (file_exists($archivo)) {
                $total_archivos++;
                $total_size += filesize($archivo);
            }
        }
    }
    
    if ($total_archivos > 0) {
        echo "<div class='alert alert-warning'>
                <h5><i class='bi bi-exclamation-triangle me-2'></i>Resumen</h5>
                <p><strong>Total de archivos a eliminar:</strong> {$total_archivos}</p>
                <p><strong>Espacio a liberar:</strong> " . number_format($total_size / 1024, 1) . " KB</p>
              </div>";
        
        echo '<div class="row mt-4">
                <div class="col-md-6 mb-3">
                    <h6>Eliminar por Categoría:</h6>';
        
        foreach ($archivos_eliminar as $cat => $archivos) {
            $count = contarArchivos($cat);
            if ($count > 0) {
                $colors = [
                    'backups' => 'warning',
                    'duplicados' => 'info', 
                    'debug' => 'danger',
                    'configuraciones' => 'secondary',
                    'temporales' => 'dark'
                ];
                $color = $colors[$cat] ?? 'primary';
                
                echo "<a href='?accion=eliminar&categoria={$cat}' class='btn btn-{$color} btn-sm me-2 mb-2' 
                        onclick='return confirm(\"¿Eliminar {$count} archivos de " . ucfirst($cat) . "?\")'>
                        " . ucfirst($cat) . " ({$count})
                      </a>";
            }
        }
        
        echo '  </div>
                <div class="col-md-6 mb-3">
                    <h6>Eliminar Todo:</h6>
                    <a href="?accion=eliminar_todo" class="btn btn-danger" 
                       onclick="return confirm(\'¿Estás seguro de eliminar TODOS los archivos innecesarios? Esta acción no se puede deshacer.\')">
                        <i class="bi bi-trash3 me-2"></i>
                        Eliminar Todos ({$total_archivos} archivos)
                    </a>
                </div>
              </div>';
    } else {
        echo '<div class="alert alert-success">
                <h5><i class="bi bi-check-circle me-2"></i>¡Sistema Limpio!</h5>
                <p>No se encontraron archivos innecesarios para eliminar. Tu sistema está optimizado.</p>
              </div>';
    }
    
    echo '<div class="text-center mt-4">
            <a href="menu_principal.php" class="btn btn-success">
                <i class="bi bi-house me-2"></i>Volver al Sistema
            </a>
          </div>';
}

mostrarFooter();
?>

