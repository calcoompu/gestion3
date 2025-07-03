<?php
// En un entorno de producción, asegúrate que display_errors esté Off en php.ini
// y que los errores se logueen a un archivo.
// Para desarrollo, puedes descomentar las siguientes líneas:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once '../../config/config.php'; // Asegúrate que esta ruta sea correcta

// Establecer la codificación interna a UTF-8 para funciones de string
mb_internal_encoding('UTF-8');

// Iniciar sesión y requerir login
iniciarSesionSegura();
requireLogin('../../login.php'); // Asegúrate que esta función exista y funcione como esperas

// Configurar headers para respuesta JSON
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Fecha en el pasado

try {
    // Verificar que sea una petición POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        throw new Exception('Método no permitido. Se esperaba POST.');
    }

    // Obtener datos JSON del cuerpo de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Verificar que se recibieron datos válidos y el JSON es correcto
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al decodificar JSON en gestionar_cliente_papelera.php: " . json_last_error_msg() . " | Input: " . $input);
        throw new Exception('Datos de solicitud no válidos o malformados. Error JSON: ' . json_last_error_msg());
    }
    
    // Verificar parámetros requeridos
    if (empty($data['accion']) || empty($data['id'])) {
        throw new Exception('Parámetros faltantes: "accion" e "id" son requeridos.');
    }

    $accion = trim($data['accion']);
    $cliente_id = intval($data['id']);
    // Usar el nombre de usuario de la sesión para auditoría
    $usuario_nombre_sesion = $_SESSION['nombre_usuario'] ?? 'Sistema'; 

    // Validar ID del cliente
    if ($cliente_id <= 0) {
        throw new Exception('ID de cliente no válido: ' . $cliente_id);
    }

    $pdo = conectarDB(); // Asegúrate que esta función devuelva un objeto PDO válido
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Para que PDO lance excepciones

    // Verificar que el cliente existe y obtener su estado actual
    $stmt_check = $pdo->prepare("SELECT id, nombre, apellido, activo, eliminado FROM clientes WHERE id = :id");
    $stmt_check->bindParam(':id', $cliente_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $cliente = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        http_response_code(404); // Not Found
        throw new Exception('Cliente no encontrado con ID: ' . $cliente_id);
    }

    $response = ['success' => false, 'message' => 'Acción no completada.'];
    $nombre_completo_cliente = htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido'], ENT_QUOTES, 'UTF-8');

    switch ($accion) {
        case 'inactivar':
            if ($cliente['activo'] == 0 && $cliente['eliminado'] == 0) {
                // Ya está inactivo pero no eliminado. Considerar éxito para evitar error si se re-intenta.
                $response['success'] = true;
                $response['message'] = 'El cliente "' . $nombre_completo_cliente . '" ya se encontraba inactivo.';
            } elseif ($cliente['eliminado'] == 1) {
                throw new Exception('El cliente "' . $nombre_completo_cliente . '" está en la papelera y no puede ser inactivado. Restáurelo primero.');
            } elseif ($cliente['activo'] == 1) { // Solo inactivar si está activo
                $stmt_update = $pdo->prepare("UPDATE clientes SET activo = 0, fecha_modificacion = NOW() WHERE id = :id");
                $stmt_update->bindParam(':id', $cliente_id, PDO::PARAM_INT);
                $result = $stmt_update->execute();

                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Cliente "' . $nombre_completo_cliente . '" inactivado correctamente.';
                } else {
                    throw new Exception('Error al inactivar el cliente "' . $nombre_completo_cliente . '" en la base de datos.');
                }
            } else {
                 // Otro caso no manejado (ej. activo=0, eliminado=1 ya cubierto arriba)
                throw new Exception('Estado inesperado del cliente para la acción de inactivar.');
            }
            break;

        case 'reactivar':
            if ($cliente['activo'] == 1 && $cliente['eliminado'] == 0) {
                $response['success'] = true;
                $response['message'] = 'El cliente "' . $nombre_completo_cliente . '" ya se encontraba activo.';
            } else {
                $stmt_update = $pdo->prepare("UPDATE clientes SET activo = 1, eliminado = 0, fecha_modificacion = NOW(), fecha_eliminacion = NULL, eliminado_por = NULL WHERE id = :id");
                $stmt_update->bindParam(':id', $cliente_id, PDO::PARAM_INT);
                $result = $stmt_update->execute();

                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Cliente "' . $nombre_completo_cliente . '" reactivado correctamente.';
                } else {
                    throw new Exception('Error al reactivar el cliente "' . $nombre_completo_cliente . '" en la base de datos.');
                }
            }
            break;

        case 'eliminar_suave': // Enviar a papelera
            if ($cliente['eliminado'] == 1) {
                $response['success'] = true;
                $response['message'] = 'El cliente "' . $nombre_completo_cliente . '" ya se encontraba en la papelera.';
            } else {
                // Aquí podrías añadir la lógica de `verificar_cliente_pendientes.php` si quieres una doble verificación en backend.
                // Por ahora, asumimos que el frontend hizo la verificación.
                
                $stmt_update = $pdo->prepare("UPDATE clientes SET activo = 0, eliminado = 1, fecha_modificacion = NOW(), fecha_eliminacion = NOW(), eliminado_por = :usuario_nombre WHERE id = :id");
                $stmt_update->bindParam(':usuario_nombre', $usuario_nombre_sesion, PDO::PARAM_STR);
                $stmt_update->bindParam(':id', $cliente_id, PDO::PARAM_INT);
                $result = $stmt_update->execute();

                if ($result) {
                    $response['success'] = true;
                    $response['message'] = 'Cliente "' . $nombre_completo_cliente . '" enviado a la papelera.';
                } else {
                    throw new Exception('Error al enviar el cliente "' . $nombre_completo_cliente . '" a la papelera.');
                }
            }
            break;

        default:
            http_response_code(400); // Bad Request
            throw new Exception('Acción no válida o no reconocida: ' . htmlspecialchars($accion, ENT_QUOTES, 'UTF-8'));
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error PDO en gestionar_cliente_papelera.php: " . $e->getMessage() . " (Código: " . $e->getCode() . ") | Input: " . ($input ?? 'N/A'));
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos. Por favor, contacte al administrador.'
        // 'debug_message' => $e->getMessage() // Solo para desarrollo
    ]);
} catch (Exception $e) {
    $errorCode = $e->getCode();
    if (!is_int($errorCode) || $errorCode < 400 || $errorCode >= 600) {
        $errorCode = 400; // Bad Request por defecto si no es un código HTTP válido
    }
    if ($e->getMessage() === 'Método no permitido. Se esperaba POST.') $errorCode = 405;


    error_log("Error General en gestionar_cliente_papelera.php: " . $e->getMessage() . " | Input: " . ($input ?? 'N/A'));
    http_response_code($errorCode);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit; // Asegurar que no haya más salida
?>