<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo '<p>Debes iniciar sesión para consultar tu cuenta.</p>';
    return;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioIdCuenta = (int) $_SESSION['usuario_id'];
$consultaCuenta = $conexion->prepare(
    'SELECT email, preferencia_modo
     FROM usuarios
     WHERE id = :usuario_id'
);
$consultaCuenta->execute([
    'usuario_id' => $usuarioIdCuenta
]);
$datosCuenta = $consultaCuenta->fetch(PDO::FETCH_ASSOC);
if (!$datosCuenta) {
    echo '<p>No se han podido cargar los datos de la cuenta.</p>';
    return;
}

$mensajeCuenta = $_SESSION['cuenta_mensaje'] ?? '';
$errorCuenta = $_SESSION['cuenta_error'] ?? '';
unset($_SESSION['cuenta_mensaje'], $_SESSION['cuenta_error']);

function escaparCuenta($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>

<div class="cuenta-contenedor">
    <header class="cuenta-cabecera">
        <h1>Mi cuenta</h1>
        <p>Consulta tus datos y configura tus preferencias.</p>
    </header>

    <?php if ($mensajeCuenta !== ''): ?>
        <div class="cuenta-alerta cuenta-alerta-exito" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span><?= escaparCuenta($mensajeCuenta) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorCuenta !== ''): ?>
        <div class="cuenta-alerta cuenta-alerta-error" role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span><?= escaparCuenta($errorCuenta) ?></span>
        </div>
    <?php endif; ?>

    <div class="cuenta-layout">
        <section class="cuenta-card" aria-labelledby="cuenta-datos-titulo">
            <div class="cuenta-card-cabecera">
                <span class="cuenta-icono" aria-hidden="true">
                    <i class="bi bi-person"></i>
                </span>
                <div>
                    <h2 id="cuenta-datos-titulo">Datos de usuario</h2>
                    <p>Información de acceso a tu cuenta.</p>
                </div>
            </div>

            <div class="cuenta-email">
                <span>Correo electrónico</span>
                <strong><?= escaparCuenta($datosCuenta['email']) ?></strong>
            </div>
        </section>

        <section class="cuenta-card cuenta-preferencias" aria-labelledby="cuenta-preferencias-titulo">
            <div class="cuenta-card-cabecera">
                <span class="cuenta-icono cuenta-icono-preferencias" aria-hidden="true">
                    <i class="bi bi-sliders"></i>
                </span>
                <div>
                    <h2 id="cuenta-preferencias-titulo">Preferencias</h2>
                    <p>Personaliza la apariencia y seguridad.</p>
                </div>
            </div>

            <form
                id="form-preferencia-modo"
                class="cuenta-formulario"
                method="POST"
                action="cuenta_acciones.php"
            >
                <input type="hidden" name="csrf_token" value="<?= escaparCuenta($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="accion" value="modo">
                <div class="cuenta-campo">
                    <label for="preferencia-modo">Modo de visualización</label>
                    <select id="preferencia-modo" name="preferencia_modo">
                        <option value="claro" <?= $datosCuenta['preferencia_modo'] === 'claro' ? 'selected' : '' ?>>Claro</option>
                        <option value="oscuro" <?= $datosCuenta['preferencia_modo'] === 'oscuro' ? 'selected' : '' ?>>Oscuro</option>
                    </select>
                </div>
                <button type="submit" class="cuenta-btn-secundario">
                    Guardar preferencia
                </button>
            </form>

            <form
                class="cuenta-formulario cuenta-formulario-password"
                method="POST"
                action="cuenta_acciones.php"
                autocomplete="off"
            >
                <input type="hidden" name="csrf_token" value="<?= escaparCuenta($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="accion" value="password">

                <div class="cuenta-campo">
                    <label for="password-actual">Contraseña actual</label>
                    <input
                        type="password"
                        id="password-actual"
                        name="password_actual"
                        autocomplete="current-password"
                        maxlength="4096"
                        required
                    >
                </div>
                <div class="cuenta-campo">
                    <label for="password-nueva">Nueva contraseña</label>
                    <input
                        type="password"
                        id="password-nueva"
                        name="password_nueva"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="4096"
                        required
                    >
                </div>
                <div class="cuenta-campo">
                    <label for="password-confirmacion">Repite la nueva contraseña</label>
                    <input
                        type="password"
                        id="password-confirmacion"
                        name="password_confirmacion"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="4096"
                        required
                    >
                </div>
                <p class="cuenta-ayuda">
                    Al actualizar la contraseña se cerrará tu sesión y tendrás que volver a iniciar sesión.
                </p>
                <button type="submit" class="cuenta-btn-principal">
                    <i class="bi bi-key" aria-hidden="true"></i>
                    Actualizar contraseña
                </button>
            </form>
        </section>
    </div>
</div>
