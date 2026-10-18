<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo '<p>Debes iniciar sesión para consultar los registros.</p>';
    return;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioIdRegistro = (int) $_SESSION['usuario_id'];
$rangoRegistro = $_GET['rango'] ?? 'mensual';
if (!is_string($rangoRegistro) || !in_array($rangoRegistro, ['mensual', 'anual'], true)) {
    $rangoRegistro = 'mensual';
}

$sqlFechasRegistro = '
    SELECT fecha FROM registros WHERE usuario_id = :usuario_registros
    UNION
    SELECT fecha FROM aportaciones_objetivos WHERE usuario_id = :usuario_aportaciones
    UNION
    SELECT fecha FROM inversiones WHERE usuario_id = :usuario_inversiones
    UNION
    SELECT mb.fecha
    FROM movimientos_bancos mb
    INNER JOIN bancos b ON b.id = mb.banco_id
    WHERE b.usuario_id = :usuario_traspasos
      AND mb.tipo = "traspaso_salida"';
$consultaFechasRegistro = $conexion->prepare(
    'SELECT DISTINCT DATE_FORMAT(fecha, "%Y-%m") AS periodo
     FROM (' . $sqlFechasRegistro . ') fechas
     ORDER BY periodo DESC'
);
$consultaFechasRegistro->execute([
    'usuario_registros' => $usuarioIdRegistro,
    'usuario_aportaciones' => $usuarioIdRegistro,
    'usuario_inversiones' => $usuarioIdRegistro,
    'usuario_traspasos' => $usuarioIdRegistro
]);
$periodosRegistro = $consultaFechasRegistro->fetchAll(PDO::FETCH_COLUMN);
$aniosRegistro = array_values(array_unique(array_map(
    static fn(string $periodo): string => substr($periodo, 0, 4),
    $periodosRegistro
)));
$periodoSolicitadoRegistro = $_GET['periodo'] ?? '';

if ($rangoRegistro === 'anual') {
    $periodoRegistro = is_string($periodoSolicitadoRegistro)
        && in_array($periodoSolicitadoRegistro, $aniosRegistro, true)
        ? $periodoSolicitadoRegistro
        : ($aniosRegistro[0] ?? date('Y'));
    $fechaInicioRegistro = $periodoRegistro . '-01-01';
    $fechaFinRegistro = ((int) $periodoRegistro + 1) . '-01-01';
} else {
    $periodoRegistro = is_string($periodoSolicitadoRegistro)
        && in_array($periodoSolicitadoRegistro, $periodosRegistro, true)
        ? $periodoSolicitadoRegistro
        : ($periodosRegistro[0] ?? date('Y-m'));
    $fechaInicioRegistro = $periodoRegistro . '-01';
    $fechaFinRegistro = (new DateTimeImmutable($fechaInicioRegistro))
        ->modify('first day of next month')
        ->format('Y-m-d');
}

$periodosRegistroJson = json_encode($periodosRegistro, JSON_THROW_ON_ERROR);
$aniosRegistroJson = json_encode($aniosRegistro, JSON_THROW_ON_ERROR);

$sqlOperacionesRegistro = '
    SELECT
        r.id AS id,
        r.tipo AS tipo,
        c.nombre AS concepto,
        r.importe AS importe,
        r.fecha AS fecha,
        r.banco_id AS banco_origen_id,
        b.nombre AS cuenta_origen,
        NULL AS banco_destino_id,
        NULL AS cuenta_destino,
        r.descripcion AS observaciones,
        "registro" AS origen,
        (SELECT mb.id FROM movimientos_bancos mb
         WHERE mb.registro_id = r.id ORDER BY mb.id LIMIT 1) AS movimiento_id,
        NULL AS movimiento_destino_id,
        r.categoria_id AS categoria_id,
        NULL AS objetivo_id
    FROM registros r
    INNER JOIN categorias c ON c.id = r.categoria_id
    INNER JOIN bancos b ON b.id = r.banco_id
    WHERE r.usuario_id = :usuario_registro

    UNION ALL

    SELECT
        a.id, "ahorro", o.nombre, a.importe, a.fecha,
        a.banco_id, b.nombre, NULL, NULL, a.descripcion,
        "ahorro",
        (SELECT mb.id FROM movimientos_bancos mb
         WHERE mb.banco_id = a.banco_id AND mb.tipo = "gasto"
           AND mb.importe = -a.importe AND mb.fecha = a.fecha
           AND mb.registro_id IS NULL
           AND mb.descripcion <=> COALESCE(a.descripcion, "Aportación a objetivo")
         ORDER BY mb.id LIMIT 1),
        NULL, NULL, a.objetivo_id
    FROM aportaciones_objetivos a
    INNER JOIN objetivos o ON o.id = a.objetivo_id
    INNER JOIN bancos b ON b.id = a.banco_id
    WHERE a.usuario_id = :usuario_ahorro

    UNION ALL

    SELECT
        i.id, "inversion", "Inversión", i.importe, i.fecha,
        i.banco_id, b.nombre, NULL, NULL, i.descripcion,
        "inversion",
        (SELECT mb.id FROM movimientos_bancos mb
         WHERE mb.banco_id = i.banco_id AND mb.tipo = "gasto"
           AND mb.importe = -i.importe AND mb.fecha = i.fecha
           AND mb.registro_id IS NULL
           AND mb.descripcion <=> COALESCE(i.descripcion, "Inversión")
         ORDER BY mb.id LIMIT 1),
        NULL, NULL, NULL
    FROM inversiones i
    INNER JOIN bancos b ON b.id = i.banco_id
    WHERE i.usuario_id = :usuario_inversion

    UNION ALL

    SELECT
        salida.id, "traspaso", "Traspaso", ABS(salida.importe), salida.fecha,
        salida.banco_id, origen.nombre, entrada.banco_id, destino.nombre,
        CASE
            WHEN salida.descripcion = CONCAT("Traspaso enviado al banco #", destino.id)
                THEN NULL
            WHEN salida.descripcion LIKE CONCAT("Traspaso enviado al banco #", destino.id, ": %")
                THEN NULLIF(TRIM(LEADING ": " FROM SUBSTRING(salida.descripcion, CHAR_LENGTH(CONCAT("Traspaso enviado al banco #", destino.id)) + 1)), "")
            WHEN salida.descripcion = CONCAT("Traspaso a ", destino.nombre)
                THEN NULL
            ELSE salida.descripcion
        END,
        "traspaso", salida.id, entrada.id, NULL, NULL
    FROM movimientos_bancos salida
    INNER JOIN bancos origen ON origen.id = salida.banco_id
    INNER JOIN movimientos_bancos entrada
        ON entrada.tipo = "traspaso_entrada"
       AND entrada.fecha = salida.fecha
       AND entrada.importe = ABS(salida.importe)
    INNER JOIN bancos destino ON destino.id = entrada.banco_id
    WHERE salida.tipo = "traspaso_salida"
      AND origen.usuario_id = :usuario_traspaso_origen
      AND destino.usuario_id = :usuario_traspaso_destino
      AND (
        entrada.descripcion = CONCAT("Traspaso recibido del banco #", origen.id)
        OR entrada.descripcion LIKE CONCAT("Traspaso recibido del banco #", origen.id, ": %")
        OR entrada.descripcion = CONCAT("Traspaso desde ", origen.nombre)
      )
      AND entrada.id = (
          SELECT MIN(entrada_candidata.id)
          FROM movimientos_bancos entrada_candidata
          WHERE entrada_candidata.tipo = "traspaso_entrada"
            AND entrada_candidata.banco_id = destino.id
            AND entrada_candidata.fecha = salida.fecha
            AND entrada_candidata.importe = ABS(salida.importe)
            AND (
                entrada_candidata.descripcion = CONCAT("Traspaso recibido del banco #", origen.id)
                OR entrada_candidata.descripcion LIKE CONCAT("Traspaso recibido del banco #", origen.id, ": %")
                OR entrada_candidata.descripcion = CONCAT("Traspaso desde ", origen.nombre)
            )
            AND entrada_candidata.id > salida.id
      )
';

$consultaOperacionesPeriodo = $conexion->prepare(
    'SELECT * FROM (' . $sqlOperacionesRegistro . ') operaciones
     WHERE fecha >= :fecha_inicio AND fecha < :fecha_fin
     ORDER BY fecha DESC, id DESC'
);
$consultaOperacionesPeriodo->execute([
    'usuario_registro' => $usuarioIdRegistro,
    'usuario_ahorro' => $usuarioIdRegistro,
    'usuario_inversion' => $usuarioIdRegistro,
    'usuario_traspaso_origen' => $usuarioIdRegistro,
    'usuario_traspaso_destino' => $usuarioIdRegistro,
    'fecha_inicio' => $fechaInicioRegistro,
    'fecha_fin' => $fechaFinRegistro
]);
$operacionesPeriodo = $consultaOperacionesPeriodo->fetchAll(PDO::FETCH_ASSOC);

$tiposRegistro = [
    'ingreso' => 'Ingreso',
    'gasto' => 'Gasto',
    'ahorro' => 'Ahorro',
    'inversion' => 'Inversión',
    'traspaso' => 'Traspaso'
];
$categoriasFiltroRegistro = [];
$cuentasFiltroRegistro = [];
foreach ($operacionesPeriodo as $operacion) {
    $categoriasFiltroRegistro[$operacion['concepto']] = $operacion['concepto'];
    if ($operacion['cuenta_origen'] !== null) {
        $cuentasFiltroRegistro[(string) $operacion['banco_origen_id']] =
            $operacion['cuenta_origen'];
    }
    if ($operacion['cuenta_destino'] !== null) {
        $cuentasFiltroRegistro[(string) $operacion['banco_destino_id']] =
            $operacion['cuenta_destino'];
    }
}
natcasesort($categoriasFiltroRegistro);
natcasesort($cuentasFiltroRegistro);

$filtroTipoRegistro = $_GET['tipo'] ?? '';
$filtroCategoriaRegistro = $_GET['categoria'] ?? '';
$filtroCuentaRegistro = $_GET['cuenta'] ?? '';
if (!is_string($filtroTipoRegistro) || !isset($tiposRegistro[$filtroTipoRegistro])) {
    $filtroTipoRegistro = '';
}
if (!is_string($filtroCategoriaRegistro) || !isset($categoriasFiltroRegistro[$filtroCategoriaRegistro])) {
    $filtroCategoriaRegistro = '';
}
if (!is_string($filtroCuentaRegistro) || !isset($cuentasFiltroRegistro[$filtroCuentaRegistro])) {
    $filtroCuentaRegistro = '';
}

$operacionesRegistro = array_values(array_filter(
    $operacionesPeriodo,
    static function (array $operacion) use (
        $filtroTipoRegistro,
        $filtroCategoriaRegistro,
        $filtroCuentaRegistro
    ): bool {
        if ($filtroTipoRegistro !== '' && $operacion['tipo'] !== $filtroTipoRegistro) {
            return false;
        }
        if ($filtroCategoriaRegistro !== '' && $operacion['concepto'] !== $filtroCategoriaRegistro) {
            return false;
        }
        return $filtroCuentaRegistro === ''
            || (string) $operacion['banco_origen_id'] === $filtroCuentaRegistro
            || (string) $operacion['banco_destino_id'] === $filtroCuentaRegistro;
    }
));

$columnasOrdenRegistro = [
    'tipo' => 'tipo',
    'concepto' => 'concepto',
    'importe' => 'importe',
    'fecha' => 'fecha',
    'cuenta_origen' => 'cuenta_origen',
    'cuenta_destino' => 'cuenta_destino',
    'observaciones' => 'observaciones'
];
$ordenRegistro = $_GET['orden'] ?? 'fecha';
$direccionRegistro = strtolower((string) ($_GET['direccion'] ?? 'desc'));
if (!isset($columnasOrdenRegistro[$ordenRegistro])) {
    $ordenRegistro = 'fecha';
}
if (!in_array($direccionRegistro, ['asc', 'desc'], true)) {
    $direccionRegistro = 'desc';
}
usort($operacionesRegistro, static function (array $a, array $b) use (
    $ordenRegistro,
    $direccionRegistro
): int {
    $comparacion = $ordenRegistro === 'importe'
        ? ((float) $a[$ordenRegistro] <=> (float) $b[$ordenRegistro])
        : strnatcasecmp((string) ($a[$ordenRegistro] ?? ''), (string) ($b[$ordenRegistro] ?? ''));
    if ($comparacion === 0) {
        $comparacion = strcmp((string) $a['fecha'], (string) $b['fecha']);
    }
    if ($comparacion === 0) {
        $comparacion = (int) $a['id'] <=> (int) $b['id'];
    }
    return $direccionRegistro === 'asc' ? $comparacion : -$comparacion;
});

function escaparRegistro($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function eurosRegistro($importe): string
{
    return number_format((float) $importe, 2, ',', '.') . ' €';
}

function etiquetaPeriodoRegistro(string $periodo): string
{
    $nombresMeses = [
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
    $partes = explode('-', $periodo);
    if (count($partes) !== 2) {
        return $periodo;
    }
    $mes = (int) $partes[1];
    return isset($nombresMeses[$mes])
        ? ucfirst($nombresMeses[$mes]) . ' de ' . $partes[0]
        : $periodo;
}

function enlaceOrdenRegistro(string $columna, string $ordenActual, string $direccionActual): string
{
    $direccion = $ordenActual === $columna && $direccionActual === 'asc'
        ? 'desc'
        : 'asc';
    $parametros = $_GET;
    $parametros['seccion'] = 'registro';
    $parametros['orden'] = $columna;
    $parametros['direccion'] = $direccion;
    return 'dashboard.php?' . http_build_query($parametros);
}

$mensajeRegistro = $_SESSION['mensaje_registro'] ?? '';
unset($_SESSION['mensaje_registro']);
?>

<div class="registro-contenedor">
    <div class="registro-barra-superior">
        <button
            type="button"
            id="btn-crear-registro-listado"
            class="btn-crear-registro"
        >
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Crear registro
        </button>

        <form class="registro-periodo-filtros" method="GET" action="dashboard.php" id="filtros-periodo-registro">
            <input type="hidden" name="seccion" value="registro">
            <input type="hidden" name="tipo" value="<?= escaparRegistro($filtroTipoRegistro ?? '') ?>">
            <input type="hidden" name="categoria" value="<?= escaparRegistro($filtroCategoriaRegistro ?? '') ?>">
            <input type="hidden" name="cuenta" value="<?= escaparRegistro($filtroCuentaRegistro ?? '') ?>">
            <input type="hidden" name="orden" value="<?= escaparRegistro($ordenRegistro) ?>">
            <input type="hidden" name="direccion" value="<?= escaparRegistro($direccionRegistro) ?>">
            <label>
                <span>Rango</span>
                <select name="rango" id="rango-periodo-registro">
                    <option value="mensual" <?= $rangoRegistro === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                    <option value="anual" <?= $rangoRegistro === 'anual' ? 'selected' : '' ?>>Anual</option>
                </select>
            </label>
            <label>
                <span>Periodo</span>
                <select
                    name="periodo"
                    id="periodo-registro"
                    data-periodos="<?= escaparRegistro($periodosRegistroJson) ?>"
                    data-anios="<?= escaparRegistro($aniosRegistroJson) ?>"
                    data-seleccionado="<?= escaparRegistro($periodoRegistro) ?>"
                >
                    <?php if (!$periodosRegistro): ?>
                        <option value="" selected disabled>No hay periodos con registros</option>
                    <?php elseif ($rangoRegistro === 'anual'): ?>
                        <?php foreach ($aniosRegistro as $anio): ?>
                            <option value="<?= escaparRegistro($anio) ?>" <?= $anio === $periodoRegistro ? 'selected' : '' ?>><?= escaparRegistro($anio) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($periodosRegistro as $periodo): ?>
                            <option value="<?= escaparRegistro($periodo) ?>" <?= $periodo === $periodoRegistro ? 'selected' : '' ?>><?= escaparRegistro(etiquetaPeriodoRegistro($periodo)) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>
        </form>
    </div>

    <?php if ($mensajeRegistro !== ''): ?>
        <p class="mensaje-registro" role="status"><?= escaparRegistro($mensajeRegistro) ?></p>
    <?php endif; ?>

    <article class="registro-tarjeta">
        <header class="registro-cabecera">
            <div>
                <h1>Registro de operaciones</h1>
                <p>Consulta y organiza todos tus movimientos del periodo.</p>
            </div>
            <span><?= count($operacionesRegistro) ?> operaciones</span>
        </header>

        <form class="registro-filtros-listado" method="GET" action="dashboard.php" id="filtros-listado-registro">
            <input type="hidden" name="seccion" value="registro">
            <input type="hidden" name="rango" value="<?= escaparRegistro($rangoRegistro) ?>">
            <input type="hidden" name="periodo" value="<?= escaparRegistro($periodoRegistro) ?>">
            <input type="hidden" name="orden" value="<?= escaparRegistro($ordenRegistro) ?>">
            <input type="hidden" name="direccion" value="<?= escaparRegistro($direccionRegistro) ?>">
            <label>
                Tipo
                <select name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposRegistro as $tipo => $etiqueta): ?>
                        <option value="<?= escaparRegistro($tipo) ?>" <?= $filtroTipoRegistro === $tipo ? 'selected' : '' ?>><?= escaparRegistro($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Categoría / concepto
                <select name="categoria">
                    <option value="">Todos</option>
                    <?php foreach ($categoriasFiltroRegistro as $categoria): ?>
                        <option value="<?= escaparRegistro($categoria) ?>" <?= $filtroCategoriaRegistro === $categoria ? 'selected' : '' ?>><?= escaparRegistro($categoria) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Cuenta
                <select name="cuenta">
                    <option value="">Todas</option>
                    <?php foreach ($cuentasFiltroRegistro as $id => $nombre): ?>
                        <option value="<?= escaparRegistro($id) ?>" <?= $filtroCuentaRegistro === (string) $id ? 'selected' : '' ?>><?= escaparRegistro($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn-secundario">Filtrar</button>
        </form>

        <div class="registro-tabla-contenedor">
            <table class="registro-tabla">
                <thead>
                    <tr>
                        <?php foreach ([
                            'tipo' => 'Tipo',
                            'concepto' => 'Concepto',
                            'importe' => 'Cantidad',
                            'fecha' => 'Fecha',
                            'cuenta_origen' => 'Cuenta origen',
                            'cuenta_destino' => 'Cuenta destino',
                            'observaciones' => 'Observaciones'
                        ] as $columna => $etiqueta): ?>
                            <th scope="col" aria-sort="<?= $ordenRegistro === $columna ? ($direccionRegistro === 'asc' ? 'ascending' : 'descending') : 'none' ?>">
                                <a href="<?= escaparRegistro(enlaceOrdenRegistro($columna, $ordenRegistro, $direccionRegistro)) ?>">
                                    <?= escaparRegistro($etiqueta) ?>
                                    <?php if ($ordenRegistro === $columna): ?>
                                        <i class="bi bi-arrow-<?= $direccionRegistro === 'asc' ? 'up' : 'down' ?>" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operacionesRegistro as $operacion): ?>
                        <?php
                        $operacionJson = json_encode(
                            $operacion,
                            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                        );
                        ?>
                        <tr>
                            <td data-label="Tipo"><?= escaparRegistro($tiposRegistro[$operacion['tipo']] ?? $operacion['tipo']) ?></td>
                            <td data-label="Concepto"><?= escaparRegistro($operacion['concepto']) ?></td>
                            <td data-label="Cantidad"><?= eurosRegistro($operacion['importe']) ?></td>
                            <td data-label="Fecha"><?= escaparRegistro(date('d/m/Y', strtotime($operacion['fecha']))) ?></td>
                            <td data-label="Cuenta origen"><?= escaparRegistro($operacion['cuenta_origen']) ?></td>
                            <td data-label="Cuenta destino"><?= escaparRegistro($operacion['cuenta_destino'] ?? 'Sin destino') ?></td>
                            <td data-label="Observaciones"><?= escaparRegistro($operacion['observaciones'] ?: '—') ?></td>
                            <td data-label="Acciones" class="registro-acciones">
                                <button type="button" class="registro-accion" data-accion-registro="editar" data-registro="<?= escaparRegistro($operacionJson) ?>" aria-label="Editar registro" title="Editar"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                                <button type="button" class="registro-accion" data-accion-registro="copiar" data-registro="<?= escaparRegistro($operacionJson) ?>" aria-label="Copiar registro" title="Copiar"><i class="bi bi-copy" aria-hidden="true"></i></button>
                                <form method="POST" action="registro_acciones.php" onsubmit="return window.confirm('¿Seguro que quieres borrar este registro? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="csrf_token" value="<?= escaparRegistro($_SESSION['csrf_token'] ?? '') ?>">
                                    <input type="hidden" name="rango" value="<?= escaparRegistro($rangoRegistro) ?>">
                                    <input type="hidden" name="periodo" value="<?= escaparRegistro($periodoRegistro) ?>">
                                    <input type="hidden" name="accion" value="borrar">
                                    <input type="hidden" name="origen" value="<?= escaparRegistro($operacion['origen']) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $operacion['id'] ?>">
                                    <input type="hidden" name="movimiento_id" value="<?= (int) ($operacion['movimiento_id'] ?? 0) ?>">
                                    <input type="hidden" name="movimiento_destino_id" value="<?= (int) ($operacion['movimiento_destino_id'] ?? 0) ?>">
                                    <button type="submit" class="registro-accion registro-accion-borrar" aria-label="Borrar registro" title="Borrar"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$operacionesRegistro): ?>
                        <tr>
                            <td colspan="8" class="registro-vacio">No hay registros que coincidan con los filtros seleccionados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</div>
