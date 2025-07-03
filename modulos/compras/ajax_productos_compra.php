<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

$pdo = conectarDB();
$busqueda = $_GET['q'] ?? '';

try {
    $sql = "
        SELECT 
            p.id,
            p.codigo,
            p.nombre,
            p.precio_compra,
            p.stock,
            p.stock_minimo,
            c.nombre as categoria,
            l.nombre as lugar
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN lugares l ON p.lugar_id = l.id
        WHERE p.activo = 1
    ";
    
    $params = [];
    
    if (!empty($busqueda)) {
        $sql .= " AND (p.codigo LIKE ? OR p.nombre LIKE ?)";
        $params[] = '%' . $busqueda . '%';
        $params[] = '%' . $busqueda . '%';
    }
    
    $sql .= " ORDER BY p.nombre LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar información adicional para compras
    foreach ($productos as &$producto) {
        // Obtener último precio de compra
        $stmt = $pdo->prepare("
            SELECT cd.precio_unitario 
            FROM compra_detalles cd 
            INNER JOIN compras c ON cd.compra_id = c.id 
            WHERE cd.producto_id = ? AND c.estado = 'recibida'
            ORDER BY c.fecha_compra DESC 
            LIMIT 1
        ");
        $stmt->execute([$producto['id']]);
        $ultimo_precio = $stmt->fetch();
        
        $producto['ultimo_precio_compra'] = $ultimo_precio ? $ultimo_precio['precio_unitario'] : $producto['precio_compra'];
        $producto['necesita_stock'] = $producto['stock'] <= $producto['stock_minimo'];
    }
    
    echo json_encode($productos);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>