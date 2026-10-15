
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
| Token CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tokenCsrfResumen = $_SESSION['csrf_token'];
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

                <select id="filtro-periodo" name="filtro-periodo">
                    <!-- Las opciones se generan con JavaScript -->
                </select>
            </div>

        </div>

    </div>

    <!-- Mensaje de estado del formulario -->
    <p
        id="mensaje-registro"
        class="mensaje-registro"
        role="status"
        aria-live="polite"
        hidden
    ></p>

    <!-- Formulario de creación de registros -->
    <div
        id="panel-crear-registro"
        class="panel-crear-registro"
        hidden
    >

        <div class="formulario-registro-encabezado">
            <div>
                <h3>Crear registro</h3>
                <p>Introduce los datos del movimiento financiero.</p>
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

        <form id="form-crear-registro" autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($tokenCsrfResumen, ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="formulario-registro-grid">

                <!-- Tipo de registro -->
                <div class="campo-registro">
                    <label for="tipo-registro">Tipo de registro</label>

                    <select
                        id="tipo-registro"
                        name="tipo"
                        required
                    >
                        <option value="">Selecciona un tipo</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="gasto">Gasto</option>
                        <option value="ahorro">Ahorro</option>
                        <option value="inversion">Inversión</option>
                        <option value="traspaso">Traspaso</option>
                    </select>
                </div>

                <!-- Fecha -->
                <div class="campo-registro">
                    <label for="fecha-registro">Fecha</label>

                    <input
                        type="date"
                        id="fecha-registro"
                        name="fecha"
                        required
                    >
                </div>

                <!-- Cantidad -->
                <div class="campo-registro">
                    <label for="cantidad-registro">Cantidad (€)</label>

                    <input
                        type="number"
                        id="cantidad-registro"
                        name="importe"
                        min="0.01"
                        step="0.01"
                        placeholder="0,00"
                        required
                    >
                </div>

                <!-- Categoría -->
                <div class="campo-registro">
                    <label for="categoria-registro">Categoría</label>

                    <select
                        id="categoria-registro"
                        name="categoria_id"
                        required
                        disabled
                    >
                        <option value="">Selecciona primero el tipo</option>

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
                    >
                        Selecciona el tipo de registro para ver las categorías disponibles.
                    </small>
                </div>

                <!-- Cuenta de origen -->
                <div class="campo-registro">
                    <label for="banco-registro">Cuenta de origen</label>

                    <select
                        id="banco-registro"
                        name="banco_id"
                        required
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

                <!-- Observaciones -->
                <div class="campo-registro campo-registro-completo">
                    <label for="observaciones-registro">Observaciones</label>

                    <textarea
                        id="observaciones-registro"
                        name="descripcion"
                        rows="3"
                        maxlength="1000"
                        placeholder="Añade una descripción si lo necesitas..."
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

    <!-- Aquí se incorporará posteriormente el contenido del resumen -->
    <div class="resumen-contenido">
        <!-- Los datos del resumen se implementarán más adelante. -->
    </div>

</div>