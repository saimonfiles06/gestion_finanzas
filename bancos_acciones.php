<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/conexion.php';

/**
 * Redirige al apartado Bancos mostrando el resultado.
 */
function volverBancos(string $resultado): void
{
    header(
        'Location: dashboard.php?seccion=bancos&resultado=' .
        rawurlencode($resultado)
    );
    exit;
}

/**
 * Comprueba que una fecha tenga el formato YYYY-MM-DD
 * y que sea una fecha real.
 */
function fechaValida(string $fecha): bool
{
    $objetoFecha = DateTime::createFromFormat('!Y-m-d', $fecha);

    return $objetoFecha !== false
        && $objetoFecha->format('Y-m-d') === $fecha;
}

/**
 * Valida importes con hasta diez dígitos enteros
 * y dos decimales opcionales.
 */
function importeValido($valor): bool
{
    if (!is_string($valor) && !is_numeric($valor)) {
        return false;
    }

    $valor = str_replace(',', '.', trim((string) $valor));

    return preg_match(
        '/^-?\d{1,10}(?:\.\d{1,2})?$/',
        $valor
    ) === 1;
}

/**
 * Obtiene el saldo actual de un banco.
 *
 * El saldo se calcula sumando el saldo inicial
 * y todos sus movimientos registrados.
 */
function obtenerSaldo(PDO $conexion, int $bancoId): float
{
    $consulta = $conexion->prepare(
        'SELECT
            b.saldo_inicial + COALESCE(SUM(m.importe), 0)
         FROM bancos b
         LEFT JOIN movimientos_bancos m
            ON m.banco_id = b.id
         WHERE b.id = ?
         GROUP BY b.id, b.saldo_inicial'
    );

    $consulta->execute([$bancoId]);

    $saldo = $consulta->fetchColumn();

    return $saldo !== false ? (float) $saldo : 0.0;
}

/**
 * Comprueba que un banco pertenece al usuario actual
 * y bloquea su fila hasta finalizar la transacción.
 */
function bloquearBanco(
    PDO $conexion,
    int $bancoId,
    int $usuarioId
): bool {
    $consulta = $conexion->prepare(
        'SELECT id
         FROM bancos
        WHERE id = ? AND usuario_id = ? AND eliminado = 0
         FOR UPDATE'
    );

    $consulta->execute([$bancoId, $usuarioId]);

    return $consulta->fetchColumn() !== false;
}

// Solo se permiten peticiones POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?seccion=bancos');
    exit;
}

// Validación del token CSRF.
$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($tokenRecibido)
    || !is_string($tokenSesion)
    || $tokenSesion === ''
    || !hash_equals($tokenSesion, $tokenRecibido)
) {
    volverBancos('error');
}

$usuarioId = (int) $_SESSION['usuario_id'];
$accion = $_POST['accion'] ?? '';

try {
    switch ($accion) {

        // ==========================================
        // CREAR UN BANCO
        // ==========================================
        case 'crear':
            $nombre = $_POST['nombre'] ?? '';
            $saldoInicial = $_POST['saldo_inicial'] ?? '';
            $fechaInicial = $_POST['fecha_inicial'] ?? '';

            if (
                !is_string($nombre)
                || trim($nombre) === ''
                || mb_strlen(trim($nombre)) > 100
                || !is_string($fechaInicial)
                || !importeValido($saldoInicial)
                || !fechaValida($fechaInicial)
            ) {
                volverBancos('datos_invalidos');
            }

            $nombre = trim($nombre);

            $saldoInicial = str_replace(
                ',',
                '.',
                trim((string) $saldoInicial)
            );

            $consulta = $conexion->prepare(
                'INSERT INTO bancos (
                    usuario_id,
                    nombre,
                    saldo_inicial,
                    fecha_inicial
                ) VALUES (?, ?, ?, ?)'
            );

            $consulta->execute([
                $usuarioId,
                $nombre,
                $saldoInicial,
                $fechaInicial
            ]);

            volverBancos('creado');
            break;

        // ==========================================
        // ACTUALIZAR PERMISOS DE INGRESOS Y GASTOS
        // ==========================================
        case 'actualizar_uso':
            $bancoId = filter_var(
                $_POST['banco_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $permiteIngresos = $_POST['permite_ingresos'] ?? '0';
            $permiteGastos = $_POST['permite_gastos'] ?? '0';

            if (
                !$bancoId
                || $bancoId < 1
                || !in_array(
                    (string) $permiteIngresos,
                    ['0', '1'],
                    true
                )
                || !in_array(
                    (string) $permiteGastos,
                    ['0', '1'],
                    true
                )
            ) {
                volverBancos('datos_invalidos');
            }

            $consulta = $conexion->prepare(
                'UPDATE bancos
                 SET permite_ingresos = ?,
                     permite_gastos = ?
                 WHERE id = ? AND usuario_id = ? AND eliminado = 0'
            );

            $consulta->execute([
                (int) $permiteIngresos,
                (int) $permiteGastos,
                $bancoId,
                $usuarioId
            ]);

            // rowCount() puede ser cero si los valores no cambian.
            if ($consulta->rowCount() === 0) {
                $comprobar = $conexion->prepare(
                    'SELECT id
                     FROM bancos
                     WHERE id = ? AND usuario_id = ? AND eliminado = 0'
                );

                $comprobar->execute([$bancoId, $usuarioId]);

                if ($comprobar->fetchColumn() === false) {
                    volverBancos('banco_no_valido');
                }
            }

            volverBancos('uso_actualizado');
            break;

        // ==========================================
        // AJUSTAR EL SALDO DE UN BANCO
        // ==========================================
        case 'ajustar_saldo':
            $bancoId = filter_var(
                $_POST['banco_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $saldoReal = $_POST['saldo_real'] ?? '';
            $fecha = $_POST['fecha'] ?? '';
            $motivo = $_POST['motivo'] ?? '';

            if (
                !$bancoId
                || $bancoId < 1
                || !is_string($fecha)
                || !is_string($motivo)
                || !importeValido($saldoReal)
                || !fechaValida($fecha)
                || mb_strlen(trim($motivo)) > 255
            ) {
                volverBancos('datos_invalidos');
            }

            $saldoReal = (float) str_replace(
                ',',
                '.',
                trim((string) $saldoReal)
            );

            $motivo = trim($motivo);

            $conexion->beginTransaction();

            if (!bloquearBanco($conexion, $bancoId, $usuarioId)) {
                $conexion->rollBack();
                volverBancos('banco_no_valido');
            }

            $saldoActual = obtenerSaldo($conexion, $bancoId);

            $diferencia = round($saldoReal - $saldoActual, 2);

            if (abs($diferencia) >= 0.005) {
                $descripcion = $motivo !== ''
                    ? $motivo
                    : 'Ajuste de saldo';

                $insertar = $conexion->prepare(
                    'INSERT INTO movimientos_bancos (
                        banco_id,
                        tipo,
                        importe,
                        fecha,
                        descripcion
                    ) VALUES (?, ?, ?, ?, ?)'
                );

                $insertar->execute([
                    $bancoId,
                    'ajuste',
                    number_format($diferencia, 2, '.', ''),
                    $fecha,
                    $descripcion
                ]);
            }

            $conexion->commit();

            volverBancos('saldo_ajustado');
            break;

        // ==========================================
        // TRASPASAR DINERO ENTRE DOS BANCOS
        // ==========================================
        case 'traspasar':
            $origenId = filter_var(
                $_POST['origen_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $destinoId = filter_var(
                $_POST['destino_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $importe = $_POST['importe'] ?? '';
            $fecha = $_POST['fecha'] ?? '';

            if (
                !$origenId
                || !$destinoId
                || $origenId < 1
                || $destinoId < 1
                || $origenId === $destinoId
                || !is_string($fecha)
                || !importeValido($importe)
                || (float) str_replace(
                    ',',
                    '.',
                    (string) $importe
                ) <= 0
                || !fechaValida($fecha)
            ) {
                volverBancos('datos_invalidos');
            }

            $importeNumerico = (float) str_replace(
                ',',
                '.',
                (string) $importe
            );

            $importe = number_format(
                $importeNumerico,
                2,
                '.',
                ''
            );

            // Evitar importes positivos que se redondeen a cero.
            if ((float) $importe <= 0) {
                volverBancos('datos_invalidos');
            }

            $conexion->beginTransaction();

            // Bloquear las cuentas por orden de ID.
            $ids = [$origenId, $destinoId];
            sort($ids);

            $bloquear = $conexion->prepare(
                'SELECT
                    id,
                    nombre,
                    permite_ingresos,
                    permite_gastos
                 FROM bancos
                 WHERE usuario_id = ? AND id IN (?, ?)
                   AND eliminado = 0
                 ORDER BY id
                 FOR UPDATE'
            );

            $bloquear->execute([
                $usuarioId,
                $ids[0],
                $ids[1]
            ]);

            $cuentas = $bloquear->fetchAll(PDO::FETCH_ASSOC);
            $porId = [];

            foreach ($cuentas as $cuenta) {
                $porId[(int) $cuenta['id']] = $cuenta;
            }

            if (
                !isset($porId[$origenId])
                || !isset($porId[$destinoId])
            ) {
                $conexion->rollBack();
                volverBancos('banco_no_valido');
            }

            $origen = $porId[$origenId];
            $destino = $porId[$destinoId];

            if (
                !(int) $origen['permite_gastos']
                || !(int) $destino['permite_ingresos']
            ) {
                $conexion->rollBack();
                volverBancos('permisos_incompatibles');
            }

            $saldoOrigen = obtenerSaldo($conexion, $origenId);

            if ($saldoOrigen + 0.000001 < (float) $importe) {
                $conexion->rollBack();
                volverBancos('saldo_insuficiente');
            }

            $insertar = $conexion->prepare(
                'INSERT INTO movimientos_bancos (
                    banco_id,
                    tipo,
                    importe,
                    fecha,
                    descripcion
                ) VALUES (?, ?, ?, ?, ?)'
            );

            // Registrar la salida en la cuenta de origen.
            $insertar->execute([
                $origenId,
                'traspaso_salida',
                '-' . $importe,
                $fecha,
                'Traspaso a ' . $destino['nombre']
            ]);

            // Registrar la entrada en la cuenta de destino.
            $insertar->execute([
                $destinoId,
                'traspaso_entrada',
                $importe,
                $fecha,
                'Traspaso desde ' . $origen['nombre']
            ]);

            $conexion->commit();

            volverBancos('traspaso_realizado');
            break;

        // ==========================================
        // ELIMINAR UN BANCO SIN SALDO
        // ==========================================
        case 'eliminar':
            $bancoId = filter_var(
                $_POST['banco_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$bancoId || $bancoId < 1) {
                volverBancos('banco_no_valido');
            }

            $conexion->beginTransaction();

            // Verificar la propiedad y bloquear el banco.
            if (!bloquearBanco($conexion, $bancoId, $usuarioId)) {
                $conexion->rollBack();
                volverBancos('banco_no_valido');
            }

            // Calcular el saldo actual, incluidos los movimientos.
            $saldoActual = obtenerSaldo($conexion, $bancoId);

            // Los saldos se almacenan con dos decimales.
            if (round($saldoActual, 2) != 0.0) {
                $conexion->rollBack();
                volverBancos('saldo_no_cero');
            }

            // Archivar para conservar las relaciones y el historial financiero.
            $eliminar = $conexion->prepare(
                'UPDATE bancos
                 SET eliminado = 1
                 WHERE id = ? AND usuario_id = ? AND eliminado = 0'
            );

            $eliminar->execute([
                $bancoId,
                $usuarioId
            ]);

            if ($eliminar->rowCount() !== 1) {
                $conexion->rollBack();
                volverBancos('banco_no_valido');
            }

            $conexion->commit();

            volverBancos('eliminado');
            break;

        // ==========================================
        // ACCIÓN NO RECONOCIDA
        // ==========================================
        default:
            volverBancos('datos_invalidos');
    }
} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log($e->getMessage());

    volverBancos('error');
}
?>