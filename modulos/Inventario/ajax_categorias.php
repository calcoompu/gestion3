<?php
require_once '../../config/config.php';

iniciarSesionSegura();
requireLogin('../../login.php');

header('Content-Type: application/json; charset=UTF-8');

try {
    $pdo = conectarDB();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
        
        if ($_POST['accion'] === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            
            if (empty($nombre)) {
                throw new Exception('El nombre es obligatorio');
            }
            
            // Verificar si ya existe
            $sql = "SELECT id FROM categorias WHERE nombre = ? AND activo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre]);
            
            if ($stmt->fetch()) {
                throw new Exception('Ya existe una categoría con ese nombre');
            }
            
            // Crear nueva categoría
            $sql = "INSERT INTO categorias (nombre, activo, fecha_creacion, fecha_modificacion) 
                    VALUES (?, 1, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre]);
            
            $nuevo_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'id' => $nuevo_id,
                'nombre' => $nombre,
                'message' => 'Categoría creada correctamente'
            ]);
            
        } else {
            throw new Exception('Acción no válida');
        }
        
    } else {
        throw new Exception('Método no permitido');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

