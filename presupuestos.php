<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo '<p>Debes iniciar sesión para consultar los presupuestos.</p>';
    return;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioIdPresupuestos = (int) $_SESSION['usuario_id'];
$consultaPresupuestos = $conexion->prepare(
    'SELECT
        p.id,
        p.categoria_id,
        p.limite,
        p.periodo,
        c.nombre AS categoria,
        COALESCE(SUM(r.importe), 0) AS gastado
     FROM presupuestos p
     INNER JOIN categorias c ON c.id = p.categoria_id
     LEFT JOIN registros r
        ON r.usuario_id = p.usuario_id
       AND r.categoria_id = p.categoria_id
       AND r.tipo = "gasto"
       AND r.fecha >= CASE
            WHEN p.periodo = "mensual"
                THEN DATE_FORMAT(CURDATE(), "%Y-%m-01")
            ELSE MAKEDATE(YEAR(CURDATE()), 1)
       END
       AND r.fecha < CASE
            WHEN p.periodo = "mensual"
                THEN DATE_ADD(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 1 MONTH)
            ELSE MAKEDATE(YEAR(CURDATE()) + 1, 1)
       END
     WHERE p.usuario_id = :usuario_id
     GROUP BY p.id, p.categoria_id, p.limite, p.periodo, c.nombre
     ORDER BY c.nombre ASC, p.periodo ASC'
);
$consultaPresupuestos->execute([
    'usuario_id' => $usuarioIdPresupuestos
]);
$presupuestos = $consultaPresupuestos->fetchAll(PDO::FETCH_ASSOC);

$consultaCategoriasPresupuestos = $conexion->prepare(
    'SELECT id, nombre
     FROM categorias
     WHERE usuario_id = :usuario_id AND tipo = "gasto"
     ORDER BY nombre ASC'
);
$consultaCategoriasPresupuestos->execute([
    'usuario_id' => $usuarioIdPresupuestos
]);
$categoriasGastoPresupuestos = $consultaCategoriasPresupuestos->fetchAll(
    PDO::FETCH_ASSOC
);

$edicionPresupuestoId = filter_var(
    $_GET['editar'] ?? null,
    FILTER_VALIDATE_INT
);
$presupuestoEnEdicion = null;
if ($edicionPresupuestoId) {
    foreach ($presupuestos as $presupuesto) {
        if ((int) $presupuesto['id'] === $edicionPresupuestoId) {
            $presupuestoEnEdicion = $presupuesto;
            break;
        }
    }
    if (!$presupuestoEnEdicion) {
        $edicionPresupuestoId = null;
    }
}

$mensajePresupuestos = $_SESSION['presupuestos_mensaje'] ?? '';
$errorPresupuestos = $_SESSION['presupuestos_error'] ?? '';
unset($_SESSION['presupuestos_mensaje'], $_SESSION['presupuestos_error']);

$mesPresupuestos = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo',
    4 => 'abril', 5 => 'mayo', 6 => 'junio',
    7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
    10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$periodoActualPresupuestos = ucfirst(
    $mesPresupuestos[(int) date('n')]
) . ' ' . date('Y');
$avisosExcesoPresupuestos = [];
foreach ($presupuestos as $presupuestoAviso) {
    $limiteAviso = (float) $presupuestoAviso['limite'];
    $gastadoAviso = (float) $presupuestoAviso['gastado'];
    if ($limiteAviso > 0 && $gastadoAviso > $limiteAviso) {
        $avisosExcesoPresupuestos[] = [
            'categoria' => $presupuestoAviso['categoria'],
            'porcentaje' => $gastadoAviso / $limiteAviso * 100,
            'gastado' => $gastadoAviso,
            'limite' => $limiteAviso
        ];
    }
}

function escaparPresupuestos($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function eurosPresupuestos($importe): string
{
    return number_format((float) $importe, 2, ',', '.') . ' €';
}
?>

<div class="presupuestos-contenedor">
    <header class="presupuestos-cabecera">
        <h1>Presupuestos</h1>
        <p>Controla el gasto de tus categorías mes a mes o durante todo el año.</p>
    </header>

    <?php if ($mensajePresupuestos !== ''): ?>
        <div class="presupuestos-alerta presupuestos-alerta-exito" role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span><?= escaparPresupuestos($mensajePresupuestos) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorPresupuestos !== ''): ?>
        <div class="presupuestos-alerta presupuestos-alerta-error" role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <span><?= escaparPresupuestos($errorPresupuestos) ?></span>
        </div>
    <?php endif; ?>

    <div class="presupuestos-layout">
        <section class="presupuestos-listado" aria-labelledby="presupuestos-listado-titulo">
            <div class="presupuestos-seccion-cabecera">
                <div>
                    <h2 id="presupuestos-listado-titulo">Presupuestos creados</h2>
                    <p>Seguimiento del periodo en curso</p>
                </div>
                <span class="presupuestos-contador"><?= count($presupuestos) ?></span>
            </div>

            <?php foreach ($avisosExcesoPresupuestos as $aviso): ?>
                <div class="presupuestos-alerta presupuestos-alerta-exceso" role="status">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    <span>
                        La categoría <strong><?= escaparPresupuestos($aviso['categoria']) ?></strong>
                        ha alcanzado el <?= number_format($aviso['porcentaje'], 1, ',', '.') ?>%:
                        <?= eurosPresupuestos($aviso['gastado']) ?> de
                        <?= eurosPresupuestos($aviso['limite']) ?>.
                    </span>
                </div>
            <?php endforeach; ?>

            <?php foreach ($presupuestos as $presupuesto): ?>
                <?php
                $limite = (float) $presupuesto['limite'];
                $gastado = (float) $presupuesto['gastado'];
                $porcentaje = $limite > 0 ? $gastado / $limite * 100 : 0;
                $porcentajeVisual = min(100, max(0, $porcentaje));
                $sobrepasado = $gastado > $limite;
                ?>

                <article class="presupuesto-card<?= $sobrepasado ? ' presupuesto-card-excedido' : '' ?>">
                    <div class="presupuesto-cabecera">
                        <div>
                            <h3><?= escaparPresupuestos($presupuesto['categoria']) ?></h3>
                            <span class="presupuesto-periodo">
                                <?= $presupuesto['periodo'] === 'mensual'
                                    ? 'Mensual · ' . $periodoActualPresupuestos
                                    : 'Anual · ' . date('Y') ?>
                            </span>
                        </div>
                        <strong class="presupuesto-porcentaje<?= $sobrepasado ? ' excedido' : '' ?>">
                            <?= number_format($porcentaje, 1, ',', '.') ?>%
                        </strong>
                    </div>

                    <div
                        class="presupuesto-progreso"
                        role="progressbar"
                        aria-label="Gasto de <?= escaparPresupuestos($presupuesto['categoria']) ?>"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="<?= number_format($porcentajeVisual, 1, '.', '') ?>"
                    >
                        <span
                            class="<?= $sobrepasado ? 'excedido' : '' ?>"
                            style="width: <?= number_format($porcentajeVisual, 2, '.', '') ?>%"
                        ></span>
                    </div>

                    <div class="presupuesto-importes">
                        <span>Gastado <strong><?= eurosPresupuestos($gastado) ?></strong></span>
                        <span>Límite <strong><?= eurosPresupuestos($limite) ?></strong></span>
                    </div>

                    <div class="presupuesto-acciones">
                        <a
                            class="presupuesto-btn-editar"
                            href="dashboard.php?seccion=presupuestos&amp;editar=<?= (int) $presupuesto['id'] ?>"
                        >
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                            Editar presupuesto
                        </a>
                        <form
                            method="POST"
                            action="presupuestos_acciones.php"
                            onsubmit="return window.confirm('¿Seguro que quieres borrar este presupuesto?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= escaparPresupuestos($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="accion" value="borrar">
                            <input type="hidden" name="presupuesto_id" value="<?= (int) $presupuesto['id'] ?>">
                            <button type="submit" class="presupuesto-btn-borrar">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                                Borrar
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$presupuestos): ?>
                <div class="presupuestos-vacio">
                    <i class="bi bi-wallet2" aria-hidden="true"></i>
                    <p>Aún no has creado presupuestos.</p>
                </div>
            <?php endif; ?>
        </section>

        <aside class="presupuestos-columna-formulario">
            <section class="presupuestos-panel-formulario">
                <div class="presupuestos-seccion-cabecera">
                    <div class="presupuestos-icono-nuevo" aria-hidden="true">
                        <i class="bi <?= $presupuestoEnEdicion ? 'bi-pencil-square' : 'bi-plus-lg' ?>"></i>
                    </div>
                    <div>
                        <h2><?= $presupuestoEnEdicion ? 'Editar presupuesto' : 'Nuevo presupuesto' ?></h2>
                        <p>Elige una categoría y establece su límite.</p>
                    </div>
                </div>

                <form method="POST" action="presupuestos_acciones.php" class="presupuesto-formulario">
                    <input type="hidden" name="csrf_token" value="<?= escaparPresupuestos($_SESSION['csrf_token'] ?? '') ?>">
                    <input
                        type="hidden"
                        name="accion"
                        value="<?= $presupuestoEnEdicion ? 'editar' : 'crear' ?>"
                    >
                    <?php if ($presupuestoEnEdicion): ?>
                        <input type="hidden" name="presupuesto_id" value="<?= (int) $presupuestoEnEdicion['id'] ?>">
                    <?php endif; ?>

                    <div class="presupuesto-campo">
                        <label for="presupuesto-categoria">Categoría de gasto</label>
                        <select id="presupuesto-categoria" name="categoria_id" required>
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categoriasGastoPresupuestos as $categoria): ?>
                                <option
                                    value="<?= (int) $categoria['id'] ?>"
                                    <?= $presupuestoEnEdicion && (int) $presupuestoEnEdicion['categoria_id'] === (int) $categoria['id'] ? 'selected' : '' ?>
                                >
                                    <?= escaparPresupuestos($categoria['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="presupuesto-campo">
                        <label for="presupuesto-limite">Cantidad límite (€)</label>
                        <input
                            type="number"
                            id="presupuesto-limite"
                            name="limite"
                            min="0.01"
                            max="9999999999.99"
                            step="0.01"
                            placeholder="0,00"
                            value="<?= escaparPresupuestos($presupuestoEnEdicion['limite'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="presupuesto-campo">
                        <label for="presupuesto-periodo">Periodo</label>
                        <select id="presupuesto-periodo" name="periodo" required>
                            <option value="mensual" <?= !$presupuestoEnEdicion || $presupuestoEnEdicion['periodo'] === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                            <option value="anual" <?= $presupuestoEnEdicion && $presupuestoEnEdicion['periodo'] === 'anual' ? 'selected' : '' ?>>Anual</option>
                        </select>
                    </div>

                    <div class="presupuesto-formulario-acciones">
                        <button type="submit" class="presupuesto-btn-principal">
                            <i class="bi bi-save" aria-hidden="true"></i>
                            <?= $presupuestoEnEdicion ? 'Guardar cambios' : 'Crear presupuesto' ?>
                        </button>
                        <?php if ($presupuestoEnEdicion): ?>
                            <a class="presupuesto-btn-cancelar" href="dashboard.php?seccion=presupuestos">
                                Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>
        </aside>
    </div>
</div>
