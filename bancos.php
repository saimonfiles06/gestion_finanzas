<?php
$mensajeBancos = [
    'creado' => 'El banco se ha creado correctamente.',
    'uso_actualizado' => 'La configuración del banco se ha actualizado.',
    'traspaso_realizado' => 'El traspaso se ha realizado correctamente.',
    'saldo_ajustado' => 'El saldo se ha ajustado correctamente.',
    'datos_invalidos' => 'Revisa los datos introducidos.',
    'banco_no_valido' => 'No se ha encontrado una de las cuentas.',
    'saldo_insuficiente' => 'La cuenta de origen no tiene saldo suficiente.',
    'permisos_incompatibles' => 'Revisa los permisos de ingresos y gastos de las cuentas.',
    'error' => 'Ha ocurrido un error. Inténtalo de nuevo.',
    'eliminado' => 'El banco se ha eliminado de la lista. Su historial se ha conservado.',
    'saldo_no_cero' => 'No puedes eliminar un banco que tenga saldo distinto de 0,00 €.',
];

$resultado = $_GET['resultado'] ?? '';
$mensaje = $mensajeBancos[$resultado] ?? '';

$erroresBancos = [
    'datos_invalidos',
    'banco_no_valido',
    'saldo_insuficiente',
    'permisos_incompatibles',
    'error',
    'saldo_no_cero'
];

$tipoMensaje = in_array($resultado, $erroresBancos, true)
    ? 'error'
    : 'exito';

$errorListado = false;
$bancos = [];
$saldoTotalBancos = 0;

try {
    $consulta = $conexion->prepare(
        'SELECT
            b.id,
            b.nombre,
            b.saldo_inicial,
            b.fecha_inicial,
            b.permite_ingresos,
            b.permite_gastos,
            b.saldo_inicial + COALESCE(SUM(m.importe), 0)
                AS saldo_actual
        FROM bancos b
        LEFT JOIN movimientos_bancos m
            ON m.banco_id = b.id
        WHERE b.usuario_id = ?
          AND b.eliminado = 0
        GROUP BY
            b.id,
            b.nombre,
            b.saldo_inicial,
            b.fecha_inicial,
            b.permite_ingresos,
            b.permite_gastos
        ORDER BY b.nombre ASC'
    );

    $consulta->execute([$_SESSION['usuario_id']]);
    $bancos = $consulta->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bancos as $banco) {
        $saldoTotalBancos += (float) $banco['saldo_actual'];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $errorListado = true;
}

function escaparBanco($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function euros($importe): string
{
    return number_format((float) $importe, 2, ',', '.') . ' €';
}
?>

<div class="bancos-pagina">
    <header class="bancos-cabecera">
        <h1>Bancos</h1>
        <p>Gestiona tus cuentas y sus saldos.</p>
    </header>

    <?php if ($mensaje !== ''): ?>
        <div
            class="mensaje-bancos mensaje-bancos--<?= escaparBanco($tipoMensaje) ?>"
            role="<?= $tipoMensaje === 'error' ? 'alert' : 'status' ?>">
            <i
                class="bi <?= $tipoMensaje === 'error'
                                ? 'bi-exclamation-circle'
                                : 'bi-check-circle' ?>"
                aria-hidden="true"></i>

            <span><?= escaparBanco($mensaje) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorListado): ?>
        <div class="mensaje-bancos mensaje-bancos--error" role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span>No se ha podido cargar el listado de bancos.</span>
        </div>
    <?php endif; ?>

    <div class="bancos-layout">

        <!-- COLUMNA 1: LISTADO DE BANCOS -->
        <section class="bancos-panel bancos-listado">
            <div class="bancos-panel-cabecera">
                <h2>Mis bancos</h2>

                <span class="bancos-total">
                    <?= euros($saldoTotalBancos) ?>
                </span>
            </div>

            <?php if (empty($bancos)): ?>
                <p class="bancos-vacio">
                    Todavía no tienes bancos registrados.
                </p>
            <?php else: ?>
                <div class="bancos-lista">
                    <?php foreach ($bancos as $banco): ?>
                        <article class="banco-card">
                            <div class="banco-card-cabecera">
                                <div class="banco-identidad">
                                    <span class="banco-icono" aria-hidden="true">
                                        <i class="bi bi-bank"></i>
                                    </span>

                                    <h3>
                                        <?= escaparBanco($banco['nombre']) ?>
                                    </h3>
                                </div>

                                <div class="banco-saldo-actual">
                                    <span>Saldo actual</span>
                                    <strong>
                                        <?= euros($banco['saldo_actual']) ?>
                                    </strong>
                                </div>
                            </div>
                            <form
                                action="bancos_acciones.php"
                                method="post"
                                class="banco-form-eliminar"
                                onsubmit="return confirm('¿Estás seguro de que deseas eliminar el banco «<?= escaparBanco($banco['nombre']) ?>»? Esta acción no se puede deshacer.');">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escaparBanco($_SESSION['csrf_token']) ?>">

                                <input type="hidden" name="accion" value="eliminar">

                                <input
                                    type="hidden"
                                    name="banco_id"
                                    value="<?= (int) $banco['id'] ?>">

                                <button
                                    type="submit"
                                    class="boton-eliminar-banco"
                                    aria-label="Eliminar banco <?= escaparBanco($banco['nombre']) ?>"
                                    title="Eliminar banco">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>

                            <div class="banco-datos-iniciales">
                                <div>
                                    <span>Saldo inicial</span>
                                    <strong>
                                        <?= euros($banco['saldo_inicial']) ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>Fecha inicial</span>
                                    <strong>
                                        <?= date(
                                            'd/m/Y',
                                            strtotime($banco['fecha_inicial'])
                                        ) ?>
                                    </strong>
                                </div>
                            </div>

                            <form
                                action="bancos_acciones.php"
                                method="post"
                                class="banco-permisos">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= escaparBanco($_SESSION['csrf_token']) ?>">
                                <input
                                    type="hidden"
                                    name="accion"
                                    value="actualizar_uso">
                                <input
                                    type="hidden"
                                    name="banco_id"
                                    value="<?= (int) $banco['id'] ?>">

                                <label class="banco-checkbox">
                                    <input
                                        type="hidden"
                                        name="permite_ingresos"
                                        value="0">
                                    <input
                                        type="checkbox"
                                        name="permite_ingresos"
                                        value="1"
                                        <?= $banco['permite_ingresos'] ? 'checked' : '' ?>
                                        onchange="this.form.requestSubmit()">
                                    <span>Ingresos</span>
                                </label>

                                <label class="banco-checkbox">
                                    <input
                                        type="hidden"
                                        name="permite_gastos"
                                        value="0">
                                    <input
                                        type="checkbox"
                                        name="permite_gastos"
                                        value="1"
                                        <?= $banco['permite_gastos'] ? 'checked' : '' ?>
                                        onchange="this.form.requestSubmit()">
                                    <span>Gastos</span>
                                </label>
                            </form>

                            <details class="banco-ajuste">
                                <summary>
                                    <i class="bi bi-sliders" aria-hidden="true"></i>
                                    Ajustar saldo
                                </summary>

                                <form
                                    action="bancos_acciones.php"
                                    method="post"
                                    class="banco-form-ajuste">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escaparBanco($_SESSION['csrf_token']) ?>">
                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="ajustar_saldo">
                                    <input
                                        type="hidden"
                                        name="banco_id"
                                        value="<?= (int) $banco['id'] ?>">

                                    <label>
                                        Saldo real (€)
                                        <input
                                            type="number"
                                            name="saldo_real"
                                            step="0.01"
                                            required>
                                    </label>

                                    <label>
                                        Fecha del ajuste
                                        <input
                                            type="date"
                                            name="fecha"
                                            value="<?= date('Y-m-d') ?>"
                                            required>
                                    </label>

                                    <label>
                                        Motivo (opcional)
                                        <input
                                            type="text"
                                            name="motivo"
                                            maxlength="255"
                                            placeholder="Motivo del ajuste">
                                    </label>

                                    <button type="submit" class="boton">
                                        Guardar ajuste
                                    </button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- COLUMNA 2: NUEVO BANCO -->
        <section class="bancos-panel">
            <div class="bancos-panel-cabecera">
                <h2>Nuevo banco</h2>
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
            </div>

            <form
                action="bancos_acciones.php"
                method="post"
                class="bancos-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparBanco($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" value="crear">

                <label>
                    Nombre del banco
                    <input
                        type="text"
                        name="nombre"
                        maxlength="100"
                        placeholder="Ej. Cuenta principal"
                        required>
                </label>

                <label>
                    Saldo inicial (€)
                    <input
                        type="number"
                        name="saldo_inicial"
                        step="0.01"
                        required
                        placeholder="0.00">
                </label>

                <label>
                    Fecha inicial
                    <input
                        type="date"
                        name="fecha_inicial"
                        value="<?= date('Y-m-d') ?>"
                        required>
                </label>

                <button type="submit" class="boton">
                    Crear banco
                </button>
            </form>
        </section>

        <!-- COLUMNA 3: TRASPASO -->
        <section class="bancos-panel">
            <div class="bancos-panel-cabecera">
                <h2>Traspaso entre bancos</h2>
                <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
            </div>

            <form
                action="bancos_acciones.php"
                method="post"
                class="bancos-form">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparBanco($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" value="traspasar">

                <label>
                    Cuenta de origen
                    <select name="origen_id" required>
                        <option value="">Selecciona una cuenta</option>

                        <?php foreach ($bancos as $banco): ?>
                            <option value="<?= (int) $banco['id'] ?>">
                                <?= escaparBanco($banco['nombre']) ?>
                                — <?= euros($banco['saldo_actual']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Cuenta de destino
                    <select name="destino_id" required>
                        <option value="">Selecciona una cuenta</option>

                        <?php foreach ($bancos as $banco): ?>
                            <option value="<?= (int) $banco['id'] ?>">
                                <?= escaparBanco($banco['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    Importe (€)
                    <input
                        type="number"
                        name="importe"
                        min="0.01"
                        step="0.01"
                        required
                        placeholder="0.00">
                </label>

                <label>
                    Fecha del traspaso
                    <input
                        type="date"
                        name="fecha"
                        value="<?= date('Y-m-d') ?>"
                        required>
                </label>

                <button
                    type="submit"
                    class="boton"
                    <?= count($bancos) < 2 ? 'disabled' : '' ?>>
                    Realizar traspaso
                </button>

                <?php if (count($bancos) < 2): ?>
                    <p class="bancos-ayuda">
                        Necesitas al menos dos bancos para realizar un traspaso.
                    </p>
                <?php endif; ?>
            </form>
        </section>

    </div>
</div>