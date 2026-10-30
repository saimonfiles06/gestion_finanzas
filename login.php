<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/conexion.php';

$error = '';
$email = '';
$loginExitoso = false;
$mensajeLogin = $_SESSION['login_mensaje'] ?? '';
unset($_SESSION['login_mensaje']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Introduce un correo electrónico válido.';
    } elseif ($password === '') {
        $error = 'Introduce tu contraseña.';
    } else {
        try {
            $consulta = $conexion->prepare(
                'SELECT id, email, password, preferencia_modo
                 FROM usuarios
                 WHERE email = ?'
            );

            $consulta->execute([$email]);
            $usuario = $consulta->fetch(PDO::FETCH_ASSOC);

            if (
                $usuario &&
                password_verify($password, $usuario['password'])
            ) {
                session_regenerate_id(true);

                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['preferencia_modo'] = $usuario['preferencia_modo'];

                $loginExitoso = true;
            } else {
                $error = 'Correo electrónico o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Ha ocurrido un error. Inténtalo de nuevo.';
        }
    }
}

function escapar(string $valor): string {
    return htmlspecialchars(
        $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión | Gestión Finanzas</title>

    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
    <main class="auth-container">
        <section class="auth-card">
            <h1>Iniciar sesión</h1>

            <p
                class="subtitle"
                <?= $loginExitoso ? 'role="status" aria-live="polite"' : '' ?>
            >
                <?php if ($loginExitoso): ?>
                    Inicio de sesión exitoso, redirigiendo...
                <?php else: ?>
                    Accede a tu cuenta para gestionar tus finanzas.
                <?php endif; ?>
            </p>

            <?php if ($error !== ''): ?>
                <p class="mensaje error" role="alert">
                    <?= escapar($error) ?>
                </p>
            <?php endif; ?>

            <?php if ($mensajeLogin !== ''): ?>
                <p class="mensaje cuenta-login-mensaje" role="status">
                    <?= escapar($mensajeLogin) ?>
                </p>
            <?php endif; ?>

            <?php if (!$loginExitoso): ?>
                <form method="POST" action="login.php">
                    <label for="email">Correo electrónico</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= escapar($email) ?>"
                        placeholder="correo@dominio.com"
                        autocomplete="username"
                        maxlength="255"
                        required
                    >

                    <label for="password">Contraseña</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Introduce tu contraseña"
                        autocomplete="current-password"
                        maxlength="4096"
                        required
                    >

                    <button type="submit" class="boton">
                        Iniciar sesión
                    </button>
                </form>

                <p class="enlace-secundario">
                    ¿Todavía no tienes una cuenta?
                    <a href="registro.php">Regístrate</a>
                </p>
            <?php endif; ?>
        </section>
    </main>

    <?php if ($loginExitoso): ?>
        <script>
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1500);
        </script>
    <?php endif; ?>
</body>
</html>
