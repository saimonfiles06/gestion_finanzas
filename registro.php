
<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'includes/conexion.php';

$error = '';
$email = '';
$registroExitoso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repetirPassword = $_POST['repetir_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Introduce un correo electrónico válido.';
    } elseif (
        strlen($password) < 8 ||
        strlen($password) > 4096
    ) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $repetirPassword) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $consulta = $conexion->prepare(
                'SELECT id FROM usuarios WHERE email = ?'
            );
            $consulta->execute([$email]);

            if ($consulta->fetch()) {
                $error = 'No se ha podido completar el registro. Comprueba el correo electrónico.';
            } else {
                $hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $consulta = $conexion->prepare(
                    'INSERT INTO usuarios (email, password)
                     VALUES (?, ?)'
                );
                $consulta->execute([$email, $hash]);

                $registroExitoso = true;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'No se ha podido completar el registro. Comprueba el correo electrónico.';
            } else {
                error_log($e->getMessage());
                $error = 'Ha ocurrido un error. Inténtalo de nuevo.';
            }
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

    <title>Crear cuenta | Gestión Finanzas</title>

    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/validaciones.js" defer></script>
</head>
<body>
    <main class="auth-container">
        <section class="auth-card">
            <h1>Crea tu cuenta</h1>

            <p class="subtitle" <?= $registroExitoso ? 'role="status" aria-live="polite"' : '' ?>>
                <?php if ($registroExitoso): ?>
                    Registro exitoso, redirigiendo a inicio...
                <?php else: ?>
                    Empieza a gestionar tus finanzas personales.
                <?php endif; ?>
            </p>

            <?php if ($error !== ''): ?>
                <p class="mensaje error" role="alert">
                    <?= escapar($error) ?>
                </p>
            <?php endif; ?>

            <?php if (!$registroExitoso): ?>
                <form
                    id="form-registro"
                    method="POST"
                    action="registro.php"
                >
                    <label for="email">Correo electrónico</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= escapar($email) ?>"
                        placeholder="correo@dominio.com"
                        autocomplete="email"
                        maxlength="255"
                        required
                    >

                    <label for="password">Contraseña</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Mínimo 8 caracteres"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="4096"
                        required
                    >

                    <label for="repetir_password">
                        Repite la contraseña
                    </label>
                    <input
                        type="password"
                        id="repetir_password"
                        name="repetir_password"
                        placeholder="Vuelve a introducir la contraseña"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="4096"
                        required
                    >

                    <p
                        id="error-password"
                        class="ayuda"
                        aria-live="polite"
                    ></p>

                    <button type="submit" class="boton">
                        Crear cuenta
                    </button>
                </form>

                <p class="enlace-secundario">
                    ¿Ya tienes una cuenta?
                    <a href="login.php">Inicia sesión</a>
                </p>
            <?php endif; ?>
        </section>
    </main>

    <?php if ($registroExitoso): ?>
        <script>
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 2000);
        </script>
    <?php endif; ?>
</body>
</html>
