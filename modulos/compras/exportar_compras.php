<?php
require_once '../../config/config.php';
iniciarSesionSegura();
requireLogin('../../login.php');

$formato = $_GET['formato'] ?? 'csv';
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

$pdo = conectarDB();

// Consulta para obtener las compras
$sql = "
    SELECT 
        c.codigo,
        p.razon_social as proveedor,
        c.fecha_compra,
        c.fecha_entrega_estimada,
        c.fecha_entrega_real,
        c.estado,
        c.subtotal,
        c.impuestos,
        c.descuento,
        c.total,
        c.observaciones
    FROM compras c
    LEFT JOIN proveedores p ON c.proveedor_id = p.id
    WHERE c.fecha_compra BETWEEN ? AND ?
    AND c.activo = 1
    ORDER BY c.fecha_compra DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$fecha_desde, $fecha_hasta]);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($formato === 'csv') {
    // Exportar como CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compras_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, [
        'Código',
        'Proveedor',
        'Fecha Compra',
        'Fecha Entrega Estimada',
        'Fecha Entrega Real',
        'Estado',
        'Subtotal',
        'Impuestos',
        'Descuento',
        'Total',
        'Observaciones'
    ]);
    
    // Datos
    foreach ($compras as $compra) {
        fputcsv($output, [
            $compra['codigo'],
            $compra['proveedor'],
            $compra['fecha_compra'],
            $compra['fecha_entrega_estimada'],
            $compra['fecha_entrega_real'],
            ucfirst($compra['estado']),
            $compra['subtotal'],
            $compra['impuestos'],
            $compra['descuento'],
            $compra['total'],
            $compra['observaciones']
        ]);
    }
    
    fclose($output);
    
} else {
    // Exportar como Excel (HTML)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="compras_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Proveedor</th>';
    echo '<th>Fecha Compra</th>';
    echo '<th>Fecha Entrega Estimada</th>';
    echo '<th>Fecha Entrega Real</th>';
    echo '<th>Estado</th>';
    echo '<th>Subtotal</th>';
    echo '<th>Impuestos</th>';
    echo '<th>Descuento</th>';
    echo '<th>Total</th>';
    echo '<th>Observaciones</th>';
    echo '</tr>';
    
    foreach ($compras as $compra) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($compra['codigo']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['proveedor']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['fecha_compra']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['fecha_entrega_estimada']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['fecha_entrega_real']) . '</td>';
        echo '<td>' . htmlspecialchars(ucfirst($compra['estado'])) . '</td>';
        echo '<td>' . htmlspecialchars($compra['subtotal']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['impuestos']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['descuento']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['total']) . '</td>';
        echo '<td>' . htmlspecialchars($compra['observaciones']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
}
?>