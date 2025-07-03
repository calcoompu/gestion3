<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Verificar que se solicite exportación
if (!isset($_POST['exportar']) || $_POST['exportar'] !== '1') {
    header('Location: productos.php');
    exit;
}

// Configurar charset UTF-8
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d_H-i-s') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Obtener filtros de la solicitud
$filtro_categoria = isset($_POST['categoria']) ? $_POST['categoria'] : '';
$filtro_lugar = isset($_POST['lugar']) ? $_POST['lugar'] : '';
$filtro_busqueda = isset($_POST['busqueda']) ? trim($_POST['busqueda']) : '';
$filtro_bajo_stock = isset($_POST['filtro']) && $_POST['filtro'] === 'bajo_stock';

// Parámetros de ordenamiento
$orden_campo = isset($_POST['orden']) ? $_POST['orden'] : 'fecha_creacion';
$orden_direccion = isset($_POST['dir']) && $_POST['dir'] === 'asc' ? 'ASC' : 'DESC';

// Campos válidos para ordenamiento
$campos_validos = [
    'codigo' => 'p.codigo',
    'nombre' => 'p.nombre', 
    'categoria' => 'c.nombre',
    'lugar' => 'l.nombre',
    'stock' => 'p.stock',
    'stock_minimo' => 'p.stock_minimo',
    'precio_venta' => 'p.precio_venta',
    'fecha_creacion' => 'p.fecha_creacion'
];

$campo_orden = isset($campos_validos[$orden_campo]) ? $campos_validos[$orden_campo] : $campos_validos['fecha_creacion'];

try {
    $pdo = conectarDB();
    
    // Configurar charset en la conexión
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Construir consulta base
    $where_conditions = ["p.activo = 1"];
    $params = [];
    
    if (!empty($filtro_categoria)) {
        if ($filtro_categoria === '') {
            $where_conditions[] = "p.categoria_id IS NULL";
        } else {
            $where_conditions[] = "p.categoria_id = ?";
            $params[] = $filtro_categoria;
        }
    }
    
    if (!empty($filtro_lugar)) {
        if ($filtro_lugar === '') {
            $where_conditions[] = "p.lugar_id IS NULL";
        } else {
            $where_conditions[] = "p.lugar_id = ?";
            $params[] = $filtro_lugar;
        }
    }
    
    if (!empty($filtro_busqueda)) {
        $where_conditions[] = "(p.nombre LIKE ? OR p.codigo LIKE ? OR p.descripcion LIKE ?)";
        $busqueda_param = "%{$filtro_busqueda}%";
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
        $params[] = $busqueda_param;
    }
    
    if ($filtro_bajo_stock) {
        $where_conditions[] = "p.stock <= p.stock_minimo";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Consulta para exportar (sin límite)
    $sql = "SELECT p.codigo, p.nombre, p.descripcion, p.stock, p.stock_minimo, 
                   p.precio_venta, p.precio_compra, p.fecha_creacion,
                   c.nombre as categoria, l.nombre as lugar
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN lugares l ON p.lugar_id = l.id 
            WHERE {$where_clause}
            ORDER BY {$campo_orden} {$orden_direccion}";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas para el resumen
    $sql_stats = "SELECT 
                    COUNT(*) as total_productos,
                    SUM(stock) as stock_total,
                    SUM(stock * precio_venta) as total_stock_valor,
                    AVG(precio_venta) as precio_promedio,
                    COUNT(CASE WHEN stock <= stock_minimo THEN 1 END) as productos_bajo_stock
                  FROM productos p
                  LEFT JOIN categorias c ON p.categoria_id = c.id 
                  LEFT JOIN lugares l ON p.lugar_id = l.id 
                  WHERE {$where_clause}";
    
    $stmt = $pdo->prepare($sql_stats);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    echo "Error al exportar: " . $e->getMessage();
    exit;
}

// Función para formatear moneda en Excel
function formatCurrencyExcel($amount) {
    return number_format($amount, 2, ',', '.');
}

// Generar contenido Excel con formato
echo "\xEF\xBB\xBF"; // BOM para UTF-8
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="ProgId" content="Excel.Sheet">
    <meta name="Generator" content="Microsoft Excel 11">
    <style>
        .header { 
            background-color: #4472C4; 
            color: white; 
            font-weight: bold; 
            text-align: center;
            font-size: 14pt;
        }
        .subheader { 
            background-color: #D9E2F3; 
            font-weight: bold; 
            text-align: center;
            font-size: 11pt;
        }
        .data { 
            text-align: center; 
            font-size: 10pt;
        }
        .currency { 
            text-align: right; 
            font-size: 10pt;
        }
        .date { 
            text-align: center; 
            font-size: 10pt;
        }
        .total { 
            background-color: #E2EFDA; 
            font-weight: bold; 
            font-size: 11pt;
        }
        .stock-low { 
            background-color: #FFCDD2; 
            color: #D32F2F;
        }
        .stock-medium { 
            background-color: #FFF3CD; 
            color: #856404;
        }
        .stock-good { 
            background-color: #D4EDDA; 
            color: #155724;
        }
    </style>
</head>
<body>
    <table border="1">
        <!-- Título del reporte -->
        <tr>
            <td colspan="10" class="header">REPORTE DE PRODUCTOS - INVENTPRO</td>
        </tr>
        <tr>
            <td colspan="10" class="subheader">Generado el <?php echo date('d/m/Y H:i:s'); ?></td>
        </tr>
        <tr><td colspan="10"></td></tr>
        
        <!-- Resumen estadístico -->
        <tr>
            <td colspan="2" class="total">RESUMEN GENERAL</td>
            <td colspan="8"></td>
        </tr>
        <tr>
            <td class="data">Total Productos:</td>
            <td class="data"><?php echo number_format($stats['total_productos']); ?></td>
            <td class="data">Stock Total:</td>
            <td class="data"><?php echo number_format($stats['stock_total']); ?></td>
            <td class="data">Valor Total:</td>
            <td class="currency">$ <?php echo formatCurrencyExcel($stats['total_stock_valor']); ?></td>
            <td class="data">Precio Promedio:</td>
            <td class="currency">$ <?php echo formatCurrencyExcel($stats['precio_promedio']); ?></td>
            <td class="data">Bajo Stock:</td>
            <td class="data"><?php echo number_format($stats['productos_bajo_stock']); ?></td>
        </tr>
        <tr><td colspan="10"></td></tr>
        
        <!-- Filtros aplicados -->
        <?php if (!empty($filtro_busqueda) || !empty($filtro_categoria) || !empty($filtro_lugar) || $filtro_bajo_stock): ?>
        <tr>
            <td colspan="2" class="total">FILTROS APLICADOS</td>
            <td colspan="8"></td>
        </tr>
        <?php if (!empty($filtro_busqueda)): ?>
        <tr>
            <td class="data">Búsqueda:</td>
            <td colspan="9" class="data"><?php echo htmlspecialchars($filtro_busqueda); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($filtro_categoria)): ?>
        <tr>
            <td class="data">Categoría:</td>
            <td colspan="9" class="data"><?php echo $filtro_categoria === '' ? 'Sin categoría' : htmlspecialchars($filtro_categoria); ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($filtro_lugar)): ?>
        <tr>
            <td class="data">Ubicación:</td>
            <td colspan="9" class="data"><?php echo $filtro_lugar === '' ? 'Sin ubicación' : htmlspecialchars($filtro_lugar); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($filtro_bajo_stock): ?>
        <tr>
            <td class="data">Estado:</td>
            <td colspan="9" class="data">Solo productos con bajo stock</td>
        </tr>
        <?php endif; ?>
        <tr><td colspan="10"></td></tr>
        <?php endif; ?>
        
        <!-- Encabezados de la tabla -->
        <tr>
            <td class="subheader">Código</td>
            <td class="subheader">Producto</td>
            <td class="subheader">Descripción</td>
            <td class="subheader">Categoría</td>
            <td class="subheader">Ubicación</td>
            <td class="subheader">Stock</td>
            <td class="subheader">Stock Mínimo</td>
            <td class="subheader">Precio Venta</td>
            <td class="subheader">Precio Compra</td>
            <td class="subheader">Fecha de Alta</td>
        </tr>
        
        <!-- Datos de productos -->
        <?php foreach ($productos as $producto): ?>
            <?php
            // Determinar clase de stock
            $stock_class = 'stock-good';
            if ($producto['stock'] <= $producto['stock_minimo']) {
                $stock_class = 'stock-low';
            } elseif ($producto['stock'] <= ($producto['stock_minimo'] * 1.5)) {
                $stock_class = 'stock-medium';
            }
            ?>
            <tr>
                <td class="data"><?php echo htmlspecialchars($producto['codigo']); ?></td>
                <td class="data"><?php echo htmlspecialchars($producto['nombre']); ?></td>
                <td class="data"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></td>
                <td class="data"><?php echo htmlspecialchars($producto['categoria'] ?? 'Sin categoría'); ?></td>
                <td class="data"><?php echo htmlspecialchars($producto['lugar'] ?? 'Sin ubicación'); ?></td>
                <td class="data <?php echo $stock_class; ?>"><?php echo number_format($producto['stock']); ?></td>
                <td class="data"><?php echo number_format($producto['stock_minimo']); ?></td>
                <td class="currency">$ <?php echo formatCurrencyExcel($producto['precio_venta']); ?></td>
                <td class="currency">$ <?php echo formatCurrencyExcel($producto['precio_compra']); ?></td>
                <td class="date"><?php echo date('d/m/Y', strtotime($producto['fecha_creacion'])); ?></td>
            </tr>
        <?php endforeach; ?>
        
        <!-- Totales -->
        <tr><td colspan="10"></td></tr>
        <tr>
            <td colspan="5" class="total">TOTALES</td>
            <td class="total"><?php echo number_format(array_sum(array_column($productos, 'stock'))); ?></td>
            <td class="total">-</td>
            <td class="total currency">$ <?php echo formatCurrencyExcel(array_sum(array_map(function($p) { return $p['stock'] * $p['precio_venta']; }, $productos))); ?></td>
            <td class="total currency">$ <?php echo formatCurrencyExcel(array_sum(array_map(function($p) { return $p['stock'] * $p['precio_compra']; }, $productos))); ?></td>
            <td class="total">-</td>
        </tr>
        
        <!-- Pie del reporte -->
        <tr><td colspan="10"></td></tr>
        <tr>
            <td colspan="10" class="subheader">Reporte generado por InventPro - Sistema de Gestión de Inventario</td>
        </tr>
    </table>
</body>
</html>

