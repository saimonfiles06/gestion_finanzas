<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

function volverRegistro(string $mensaje): never
{
    $query = ['seccion' => 'registro'];
    $rango = $_POST['rango'] ?? '';
    $periodo = $_POST['periodo'] ?? '';
    if (is_string($rango) && in_array($rango, ['mensual', 'anual'], true)) {
        $query['rango'] = $rango;
    }
    if (is_string($periodo) && preg_match('/^\d{4}(-\d{2})?$/', $periodo)) {
        $query['periodo'] = $periodo;
    }
    $_SESSION['mensaje_registro'] = $mensaje;
    header('Location: dashboard.php?' . http_build_query($query));
    exit;
}

function cargarParTraspaso(
    PDO $conexion,
    int $salidaId,
    int $entradaId,
    int $usuarioId
): array {
    $consulta = $conexion->prepare(
        'SELECT mb.id, mb.tipo, mb.banco_id, b.nombre AS cuenta,
                mb.importe, mb.fecha, mb.descripcion
         FROM movimientos_bancos mb
         INNER JOIN bancos b ON b.id = mb.banco_id
         WHERE mb.id IN (:salida_id, :entrada_id)
           AND b.usuario_id = :usuario_id
         FOR UPDATE'
    );
    $consulta->execute([
        'salida_id' => $salidaId,
        'entrada_id' => $entradaId,
        'usuario_id' => $usuarioId
    ]);
    $movimientos = [];
    foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $movimiento) {
        $movimientos[(int) $movimiento['id']] = $movimiento;
    }

    $salida = $movimientos[$salidaId] ?? null;
    $entrada = $movimientos[$entradaId] ?? null;
    if (
        !$salida
        || !$entrada
        || $salida['tipo'] !== 'traspaso_salida'
        || $entrada['tipo'] !== 'traspaso_entrada'
        || (int) $salida['banco_id'] === (int) $entrada['banco_id']
        || $salida['fecha'] !== $entrada['fecha']
        || (float) $salida['importe'] >= 0
        || (float) $entrada['importe'] <= 0
        || abs((float) $salida['importe']) !== (float) $entrada['importe']
    ) {
        throw new RuntimeException('No se han encontrado los dos movimientos del traspaso.');
    }

    $salidaGenerada = 'Traspaso enviado al banco #' . $entrada['banco_id'];
    $entradaGenerada = 'Traspaso recibido del banco #' . $salida['banco_id'];
    $salidaAntigua = 'Traspaso a ' . $entrada['cuenta'];
    $entradaAntigua = 'Traspaso desde ' . $salida['cuenta'];
    $descripcionSalida = (string) ($salida['descripcion'] ?? '');
    $descripcionEntrada = (string) ($entrada['descripcion'] ?? '');
    $esFormatoActual =
        strncmp($descripcionSalida, $salidaGenerada, strlen($salidaGenerada)) === 0
        && strncmp($descripcionEntrada, $entradaGenerada, strlen($entradaGenerada)) === 0;
    $esFormatoAntiguo =
        $descripcionSalida === $salidaAntigua
        && $descripcionEntrada === $entradaAntigua;
    if (!$esFormatoActual && !$esFormatoAntiguo) {
        throw new RuntimeException('Los movimientos no forman parte del mismo traspaso.');
    }

    return [$salida, $entrada];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverRegistro('La solicitud no es válida.');
}

$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';
if (
    !is_string($tokenRecibido)
    || !is_string($tokenSesion)
    || $tokenSesion === ''
    || !hash_equals($tokenSesion, $tokenRecibido)
) {
    volverRegistro('La sesión ha caducado. Recarga la página e inténtalo de nuevo.');
}

$accion = $_POST['accion'] ?? '';
$origen = $_POST['origen'] ?? '';
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$movimientoId = filter_var($_POST['movimiento_id'] ?? null, FILTER_VALIDATE_INT);
$movimientoDestinoId = filter_var(
    $_POST['movimiento_destino_id'] ?? null,
    FILTER_VALIDATE_INT
);
$tiposOrigen = ['registro', 'ahorro', 'inversion', 'traspaso'];

if (
    !in_array($accion, ['editar', 'borrar'], true)
    || !is_string($origen)
    || !in_array($origen, $tiposOrigen, true)
    || !$id
) {
    volverRegistro('Los datos del registro no son válidos.');
}

if ($accion === 'editar') {
    $tipo = $_POST['tipo'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $importeTexto = $_POST['importe'] ?? '';
    $descripcionRecibida = $_POST['descripcion'] ?? '';
    if (!is_string($descripcionRecibida)) {
        volverRegistro('Las observaciones no son válidas.');
    }
    $descripcion = trim($descripcionRecibida);
    $bancoId = filter_var($_POST['banco_id'] ?? null, FILTER_VALIDATE_INT);
    $categoriaId = filter_var(
        $_POST['categoria_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $objetivoId = filter_var(
        $_POST['objetivo_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $bancoOrigenId = filter_var(
        $_POST['banco_origen_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $bancoDestinoId = filter_var(
        $_POST['banco_destino_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $fechaObjeto = is_string($fecha)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha)
        : false;
    if (
        !is_string($fecha)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
        || !$fechaObjeto
        || $fechaObjeto->format('Y-m-d') !== $fecha
        || !is_string($importeTexto)
        || !preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $importeTexto)
        || (float) str_replace(',', '.', $importeTexto) <= 0
        || mb_strlen($descripcion) > 255
    ) {
        volverRegistro('Revisa la fecha, la cantidad y las observaciones.');
    }
    $importe = number_format((float) str_replace(',', '.', $importeTexto), 2, '.', '');
    $descripcion = $descripcion !== '' ? $descripcion : null;

    try {
        $conexion->beginTransaction();

        if ($origen === 'registro' && in_array($tipo, ['ingreso', 'gasto'], true)) {
            $consulta = $conexion->prepare(
                'SELECT id, tipo, banco_id, importe, fecha
                 FROM registros
                 WHERE id = :id AND usuario_id = :usuario_id
                 FOR UPDATE'
            );
            $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
            $actual = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$actual || $actual['tipo'] !== $tipo || !$bancoId || !$categoriaId) {
                throw new RuntimeException('No se ha encontrado el registro o el tipo no se puede cambiar.');
            }

            $consulta = $conexion->prepare(
                'SELECT id, permite_ingresos, permite_gastos FROM bancos
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute(['id' => $bancoId, 'usuario_id' => $usuarioId]);
            $bancoSeleccionado = $consulta->fetch(PDO::FETCH_ASSOC);
            if (
                !$bancoSeleccionado
                || ($tipo === 'ingreso' && !(int) $bancoSeleccionado['permite_ingresos'])
                || ($tipo === 'gasto' && !(int) $bancoSeleccionado['permite_gastos'])
            ) {
                throw new RuntimeException('La cuenta seleccionada no existe.');
            }
            $consulta = $conexion->prepare(
                'SELECT id FROM categorias
                 WHERE id = :id AND usuario_id = :usuario_id AND tipo = :tipo'
            );
            $consulta->execute([
                'id' => $categoriaId,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo
            ]);
            if (!$consulta->fetch()) {
                throw new RuntimeException('La categoría no corresponde al tipo del registro.');
            }
            $consulta = $conexion->prepare(
                'SELECT mb.id FROM movimientos_bancos mb
                 INNER JOIN bancos b ON b.id = mb.banco_id
                 WHERE mb.registro_id = :registro_id
                   AND b.usuario_id = :usuario_id
                 FOR UPDATE'
            );
            $consulta->execute([
                'registro_id' => $id,
                'usuario_id' => $usuarioId
            ]);
            if (count($consulta->fetchAll(PDO::FETCH_COLUMN)) !== 1) {
                throw new RuntimeException('No se ha encontrado el movimiento asociado al registro.');
            }

            $consulta = $conexion->prepare(
                'UPDATE registros
                 SET banco_id = :banco_id, categoria_id = :categoria_id,
                     importe = :importe, fecha = :fecha, descripcion = :descripcion
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute([
                'banco_id' => $bancoId,
                'categoria_id' => $categoriaId,
                'importe' => $importe,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'id' => $id,
                'usuario_id' => $usuarioId
            ]);
            $consulta = $conexion->prepare(
                'UPDATE movimientos_bancos mb
                 INNER JOIN bancos b ON b.id = mb.banco_id
                 SET mb.banco_id = :banco_id, mb.tipo = :tipo,
                     mb.importe = :importe, mb.fecha = :fecha,
                     mb.descripcion = :descripcion
                 WHERE mb.registro_id = :registro_id
                   AND b.usuario_id = :usuario_id'
            );
            $consulta->execute([
                'banco_id' => $bancoId,
                'tipo' => $tipo,
                'importe' => $tipo === 'gasto' ? '-' . $importe : $importe,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'registro_id' => $id,
                'usuario_id' => $usuarioId
            ]);
        } elseif ($origen === 'ahorro' && $tipo === 'ahorro' && $bancoId && $objetivoId) {
            $consulta = $conexion->prepare(
                'SELECT id, objetivo_id, banco_id, importe, fecha
                 FROM aportaciones_objetivos
                 WHERE id = :id AND usuario_id = :usuario_id
                 FOR UPDATE'
            );
            $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
            $actual = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$actual) {
                throw new RuntimeException('No se ha encontrado la aportación de ahorro.');
            }
            $consulta = $conexion->prepare(
                'SELECT id FROM objetivos WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute(['id' => $objetivoId, 'usuario_id' => $usuarioId]);
            if (!$consulta->fetch()) {
                throw new RuntimeException('El objetivo seleccionado no existe.');
            }
            $consulta = $conexion->prepare(
                'SELECT id, permite_gastos FROM bancos
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute(['id' => $bancoId, 'usuario_id' => $usuarioId]);
            $bancoSeleccionado = $consulta->fetch(PDO::FETCH_ASSOC);
            if (
                !$bancoSeleccionado
                || !(int) $bancoSeleccionado['permite_gastos']
                || !$movimientoId
            ) {
                throw new RuntimeException('No se ha encontrado la cuenta o el movimiento bancario asociado.');
            }
            $consulta = $conexion->prepare(
                'SELECT mb.id FROM movimientos_bancos mb
                 INNER JOIN bancos b ON b.id = mb.banco_id
                 WHERE mb.id = :id AND mb.banco_id = :banco_id
                   AND mb.tipo = "gasto" AND mb.importe = -:importe
                   AND mb.fecha = :fecha AND mb.registro_id IS NULL
                   AND b.usuario_id = :usuario_id
                 FOR UPDATE'
            );
            $consulta->execute([
                'id' => $movimientoId,
                'banco_id' => $actual['banco_id'],
                'importe' => $actual['importe'],
                'fecha' => $actual['fecha'],
                'usuario_id' => $usuarioId
            ]);
            if (!$consulta->fetch()) {
                throw new RuntimeException('El movimiento bancario no corresponde a esta aportación.');
            }

            $consulta = $conexion->prepare(
                'UPDATE objetivos SET saldo_actual = saldo_actual - :importe
                 WHERE id = :objetivo_id AND usuario_id = :usuario_id'
            );
            $consulta->execute([
                'importe' => $actual['importe'],
                'objetivo_id' => $actual['objetivo_id'],
                'usuario_id' => $usuarioId
            ]);
            $consulta = $conexion->prepare(
                'UPDATE objetivos SET saldo_actual = saldo_actual + :importe,
                    fecha_saldo = :fecha
                 WHERE id = :objetivo_id AND usuario_id = :usuario_id'
            );
            $consulta->execute([
                'importe' => $importe,
                'fecha' => $fecha,
                'objetivo_id' => $objetivoId,
                'usuario_id' => $usuarioId
            ]);
            $consulta = $conexion->prepare(
                'UPDATE aportaciones_objetivos
                 SET objetivo_id = :objetivo_id, banco_id = :banco_id,
                     importe = :importe, fecha = :fecha, descripcion = :descripcion
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute([
                'objetivo_id' => $objetivoId,
                'banco_id' => $bancoId,
                'importe' => $importe,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'id' => $id,
                'usuario_id' => $usuarioId
            ]);
            $consulta = $conexion->prepare(
                'UPDATE movimientos_bancos
                 SET banco_id = :banco_id, importe = :importe,
                     fecha = :fecha, descripcion = :descripcion
                 WHERE id = :movimiento_id AND tipo = "gasto" AND registro_id IS NULL'
            );
            $consulta->execute([
                'banco_id' => $bancoId,
                'importe' => '-' . $importe,
                'fecha' => $fecha,
                'descripcion' => $descripcion ?? 'Aportación a objetivo',
                'movimiento_id' => $movimientoId
            ]);
        } elseif ($origen === 'inversion' && $tipo === 'inversion' && $bancoId) {
            $consulta = $conexion->prepare(
                'SELECT id, banco_id, importe, fecha FROM inversiones
                 WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE'
            );
            $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
            $actual = $consulta->fetch(PDO::FETCH_ASSOC);
            if (!$actual) {
                throw new RuntimeException('No se ha encontrado la inversión.');
            }
            $consulta = $conexion->prepare(
                'SELECT id, permite_gastos FROM bancos
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute(['id' => $bancoId, 'usuario_id' => $usuarioId]);
            $bancoSeleccionado = $consulta->fetch(PDO::FETCH_ASSOC);
            if (
                !$bancoSeleccionado
                || !(int) $bancoSeleccionado['permite_gastos']
                || !$movimientoId
            ) {
                throw new RuntimeException('No se ha encontrado la cuenta o el movimiento bancario asociado.');
            }
            $consulta = $conexion->prepare(
                'SELECT mb.id FROM movimientos_bancos mb
                 INNER JOIN bancos b ON b.id = mb.banco_id
                 WHERE mb.id = :id AND mb.banco_id = :banco_id
                   AND mb.tipo = "gasto" AND mb.importe = -:importe
                   AND mb.fecha = :fecha AND mb.registro_id IS NULL
                   AND b.usuario_id = :usuario_id
                 FOR UPDATE'
            );
            $consulta->execute([
                'id' => $movimientoId,
                'banco_id' => $actual['banco_id'],
                'importe' => $actual['importe'],
                'fecha' => $actual['fecha'],
                'usuario_id' => $usuarioId
            ]);
            if (!$consulta->fetch()) {
                throw new RuntimeException('El movimiento bancario no corresponde a esta inversión.');
            }
            $consulta = $conexion->prepare(
                'UPDATE inversiones
                 SET banco_id = :banco_id, importe = :importe,
                     fecha = :fecha, descripcion = :descripcion
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $consulta->execute([
                'banco_id' => $bancoId,
                'importe' => $importe,
                'fecha' => $fecha,
                'descripcion' => $descripcion,
                'id' => $id,
                'usuario_id' => $usuarioId
            ]);
            $consulta = $conexion->prepare(
                'UPDATE movimientos_bancos
                 SET banco_id = :banco_id, importe = :importe,
                     fecha = :fecha, descripcion = :descripcion
                 WHERE id = :movimiento_id AND tipo = "gasto" AND registro_id IS NULL'
            );
            $consulta->execute([
                'banco_id' => $bancoId,
                'importe' => '-' . $importe,
                'fecha' => $fecha,
                'descripcion' => $descripcion ?? 'Inversión',
                'movimiento_id' => $movimientoId
            ]);
        } elseif (
            $origen === 'traspaso'
            && $tipo === 'traspaso'
            && $bancoOrigenId
            && $bancoDestinoId
            && $bancoOrigenId !== $bancoDestinoId
            && $movimientoId
            && $movimientoDestinoId
            && $id === $movimientoId
        ) {
            [$salidaActual, $entradaActual] = cargarParTraspaso(
                $conexion,
                $movimientoId,
                $movimientoDestinoId,
                $usuarioId
            );
            $consulta = $conexion->prepare(
                'SELECT id, nombre, permite_ingresos, permite_gastos FROM bancos
                 WHERE usuario_id = :usuario_id AND id IN (:origen_id, :destino_id)'
            );
            $consulta->execute([
                'usuario_id' => $usuarioId,
                'origen_id' => $bancoOrigenId,
                'destino_id' => $bancoDestinoId
            ]);
            $bancos = [];
            foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $banco) {
                $bancos[(int) $banco['id']] = $banco;
            }
            if (
                !isset($bancos[$bancoOrigenId], $bancos[$bancoDestinoId])
                || !(int) $bancos[$bancoOrigenId]['permite_gastos']
                || !(int) $bancos[$bancoDestinoId]['permite_ingresos']
            ) {
                throw new RuntimeException('Una de las cuentas del traspaso no existe.');
            }
            if (
                (int) $salidaActual['banco_id'] !== $bancoOrigenId
                || (int) $entradaActual['banco_id'] !== $bancoDestinoId
            ) {
                throw new RuntimeException('Las cuentas origen y destino no corresponden al traspaso.');
            }
            if ($descripcion !== null && mb_strlen($descripcion) > 190) {
                throw new RuntimeException('Las observaciones del traspaso no pueden superar los 190 caracteres.');
            }
            $textoSalida = 'Traspaso enviado al banco #' . $bancoDestinoId;
            $textoEntrada = 'Traspaso recibido del banco #' . $bancoOrigenId;
            if ($descripcion !== null) {
                $textoSalida .= ': ' . $descripcion;
                $textoEntrada .= ': ' . $descripcion;
            }
            $consulta = $conexion->prepare(
                'UPDATE movimientos_bancos SET banco_id = :banco_id, importe = :importe,
                    fecha = :fecha, descripcion = :descripcion
                 WHERE id = :id AND tipo = "traspaso_salida"'
            );
            $consulta->execute([
                'banco_id' => $bancoOrigenId,
                'importe' => '-' . $importe,
                'fecha' => $fecha,
                'descripcion' => $textoSalida,
                'id' => $movimientoId
            ]);
            $consulta = $conexion->prepare(
                'UPDATE movimientos_bancos SET banco_id = :banco_id, importe = :importe,
                    fecha = :fecha, descripcion = :descripcion
                 WHERE id = :id AND tipo = "traspaso_entrada"'
            );
            $consulta->execute([
                'banco_id' => $bancoDestinoId,
                'importe' => $importe,
                'fecha' => $fecha,
                'descripcion' => $textoEntrada,
                'id' => $movimientoDestinoId
            ]);
        } else {
            throw new RuntimeException('No se puede cambiar el tipo de una operación existente.');
        }

        $conexion->commit();
        volverRegistro('El registro se ha actualizado correctamente.');
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        error_log('Error al editar un registro: ' . $e->getMessage());
        volverRegistro($e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se ha podido actualizar el registro.');
    }
}

try {
    $conexion->beginTransaction();

    if ($origen === 'registro') {
        $consulta = $conexion->prepare(
            'SELECT r.id FROM registros r
             WHERE r.id = :id AND r.usuario_id = :usuario_id FOR UPDATE'
        );
        $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        if (!$consulta->fetch()) {
            throw new RuntimeException('No se ha encontrado el registro.');
        }
        $consulta = $conexion->prepare(
            'SELECT mb.id FROM movimientos_bancos mb
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE mb.registro_id = :registro_id
               AND b.usuario_id = :usuario_id
             FOR UPDATE'
        );
        $consulta->execute([
            'registro_id' => $id,
            'usuario_id' => $usuarioId
        ]);
        if (count($consulta->fetchAll(PDO::FETCH_COLUMN)) !== 1) {
            throw new RuntimeException('No se ha encontrado el movimiento asociado al registro.');
        }
        $consulta = $conexion->prepare(
            'DELETE mb FROM movimientos_bancos mb
             INNER JOIN registros r ON r.id = mb.registro_id
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE r.id = :id AND r.usuario_id = :usuario_id
               AND b.usuario_id = :usuario_movimiento'
        );
        $consulta->execute([
            'id' => $id,
            'usuario_id' => $usuarioId,
            'usuario_movimiento' => $usuarioId
        ]);
        $consulta = $conexion->prepare(
            'DELETE FROM registros WHERE id = :id AND usuario_id = :usuario_id'
        );
        $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se ha encontrado el registro.');
        }
    } elseif ($origen === 'ahorro') {
        if (!$movimientoId) {
            throw new RuntimeException('No se ha encontrado el movimiento asociado al ahorro.');
        }
        $consulta = $conexion->prepare(
            'SELECT objetivo_id, banco_id, importe, fecha FROM aportaciones_objetivos
             WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE'
        );
        $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $aporte = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$aporte) {
            throw new RuntimeException('No se ha encontrado la aportación de ahorro.');
        }
        $consulta = $conexion->prepare(
            'SELECT mb.id FROM movimientos_bancos mb
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE mb.id = :id AND mb.banco_id = :banco_id
               AND mb.tipo = "gasto" AND mb.importe = -:importe
               AND mb.fecha = :fecha AND mb.registro_id IS NULL
               AND b.usuario_id = :usuario_id FOR UPDATE'
        );
        $consulta->execute([
            'id' => $movimientoId,
            'banco_id' => $aporte['banco_id'],
            'importe' => $aporte['importe'],
            'fecha' => $aporte['fecha'],
            'usuario_id' => $usuarioId
        ]);
        if (!$consulta->fetch()) {
            throw new RuntimeException('El movimiento bancario no corresponde a esta aportación.');
        }
        $consulta = $conexion->prepare(
            'DELETE mb FROM movimientos_bancos mb
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE mb.id = :id AND mb.banco_id = :banco_id
               AND mb.tipo = "gasto" AND mb.registro_id IS NULL
               AND b.usuario_id = :usuario_id'
        );
        $consulta->execute([
            'id' => $movimientoId,
            'banco_id' => $aporte['banco_id'],
            'usuario_id' => $usuarioId
        ]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se ha podido borrar el movimiento bancario del ahorro.');
        }
        $consulta = $conexion->prepare(
            'UPDATE objetivos SET saldo_actual = saldo_actual - :importe
             WHERE id = :objetivo_id AND usuario_id = :usuario_id'
        );
        $consulta->execute([
            'importe' => $aporte['importe'],
            'objetivo_id' => $aporte['objetivo_id'],
            'usuario_id' => $usuarioId
        ]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se ha podido actualizar el saldo del objetivo.');
        }
        $consulta = $conexion->prepare(
            'DELETE FROM aportaciones_objetivos
             WHERE id = :id AND usuario_id = :usuario_id'
        );
        $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se ha podido borrar la aportación.');
        }
    } elseif ($origen === 'inversion') {
        if (!$movimientoId) {
            throw new RuntimeException('No se ha encontrado el movimiento asociado a la inversión.');
        }
        $consulta = $conexion->prepare(
            'SELECT banco_id, importe, fecha FROM inversiones
             WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE'
        );
        $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $inversion = $consulta->fetch(PDO::FETCH_ASSOC);
        if (!$inversion) {
            throw new RuntimeException('No se ha encontrado la inversión.');
        }
        $consulta = $conexion->prepare(
            'SELECT mb.id FROM movimientos_bancos mb
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE mb.id = :id AND mb.banco_id = :banco_id
               AND mb.tipo = "gasto" AND mb.importe = -:importe
               AND mb.fecha = :fecha AND mb.registro_id IS NULL
               AND b.usuario_id = :usuario_id FOR UPDATE'
        );
        $consulta->execute([
            'id' => $movimientoId,
            'banco_id' => $inversion['banco_id'],
            'importe' => $inversion['importe'],
            'fecha' => $inversion['fecha'],
            'usuario_id' => $usuarioId
        ]);
        if (!$consulta->fetch()) {
            throw new RuntimeException('El movimiento bancario no corresponde a esta inversión.');
        }
        $consulta = $conexion->prepare(
            'DELETE mb FROM movimientos_bancos mb
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE mb.id = :id AND mb.banco_id = :banco_id
               AND mb.tipo = "gasto" AND mb.registro_id IS NULL
               AND b.usuario_id = :usuario_id'
        );
        $consulta->execute([
            'id' => $movimientoId,
            'banco_id' => $inversion['banco_id'],
            'usuario_id' => $usuarioId
        ]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se ha podido borrar el movimiento bancario de la inversión.');
        }
        $consulta = $conexion->prepare(
            'DELETE FROM inversiones WHERE id = :id AND usuario_id = :usuario_id'
        );
        $consulta->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        if ($consulta->rowCount() !== 1) {
            throw new RuntimeException('No se ha podido borrar la inversión.');
        }
    } else {
        if (!$movimientoId || !$movimientoDestinoId || $id !== $movimientoId) {
            throw new RuntimeException('No se han encontrado ambos movimientos del traspaso.');
        }
        cargarParTraspaso(
            $conexion,
            $movimientoId,
            $movimientoDestinoId,
            $usuarioId
        );
        $consulta = $conexion->prepare(
            'DELETE mb FROM movimientos_bancos mb
             INNER JOIN bancos b ON b.id = mb.banco_id
             WHERE mb.id IN (:salida_id, :entrada_id)
               AND mb.tipo IN ("traspaso_salida", "traspaso_entrada")
               AND b.usuario_id = :usuario_id'
        );
        $consulta->execute([
            'salida_id' => $movimientoId,
            'entrada_id' => $movimientoDestinoId,
            'usuario_id' => $usuarioId
        ]);
        if ($consulta->rowCount() !== 2) {
            throw new RuntimeException('No se han podido borrar los dos movimientos del traspaso.');
        }
    }

    $conexion->commit();
    volverRegistro('El registro se ha borrado correctamente.');
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log('Error al borrar un registro: ' . $e->getMessage());
    volverRegistro($e instanceof RuntimeException
        ? $e->getMessage()
        : 'No se ha podido borrar el registro.');
}
