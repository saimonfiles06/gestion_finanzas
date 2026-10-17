
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
                    <option value="mensual">Mensual</option>
                    <option value="anual">Anual</option>
                </select>
            </div>

            <div class="resumen-filtro">
                <label for="filtro-periodo">Período</label>

                <select id="filtro-periodo" name="filtro-periodo"></select>
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

    <!-- El contenido del resumen se implementará posteriormente -->
    <div class="resumen-contenido"></div>

</div>
