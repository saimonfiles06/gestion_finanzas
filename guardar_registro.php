
<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

function volverConMensaje(string $mensaje): never
{
    if (($_POST['volver_a_registro'] ?? '') === '1') {
        $_SESSION['mensaje_registro'] = $mensaje;
        $query = ['seccion' => 'registro'];
        $rango = $_POST['rango'] ?? '';
        $periodo = $_POST['periodo'] ?? '';
        if (is_string($rango) && in_array($rango, ['mensual', 'anual'], true)) {
            $query['rango'] = $rango;
        }
        if (is_string($periodo) && preg_match('/^\d{4}(-\d{2})?$/', $periodo)) {
            $query['periodo'] = $periodo;
        }
        header('Location: dashboard.php?' . http_build_query($query));
    } else {
        $_SESSION['mensaje_resumen'] = $mensaje;
        header('Location: dashboard.php?seccion=resumen');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    volverConMensaje('Método de solicitud no válido.');
}

$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($tokenRecibido) ||
    !is_string($tokenSesion) ||
    $tokenSesion === '' ||
    !hash_equals($tokenSesion, $tokenRecibido)
) {
    volverConMensaje('La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
}

$tipo = $_POST['tipo'] ?? '';
$fecha = $_POST['fecha'] ?? '';
$importeTexto = $_POST['importe'] ?? '';
$descripcion = trim($_POST['descripcion'] ?? '');

$tiposPermitidos = [
    'ingreso',
    'gasto',
    'ahorro',
    'inversion',
    'traspaso'
];

if (!in_array($tipo, $tiposPermitidos, true)) {
    volverConMensaje('El tipo de operación no es válido.');
}

if (
    !is_string($fecha) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
) {
    volverConMensaje('La fecha no es válida.');
}

$fechaObjeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

if (!$fechaObjeto || $fechaObjeto->format('Y-m-d') !== $fecha) {
    volverConMensaje('La fecha indicada no existe.');
}

if (
    !is_string($importeTexto) ||
    !preg_match('/^\d{1,10}([.,]\d{1,2})?$/', $importeTexto)
) {
    volverConMensaje('Introduce un importe válido.');
}

$importe = (float) str_replace(',', '.', $importeTexto);

if (!is_finite($importe) || $importe <= 0 || $importe > 9999999999.99) {
    volverConMensaje('El importe debe ser mayor que cero y no superar el límite permitido.');
}

if (strlen($descripcion) > 255) {
    volverConMensaje('Las notas no pueden superar los 255 caracteres.');
}

$descripcion = $descripcion !== '' ? $descripcion : null;

try {
    $conexion->beginTransaction();

    /*
     * INGRESOS Y GASTOS
     */
    if ($tipo === 'ingreso' || $tipo === 'gasto') {
        $bancoId = filter_var(
            $_POST['banco_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $categoriaId = filter_var(
            $_POST['categoria_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$bancoId || !$categoriaId) {
            throw new RuntimeException(
                'Selecciona un banco y una categoría.'
            );
        }

        $consulta = $conexion->prepare(
            'SELECT id, permite_ingresos, permite_gastos
             FROM bancos
             WHERE id = :banco_id AND usuario_id = :usuario_id
               AND eliminado = 0
             FOR UPDATE'
        );

        $consulta->execute([
            'banco_id' => $bancoId,
            'usuario_id' => $usuarioId
        ]);

        $banco = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$banco) {
            throw new RuntimeException('El banco seleccionado no existe.');
        }

        if (
            ($tipo === 'ingreso' && !(int) $banco['permite_ingresos']) ||
            ($tipo === 'gasto' && !(int) $banco['permite_gastos'])
        ) {
            throw new RuntimeException(
                'Este banco no permite la operación seleccionada.'
            );
        }

        $consulta = $conexion->prepare(
            'SELECT id
             FROM categorias
             WHERE id = :categoria_id
               AND usuario_id = :usuario_id
               AND tipo = :tipo'
        );

        $consulta->execute([
            'categoria_id' => $categoriaId,
            'usuario_id' => $usuarioId,
            'tipo' => $tipo
        ]);

        if (!$consulta->fetch()) {
            throw new RuntimeException(
                'La categoría no existe o no corresponde al tipo de operación.'
            );
        }

        $consulta = $conexion->prepare(
            'INSERT INTO registros
                (usuario_id, banco_id, categoria_id, tipo,
                 importe, fecha, descripcion)
             VALUES
                (:usuario_id, :banco_id, :categoria_id, :tipo,
                 :importe, :fecha, :descripcion)'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'banco_id' => $bancoId,
            'categoria_id' => $categoriaId,
            'tipo' => $tipo,
            'importe' => number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => $descripcion
        ]);

        $registroId = (int) $conexion->lastInsertId();

        $consulta = $conexion->prepare(
            'INSERT INTO movimientos_bancos
                (banco_id, tipo, importe, fecha, descripcion, registro_id)
             VALUES
                (:banco_id, :tipo, :importe, :fecha, :descripcion, :registro_id)'
        );

        $consulta->execute([
            'banco_id' => $bancoId,
            'tipo' => $tipo,
            'importe' => $tipo === 'gasto'
                ? '-' . number_format($importe, 2, '.', '')
                : number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => $descripcion,
            'registro_id' => $registroId
        ]);
    }

    /*
     * AHORRO: aportación a un objetivo.
     */
    if ($tipo === 'ahorro') {
        $objetivoId = filter_var(
            $_POST['objetivo_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $bancoId = filter_var(
            $_POST['banco_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$objetivoId || !$bancoId) {
            throw new RuntimeException(
                'Selecciona un objetivo y un banco.'
            );
        }

        $consulta = $conexion->prepare(
            'SELECT id, permite_gastos
             FROM bancos
             WHERE id = :banco_id AND usuario_id = :usuario_id
               AND eliminado = 0
             FOR UPDATE'
        );

        $consulta->execute([
            'banco_id' => $bancoId,
            'usuario_id' => $usuarioId
        ]);

        $banco = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$banco || !(int) $banco['permite_gastos']) {
            throw new RuntimeException(
                'El banco no existe o no permite salidas de dinero.'
            );
        }

        $consulta = $conexion->prepare(
            'SELECT id, saldo_actual
             FROM objetivos
             WHERE id = :objetivo_id AND usuario_id = :usuario_id
             FOR UPDATE'
        );

        $consulta->execute([
            'objetivo_id' => $objetivoId,
            'usuario_id' => $usuarioId
        ]);

        $objetivo = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$objetivo) {
            throw new RuntimeException(
                'El objetivo seleccionado no existe.'
            );
        }

        $consulta = $conexion->prepare(
            'INSERT INTO aportaciones_objetivos
                (usuario_id, objetivo_id, banco_id, importe, fecha, descripcion)
             VALUES
                (:usuario_id, :objetivo_id, :banco_id, :importe, :fecha, :descripcion)'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'objetivo_id' => $objetivoId,
            'banco_id' => $bancoId,
            'importe' => number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => $descripcion
        ]);

        $consulta = $conexion->prepare(
            'UPDATE objetivos
             SET saldo_actual = saldo_actual + :importe,
                 fecha_saldo = :fecha
             WHERE id = :objetivo_id AND usuario_id = :usuario_id'
        );

        $consulta->execute([
            'importe' => number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'objetivo_id' => $objetivoId,
            'usuario_id' => $usuarioId
        ]);

        $consulta = $conexion->prepare(
            'INSERT INTO movimientos_bancos
                (banco_id, tipo, importe, fecha, descripcion, registro_id)
             VALUES
                (:banco_id, "gasto", :importe, :fecha, :descripcion, NULL)'
        );

        $consulta->execute([
            'banco_id' => $bancoId,
            'importe' => '-' . number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => $descripcion ?? 'Aportación a objetivo'
        ]);
    }

    /*
     * INVERSIÓN: se registra como inversión y como salida bancaria.
     */
    if ($tipo === 'inversion') {
        $bancoId = filter_var(
            $_POST['banco_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$bancoId) {
            throw new RuntimeException('Selecciona un banco.');
        }

        $consulta = $conexion->prepare(
            'SELECT id, permite_gastos
             FROM bancos
             WHERE id = :banco_id AND usuario_id = :usuario_id
               AND eliminado = 0
             FOR UPDATE'
        );

        $consulta->execute([
            'banco_id' => $bancoId,
            'usuario_id' => $usuarioId
        ]);

        $banco = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$banco || !(int) $banco['permite_gastos']) {
            throw new RuntimeException(
                'El banco no existe o no permite salidas de dinero.'
            );
        }

        $consulta = $conexion->prepare(
            'INSERT INTO inversiones
                (usuario_id, banco_id, importe, fecha, descripcion)
             VALUES
                (:usuario_id, :banco_id, :importe, :fecha, :descripcion)'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'banco_id' => $bancoId,
            'importe' => number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => $descripcion
        ]);

        $consulta = $conexion->prepare(
            'INSERT INTO movimientos_bancos
                (banco_id, tipo, importe, fecha, descripcion, registro_id)
             VALUES
                (:banco_id, "gasto", :importe, :fecha, :descripcion, NULL)'
        );

        $consulta->execute([
            'banco_id' => $bancoId,
            'importe' => '-' . number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => $descripcion ?? 'Inversión'
        ]);
    }

    /*
     * TRASPASO: salida de un banco y entrada en otro.
     */
    if ($tipo === 'traspaso') {
        $origenId = filter_var(
            $_POST['banco_origen_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $destinoId = filter_var(
            $_POST['banco_destino_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$origenId || !$destinoId || $origenId === $destinoId) {
            throw new RuntimeException(
                'Selecciona dos bancos distintos para el traspaso.'
            );
        }

        // Bloqueamos ambos bancos en orden de ID para reducir conflictos.
        $idsOrdenados = [$origenId, $destinoId];
        sort($idsOrdenados, SORT_NUMERIC);

        $consulta = $conexion->prepare(
            'SELECT id, permite_ingresos, permite_gastos
             FROM bancos
             WHERE id = :banco_id AND usuario_id = :usuario_id
               AND eliminado = 0
             FOR UPDATE'
        );

        $bancos = [];

        foreach ($idsOrdenados as $id) {
            $consulta->execute([
                'banco_id' => $id,
                'usuario_id' => $usuarioId
            ]);

            $banco = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$banco) {
                throw new RuntimeException(
                    'Uno de los bancos seleccionados no existe.'
                );
            }

            $bancos[$id] = $banco;
        }

        if (
            !(int) $bancos[$origenId]['permite_gastos'] ||
            !(int) $bancos[$destinoId]['permite_ingresos']
        ) {
            throw new RuntimeException(
                'Los bancos seleccionados no permiten este traspaso.'
            );
        }

        $consulta = $conexion->prepare(
            'INSERT INTO movimientos_bancos
                (banco_id, tipo, importe, fecha, descripcion, registro_id)
             VALUES
                (:banco_id, :tipo, :importe, :fecha, :descripcion, NULL)'
        );

        $textoSalida = 'Traspaso enviado al banco #' . $destinoId;
        $textoEntrada = 'Traspaso recibido del banco #' . $origenId;

        if ($descripcion !== null) {
            $textoSalida .= ': ' . $descripcion;
            $textoEntrada .= ': ' . $descripcion;
        }

        $consulta->execute([
            'banco_id' => $origenId,
            'tipo' => 'traspaso_salida',
            'importe' => number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => mb_substr($textoSalida, 0, 255)
        ]);

        $consulta->execute([
            'banco_id' => $destinoId,
            'tipo' => 'traspaso_entrada',
            'importe' => number_format($importe, 2, '.', ''),
            'fecha' => $fecha,
            'descripcion' => mb_substr($textoEntrada, 0, 255)
        ]);
    }

    $conexion->commit();

    volverConMensaje('La operación se ha guardado correctamente.');

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log('Error al guardar una operación: ' . $e->getMessage());

    if ($e instanceof RuntimeException) {
        volverConMensaje($e->getMessage());
    }

    volverConMensaje(
        'No se ha podido guardar la operación. Comprueba la base de datos e inténtalo de nuevo.'
    );
}
?>