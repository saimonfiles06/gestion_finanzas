
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo '<p>Debes iniciar sesión para consultar el resumen.</p>';
    return;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

/*
|--------------------------------------------------------------------------
| Periodo seleccionado para los totales del resumen
|--------------------------------------------------------------------------
*/

$rangoResumen = $_GET['rango'] ?? 'mensual';
if (!is_string($rangoResumen) || !in_array($rangoResumen, ['mensual', 'anual'], true)) {
    $rangoResumen = 'mensual';
}

$consultaPeriodosConRegistros = $conexion->prepare(
    'SELECT DISTINCT DATE_FORMAT(actividad.fecha, "%Y-%m") AS periodo
     FROM (
         SELECT fecha
         FROM registros
         WHERE usuario_id = :usuario_registros

         UNION ALL

         SELECT fecha
         FROM aportaciones_objetivos
         WHERE usuario_id = :usuario_aportaciones

         UNION ALL

         SELECT fecha
         FROM inversiones
         WHERE usuario_id = :usuario_inversiones

         UNION ALL

         SELECT mb.fecha
         FROM movimientos_bancos mb
         INNER JOIN bancos b ON b.id = mb.banco_id
         WHERE b.usuario_id = :usuario_movimientos
     ) actividad
     ORDER BY periodo DESC'
);
$consultaPeriodosConRegistros->execute([
    'usuario_registros' => $usuarioId,
    'usuario_aportaciones' => $usuarioId,
    'usuario_inversiones' => $usuarioId,
    'usuario_movimientos' => $usuarioId
]);
$periodosMensualesResumen = $consultaPeriodosConRegistros->fetchAll(PDO::FETCH_COLUMN);
$aniosConRegistrosResumen = array_values(array_unique(array_map(
    static fn(string $periodo): string => substr($periodo, 0, 4),
    $periodosMensualesResumen
)));

$anioActualResumen = (int) date('Y');
$periodoResumen = $_GET['periodo'] ?? '';

if ($rangoResumen === 'anual') {
    $anioSolicitado = is_string($periodoResumen)
        ? $periodoResumen
        : '';

    if (!in_array($anioSolicitado, $aniosConRegistrosResumen, true)) {
        $anioActualTexto = (string) $anioActualResumen;
        $periodoResumen = in_array(
            $anioActualTexto,
            $aniosConRegistrosResumen,
            true
        )
            ? $anioActualTexto
            : ($aniosConRegistrosResumen[0] ?? $anioActualTexto);
    } else {
        $periodoResumen = $anioSolicitado;
    }

    $fechaInicioResumen = $periodoResumen . '-01-01';
    $fechaFinResumen = ((int) $periodoResumen + 1) . '-01-01';
    $etiquetaPeriodoResumen = $periodoResumen;
} else {
    $periodoSolicitado = is_string($periodoResumen)
        ? $periodoResumen
        : '';

    if (!in_array($periodoSolicitado, $periodosMensualesResumen, true)) {
        $periodoActual = date('Y-m');
        $periodoResumen = in_array(
            $periodoActual,
            $periodosMensualesResumen,
            true
        )
            ? $periodoActual
            : ($periodosMensualesResumen[0] ?? $periodoActual);
    } else {
        $periodoResumen = $periodoSolicitado;
    }

    $fechaInicioResumen = $periodoResumen . '-01';
    $fechaFinResumen = (new DateTimeImmutable($fechaInicioResumen))
        ->modify('first day of next month')
        ->format('Y-m-d');

    $nombresMesesResumen = [
        1 => 'enero',
        2 => 'febrero',
        3 => 'marzo',
        4 => 'abril',
        5 => 'mayo',
        6 => 'junio',
        7 => 'julio',
        8 => 'agosto',
        9 => 'septiembre',
        10 => 'octubre',
        11 => 'noviembre',
        12 => 'diciembre'
    ];
    $partesPeriodo = explode('-', $periodoResumen);
    $etiquetaPeriodoResumen = ucfirst(
        $nombresMesesResumen[(int) $partesPeriodo[1]]
    ) . ' de ' . $partesPeriodo[0];
}

$periodosMensualesJson = json_encode(
    $periodosMensualesResumen,
    JSON_THROW_ON_ERROR
);
$aniosConRegistrosJson = json_encode(
    $aniosConRegistrosResumen,
    JSON_THROW_ON_ERROR
);

$consultaTotalesOperaciones = $conexion->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN tipo = "ingreso" THEN importe ELSE 0 END), 0)
            AS ingresos,
        COALESCE(SUM(CASE WHEN tipo = "gasto" THEN importe ELSE 0 END), 0)
            AS gastos
     FROM registros
     WHERE usuario_id = :usuario_id
       AND fecha >= :fecha_inicio
       AND fecha < :fecha_fin'
);
$consultaTotalesOperaciones->execute([
    'usuario_id' => $usuarioId,
    'fecha_inicio' => $fechaInicioResumen,
    'fecha_fin' => $fechaFinResumen
]);
$totalesOperacionesResumen = $consultaTotalesOperaciones->fetch(PDO::FETCH_ASSOC);
$ingresosPeriodoResumen = (float) $totalesOperacionesResumen['ingresos'];
$gastosPeriodoResumen = (float) $totalesOperacionesResumen['gastos'];
$balancePeriodoResumen = $ingresosPeriodoResumen - $gastosPeriodoResumen;

$consultaCategoriasPeriodo = $conexion->prepare(
    'SELECT c.nombre, SUM(r.importe) AS importe
     FROM registros r
     INNER JOIN categorias c ON c.id = r.categoria_id
     WHERE r.usuario_id = :usuario_id
       AND r.tipo = :tipo
       AND r.fecha >= :fecha_inicio
       AND r.fecha < :fecha_fin
     GROUP BY c.id, c.nombre
     ORDER BY importe DESC, c.nombre ASC'
);

$categoriasIngresosResumen = [];
$categoriasGastosResumen = [];
$coloresPorTipoResumen = [
    'ingreso' => ['#16a34a', '#0891b2', '#2563eb', '#7c3aed', '#0f766e', '#65a30d'],
    'gasto' => ['#dc2626', '#ea580c', '#d97706', '#db2777', '#9333ea', '#b45309']
];

foreach (['ingreso', 'gasto'] as $tipoCategoriaResumen) {
    $consultaCategoriasPeriodo->execute([
        'usuario_id' => $usuarioId,
        'tipo' => $tipoCategoriaResumen,
        'fecha_inicio' => $fechaInicioResumen,
        'fecha_fin' => $fechaFinResumen
    ]);

    $categoriasPeriodo = $consultaCategoriasPeriodo->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categoriasPeriodo as $indiceCategoria => &$categoriaPeriodo) {
        $categoriaPeriodo['importe'] = (float) $categoriaPeriodo['importe'];
        $categoriaPeriodo['color'] = $coloresPorTipoResumen[$tipoCategoriaResumen][
            $indiceCategoria % count($coloresPorTipoResumen[$tipoCategoriaResumen])
        ];
    }
    unset($categoriaPeriodo);

    if ($tipoCategoriaResumen === 'ingreso') {
        $categoriasIngresosResumen = $categoriasPeriodo;
    } else {
        $categoriasGastosResumen = $categoriasPeriodo;
    }
}

$periodosBalanceResumen = [];
$cursorPeriodoBalance = new DateTimeImmutable($fechaInicioResumen);
$finPeriodoBalance = new DateTimeImmutable($fechaFinResumen);

if ($rangoResumen === 'mensual') {
    while ($cursorPeriodoBalance < $finPeriodoBalance) {
        $periodosBalanceResumen[$cursorPeriodoBalance->format('Y-m-d')] = [
            'etiqueta' => (string) (int) $cursorPeriodoBalance->format('j'),
            'ingresos' => 0.0,
            'gastos' => 0.0,
            'registros_ingresos' => 0,
            'registros_gastos' => 0,
            'ahorro' => 0.0,
            'inversion' => 0.0,
            'registros_ahorro' => 0,
            'registros_inversion' => 0
        ];
        $cursorPeriodoBalance = $cursorPeriodoBalance->modify('+1 day');
    }
    $formatoAgrupacionBalance = '%Y-%m-%d';
} else {
    $nombresMesesCortosResumen = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic'
    ];

    while ($cursorPeriodoBalance < $finPeriodoBalance) {
        $claveMesBalance = $cursorPeriodoBalance->format('Y-m');
        $periodosBalanceResumen[$claveMesBalance] = [
            'etiqueta' => $nombresMesesCortosResumen[
                (int) $cursorPeriodoBalance->format('n')
            ],
            'ingresos' => 0.0,
            'gastos' => 0.0,
            'registros_ingresos' => 0,
            'registros_gastos' => 0,
            'ahorro' => 0.0,
            'inversion' => 0.0,
            'registros_ahorro' => 0,
            'registros_inversion' => 0
        ];
        $cursorPeriodoBalance = $cursorPeriodoBalance->modify('+1 month');
    }
    $formatoAgrupacionBalance = '%Y-%m';
}

$consultaBalanceTemporal = $conexion->prepare(
    'SELECT
        DATE_FORMAT(fecha, :formato) AS periodo,
        SUM(CASE WHEN tipo = "ingreso" THEN importe ELSE 0 END) AS ingresos,
        SUM(CASE WHEN tipo = "gasto" THEN importe ELSE 0 END) AS gastos,
        SUM(CASE WHEN tipo = "ingreso" THEN 1 ELSE 0 END) AS registros_ingresos,
        SUM(CASE WHEN tipo = "gasto" THEN 1 ELSE 0 END) AS registros_gastos
     FROM registros
     WHERE usuario_id = :usuario_id
       AND fecha >= :fecha_inicio
       AND fecha < :fecha_fin
     GROUP BY DATE_FORMAT(fecha, :formato_agrupacion)
     ORDER BY periodo'
);
$consultaBalanceTemporal->execute([
    'formato' => $formatoAgrupacionBalance,
    'usuario_id' => $usuarioId,
    'fecha_inicio' => $fechaInicioResumen,
    'fecha_fin' => $fechaFinResumen,
    'formato_agrupacion' => $formatoAgrupacionBalance
]);

foreach ($consultaBalanceTemporal->fetchAll(PDO::FETCH_ASSOC) as $balanceTemporal) {
    if (isset($periodosBalanceResumen[$balanceTemporal['periodo']])) {
        $periodosBalanceResumen[$balanceTemporal['periodo']]['ingresos'] =
            (float) $balanceTemporal['ingresos'];
        $periodosBalanceResumen[$balanceTemporal['periodo']]['gastos'] =
            (float) $balanceTemporal['gastos'];
        $periodosBalanceResumen[$balanceTemporal['periodo']]['registros_ingresos'] =
            (int) $balanceTemporal['registros_ingresos'];
        $periodosBalanceResumen[$balanceTemporal['periodo']]['registros_gastos'] =
            (int) $balanceTemporal['registros_gastos'];
    }
}

$consultaAhorroInversionTemporal = $conexion->prepare(
    'SELECT periodo, tipo, SUM(importe) AS importe, COUNT(*) AS registros
     FROM (
         SELECT
             DATE_FORMAT(fecha, :formato_aportaciones) AS periodo,
             "ahorro" AS tipo,
             importe
         FROM aportaciones_objetivos
         WHERE usuario_id = :usuario_aportaciones
           AND fecha >= :inicio_aportaciones
           AND fecha < :fin_aportaciones

         UNION ALL

         SELECT
             DATE_FORMAT(fecha, :formato_inversiones) AS periodo,
             "inversion" AS tipo,
             importe
         FROM inversiones
         WHERE usuario_id = :usuario_inversiones
           AND fecha >= :inicio_inversiones
           AND fecha < :fin_inversiones
     ) aportes
     GROUP BY periodo, tipo
     ORDER BY periodo'
);
$consultaAhorroInversionTemporal->execute([
    'formato_aportaciones' => $formatoAgrupacionBalance,
    'usuario_aportaciones' => $usuarioId,
    'inicio_aportaciones' => $fechaInicioResumen,
    'fin_aportaciones' => $fechaFinResumen,
    'formato_inversiones' => $formatoAgrupacionBalance,
    'usuario_inversiones' => $usuarioId,
    'inicio_inversiones' => $fechaInicioResumen,
    'fin_inversiones' => $fechaFinResumen
]);

foreach (
    $consultaAhorroInversionTemporal->fetchAll(PDO::FETCH_ASSOC)
    as $movimientoPatrimonial
) {
    $periodo = $movimientoPatrimonial['periodo'];
    $tipo = $movimientoPatrimonial['tipo'];

    if (!isset($periodosBalanceResumen[$periodo])) {
        continue;
    }

    $periodosBalanceResumen[$periodo][$tipo] =
        (float) $movimientoPatrimonial['importe'];
    $periodosBalanceResumen[$periodo]['registros_' . $tipo] =
        (int) $movimientoPatrimonial['registros'];
}

$datosBalanceTemporalJson = json_encode(
    array_values($periodosBalanceResumen),
    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$consultaSaldoBancos = $conexion->prepare(
    'SELECT COALESCE(SUM(b.saldo_inicial + COALESCE(m.saldo, 0)), 0)
     FROM bancos b
     LEFT JOIN (
         SELECT banco_id, SUM(importe) AS saldo
         FROM movimientos_bancos
         GROUP BY banco_id
     ) m ON m.banco_id = b.id
     WHERE b.usuario_id = :usuario_id'
);
$consultaSaldoBancos->execute(['usuario_id' => $usuarioId]);
$saldoBancosResumen = (float) $consultaSaldoBancos->fetchColumn();

$consultaAhorroActual = $conexion->prepare(
    'SELECT COALESCE(SUM(saldo_actual), 0)
     FROM objetivos
     WHERE usuario_id = :usuario_id'
);
$consultaAhorroActual->execute(['usuario_id' => $usuarioId]);
$ahorroActualResumen = (float) $consultaAhorroActual->fetchColumn();

$consultaAhorroPeriodo = $conexion->prepare(
    'SELECT COALESCE(SUM(importe), 0)
     FROM aportaciones_objetivos
     WHERE usuario_id = :usuario_id
       AND fecha >= :fecha_inicio
       AND fecha < :fecha_fin'
);
$consultaAhorroPeriodo->execute([
    'usuario_id' => $usuarioId,
    'fecha_inicio' => $fechaInicioResumen,
    'fecha_fin' => $fechaFinResumen
]);
$ahorroPeriodoResumen = (float) $consultaAhorroPeriodo->fetchColumn();

$consultaInversionActual = $conexion->prepare(
    'SELECT COALESCE(SUM(importe), 0)
     FROM inversiones
     WHERE usuario_id = :usuario_id'
);
$consultaInversionActual->execute(['usuario_id' => $usuarioId]);
$inversionActualResumen = (float) $consultaInversionActual->fetchColumn();

$consultaInversionPeriodo = $conexion->prepare(
    'SELECT COALESCE(SUM(importe), 0)
     FROM inversiones
     WHERE usuario_id = :usuario_id
       AND fecha >= :fecha_inicio
       AND fecha < :fecha_fin'
);
$consultaInversionPeriodo->execute([
    'usuario_id' => $usuarioId,
    'fecha_inicio' => $fechaInicioResumen,
    'fecha_fin' => $fechaFinResumen
]);
$inversionPeriodoResumen = (float) $consultaInversionPeriodo->fetchColumn();

function formatearEurosResumen(float $importe): string
{
    return number_format($importe, 2, ',', '.') . ' €';
}

function formatearCambioPeriodoResumen(float $importe): string
{
    $signo = $importe > 0 ? '+' : ($importe < 0 ? '-' : '');

    return $signo . number_format(abs($importe), 2, ',', '.') . ' €';
}

/*
|--------------------------------------------------------------------------
| Obtener las categorías del usuario
|--------------------------------------------------------------------------
*/

$consultaCategorias = $conexion->prepare(
    'SELECT id, nombre, tipo
     FROM categorias
     WHERE usuario_id = :usuario_id
     ORDER BY nombre ASC'
);

$consultaCategorias->execute([
    'usuario_id' => $usuarioId
]);

$categoriasResumen = $consultaCategorias->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Obtener las cuentas bancarias del usuario
|--------------------------------------------------------------------------
*/

$consultaBancos = $conexion->prepare(
    'SELECT id, nombre
     FROM bancos
     WHERE usuario_id = :usuario_id
     ORDER BY nombre ASC'
);

$consultaBancos->execute([
    'usuario_id' => $usuarioId
]);

$bancosResumen = $consultaBancos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Obtener los objetivos del usuario
|--------------------------------------------------------------------------
*/

$consultaObjetivos = $conexion->prepare(
    'SELECT id, nombre
     FROM objetivos
     WHERE usuario_id = :usuario_id
     ORDER BY es_fondo_emergencia DESC, nombre ASC'
);

$consultaObjetivos->execute([
    'usuario_id' => $usuarioId
]);

$objetivosResumen = $consultaObjetivos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tokenCsrfResumen = $_SESSION['csrf_token'];

$mensajeResumen = $_SESSION['mensaje_resumen'] ?? '';
unset($_SESSION['mensaje_resumen']);
?>

<div class="resumen-contenedor">

    <!-- Botón de creación y filtros -->
    <div class="resumen-barra-superior">

        <button
            type="button"
            id="btn-crear-registro"
            class="btn-crear-registro"
            aria-expanded="false"
            aria-controls="panel-crear-registro"
        >
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Crear registro
        </button>

        <div class="resumen-filtros">

            <div class="resumen-filtro">
                <label for="filtro-rango">Rango</label>

                <select id="filtro-rango" name="filtro-rango">
                    <option value="mensual" <?= $rangoResumen === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                    <option value="anual" <?= $rangoResumen === 'anual' ? 'selected' : '' ?>>Anual</option>
                </select>
            </div>

            <div class="resumen-filtro">
                <label for="filtro-periodo">Período</label>

                <select
                    id="filtro-periodo"
                    name="filtro-periodo"
                    data-periodos-mensuales="<?= htmlspecialchars($periodosMensualesJson, ENT_QUOTES, 'UTF-8') ?>"
                    data-anios="<?= htmlspecialchars($aniosConRegistrosJson, ENT_QUOTES, 'UTF-8') ?>"
                    data-periodo-seleccionado="<?= htmlspecialchars($periodoResumen, ENT_QUOTES, 'UTF-8') ?>"
                ></select>
            </div>

        </div>

    </div>

    <!-- Mensaje de estado -->
    <p
        id="mensaje-registro"
        class="mensaje-registro"
        role="status"
        aria-live="polite"
        <?= $mensajeResumen === '' ? 'hidden' : '' ?>
    ><?= htmlspecialchars($mensajeResumen, ENT_QUOTES, 'UTF-8') ?></p>

    <!-- Formulario de creación -->
    <div
        id="panel-crear-registro"
        class="panel-crear-registro"
        hidden
    >

        <div class="formulario-registro-encabezado">
            <div>
                <h3>Crear registro</h3>
                <p id="descripcion-formulario-registro">
                    Selecciona el tipo de registro e introduce sus datos.
                </p>
            </div>

            <button
                type="button"
                id="btn-cerrar-registro"
                class="btn-cerrar-registro"
                aria-label="Cerrar formulario"
            >
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <form id="form-crear-registro" action="guardar_registro.php" method="POST" autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($tokenCsrfResumen, ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="formulario-registro-grid">

                <!-- Tipo de registro: siempre visible -->
                <div class="campo-registro">
                    <label for="tipo-registro">Tipo de registro</label>

                    <select id="tipo-registro" name="tipo" required>
                        <option value="">Selecciona un tipo</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="gasto">Gasto</option>
                        <option value="ahorro">Ahorro</option>
                        <option value="inversion">Inversión</option>
                        <option value="traspaso">Traspaso</option>
                    </select>
                </div>

                <!-- Fecha: compartida por todos los tipos -->
                <div class="campo-registro grupo-campo-registro" data-grupo="comun">
                    <label for="fecha-registro">Fecha</label>

                    <input
                        type="date"
                        id="fecha-registro"
                        name="fecha"
                        required
                        disabled
                    >
                </div>

                <!-- Cantidad: compartida por todos los tipos -->
                <div class="campo-registro grupo-campo-registro" data-grupo="comun">
                    <label for="cantidad-registro">Cantidad (€)</label>

                    <input
                        type="number"
                        id="cantidad-registro"
                        name="importe"
                        min="0.01"
                        step="0.01"
                        placeholder="0,00"
                        required
                        disabled
                    >
                </div>

                <!-- Categoría: solo ingreso y gasto -->
                <div
                    class="campo-registro grupo-campo-registro"
                    data-grupo="categoria"
                    hidden
                >
                    <label for="categoria-registro">Categoría</label>

                    <select
                        id="categoria-registro"
                        name="categoria_id"
                        disabled
                    >
                        <option value="">Selecciona una categoría</option>

                        <?php foreach ($categoriasResumen as $categoria): ?>
                            <option
                                value="<?= (int) $categoria['id'] ?>"
                                data-tipo="<?= htmlspecialchars($categoria['tipo'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small
                        id="ayuda-categoria-registro"
                        class="ayuda-campo"
                    ></small>
                </div>

                <!-- Objetivo: solo ahorro -->
                <div
                    class="campo-registro grupo-campo-registro"
                    data-grupo="objetivo"
                    hidden
                >
                    <label for="objetivo-registro">Objetivo de ahorro</label>

                    <select
                        id="objetivo-registro"
                        name="objetivo_id"
                        disabled
                    >
                        <option value="">Selecciona un objetivo</option>

                        <?php foreach ($objetivosResumen as $objetivo): ?>
                            <option value="<?= (int) $objetivo['id'] ?>">
                                <?= htmlspecialchars($objetivo['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($objetivosResumen)): ?>
                        <small class="ayuda-campo">
                            Todavía no tienes objetivos creados.
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Banco único: ingreso, gasto, ahorro e inversión -->
                <div
                    class="campo-registro grupo-campo-registro"
                    data-grupo="banco"
                    hidden
                >
                    <label for="banco-registro">Banco</label>

                    <select
                        id="banco-registro"
                        name="banco_id"
                        disabled
                    >
                        <option value="">Selecciona una cuenta</option>

                        <?php foreach ($bancosResumen as $banco): ?>
                            <option value="<?= (int) $banco['id'] ?>">
                                <?= htmlspecialchars($banco['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (empty($bancosResumen)): ?>
                        <small class="ayuda-campo">
                            No tienes cuentas bancarias creadas todavía.
                        </small>
                    <?php endif; ?>
                </div>

                <!-- Banco de origen: solo traspaso -->
                <div
                    class="campo-registro grupo-campo-registro"
                    data-grupo="traspaso"
                    hidden
                >
                    <label for="banco-origen-traspaso">Banco de origen</label>

                    <select
                        id="banco-origen-traspaso"
                        name="banco_origen_id"
                        disabled
                    >
                        <option value="">Selecciona la cuenta de origen</option>

                        <?php foreach ($bancosResumen as $banco): ?>
                            <option value="<?= (int) $banco['id'] ?>">
                                <?= htmlspecialchars($banco['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Banco de destino: solo traspaso -->
                <div
                    class="campo-registro grupo-campo-registro"
                    data-grupo="traspaso"
                    hidden
                >
                    <label for="banco-destino-traspaso">Banco de destino</label>

                    <select
                        id="banco-destino-traspaso"
                        name="banco_destino_id"
                        disabled
                    >
                        <option value="">Selecciona la cuenta de destino</option>

                        <?php foreach ($bancosResumen as $banco): ?>
                            <option value="<?= (int) $banco['id'] ?>">
                                <?= htmlspecialchars($banco['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small
                        id="ayuda-traspaso"
                        class="ayuda-campo"
                    >
                        Las cuentas de origen y destino deben ser distintas.
                    </small>
                </div>

                <!-- Notas: opcionales en todos los tipos -->
                <div
                    class="campo-registro campo-registro-completo grupo-campo-registro"
                    data-grupo="notas"
                    hidden
                >
                    <label for="observaciones-registro">Notas (opcional)</label>

                    <textarea
                        id="observaciones-registro"
                        name="descripcion"
                        rows="3"
                        maxlength="255"
                        placeholder="Añade observaciones si lo necesitas..."
                        disabled
                    ></textarea>
                </div>

            </div>

            <div class="formulario-registro-acciones">
                <button
                    type="button"
                    id="btn-cancelar-registro"
                    class="btn-secundario"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn-guardar-registro"
                >
                    Guardar registro
                </button>
            </div>

        </form>

    </div>

    <div class="resumen-contenido">
        <section class="resumen-grupo" aria-labelledby="resumen-operaciones-titulo">
            <h2 id="resumen-operaciones-titulo">
                Operaciones de <?= htmlspecialchars($etiquetaPeriodoResumen, ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <div class="resumen-tarjetas">
                <article class="resumen-tarjeta resumen-tarjeta-ingresos">
                    <div class="resumen-tarjeta-icono" aria-hidden="true">
                        <i class="bi bi-arrow-down-left"></i>
                    </div>
                    <h3>Ingresos totales</h3>
                    <p class="resumen-tarjeta-importe<?= $ingresosPeriodoResumen > 0 ? ' resumen-importe-positivo' : '' ?>">
                        <?= formatearEurosResumen($ingresosPeriodoResumen) ?>
                    </p>
                </article>

                <article class="resumen-tarjeta resumen-tarjeta-gastos">
                    <div class="resumen-tarjeta-icono" aria-hidden="true">
                        <i class="bi bi-arrow-up-right"></i>
                    </div>
                    <h3>Gastos totales</h3>
                    <p class="resumen-tarjeta-importe">
                        <?= formatearEurosResumen($gastosPeriodoResumen) ?>
                    </p>
                </article>

                <article class="resumen-tarjeta resumen-tarjeta-balance">
                    <div class="resumen-tarjeta-icono" aria-hidden="true">
                        <i class="bi bi-calculator"></i>
                    </div>
                    <h3>Balance</h3>
                    <p class="resumen-tarjeta-importe <?= $balancePeriodoResumen > 0 ? 'resumen-importe-positivo' : ($balancePeriodoResumen < 0 ? 'resumen-importe-negativo' : 'resumen-importe-neutro') ?>">
                        <?= formatearEurosResumen($balancePeriodoResumen) ?>
                    </p>
                </article>
            </div>

        </section>

        <section class="resumen-grupo" aria-labelledby="resumen-patrimonio-titulo">
            <h2 id="resumen-patrimonio-titulo">Patrimonio</h2>

            <div class="resumen-tarjetas">
                <article class="resumen-tarjeta resumen-tarjeta-cuentas">
                    <div class="resumen-tarjeta-icono" aria-hidden="true">
                        <i class="bi bi-bank"></i>
                    </div>
                    <h3>Saldo en cuentas</h3>
                    <p class="resumen-tarjeta-importe">
                        <?= formatearEurosResumen($saldoBancosResumen) ?>
                    </p>
                </article>

                <article class="resumen-tarjeta resumen-tarjeta-ahorro">
                    <div class="resumen-tarjeta-icono" aria-hidden="true">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <h3>Ahorro</h3>
                    <p class="resumen-tarjeta-importe">
                        <?= formatearEurosResumen($ahorroActualResumen) ?>
                    </p>
                    <p class="resumen-tarjeta-periodo">
                        <strong class="<?= $ahorroPeriodoResumen > 0 ? 'resumen-variacion-positiva' : ($ahorroPeriodoResumen < 0 ? 'resumen-variacion-negativa' : 'resumen-variacion-neutra') ?>">
                            <?= formatearCambioPeriodoResumen($ahorroPeriodoResumen) ?>
                        </strong> en este periodo
                    </p>
                </article>

                <article class="resumen-tarjeta resumen-tarjeta-inversion">
                    <div class="resumen-tarjeta-icono" aria-hidden="true">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h3>Invertido</h3>
                    <p class="resumen-tarjeta-importe">
                        <?= formatearEurosResumen($inversionActualResumen) ?>
                    </p>
                    <p class="resumen-tarjeta-periodo">
                        <strong class="<?= $inversionPeriodoResumen > 0 ? 'resumen-variacion-positiva' : ($inversionPeriodoResumen < 0 ? 'resumen-variacion-negativa' : 'resumen-variacion-neutra') ?>">
                            <?= formatearCambioPeriodoResumen($inversionPeriodoResumen) ?>
                        </strong> en este periodo
                    </p>
                </article>
            </div>

        </section>

        <section class="resumen-grupo" aria-labelledby="resumen-categorias-titulo">
            <h2 id="resumen-categorias-titulo">
                Ingresos y gastos por categoría de <?= htmlspecialchars($etiquetaPeriodoResumen, ENT_QUOTES, 'UTF-8') ?>
            </h2>

            <div class="resumen-graficos">
                <article class="resumen-grafico-tarjeta">
                    <h3>Ingresos por categoría</h3>

                    <div class="resumen-grafico-contenido">
                        <canvas
                            class="resumen-grafico-circular"
                            data-categorias="<?= htmlspecialchars(
                                json_encode($categoriasIngresosResumen, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            role="img"
                            aria-label="Gráfico circular de ingresos por categoría"
                        ></canvas>

                        <?php if ($categoriasIngresosResumen): ?>
                            <ul class="resumen-grafico-leyenda">
                                <?php foreach ($categoriasIngresosResumen as $categoria): ?>
                                    <li>
                                        <span
                                            class="resumen-grafico-indicador"
                                            style="--categoria-color: <?= htmlspecialchars($categoria['color'], ENT_QUOTES, 'UTF-8') ?>"
                                            aria-hidden="true"
                                        ></span>
                                        <span class="resumen-grafico-nombre">
                                            <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="resumen-grafico-vacio">No hay ingresos en este periodo.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="resumen-grafico-tarjeta">
                    <h3>Gastos por categoría</h3>

                    <div class="resumen-grafico-contenido">
                        <canvas
                            class="resumen-grafico-circular"
                            data-categorias="<?= htmlspecialchars(
                                json_encode($categoriasGastosResumen, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            role="img"
                            aria-label="Gráfico circular de gastos por categoría"
                        ></canvas>

                        <?php if ($categoriasGastosResumen): ?>
                            <ul class="resumen-grafico-leyenda">
                                <?php foreach ($categoriasGastosResumen as $categoria): ?>
                                    <li>
                                        <span
                                            class="resumen-grafico-indicador"
                                            style="--categoria-color: <?= htmlspecialchars($categoria['color'], ENT_QUOTES, 'UTF-8') ?>"
                                            aria-hidden="true"
                                        ></span>
                                        <span class="resumen-grafico-nombre">
                                            <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="resumen-grafico-vacio">No hay gastos en este periodo.</p>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <article class="resumen-grafico-tarjeta resumen-grafico-area-tarjeta">
                <h3>Ingresos y gastos por <?= $rangoResumen === 'mensual' ? 'día' : 'mes' ?></h3>
                <div class="resumen-grafico-area-contenido">
                    <canvas
                        class="resumen-grafico-area"
                        data-balance="<?= htmlspecialchars(
                            $datosBalanceTemporalJson,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        role="img"
                        aria-label="Gráfico de áreas superpuestas de ingresos y gastos por <?= $rangoResumen === 'mensual' ? 'día' : 'mes' ?>"
                    ></canvas>
                    <div class="resumen-area-tooltip" role="tooltip" hidden></div>
                </div>
                <div class="resumen-area-leyenda" aria-hidden="true">
                    <span><i class="resumen-area-indicador resumen-area-ingresos"></i>Ingresos</span>
                    <span><i class="resumen-area-indicador resumen-area-gastos"></i>Gastos</span>
                </div>
            </article>

            <article class="resumen-grafico-tarjeta resumen-grafico-area-tarjeta">
                <h3>Balance por <?= $rangoResumen === 'mensual' ? 'día' : 'mes' ?></h3>
                <div class="resumen-grafico-area-contenido">
                    <canvas
                        class="resumen-grafico-balance"
                        data-balance="<?= htmlspecialchars(
                            $datosBalanceTemporalJson,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        role="img"
                        aria-label="Gráfico de área azul del balance neto, ingresos menos gastos, por <?= $rangoResumen === 'mensual' ? 'día' : 'mes' ?>"
                    ></canvas>
                    <div class="resumen-area-tooltip" role="tooltip" hidden></div>
                </div>
                <div class="resumen-area-leyenda" aria-hidden="true">
                    <span><i class="resumen-area-indicador resumen-area-balance"></i>Balance (ingresos menos gastos)</span>
                </div>
            </article>

            <article class="resumen-grafico-tarjeta resumen-grafico-area-tarjeta">
                <h3>Ahorro e inversión por <?= $rangoResumen === 'mensual' ? 'día' : 'mes' ?></h3>
                <div class="resumen-grafico-area-contenido">
                    <canvas
                        class="resumen-grafico-ahorro-inversion"
                        data-balance="<?= htmlspecialchars(
                            $datosBalanceTemporalJson,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        role="img"
                        aria-label="Gráfico de áreas superpuestas de ahorro e inversión por <?= $rangoResumen === 'mensual' ? 'día' : 'mes' ?>"
                    ></canvas>
                    <div class="resumen-area-tooltip" role="tooltip" hidden></div>
                </div>
                <div class="resumen-area-leyenda" aria-hidden="true">
                    <span><i class="resumen-area-indicador resumen-area-ahorro"></i>Ahorro</span>
                    <span><i class="resumen-area-indicador resumen-area-inversion"></i>Inversión</span>
                </div>
            </article>
        </section>
    </div>

</div>
