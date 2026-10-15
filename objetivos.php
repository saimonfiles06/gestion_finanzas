<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo '<p>Debes iniciar sesión para consultar tus objetivos.</p>';
    return;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

// Crear el token CSRF si todavía no existe.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tokenCsrf = $_SESSION['csrf_token'];

$mensaje = $_SESSION['objetivos_mensaje'] ?? '';
$error = $_SESSION['objetivos_error'] ?? '';

unset(
    $_SESSION['objetivos_mensaje'],
    $_SESSION['objetivos_error']
);

// Crear el fondo de emergencia si todavía no existe.
$consulta = $conexion->prepare(
    'SELECT id
     FROM objetivos
     WHERE usuario_id = :usuario_id
       AND es_fondo_emergencia = 1
     LIMIT 1'
);

$consulta->execute([
    'usuario_id' => $usuarioId
]);

if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
    $consulta = $conexion->prepare(
        'INSERT INTO objetivos (
            usuario_id,
            nombre,
            cantidad_objetivo,
            fecha_objetivo,
            saldo_actual,
            fecha_saldo,
            es_fondo_emergencia,
            configurado
        ) VALUES (
            :usuario_id,
            :nombre,
            0,
            NULL,
            0,
            :fecha_saldo,
            1,
            0
        )'
    );

    $consulta->execute([
        'usuario_id' => $usuarioId,
        'nombre' => 'Fondo de emergencia',
        'fecha_saldo' => date('Y-m-d')
    ]);
}

// Consultar los objetivos del usuario.
$consulta = $conexion->prepare(
    'SELECT
        id,
        nombre,
        cantidad_objetivo,
        fecha_objetivo,
        saldo_actual,
        fecha_saldo,
        es_fondo_emergencia,
        configurado
     FROM objetivos
     WHERE usuario_id = :usuario_id
     ORDER BY es_fondo_emergencia DESC, fecha_creacion DESC'
);

$consulta->execute([
    'usuario_id' => $usuarioId
]);

$objetivos = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Formatear fechas para mostrarlas en español.
function objetivosFechaVisible(?string $fecha): string
{
    if (!$fecha) {
        return 'Sin configurar';
    }

    $fechaObjeto = DateTime::createFromFormat('!Y-m-d', $fecha);

    return $fechaObjeto
        ? $fechaObjeto->format('d/m/Y')
        : 'Sin configurar';
}

?>

<div class="objetivos-contenedor">

    <header class="objetivos-cabecera">
        <div>
            <h1>Objetivos</h1>
            <p>Planifica tus metas de ahorro y consulta tu progreso.</p>
        </div>
    </header>

    <?php if ($mensaje !== ''): ?>
        <div class="objetivos-alerta objetivos-alerta-exito" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="objetivos-alerta objetivos-alerta-error" role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <div class="objetivos-layout">

        <!-- COLUMNA IZQUIERDA: LISTADO -->
        <section class="objetivos-listado">

            <div class="objetivos-seccion-cabecera">
                <div>
                    <h2>Mis objetivos</h2>
                    <p><?= count($objetivos) ?> objetivos</p>
                </div>
            </div>

            <?php if (empty($objetivos)): ?>
                <div class="objetivos-vacio">
                    <i class="bi bi-bullseye" aria-hidden="true"></i>
                    <p>Aún no tienes objetivos de ahorro.</p>
                </div>
            <?php else: ?>

                <div class="objetivos-lista">

                    <?php foreach ($objetivos as $objetivo): ?>

                        <?php
                        $esFondo = (bool) $objetivo['es_fondo_emergencia'];
                        $estaConfigurado = (bool) $objetivo['configurado'];

                        $cantidadObjetivo = (float) $objetivo['cantidad_objetivo'];
                        $saldoActual = (float) $objetivo['saldo_actual'];

                        $porcentaje = 0;

                        if ($cantidadObjetivo > 0) {
                            $porcentaje = ($saldoActual / $cantidadObjetivo) * 100;
                        }

                        $porcentajeVisual = min(100, max(0, $porcentaje));
                        ?>

                        <article class="objetivo-card <?= $esFondo ? 'objetivo-card-fondo' : '' ?>">

                            <div class="objetivo-card-cabecera">

                                <div class="objetivo-icono <?= $esFondo ? 'objetivo-icono-fondo' : '' ?>">
                                    <i
                                        class="bi <?= $esFondo ? 'bi-shield-check' : 'bi-bullseye' ?>"
                                        aria-hidden="true"></i>
                                </div>

                                <div class="objetivo-card-titulo">
                                    <h3>
                                        <?= htmlspecialchars(
                                            $objetivo['nombre'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h3>

                                    <?php if ($esFondo): ?>
                                        <span class="objetivo-etiqueta">
                                            Fondo protegido
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$esFondo): ?>
                                    <form
                                        method="POST"
                                        action="objetivos_acciones.php"
                                        class="objetivo-form-eliminar"
                                        onsubmit="return confirm('¿Seguro que quieres eliminar este objetivo?');">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($tokenCsrf, ENT_QUOTES, 'UTF-8') ?>">

                                        <input type="hidden" name="accion" value="borrar">

                                        <input type="hidden" name="objetivo_id"
                                            value="<?= (int) $objetivo['id'] ?>">

                                        <button
                                            type="submit"
                                            class="objetivo-btn-eliminar"
                                            aria-label="Eliminar objetivo"
                                            title="Eliminar objetivo">
                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>

                            <?php if ($esFondo && !$estaConfigurado): ?>

                                <div class="objetivo-configuracion-inicial">
                                    <p>
                                        Configura tu fondo de emergencia indicando
                                        cuánto quieres ahorrar y para cuándo.
                                    </p>

                                    <form
                                        method="POST"
                                        action="objetivos_acciones.php"
                                        class="objetivo-formulario objetivo-formulario-fondo">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($tokenCsrf, ENT_QUOTES, 'UTF-8') ?>">

                                        <input type="hidden" name="accion" value="configurar_fondo">

                                        <input type="hidden" name="objetivo_id"
                                            value="<?= (int) $objetivo['id'] ?>">

                                        <div class="objetivo-campo">
                                            <label for="fondo-cantidad">
                                                Cantidad objetivo (€)
                                            </label>
                                            <input
                                                type="number"
                                                id="fondo-cantidad"
                                                name="cantidad_objetivo"
                                                min="0.01"
                                                max="9999999999.99"
                                                step="0.01"
                                                placeholder="0,00"
                                                required>
                                        </div>

                                        <div class="objetivo-campo">
                                            <label for="fondo-fecha">
                                                Fecha objetivo
                                            </label>
                                            <input
                                                type="date"
                                                id="fondo-fecha"
                                                name="fecha_objetivo"
                                                required>
                                        </div>

                                        <button type="submit" class="objetivo-btn-principal">
                                            <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                            Configurar fondo
                                        </button>

                                    </form>
                                </div>

                            <?php else: ?>

                                <div class="objetivo-fecha">
                                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                    <span>
                                        Fecha objetivo:
                                        <strong>
                                            <?= htmlspecialchars(
                                                objetivosFechaVisible($objetivo['fecha_objetivo']),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>
                                    </span>
                                </div>

                                <div class="objetivo-progreso-cabecera">
                                    <span>Progreso de ahorro</span>
                                    <strong>
                                        <?= number_format($porcentaje, 1, ',', '.') ?> %
                                    </strong>
                                </div>

                                <div
                                    class="objetivo-barra-fondo"
                                    role="progressbar"
                                    aria-label="Progreso de <?= htmlspecialchars($objetivo['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="<?= (int) round($porcentajeVisual) ?>">

                                    <div
                                        class="objetivo-barra"
                                        style="width: <?= $porcentajeVisual ?>%">
                                    </div>
                                </div>

                                <div class="objetivo-saldos">
                                    <div>
                                        <span>Ahorrado</span>
                                        <strong>
                                            <?= number_format($saldoActual, 2, ',', '.') ?> €
                                        </strong>
                                    </div>

                                    <div class="objetivo-saldo-meta">
                                        <span>Objetivo</span>
                                        <strong>
                                            <?= number_format($cantidadObjetivo, 2, ',', '.') ?> €
                                        </strong>
                                    </div>
                                </div>

                                <div class="objetivo-fecha-saldo">
                                    Saldo actualizado a:
                                    <?= htmlspecialchars(
                                        objetivosFechaVisible($objetivo['fecha_saldo']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </div>

                                <details class="objetivo-gestion">

                                    <summary class="objetivo-btn-gestionar">
                                        <i class="bi bi-sliders" aria-hidden="true"></i>
                                        Gestionar objetivo
                                        <i class="bi bi-chevron-down objetivo-chevron" aria-hidden="true"></i>
                                    </summary>

                                    <form
                                        method="POST"
                                        action="objetivos_acciones.php"
                                        class="objetivo-formulario objetivo-formulario-edicion">

                                        <input type="hidden" name="csrf_token"
                                            value="<?= htmlspecialchars($tokenCsrf, ENT_QUOTES, 'UTF-8') ?>">

                                        <input type="hidden" name="accion" value="editar">

                                        <input type="hidden" name="objetivo_id"
                                            value="<?= (int) $objetivo['id'] ?>">

                                        <?php if (!$esFondo): ?>
                                            <div class="objetivo-campo">
                                                <label for="objetivo-nombre-<?= (int) $objetivo['id'] ?>">
                                                    Nombre
                                                </label>
                                                <input
                                                    type="text"
                                                    id="objetivo-nombre-<?= (int) $objetivo['id'] ?>"
                                                    name="nombre"
                                                    maxlength="100"
                                                    value="<?= htmlspecialchars($objetivo['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                                    required>
                                            </div>
                                        <?php else: ?>
                                            <div class="objetivo-campo">
                                                <label>Nombre</label>
                                                <input
                                                    type="text"
                                                    value="Fondo de emergencia"
                                                    disabled>
                                            </div>
                                        <?php endif; ?>

                                        <div class="objetivo-campo">
                                            <label for="objetivo-cantidad-<?= (int) $objetivo['id'] ?>">
                                                Cantidad objetivo (€)
                                            </label>
                                            <input
                                                type="number"
                                                id="objetivo-cantidad-<?= (int) $objetivo['id'] ?>"
                                                name="cantidad_objetivo"
                                                min="0.01"
                                                max="9999999999.99"
                                                step="0.01"
                                                value="<?= htmlspecialchars((string) $objetivo['cantidad_objetivo'], ENT_QUOTES, 'UTF-8') ?>"
                                                required>
                                        </div>

                                        <div class="objetivo-campo">
                                            <label for="objetivo-fecha-<?= (int) $objetivo['id'] ?>">
                                                Fecha objetivo
                                            </label>
                                            <input
                                                type="date"
                                                id="objetivo-fecha-<?= (int) $objetivo['id'] ?>"
                                                name="fecha_objetivo"
                                                value="<?= htmlspecialchars($objetivo['fecha_objetivo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                required>
                                        </div>

                                        <p class="objetivo-nota-saldo">
                                            El saldo se actualiza desde la sección Resumen.
                                        </p>

                                        <button type="submit" class="objetivo-btn-principal">
                                            <i class="bi bi-save" aria-hidden="true"></i>
                                            Guardar cambios
                                        </button>

                                    </form>
                                </details>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <!-- COLUMNA DERECHA: CREAR OBJETIVO -->
        <aside class="objetivos-columna-formulario">

            <section class="objetivos-panel-formulario">

                <div class="objetivos-seccion-cabecera">
                    <div class="objetivos-icono-nuevo">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </div>

                    <div>
                        <h2>Nuevo objetivo</h2>
                        <p>Define tu próxima meta de ahorro.</p>
                    </div>
                </div>

                <form
                    method="POST"
                    action="objetivos_acciones.php"
                    class="objetivo-formulario">

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($tokenCsrf, ENT_QUOTES, 'UTF-8') ?>">

                    <input type="hidden" name="accion" value="crear">

                    <div class="objetivo-campo">
                        <label for="nuevo-objetivo-nombre">Nombre</label>
                        <input
                            type="text"
                            id="nuevo-objetivo-nombre"
                            name="nombre"
                            maxlength="100"
                            placeholder="Ej. Viaje, coche, estudios..."
                            autocomplete="off"
                            required>
                    </div>

                    <div class="objetivo-campo">
                        <label for="nuevo-objetivo-cantidad">
                            Cantidad objetivo (€)
                        </label>
                        <input
                            type="number"
                            id="nuevo-objetivo-cantidad"
                            name="cantidad_objetivo"
                            min="0.01"
                            max="9999999999.99"
                            step="0.01"
                            placeholder="0,00"
                            required>
                    </div>

                    <div class="objetivo-campo">
                        <label for="nuevo-objetivo-fecha">
                            Fecha objetivo
                        </label>
                        <input
                            type="date"
                            id="nuevo-objetivo-fecha"
                            name="fecha_objetivo"
                            required>
                    </div>

                    <div class="objetivo-formulario-grid">

                        <div class="objetivo-campo">
                            <label for="nuevo-objetivo-saldo">
                                Saldo inicial (€)
                            </label>
                            <input
                                type="number"
                                id="nuevo-objetivo-saldo"
                                name="saldo_actual"
                                min="0"
                                max="9999999999.99"
                                step="0.01"
                                value="0.00"
                                required>
                        </div>

                        <div class="objetivo-campo">
                            <label for="nuevo-objetivo-fecha-saldo">
                                Fecha del saldo
                            </label>
                            <input
                                type="date"
                                id="nuevo-objetivo-fecha-saldo"
                                name="fecha_saldo"
                                value="<?= date('Y-m-d') ?>"
                                required>
                        </div>

                    </div>

                    <p class="objetivo-nota-saldo">
                        El saldo inicial será el punto de partida.
                        Podrás actualizarlo desde Resumen.
                    </p>

                    <button type="submit" class="objetivo-btn-principal">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i>
                        Crear objetivo
                    </button>

                </form>

            </section>

        </aside>

    </div>

</div>