<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

function volverCuenta(string $tipo, string $mensaje): never
{
    $_SESSION[$tipo === 'error' ? 'cuenta_error' : 'cuenta_mensaje'] = $mensaje;
    $seccion = $_POST['volver_seccion'] ?? 'cuenta';
    $seccionesPermitidas = [
        'resumen',
        'ingresos-gastos',
        'registro',
        'categorias',
        'bancos',
        'objetivos',
        'presupuestos',
        'cuenta'
    ];
    if (!is_string($seccion) || !in_array($seccion, $seccionesPermitidas, true)) {
        $seccion = 'cuenta';
    }
    header('Location: dashboard.php?' . http_build_query(['seccion' => $seccion]));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverCuenta('error', 'La solicitud no es válida.');
}

$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';
if (
    !is_string($tokenRecibido)
    || !is_string($tokenSesion)
    || $tokenSesion === ''
    || !hash_equals($tokenSesion, $tokenRecibido)
) {
    volverCuenta(
        'error',
        'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.'
    );
}

$accion = $_POST['accion'] ?? '';
if ($accion === 'modo') {
    $modo = $_POST['preferencia_modo'] ?? '';
    if (!is_string($modo) || !in_array($modo, ['claro', 'oscuro'], true)) {
        volverCuenta('error', 'Selecciona un modo de visualización válido.');
    }

    try {
        $consulta = $conexion->prepare(
            'UPDATE usuarios
             SET preferencia_modo = :modo
             WHERE id = :usuario_id'
        );
        $consulta->execute([
            'modo' => $modo,
            'usuario_id' => $usuarioId
        ]);
        $_SESSION['preferencia_modo'] = $modo;
        volverCuenta('mensaje', 'La preferencia de visualización se ha guardado.');
    } catch (PDOException $e) {
        error_log('Error al guardar el modo de visualización: ' . $e->getMessage());
        volverCuenta('error', 'No se ha podido guardar la preferencia. Inténtalo de nuevo.');
    }
}

if ($accion !== 'password') {
    volverCuenta('error', 'La operación solicitada no es válida.');
}

$passwordActual = $_POST['password_actual'] ?? '';
$passwordNueva = $_POST['password_nueva'] ?? '';
$passwordConfirmacion = $_POST['password_confirmacion'] ?? '';
if (
    !is_string($passwordActual)
    || !is_string($passwordNueva)
    || !is_string($passwordConfirmacion)
    || strlen($passwordActual) > 4096
    || strlen($passwordNueva) < 8
    || strlen($passwordNueva) > 4096
    || $passwordNueva !== $passwordConfirmacion
) {
    volverCuenta(
        'error',
        'Comprueba la contraseña actual y asegúrate de que la nueva tenga al menos 8 caracteres y coincida con su confirmación.'
    );
}

try {
    $conexion->beginTransaction();
    $consulta = $conexion->prepare(
        'SELECT password
         FROM usuarios
         WHERE id = :usuario_id
         FOR UPDATE'
    );
    $consulta->execute(['usuario_id' => $usuarioId]);
    $hashActual = $consulta->fetchColumn();

    if (!is_string($hashActual) || !password_verify($passwordActual, $hashActual)) {
        $conexion->rollBack();
        volverCuenta('error', 'La contraseña actual no es correcta.');
    }

    if (password_verify($passwordNueva, $hashActual)) {
        $conexion->rollBack();
        volverCuenta('error', 'La nueva contraseña debe ser distinta de la actual.');
    }

    $nuevoHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    if (!is_string($nuevoHash)) {
        throw new RuntimeException('No se ha podido proteger la nueva contraseña.');
    }
    $consulta = $conexion->prepare(
        'UPDATE usuarios SET password = :password WHERE id = :usuario_id'
    );
    $consulta->execute([
        'password' => $nuevoHash,
        'usuario_id' => $usuarioId
    ]);
    if ($consulta->rowCount() !== 1) {
        throw new RuntimeException('No se ha podido actualizar la contraseña.');
    }
    $conexion->commit();
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al actualizar la contraseña de usuario: ' . $e->getMessage());
    volverCuenta(
        'error',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se ha podido actualizar la contraseña. Inténtalo de nuevo.'
    );
}

session_unset();
session_destroy();
session_start();
session_regenerate_id(true);
$_SESSION['login_mensaje'] = 'La contraseña se ha actualizado. Inicia sesión con tu nueva contraseña.';
header('Location: login.php');
exit;
