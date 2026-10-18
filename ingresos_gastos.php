<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo '<p>Debes iniciar sesión para consultar ingresos y gastos.</p>';
    return;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];
$rangoIngresosGastos = $_GET['rango'] ?? 'mensual';

if (
    !is_string($rangoIngresosGastos)
    || !in_array($rangoIngresosGastos, ['mensual', 'anual'], true)
) {
    $rangoIngresosGastos = 'mensual';
}

$consultaPeriodosIngresosGastos = $conexion->prepare(
    'SELECT DISTINCT DATE_FORMAT(fecha, "%Y-%m") AS periodo
     FROM registros
     WHERE usuario_id = :usuario_id
       AND tipo IN ("ingreso", "gasto")
     ORDER BY periodo DESC'
);
$consultaPeriodosIngresosGastos->execute([
    'usuario_id' => $usuarioId
]);
$periodosIngresosGastos = $consultaPeriodosIngresosGastos->fetchAll(
    PDO::FETCH_COLUMN
);
$aniosIngresosGastos = array_values(array_unique(array_map(
    static fn(string $periodo): string => substr($periodo, 0, 4),
    $periodosIngresosGastos
)));

$periodoSolicitadoIngresosGastos = $_GET['periodo'] ?? '';

if ($rangoIngresosGastos === 'anual') {
    $periodoValido = is_string($periodoSolicitadoIngresosGastos)
        && in_array($periodoSolicitadoIngresosGastos, $aniosIngresosGastos, true);

    $periodoIngresosGastos = $periodoValido
        ? $periodoSolicitadoIngresosGastos
        : ($aniosIngresosGastos[0] ?? date('Y'));

    $fechaInicioIngresosGastos = $periodoIngresosGastos . '-01-01';
    $fechaFinIngresosGastos = ((int) $periodoIngresosGastos + 1) . '-01-01';
    $etiquetaPeriodoIngresosGastos = $periodoIngresosGastos;
} else {
    $periodoValido = is_string($periodoSolicitadoIngresosGastos)
        && in_array(
            $periodoSolicitadoIngresosGastos,
            $periodosIngresosGastos,
            true
        );

    $periodoIngresosGastos = $periodoValido
        ? $periodoSolicitadoIngresosGastos
        : ($periodosIngresosGastos[0] ?? date('Y-m'));

    $fechaInicioIngresosGastos = $periodoIngresosGastos . '-01';
    $fechaFinIngresosGastos = (new DateTimeImmutable($fechaInicioIngresosGastos))
        ->modify('first day of next month')
        ->format('Y-m-d');

    $partesPeriodoIngresosGastos = explode('-', $periodoIngresosGastos);
    $nombresMesesIngresosGastos = [
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
    $etiquetaPeriodoIngresosGastos = ucfirst(
        $nombresMesesIngresosGastos[(int) $partesPeriodoIngresosGastos[1]]
    ) . ' de ' . $partesPeriodoIngresosGastos[0];
}

$diasPeriodoIngresosGastos = (int) (
    (new DateTimeImmutable($fechaFinIngresosGastos))
        ->diff(new DateTimeImmutable($fechaInicioIngresosGastos))
        ->days
);

$consultaTotalesIngresosGastos = $conexion->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN tipo = "ingreso" THEN importe ELSE 0 END), 0)
            AS ingresos,
        COALESCE(SUM(CASE WHEN tipo = "gasto" THEN importe ELSE 0 END), 0)
            AS gastos
     FROM registros
     WHERE usuario_id = :usuario_id
       AND fecha >= :fecha_inicio
       AND fecha < :fecha_fin
       AND tipo IN ("ingreso", "gasto")'
);
$consultaTotalesIngresosGastos->execute([
    'usuario_id' => $usuarioId,
    'fecha_inicio' => $fechaInicioIngresosGastos,
    'fecha_fin' => $fechaFinIngresosGastos
]);
$totalesIngresosGastos = $consultaTotalesIngresosGastos->fetch(PDO::FETCH_ASSOC);
$totalIngresos = (float) $totalesIngresosGastos['ingresos'];
$totalGastos = (float) $totalesIngresosGastos['gastos'];
$mediaDiariaIngresos = $diasPeriodoIngresosGastos > 0
    ? $totalIngresos / $diasPeriodoIngresosGastos
    : 0;
$mediaDiariaGastos = $diasPeriodoIngresosGastos > 0
    ? $totalGastos / $diasPeriodoIngresosGastos
    : 0;

$consultaCategoriasIngresosGastos = $conexion->prepare(
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

$consultaCategoriasIngresosGastos->execute([
    'usuario_id' => $usuarioId,
    'tipo' => 'ingreso',
    'fecha_inicio' => $fechaInicioIngresosGastos,
    'fecha_fin' => $fechaFinIngresosGastos
]);
$categoriasIngresos = $consultaCategoriasIngresosGastos->fetchAll(PDO::FETCH_ASSOC);

$consultaCategoriasIngresosGastos->execute([
    'usuario_id' => $usuarioId,
    'tipo' => 'gasto',
    'fecha_inicio' => $fechaInicioIngresosGastos,
    'fecha_fin' => $fechaFinIngresosGastos
]);
$categoriasGastos = $consultaCategoriasIngresosGastos->fetchAll(PDO::FETCH_ASSOC);

foreach ([
    'ingreso' => &$categoriasIngresos,
    'gasto' => &$categoriasGastos
] as $tipo => &$categorias) {
    $totalTipo = $tipo === 'ingreso' ? $totalIngresos : $totalGastos;

    foreach ($categorias as &$categoria) {
        $categoria['importe'] = (float) $categoria['importe'];
        $categoria['porcentaje'] = $totalTipo > 0
            ? min(100, $categoria['importe'] / $totalTipo * 100)
            : 0;
    }
    unset($categoria);
}
unset($categorias);

function eurosIngresosGastos(float $importe): string
{
    return number_format($importe, 2, ',', '.') . ' €';
}

$periodosMensualesIngresosGastosJson = json_encode(
    $periodosIngresosGastos,
    JSON_THROW_ON_ERROR
);
$aniosIngresosGastosJson = json_encode(
    $aniosIngresosGastos,
    JSON_THROW_ON_ERROR
);
?>

<div class="ingresos-gastos-contenedor">
    <div class="ingresos-gastos-barra-superior">
        <button
            type="button"
            id="btn-crear-registro-ingresos-gastos"
            class="btn-crear-registro"
        >
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Crear registro
        </button>

        <form
            id="filtros-ingresos-gastos"
            class="ingresos-gastos-filtros"
            method="GET"
            action="dashboard.php"
        >
            <input type="hidden" name="seccion" value="ingresos-gastos">
            <label>
                <span>Rango</span>
                <select name="rango" id="rango-ingresos-gastos">
                    <option value="mensual" <?= $rangoIngresosGastos === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                    <option value="anual" <?= $rangoIngresosGastos === 'anual' ? 'selected' : '' ?>>Anual</option>
                </select>
            </label>
            <label>
                <span>Periodo</span>
                <select
                    name="periodo"
                    id="periodo-ingresos-gastos"
                    data-periodos="<?= htmlspecialchars($periodosMensualesIngresosGastosJson, ENT_QUOTES, 'UTF-8') ?>"
                    data-anios="<?= htmlspecialchars($aniosIngresosGastosJson, ENT_QUOTES, 'UTF-8') ?>"
                    data-seleccionado="<?= htmlspecialchars($periodoIngresosGastos, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <?php if (!$periodosIngresosGastos): ?>
                        <option value="" selected disabled>
                            No hay periodos con registros
                        </option>
                    <?php elseif ($rangoIngresosGastos === 'mensual'): ?>
                        <?php foreach ($aniosIngresosGastos as $anio): ?>
                            <?php foreach ([
                                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
                                4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
                                10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                            ] as $numeroMes => $nombreMes): ?>
                                <?php $periodoOpcion = $anio . '-' . str_pad((string) $numeroMes, 2, '0', STR_PAD_LEFT); ?>
                                <option
                                    value="<?= htmlspecialchars($periodoOpcion, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= !in_array($periodoOpcion, $periodosIngresosGastos, true) ? 'disabled' : '' ?>
                                    <?= $periodoOpcion === $periodoIngresosGastos ? 'selected' : '' ?>
                                ><?= $nombreMes ?> de <?= htmlspecialchars($anio, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($aniosIngresosGastos as $anio): ?>
                            <option
                                value="<?= htmlspecialchars($anio, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $anio === $periodoIngresosGastos ? 'selected' : '' ?>
                            ><?= htmlspecialchars($anio, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>
        </form>
    </div>

    <h1 class="ingresos-gastos-titulo">
        Ingresos y gastos de <?= htmlspecialchars($etiquetaPeriodoIngresosGastos, ENT_QUOTES, 'UTF-8') ?>
    </h1>

    <div class="ingresos-gastos-medias">
        <article class="ingresos-gastos-media">
            <span>Media diaria de ingresos</span>
            <strong class="ingresos-gastos-positivo">
                <?= eurosIngresosGastos($mediaDiariaIngresos) ?>
            </strong>
        </article>
        <article class="ingresos-gastos-media">
            <span>Media diaria de gastos</span>
            <strong class="ingresos-gastos-negativo">
                <?= eurosIngresosGastos($mediaDiariaGastos) ?>
            </strong>
        </article>
    </div>

    <div class="ingresos-gastos-tarjetas">
        <?php foreach ([
            [
                'tipo' => 'ingreso',
                'titulo' => 'Ingresos',
                'total' => $totalIngresos,
                'categorias' => $categoriasIngresos
            ],
            [
                'tipo' => 'gasto',
                'titulo' => 'Gastos',
                'total' => $totalGastos,
                'categorias' => $categoriasGastos
            ]
        ] as $tarjeta): ?>
            <article class="ingresos-gastos-tarjeta ingresos-gastos-tarjeta-<?= $tarjeta['tipo'] ?>">
                <div class="ingresos-gastos-tarjeta-cabecera">
                    <h2><?= $tarjeta['titulo'] ?></h2>
                    <strong><?= eurosIngresosGastos($tarjeta['total']) ?></strong>
                </div>

                <?php if ($tarjeta['categorias']): ?>
                    <ul class="ingresos-gastos-categorias">
                        <?php foreach ($tarjeta['categorias'] as $categoria): ?>
                            <li>
                                <div class="ingresos-gastos-categoria-detalle">
                                    <span><?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <strong><?= eurosIngresosGastos($categoria['importe']) ?></strong>
                                </div>
                                <div
                                    class="ingresos-gastos-progreso"
                                    role="progressbar"
                                    aria-label="<?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="<?= number_format($categoria['porcentaje'], 1, '.', '') ?>"
                                >
                                    <span style="width: <?= number_format($categoria['porcentaje'], 2, '.', '') ?>%"></span>
                                </div>
                                <small><?= number_format($categoria['porcentaje'], 1, ',', '.') ?>% del total</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ingresos-gastos-vacio">
                        No hay <?= strtolower($tarjeta['titulo']) ?> en este periodo.
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
