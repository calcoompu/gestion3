<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Verificar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener datos JSON del cuerpo de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Verificar que se recibieron datos válidos
    if (!$data) {
        throw new Exception('Datos no válidos');
    }
    
    // Verificar parámetros requeridos
    if (!isset($data['accion']) || !isset($data['id'])) {
        throw new Exception('Parámetros faltantes: acción e id son requeridos');
    }
    
    $accion = $data['accion'];
    $producto_id = intval($data['id']);
    
    // Validar ID del producto
    if ($producto_id <= 0) {
        throw new Exception('ID de producto no válido');
    }
    
    $pdo = conectarDB();
    
    // Verificar que el producto existe
    $stmt = $pdo->prepare("SELECT id, nombre, activo FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }
    
    $response = ['success' => false, 'message' => ''];
    
    switch ($accion) {
        case 'inactivar':
            // Verificar que el producto esté activo
            if ($producto['activo'] == 0) {
                throw new Exception('El producto ya está inactivo');
            }
            
            // Inactivar producto
            $stmt = $pdo->prepare("UPDATE productos SET activo = 0, fecha_modificacion = NOW() WHERE id = ?");
            $result = $stmt->execute([$producto_id]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Producto "' . $producto['nombre'] . '" inactivado correctamente';
                $response['action'] = 'remove_row';
            } else {
                throw new Exception('Error al inactivar el producto');
            }
            break;
            
        case 'reactivar':
            // Verificar que el producto esté inactivo
            if ($producto['activo'] == 1) {
                throw new Exception('El producto ya está activo');
            }
            
            // Reactivar producto
            $stmt = $pdo->prepare("UPDATE productos SET activo = 1, fecha_modificacion = NOW() WHERE id = ?");
            $result = $stmt->execute([$producto_id]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Producto "' . $producto['nombre'] . '" reactivado correctamente';
                $response['action'] = 'remove_row';
            } else {
                throw new Exception('Error al reactivar el producto');
            }
            break;
            
        case 'eliminar':
            // Eliminar producto definitivamente
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
            $result = $stmt->execute([$producto_id]);
            
            if ($result) {
                $response['success'] = true;
                $response['message'] = 'Producto "' . $producto['nombre'] . '" eliminado definitivamente';
                $response['action'] = 'remove_row';
            } else {
                throw new Exception('Error al eliminar el producto');
            }
            break;
            
        default:
            throw new Exception('Acción no válida: ' . $accion);
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>


