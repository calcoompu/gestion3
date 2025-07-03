<?php
require_once '../../config/config.php';
iniciarSesionSegura();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$categoria = $data['categoria'] ?? '';
$configuraciones = $data['configuraciones'] ?? [];

try {
    $pdo = conectarDB();
    
    foreach ($configuraciones as $clave => $valor) {
        $stmt = $pdo->prepare("INSERT INTO configuracion (categoria, clave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->execute([$categoria, $clave, $valor, $valor]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Configuración guardada exitosamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>