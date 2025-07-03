<?php
// Asegúrate de que este script esté en la raíz de tu proyecto o en una ubicación accesible.

// --- Configuración ---
// Define la ruta a tu archivo base del menú (donde está el menú original).
define('RUTA_MENU_PRINCIPAL', 'menu_principal.php');

// Define la estructura de archivos a actualizar.
$archivos_a_actualizar = [
    'Raíz' => [
        'menu_principal.php' => 'menu_principal.php', // Este archivo se usará como referencia, no se modificará a sí mismo.
        'login.php' => 'login.php',
        'logout.php' => 'logout.php'
    ],
    'Inventario' => [
        'ajax_categorias.php' => 'modulos/Inventario/ajax_categorias.php',
        'ajax_lugares.php' => 'modulos/Inventario/ajax_lugares.php',
        'exportar_excel.php' => 'modulos/Inventario/exportar_excel.php',
        'gestionar_producto.php' => 'modulos/Inventario/gestionar_producto.php',
        'index.php' => 'modulos/Inventario/index.php',
        'producto_detalle.php' => 'modulos/Inventario/producto_detalle.php',
        'producto_form.php' => 'modulos/Inventario/producto_form.php',
        'producto_form_prueba.php' => 'modulos/Inventario/producto_form_prueba.php',
        'productos.php' => 'modulos/Inventario/productos.php',
        'productos_inactivos.php' => 'modulos/Inventario/productos_inactivos.php',
        'productos_por_categoria.php' => 'modulos/Inventario/productos_por_categoria.php',
        'productos_por_lugar.php' => 'modulos/Inventario/productos_por_lugar.php',
        'produto_form_prueba.php' => 'modulos/Inventario/produto_form_prueba.php',
        'reportes.php' => 'modulos/Inventario/reportes.php'
    ],
    'Clientes' => [
        'clientes.php' => 'modulos/clientes/clientes.php',
        'cliente_form.php' => 'modulos/clientes/cliente_form.php',
        'cliente_detalle.php' => 'modulos/clientes/cliente_detalle.php',
        'gestionar_cliente.php' => 'modulos/clientes/gestionar_cliente.php',
        'clientes_inactivos.php' => 'modulos/clientes/clientes_inactivos.php',
        'papelera_clientes.php' => 'modulos/clientes/papelera_clientes.php',
        'gestionar_papelera.php' => 'modulos/clientes/gestionar_papelera.php',
        'importar_clientes.php' => 'modulos/clientes/importar_clientes.php',
        'exportar_clientes.php' => 'modulos/clientes/exportar_clientes.php',
        'reportes_clientes.php' => 'modulos/clientes/reportes_clientes.php'
    ],
    'Pedidos' => [
        'pedidos.php' => 'modulos/pedidos/pedidos.php',
        'pedido_form.php' => 'modulos/pedidos/pedido_form.php',
        'pedido_detalle.php' => 'modulos/pedidos/pedido_detalle.php',
        'gestionar_pedido.php' => 'modulos/pedidos/gestionar_pedido.php',
        'pedidos_pendientes.php' => 'modulos/pedidos/pedidos_pendientes.php',
        'cambiar_estado_pedido.php' => 'modulos/pedidos/cambiar_estado_pedido.php',
        'exportar_pedidos.php' => 'modulos/pedidos/exportar_pedidos.php',
        'reportes_pedidos.php' => 'modulos/pedidos/reportes_pedidos.php',
        'generar_pdf_pedido.php' => 'modulos/pedidos/generar_pdf_pedido.php',
        'ajax_productos_pedido.php' => 'modulos/pedidos/ajax_productos_pedido.php'
    ],
    'Facturas' => [
        'facturas.php' => 'modulos/facturas/facturas.php',
        'factura_form.php' => 'modulos/facturas/factura_form.php',
        'factura_detalle.php' => 'modulos/facturas/factura_detalle.php',
        'gestionar_factura.php' => 'modulos/facturas/gestionar_factura.php',
        'facturas_pendientes.php' => 'modulos/facturas/facturas_pendientes.php',
        'facturas_por_cliente.php' => 'modulos/facturas/facturas_por_cliente.php',
        'cambiar_estado_factura.php' => 'modulos/facturas/cambiar_estado_factura.php',
        'exportar_facturas.php' => 'modulos/facturas/exportar_facturas.php',
        'reportes_facturas.php' => 'modulos/facturas/reportes_facturas.php',
        'generar_pdf_factura.php' => 'modulos/facturas/generar_pdf_factura.php',
        'registrar_pago.php' => 'modulos/facturas/registrar_pago.php'
    ],
    'Compras' => [
        'compras.php' => 'modulos/compras/compras.php',
        'compra_form.php' => 'modulos/compras/compra_form.php',
        'compra_detalle.php' => 'modulos/compras/compra_detalle.php',
        'gestionar_compra.php' => 'modulos/compras/gestionar_compra.php',
        'proveedores.php' => 'modulos/compras/proveedores.php',
        'proveedor_form.php' => 'modulos/compras/proveedor_form.php',
        'gestionar_proveedor.php' => 'modulos/compras/gestionar_proveedor.php',
        'recepcion_mercaderia.php' => 'modulos/compras/recepcion_mercaderia.php',
        'compras_pendientes.php' => 'modulos/compras/compras_pendientes.php',
        'reportes_compras.php' => 'modulos/compras/reportes_compras.php',
        'exportar_compras.php' => 'modulos/compras/exportar_compras.php',
        'ajax_productos_compra.php' => 'modulos/compras/ajax_productos_compra.php'
    ],
    'Administración' => [
        'admin_dashboard.php' => 'modulos/admin/admin_dashboard.php',
        'usuarios.php' => 'modulos/admin/usuarios.php',
        'usuario_form.php' => 'modulos/admin/usuario_form.php',
        'gestionar_usuario.php' => 'modulos/admin/gestionar_usuario.php',
        'categorias_admin.php' => 'modulos/admin/categorias_admin.php',
        'categoria_form.php' => 'modulos/admin/categoria_form.php',
        'gestionar_categoria.php' => 'modulos/admin/gestionar_categoria.php',
        'lugares_admin.php' => 'modulos/admin/lugares_admin.php',
        'lugar_form.php' => 'modulos/admin/lugar_form.php',
        'gestionar_lugar.php' => 'modulos/admin/gestionar_lugar.php',
        'configuracion_sistema.php' => 'modulos/admin/configuracion_sistema.php',
        'gestionar_configuracion.php' => 'modulos/admin/gestionar_configuracion.php',
        'reportes_admin.php' => 'modulos/admin/reportes_admin.php',
        'backup_sistema.php' => 'modulos/admin/backup_sistema.php',
        'logs_sistema.php' => 'modulos/admin/logs_sistema.php'
    ]
];

// Marcador para indicar si el menú ya fue copiado. Este debe ser único y poco probable en el código existente.
$menu_ya_copiado_flag = '<!-- Menu de Navegacion Principal Copiado por Herramienta -->';
$backup_suffix = '.bak'; // Sufijo para los archivos de backup

// --- Funciones Auxiliares ---

/**
 * Crea un backup de un archivo si no existe ya un backup con el sufijo especificado.
 *
 * @param string $ruta_archivo Ruta del archivo a respaldar.
 * @param string $sufijo Sufijo para el archivo de backup.
 * @return string Mensaje de estado.
 */
function crearBackup(string $ruta_archivo, string $sufijo): string {
    if (!file_exists($ruta_archivo)) {
        return "Error: El archivo de destino '$ruta_archivo' no existe para crear backup.";
    }

    $ruta_backup = $ruta_archivo . $sufijo;

    // Verificar si ya existe un archivo de backup
    if (file_exists($ruta_backup)) {
        return "Info: Backup '$ruta_backup' ya existe. No se creó uno nuevo.";
    }

    // Intentar copiar el archivo
    if (copy($ruta_archivo, $ruta_backup)) {
        return "Éxito: Se creó backup para '$ruta_archivo' como '$ruta_backup'.";
    } else {
        // Se intenta dar un mensaje más específico si es un error de permisos
        if (!is_writable(dirname($ruta_archivo))) {
            return "Error de Permisos: No se puede escribir el backup en el directorio de '$ruta_archivo'.";
        }
        return "Error: No se pudo crear el backup para '$ruta_archivo'.";
    }
}


/**
 * Verifica si el menu de navegacion ya existe en un archivo dado buscando un marcador.
 *
 * @param string $ruta_archivo La ruta completa al archivo a verificar.
 * @return bool True si el menu ya existe, False en caso contrario.
 */
function verificarMenuExistente(string $ruta_archivo): bool {
    if (!file_exists($ruta_archivo)) {
        return false; // El archivo no existe, por lo tanto el menú no puede existir.
    }
    $contenido = file_get_contents($ruta_archivo);
    // Buscamos el marcador único que indica la presencia del menú.
    return strpos($contenido, $GLOBALS['menu_ya_copiado_flag']) !== false;
}

/**
 * Extrae la sección del menú de navegación principal del archivo source.
 *
 * @param string $ruta_source La ruta al archivo de donde extraer el menú.
 * @return string|false El código HTML del menú extraído, o false si no se encuentra.
 */
function extraerMenuNavegacion(string $ruta_source): string|false {
    if (!file_exists($ruta_source)) {
        return false;
    }

    $contenido_principal = file_get_contents($ruta_source);

    // Buscamos el inicio de la etiqueta <nav> que contiene el menú principal.
    $inicio_nav_tag = strpos($contenido_principal, '<nav class="navbar');
    if ($inicio_nav_tag === false) {
        return false; // No se encontró la etiqueta de inicio del nav.
    }

    // Buscamos el div que contiene el menú colapsable.
    $inicio_menu_div = strpos($contenido_principal, '<div class="collapse navbar-collapse" id="navbarNav">', $inicio_nav_tag);
    if ($inicio_menu_div === false) {
         return false; // No se encontró el div del menú colapsable.
    }

    // Buscamos el cierre del primer ul principal dentro del menú colapsable.
    $fin_primer_ul = strpos($contenido_principal, '</ul>', $inicio_menu_div);
    if ($fin_primer_ul === false) {
        return false; // No se encontró el cierre del primer ul.
    }
    // Ajustamos la posición para incluir la etiqueta de cierre del UL.
    $fin_menu_section = $fin_primer_ul + strlen('</ul>');

    // Extraemos la sección completa del menú que contiene todas las listas (ULs).
    $seccion_menu_html = substr($contenido_principal, $inicio_menu_div, $fin_menu_section - $inicio_menu_div);

    return $seccion_menu_html;
}

/**
 * Inserta el menú de navegación extraído en el archivo de destino.
 *
 * @param string $ruta_destino Ruta del archivo donde insertar el menú.
 * @param string $menu_html Código HTML del menú a insertar.
 * @return string Mensaje de estado de la operación.
 */
function insertarMenuEnArchivo(string $ruta_destino, string $menu_html): string {
    // 1. Crear Backup antes de modificar
    $mensaje_backup = crearBackup($ruta_destino, $GLOBALS['backup_suffix']);
    // Si hubo un error grave en el backup (no un info), podríamos detenernos.
    if (strpos($mensaje_backup, 'Error: No se pudo crear') !== false || strpos($mensaje_backup, 'Error de Permisos') !== false) {
        return $mensaje_backup; // Detener si el backup falló
    }

    // Preparamos el contenido a insertar con el marcador.
    $contenido_a_insertar = $GLOBALS['menu_ya_copiado_flag'] . "\n" . $menu_html;

    // Leer el contenido del archivo de destino.
    $contenido_destino = file_get_contents($ruta_destino);

    // Encontrar el lugar adecuado para insertar el menú.
    // Buscamos el cierre de la etiqueta <body> o el inicio del archivo si <body> no está presente.
    $inicio_body_tag = strpos($contenido_destino, '<body>');
    $posicion_insercion = 0; // Por defecto, al inicio si no hay body.

    if ($inicio_body_tag !== false) {
        $posicion_insercion = $inicio_body_tag + strlen('<body>');
    }

    // Construir el nuevo contenido del archivo de destino.
    // Parte anterior + Menú insertado + Parte posterior.
    $nuevo_contenido = substr($contenido_destino, 0, $posicion_insercion)
                     . "\n" . $contenido_a_insertar . "\n"
                     . substr($contenido_destino, $posicion_insercion);

    // Escribir el contenido modificado de vuelta al archivo de destino.
    if (file_put_contents($ruta_destino, $nuevo_contenido) !== false) {
        // Combinar mensajes de backup y copia
        return $mensaje_backup . " | " . "Éxito: Menú copiado a '" . $ruta_destino . "'.";
    } else {
        // Si falla la escritura, intentar restaurar desde backup si se creó
        $mensaje_restauracion = "";
        if (file_exists($ruta_destino . $GLOBALS['backup_suffix'])) {
            if (copy($ruta_destino . $GLOBALS['backup_suffix'], $ruta_destino)) {
                $mensaje_restauracion = "Restaurado desde backup.";
            } else {
                $mensaje_restauracion = "Fallo al restaurar desde backup.";
            }
        }
        return $mensaje_backup . " | " . "Error: Falló la escritura en '$ruta_destino'. {$mensaje_restauracion}";
    }
}

// --- Lógica del Script ---

$mensajes = [];
$menu_html_extraido = null; // Para almacenar el menú extraído una vez.

// Manejar la solicitud POST si se ha marcado algún checkbox.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Primero, extraemos el menú una sola vez para evitar leer el archivo principal repetidamente.
    $menu_html_extraido = extraerMenuNavegacion(RUTA_MENU_PRINCIPAL);

    if ($menu_html_extraido === false) {
        $mensajes[] = "Error Crítico: No se pudo extraer el menú de navegación del archivo '" . RUTA_MENU_PRINCIPAL . "'.";
    } else {
        if (isset($_POST['actualizar_archivos']) && is_array($_POST['actualizar_archivos'])) {
            foreach ($_POST['actualizar_archivos'] as $categoria => $archivos_seleccionados) {
                if (isset($archivos_a_actualizar[$categoria])) {
                    foreach ($archivos_seleccionados as $nombre_archivo_key => $valor) {
                        // Solo procesar si el archivo está seleccionado y no es el archivo principal.
                        if ($valor === 'on' && $nombre_archivo_key !== 'menu_principal.php') {
                            $ruta_completa_archivo = $archivos_a_actualizar[$categoria][$nombre_archivo_key];

                            // Verificar si el menú ya está presente antes de intentar copiar.
                            if (!verificarMenuExistente($ruta_completa_archivo)) {
                                 $mensajes[] = insertarMenuEnArchivo($ruta_completa_archivo, $menu_html_extraido);
                            } else {
                                $mensajes[] = "Info: Menú ya presente en '$ruta_completa_archivo'. No se realizaron cambios.";
                            }
                        }
                    }
                }
            }
        }
    }
}

// --- Generación de la Interfaz HTML ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herramienta de Actualización de Menú</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { padding-top: 20px; background-color: #f8f9fa; font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .container { max-width: 960px; }
        .card { margin-bottom: 20px; border-radius: .75rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15) !important; border: none;}
        .card-header { background-color: #e9ecef; font-weight: bold; border-bottom: 1px solid #dee2e6; border-top-left-radius: .75rem; border-top-right-radius: .75rem;}
        .form-check label { cursor: pointer; }
        .alert { margin-top: 15px; border-radius: .5rem;}
        .disabled-checkbox-label { color: #6c757d; font-style: italic; }
        .disabled-checkbox { pointer-events: none; opacity: 0.6; }
        .icon-menu { margin-right: 5px; }
        .alert-custom-info { background-color: #e0f7fa; border-color: #b2ebf2; color: #006064; } /* Info general y de backup existente */
        .alert-custom-success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; } /* Éxito en copia y backup */
        .alert-custom-error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; } /* Errores varios */
        .alert-custom-warning { background-color: #fff3cd; border-color: #ffeeba; color: #856404; } /* Advertencias/Errores críticos */
        .badge-ref { background-color: #007bff; } /* Azul para Referencia */
        .badge-copied { background-color: #6c757d; } /* Gris para Ya Copiado */
        .badge-warning-custom { background-color: #ffc107; color: #212529; } /* Amarillo para advertencias */
        .alert-dismissible .btn-close { /* Ajuste para que el botón de cerrar se vea mejor en las alertas personalizadas */
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4 text-center"><i class="bi bi-list"></i> Herramienta de Actualización de Menú</h1>
        <p class="text-center text-muted mb-4">Selecciona los archivos de destino para copiar el menú de navegación principal.</p>

        <?php if (!empty($mensajes)): ?>
            <div id="mensajeContainer">
                <?php foreach ($mensajes as $mensaje): ?>
                    <?php
                    $alert_class = 'alert-info'; // Clase por defecto
                    if (strpos($mensaje, 'Éxito:') !== false) {
                        $alert_class = 'alert-custom-success';
                    } elseif (strpos($mensaje, 'Error:') !== false && strpos($mensaje, 'No se pudo crear') === false && strpos($mensaje, 'Fallo al restaurar') === false) {
                        $alert_class = 'alert-custom-error';
                    } elseif (strpos($mensaje, 'Error de Permisos:') !== false || strpos($mensaje, 'Error Crítico:') !== false) {
                        $alert_class = 'alert-custom-error'; // Mantener errores graves como error
                    } elseif (strpos($mensaje, 'Info:') !== false || strpos($mensaje, 'Menú ya presente') !== false || strpos($mensaje, 'Error: El archivo') !== false && strpos($mensaje, 'no existe') !== false || strpos($mensaje, 'Backup') !== false && strpos($mensaje, 'ya existe') !== false) {
                        $alert_class = 'alert-custom-info';
                    } elseif (strpos($mensaje, 'Fallo al restaurar') !== false || strpos($mensaje, 'No se pudo encontrar') !== false) { // Advertencias si hay fallos post-copia o de extracción
                         $alert_class = 'alert-custom-warning';
                    }
                    ?>
                    <div class="alert <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php if ($menu_html_extraido === false && $_SERVER['REQUEST_METHOD'] !== 'POST'): // Solo mostrar formulario si el menú se pudo extraer y no hubo un POST con error crítico previo ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> No se pudo extraer el menú de navegación de `menu_principal.php`. Asegúrate de que el archivo exista y tenga la estructura correcta.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php else: ?>
                <?php foreach ($archivos_a_actualizar as $categoria => $archivos): ?>
                    <div class="card">
                        <div class="card-header"><i class="bi bi-folder"></i> <?php echo htmlspecialchars($categoria); ?></div>
                        <div class="card-body">
                            <?php foreach ($archivos as $nombre_archivo_key => $ruta_archivo_destino): ?>
                                <?php
                                $ya_existe = verificarMenuExistente($ruta_archivo_destino);
                                $es_archivo_referencia = ($nombre_archivo_key === 'menu_principal.php');
                                $checkbox_disabled = $ya_existe || $es_archivo_referencia || $menu_html_extraido === false; // Deshabilitar si ya existe, es ref, o si el menú no se pudo extraer
                                ?>
                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="actualizar_archivos[<?php echo htmlspecialchars($categoria); ?>][<?php echo htmlspecialchars($nombre_archivo_key); ?>]"
                                        value="on"
                                        id="check_<?php echo htmlspecialchars($categoria . '_' . $nombre_archivo_key); ?>"
                                        <?php echo $checkbox_disabled ? 'disabled' : ''; ?>
                                        <?php echo $es_archivo_referencia ? 'checked' : ''; // Marcar como checked el de referencia ?>
                                    >
                                    <label class="form-check-label" for="check_<?php echo htmlspecialchars($categoria . '_' . $nombre_archivo_key); ?>">
                                        <?php echo htmlspecialchars($nombre_archivo_key); ?>
                                        <?php if ($es_archivo_referencia): ?>
                                            <span class="badge badge-ref">Referencia</span>
                                        <?php elseif ($ya_existe): ?>
                                            <span class="badge badge-copied">Menú ya copiado</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg" <?php echo ($menu_html_extraido === false) ? 'disabled' : ''; ?>><i class="bi bi-download"></i> Copiar Menú Seleccionado</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script para manejar la lógica de los mensajes y su clase de alerta
        document.addEventListener('DOMContentLoaded', function() {
            const mensajesDiv = document.getElementById('mensajeContainer');
            if (!mensajesDiv) return;

            mensajesDiv.querySelectorAll('.alert').forEach(alertEl => {
                const alertText = alertEl.textContent.trim();
                if (alertText.startsWith('Éxito:') || alertText.startsWith('Éxito: Se creó backup')) {
                    alertEl.classList.add('alert-custom-success');
                } else if (alertText.startsWith('Error: Falló la escritura') || alertText.startsWith('Error de Permisos:') || alertText.startsWith('Error Crítico:') || alertText.startsWith('Fallo al restaurar')) {
                    alertEl.classList.add('alert-custom-error');
                } else if (alertText.startsWith('Info:') || alertText.startsWith('Menú ya presente') || alertText.startsWith('Error: El archivo') || alertText.startsWith('Backup') !== false && alertText.startsWith('ya existe') !== false || alertText.startsWith('No se pudo encontrar')) {
                    alertEl.classList.add('alert-custom-info');
                } else if (alertText.startsWith('Error: No se pudo extraer') || alertText.startsWith('Error: No se pudo encontrar la etiqueta')) {
                     alertEl.classList.add('alert-custom-warning');
                }
            });
        });
    </script>
</body>
</html>