<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirigirObjetivos(): void
{
    header('Location: dashboard.php?seccion=objetivos');
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

// Aceptar únicamente solicitudes POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigirObjetivos();
}

// Validar CSRF.
$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($tokenRecibido) ||
    !is_string($tokenSesion) ||
    $tokenSesion === '' ||
    !hash_equals($tokenSesion, $tokenRecibido)
) {
    $_SESSION['objetivos_error'] =
        'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.';

    redirigirObjetivos();
}

// Validar importes decimales.
function validarImporteObjetivo(mixed $valor, bool $permitirCero = false): float
{
    if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
        throw new RuntimeException('Introduce una cantidad válida.');
    }

    $valor = (string) $valor;

    if (
        $valor === '' ||
        !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $valor)
    ) {
        throw new RuntimeException(
            'Introduce una cantidad válida con un máximo de dos decimales.'
        );
    }

    $cantidad = (float) $valor;

    if ($permitirCero ? $cantidad < 0 : $cantidad <= 0) {
        throw new RuntimeException(
            $permitirCero
                ? 'El saldo inicial no puede ser negativo.'
                : 'La cantidad objetivo debe ser mayor que cero.'
        );
    }

    return round($cantidad, 2);
}

// Validar fechas con formato YYYY-MM-DD.
function validarFechaObjetivo(mixed $valor, string $campo): string
{
    if (!is_string($valor) || $valor === '') {
        throw new RuntimeException(
            'Debes indicar ' . $campo . '.'
        );
    }

    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
    $erroresFecha = DateTime::getLastErrors();

    if (
        !$fecha ||
        ($erroresFecha !== false &&
            ($erroresFecha['warning_count'] > 0 ||
             $erroresFecha['error_count'] > 0)) ||
        $fecha->format('Y-m-d') !== $valor
    ) {
        throw new RuntimeException(
            'La fecha indicada para ' . $campo . ' no es válida.'
        );
    }

    return $valor;
}

$accion = $_POST['accion'] ?? '';

try {

    /*
    |--------------------------------------------------------------------------
    | CREAR OBJETIVO
    |--------------------------------------------------------------------------
    */
    if ($accion === 'crear') {

        $nombre = trim($_POST['nombre'] ?? '');

        if ($nombre === '') {
            throw new RuntimeException(
                'Introduce el nombre del objetivo.'
            );
        }

        if (mb_strlen($nombre) > 100) {
            throw new RuntimeException(
                'El nombre no puede superar los 100 caracteres.'
            );
        }

        $cantidadObjetivo = validarImporteObjetivo(
            $_POST['cantidad_objetivo'] ?? '',
            false
        );

        $saldoActual = validarImporteObjetivo(
            $_POST['saldo_actual'] ?? '',
            true
        );

        $fechaObjetivo = validarFechaObjetivo(
            $_POST['fecha_objetivo'] ?? '',
            'la fecha objetivo'
        );

        $fechaSaldo = validarFechaObjetivo(
            $_POST['fecha_saldo'] ?? '',
            'la fecha del saldo'
        );

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
                :cantidad_objetivo,
                :fecha_objetivo,
                :saldo_actual,
                :fecha_saldo,
                0,
                1
            )'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'nombre' => $nombre,
            'cantidad_objetivo' => $cantidadObjetivo,
            'fecha_objetivo' => $fechaObjetivo,
            'saldo_actual' => $saldoActual,
            'fecha_saldo' => $fechaSaldo
        ]);

        $_SESSION['objetivos_mensaje'] =
            'El objetivo se ha creado correctamente.';

    /*
    |--------------------------------------------------------------------------
    | CONFIGURAR FONDO DE EMERGENCIA
    |--------------------------------------------------------------------------
    */
    } elseif ($accion === 'configurar_fondo') {

        $objetivoId = filter_var(
            $_POST['objetivo_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $objetivoId === false ||
            $objetivoId === null ||
            $objetivoId < 1
        ) {
            throw new RuntimeException(
                'El fondo de emergencia seleccionado no es válido.'
            );
        }

        $cantidadObjetivo = validarImporteObjetivo(
            $_POST['cantidad_objetivo'] ?? '',
            false
        );

        $fechaObjetivo = validarFechaObjetivo(
            $_POST['fecha_objetivo'] ?? '',
            'la fecha objetivo'
        );

        $consulta = $conexion->prepare(
            'SELECT id, configurado
             FROM objetivos
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND es_fondo_emergencia = 1
             LIMIT 1'
        );

        $consulta->execute([
            'id' => $objetivoId,
            'usuario_id' => $usuarioId
        ]);

        $fondo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$fondo || (bool) $fondo['configurado']) {
            throw new RuntimeException(
                'El fondo de emergencia no existe o ya está configurado.'
            );
        }

        $consulta = $conexion->prepare(
            'UPDATE objetivos
             SET nombre = :nombre,
                 cantidad_objetivo = :cantidad_objetivo,
                 fecha_objetivo = :fecha_objetivo,
                 saldo_actual = 0.00,
                 fecha_saldo = :fecha_saldo,
                 configurado = 1
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND es_fondo_emergencia = 1
               AND configurado = 0'
        );

        $consulta->execute([
            'nombre' => 'Fondo de emergencia',
            'cantidad_objetivo' => $cantidadObjetivo,
            'fecha_objetivo' => $fechaObjetivo,
            'fecha_saldo' => date('Y-m-d'),
            'id' => $objetivoId,
            'usuario_id' => $usuarioId
        ]);

        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException(
                'No se ha podido configurar el fondo de emergencia.'
            );
        }

        $_SESSION['objetivos_mensaje'] =
            'El fondo de emergencia se ha configurado correctamente.';

    /*
    |--------------------------------------------------------------------------
    | EDITAR OBJETIVO
    |--------------------------------------------------------------------------
    */
    } elseif ($accion === 'editar') {

        $objetivoId = filter_var(
            $_POST['objetivo_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $objetivoId === false ||
            $objetivoId === null ||
            $objetivoId < 1
        ) {
            throw new RuntimeException(
                'El objetivo seleccionado no es válido.'
            );
        }

        $consulta = $conexion->prepare(
            'SELECT id, nombre, es_fondo_emergencia, configurado
             FROM objetivos
             WHERE id = :id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );

        $consulta->execute([
            'id' => $objetivoId,
            'usuario_id' => $usuarioId
        ]);

        $objetivo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$objetivo) {
            throw new RuntimeException(
                'El objetivo no existe o no tienes permiso para modificarlo.'
            );
        }

        if (
            (bool) $objetivo['es_fondo_emergencia'] &&
            !(bool) $objetivo['configurado']
        ) {
            throw new RuntimeException(
                'Primero debes configurar el fondo de emergencia.'
            );
        }

        $cantidadObjetivo = validarImporteObjetivo(
            $_POST['cantidad_objetivo'] ?? '',
            false
        );

        $fechaObjetivo = validarFechaObjetivo(
            $_POST['fecha_objetivo'] ?? '',
            'la fecha objetivo'
        );

        if ((bool) $objetivo['es_fondo_emergencia']) {

            // El nombre del fondo de emergencia nunca se modifica.
            $consulta = $conexion->prepare(
                'UPDATE objetivos
                 SET nombre = :nombre,
                     cantidad_objetivo = :cantidad_objetivo,
                     fecha_objetivo = :fecha_objetivo
                 WHERE id = :id
                   AND usuario_id = :usuario_id
                   AND es_fondo_emergencia = 1'
            );

            $consulta->execute([
                'nombre' => 'Fondo de emergencia',
                'cantidad_objetivo' => $cantidadObjetivo,
                'fecha_objetivo' => $fechaObjetivo,
                'id' => $objetivoId,
                'usuario_id' => $usuarioId
            ]);

        } else {

            $nombre = trim($_POST['nombre'] ?? '');

            if ($nombre === '') {
                throw new RuntimeException(
                    'Introduce el nombre del objetivo.'
                );
            }

            if (mb_strlen($nombre) > 100) {
                throw new RuntimeException(
                    'El nombre no puede superar los 100 caracteres.'
                );
            }

            $consulta = $conexion->prepare(
                'UPDATE objetivos
                 SET nombre = :nombre,
                     cantidad_objetivo = :cantidad_objetivo,
                     fecha_objetivo = :fecha_objetivo
                 WHERE id = :id
                   AND usuario_id = :usuario_id
                   AND es_fondo_emergencia = 0'
            );

            $consulta->execute([
                'nombre' => $nombre,
                'cantidad_objetivo' => $cantidadObjetivo,
                'fecha_objetivo' => $fechaObjetivo,
                'id' => $objetivoId,
                'usuario_id' => $usuarioId
            ]);
        }

        $_SESSION['objetivos_mensaje'] =
            'El objetivo se ha actualizado correctamente.';

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR OBJETIVO
    |--------------------------------------------------------------------------
    */
    } elseif ($accion === 'borrar') {

        $objetivoId = filter_var(
            $_POST['objetivo_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $objetivoId === false ||
            $objetivoId === null ||
            $objetivoId < 1
        ) {
            throw new RuntimeException(
                'El objetivo seleccionado no es válido.'
            );
        }

        $consulta = $conexion->prepare(
            'DELETE FROM objetivos
             WHERE id = :id
               AND usuario_id = :usuario_id
               AND es_fondo_emergencia = 0'
        );

        $consulta->execute([
            'id' => $objetivoId,
            'usuario_id' => $usuarioId
        ]);

        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException(
                'No se puede eliminar el objetivo seleccionado.'
            );
        }

        $_SESSION['objetivos_mensaje'] =
            'El objetivo se ha eliminado correctamente.';

    } else {
        throw new RuntimeException(
            'La operación solicitada no es válida.'
        );
    }

} catch (RuntimeException $e) {

    $_SESSION['objetivos_error'] = $e->getMessage();

} catch (PDOException $e) {

    error_log('Error en objetivos: ' . $e->getMessage());

    $_SESSION['objetivos_error'] =
        'No se ha podido completar la operación. Inténtalo de nuevo.';
}

redirigirObjetivos();
?>