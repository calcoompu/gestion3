<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'sistemasia_inventpro');
define('DB_PASS', 'Santiago2980%%');
define('DB_NAME', 'sistemasia_inventpro');

// Configuración del sistema
define('SISTEMA_NOMBRE', 'Gestion Administrativa');
define('SISTEMA_VERSION', '1.0.0');

// Rutas del sistema
define('UPLOADS_PATH', __DIR__ . '/../assets/uploads');

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Función para conectar a la base de datos
function conectarDB() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para iniciar sesión
function iniciarSesionSegura() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Función para formatear moneda
function formatCurrency($amount) {
    return '$ ' . number_format($amount, 2, ',', '.');
}

// Función para verificar login
function requireLogin($redirect = 'login.php') {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: $redirect");
        exit;
    }
}

// Función para verificar permisos
function hasPermission($modulo, $accion) {
    return isset($_SESSION['id_usuario']);
}

// Función para requerir permisos
function requirePermission($modulo, $accion, $redirect = 'menu_principal.php') {
    if (!hasPermission($modulo, $accion)) {
        header("Location: $redirect");
        exit;
    }
}

// Función para obtener estadísticas básicas
function obtenerEstadisticasInventario($pdo) {
    try {
        $stats = array();
        
        // Total productos
        $sql = "SELECT COUNT(*) as total FROM productos WHERE activo = 1";
        $stmt = $pdo->query($sql);
        $stats['total_productos'] = $stmt->fetchColumn();
        
        // Productos con bajo stock
        $sql = "SELECT COUNT(*) as total FROM productos WHERE stock <= stock_minimo AND activo = 1";
        $stmt = $pdo->query($sql);
        $stats['productos_bajo_stock'] = $stmt->fetchColumn();
        
        // Valor total del inventario
        $sql = "SELECT SUM(precio_venta * stock) as total FROM productos WHERE activo = 1";
        $stmt = $pdo->query($sql);
        $stats['valor_total_inventario'] = $stmt->fetchColumn() ?? 0;
        
        // Precio promedio
        $sql = "SELECT AVG(precio_venta) as promedio FROM productos WHERE activo = 1";
        $stmt = $pdo->query($sql);
        $stats['precio_promedio'] = $stmt->fetchColumn() ?? 0;
        
        return $stats;
    } catch (Exception $e) {
        return array(
            'total_productos' => 0,
            'productos_bajo_stock' => 0,
            'valor_total_inventario' => 0,
            'precio_promedio' => 0
        );
    }
}

// Función para generar token CSRF
function generarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para limpiar entrada
function limpiarEntrada($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Función para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para generar código único
function generarCodigoUnico($prefijo = 'PROD') {
    return $prefijo . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

