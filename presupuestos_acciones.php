<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

function volverPresupuestos(string $tipoMensaje, string $mensaje, ?int $presupuestoId = null): never
{
    $_SESSION[$tipoMensaje === 'error' ? 'presupuestos_error' : 'presupuestos_mensaje'] = $mensaje;
    $query = ['seccion' => 'presupuestos'];
    if ($presupuestoId !== null && $presupuestoId > 0) {
        $query['editar'] = $presupuestoId;
    }
    header('Location: dashboard.php?' . http_build_query($query));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverPresupuestos('error', 'La solicitud no es válida.');
}

$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';
if (
    !is_string($tokenRecibido)
    || !is_string($tokenSesion)
    || $tokenSesion === ''
    || !hash_equals($tokenSesion, $tokenRecibido)
) {
    volverPresupuestos(
        'error',
        'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.'
    );
}

$accion = $_POST['accion'] ?? '';
$presupuestoId = filter_var(
    $_POST['presupuesto_id'] ?? null,
    FILTER_VALIDATE_INT
);
if (
    !is_string($accion)
    || !in_array($accion, ['crear', 'editar', 'borrar'], true)
) {
    volverPresupuestos('error', 'La operación solicitada no es válida.');
}

if ($accion === 'borrar') {
    if ($presupuestoId === false || $presupuestoId === null || $presupuestoId < 1) {
        volverPresupuestos('error', 'El presupuesto seleccionado no es válido.');
    }

    try {
        $consulta = $conexion->prepare(
            'DELETE FROM presupuestos
             WHERE id = :id AND usuario_id = :usuario_id'
        );
        $consulta->execute([
            'id' => $presupuestoId,
            'usuario_id' => $usuarioId
        ]);
    } catch (PDOException $e) {
        error_log('Error al eliminar un presupuesto: ' . $e->getMessage());
        volverPresupuestos(
            'error',
            'No se ha podido eliminar el presupuesto. Inténtalo de nuevo.'
        );
    }
    if ($consulta->rowCount() !== 1) {
        volverPresupuestos(
            'error',
            'El presupuesto no existe o no tienes permiso para eliminarlo.'
        );
    }

    volverPresupuestos('mensaje', 'El presupuesto se ha eliminado correctamente.');
}

$categoriaId = filter_var(
    $_POST['categoria_id'] ?? null,
    FILTER_VALIDATE_INT
);
$limiteTexto = $_POST['limite'] ?? '';
$periodo = $_POST['periodo'] ?? '';

if (
    $categoriaId === false
    || $categoriaId === null
    || $categoriaId < 1
    || !is_string($limiteTexto)
    || !preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $limiteTexto)
    || !is_string($periodo)
    || !in_array($periodo, ['mensual', 'anual'], true)
) {
    volverPresupuestos(
        'error',
        'Selecciona una categoría, un límite válido y un periodo.'
    );
}

$limiteNumerico = (float) str_replace(',', '.', $limiteTexto);
if (!is_finite($limiteNumerico) || $limiteNumerico <= 0 || $limiteNumerico > 9999999999.99) {
    volverPresupuestos(
        'error',
        'El límite debe ser mayor que cero y no superar 9.999.999.999,99 €.',
        $accion === 'editar' ? (int) $presupuestoId : null
    );
}
$limite = number_format($limiteNumerico, 2, '.', '');
if ((float) $limite <= 0) {
    volverPresupuestos(
        'error',
        'El límite debe ser mayor que cero.',
        $accion === 'editar' ? (int) $presupuestoId : null
    );
}

if ($accion === 'editar' && (
    $presupuestoId === false
    || $presupuestoId === null
    || $presupuestoId < 1
)) {
    volverPresupuestos('error', 'El presupuesto seleccionado no es válido.');
}

$edicionId = $accion === 'editar' ? (int) $presupuestoId : null;

try {
    $consulta = $conexion->prepare(
        'SELECT id FROM categorias
         WHERE id = :id AND usuario_id = :usuario_id AND tipo = "gasto"'
    );
    $consulta->execute([
        'id' => $categoriaId,
        'usuario_id' => $usuarioId
    ]);
    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'La categoría no existe, no te pertenece o no es una categoría de gasto.'
        );
    }

    if ($accion === 'crear') {
        $consulta = $conexion->prepare(
            'INSERT INTO presupuestos (
                usuario_id, categoria_id, limite, periodo
             ) VALUES (
                :usuario_id, :categoria_id, :limite, :periodo
             )'
        );
        $consulta->execute([
            'usuario_id' => $usuarioId,
            'categoria_id' => $categoriaId,
            'limite' => $limite,
            'periodo' => $periodo
        ]);
        volverPresupuestos(
            'mensaje',
            'El presupuesto se ha creado correctamente.'
        );
    }

    $consulta = $conexion->prepare(
        'SELECT id
         FROM presupuestos
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    $consulta->execute([
        'id' => $edicionId,
        'usuario_id' => $usuarioId
    ]);
    if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'El presupuesto no existe o no tienes permiso para modificarlo.'
        );
    }

    $consulta = $conexion->prepare(
        'UPDATE presupuestos
         SET categoria_id = :categoria_id,
             limite = :limite,
             periodo = :periodo
         WHERE id = :id AND usuario_id = :usuario_id'
    );
    $consulta->execute([
        'categoria_id' => $categoriaId,
        'limite' => $limite,
        'periodo' => $periodo,
        'id' => $edicionId,
        'usuario_id' => $usuarioId
    ]);
    volverPresupuestos(
        'mensaje',
        'El presupuesto se ha actualizado correctamente.'
    );
} catch (RuntimeException $e) {
    volverPresupuestos('error', $e->getMessage(), $edicionId);
} catch (PDOException $e) {
    error_log('Error al guardar un presupuesto: ' . $e->getMessage());
    if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062) {
        volverPresupuestos(
            'error',
            'Ya existe un presupuesto para esa categoría y ese periodo.',
            $edicionId
        );
    }
    volverPresupuestos(
        'error',
        'No se ha podido guardar el presupuesto. Inténtalo de nuevo.',
        $edicionId
    );
}
