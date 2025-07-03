<?php
require_once 'config/config.php';

iniciarSesionSegura();

if (isset($_SESSION['id_usuario'])) {
    header("Location: menu_principal.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        try {
            $pdo = conectarDB();
            
            $sql = "SELECT id, username, password, nombre, email, rol, activo 
                    FROM usuarios 
                    WHERE username = ? AND activo = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();
            
            

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['id_usuario'] = $user['id'];
                $_SESSION['nombre_usuario'] = $user['nombre'];
                $_SESSION['correo_electronico_usuario'] = $user['email'];
                $_SESSION['usuario'] = $user['username'];
                $_SESSION['rol_usuario'] = $user['rol'];
                $_SESSION['hora_inicio_sesion'] = time();
                
                // Actualizar último acceso
                $sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user['id']]);
                
                header("Location: menu_principal.php");
                exit;
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (Exception $e) {
            $error = 'Error de conexión. Intente nuevamente.';
        }
    }
}

$pageTitle = "Iniciar Sesión - " . SISTEMA_NOMBRE;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100% );
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }
        .input-group-password {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card login-card border-0 mx-auto">
                    <div class="login-header">
                        <i class="bi bi-box-seam-fill fs-1 mb-3"></i>
                        <h3 class="mb-0"><?php echo htmlspecialchars(SISTEMA_NOMBRE); ?></h3>
                        <p class="mb-0 opacity-75">Sistema de Gestión</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['expired']) && $_GET['expired'] == '1'): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="bi bi-clock me-2"></i>
                                Su sesión ha expirado. Por favor, inicie sesión nuevamente.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="usuario" class="form-label">
                                    <i class="bi bi-person me-1"></i>Usuario
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="usuario" 
                                       name="usuario" 
                                       value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>"
                                       required 
                                       placeholder="Ingrese su usuario">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock me-1"></i>Contraseña
                                </label>
                                <div class="input-group-password">
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required 
                                           placeholder="Ingrese su contraseña">
                                    <button type="button" 
                                            class="password-toggle" 
                                            id="togglePassword"
                                            title="Mostrar/Ocultar contraseña">
                                        <i class="bi bi-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Iniciar Sesión
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Credenciales por defecto: admin / admin123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword' ).addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
                this.title = 'Ocultar contraseña';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'bi bi-eye';
                this.title = 'Mostrar contraseña';
            }
        });

        document.getElementById('usuario').focus();
    </script>
</body>
</html>
