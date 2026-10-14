<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Comprobar que el usuario ha iniciado sesión.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/conexion.php';

$usuarioId = (int) $_SESSION['usuario_id'];

$error = '';
$mensaje = '';

// Crear el token CSRF si todavía no existe.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tokenCsrf = $_SESSION['csrf_token'];

// Recuperar el mensaje de éxito después de una acción.
if (isset($_SESSION['categorias_mensaje'])) {
    $mensaje = $_SESSION['categorias_mensaje'];
    unset($_SESSION['categorias_mensaje']);
}

// Recuperar el mensaje de error después de una acción.
if (isset($_SESSION['categorias_error'])) {
    $error = $_SESSION['categorias_error'];
    unset($_SESSION['categorias_error']);
}

// Obtener las categorías del usuario autenticado.
$consulta = $conexion->prepare(
    'SELECT id, nombre, tipo
     FROM categorias
     WHERE usuario_id = :usuario_id
     ORDER BY nombre ASC'
);

$consulta->execute([
    'usuario_id' => $usuarioId
]);

$categorias = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Separar las categorías por tipo.
$categoriasIngresos = [];
$categoriasGastos = [];

foreach ($categorias as $categoria) {
    if ($categoria['tipo'] === 'ingreso') {
        $categoriasIngresos[] = $categoria;
    } elseif ($categoria['tipo'] === 'gasto') {
        $categoriasGastos[] = $categoria;
    }
}

?>

<div class="categorias-contenedor">

    <header class="categorias-cabecera">
        <div>
            <h1>Categorías</h1>
            <p>Organiza tus ingresos y gastos personales.</p>
        </div>
    </header>

    <?php if ($mensaje !== ''): ?>
        <div
            class="categorias-alerta categorias-alerta-exito"
            role="status">
            <i class="bi bi-check-circle" aria-hidden="true"></i>

            <span>
                <?= htmlspecialchars(
                    $mensaje,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div
            class="categorias-alerta categorias-alerta-error"
            role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>

            <span>
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="categorias-grid">

        <!-- CARD 1: INGRESOS -->
        <section class="categorias-card categorias-card-ingresos">

            <div class="categorias-card-cabecera">
                <div class="categorias-icono categorias-icono-ingresos">
                    <i class="bi bi-arrow-down-left" aria-hidden="true"></i>
                </div>

                <div class="categorias-card-titulo">
                    <h2>Ingresos</h2>
                    <p>Fuentes de dinero que recibes</p>
                </div>

                <span class="categorias-total">
                    <?= count($categoriasIngresos) ?>
                </span>
            </div>

            <div class="categorias-lista">

                <div class="categorias-lista-cabecera">
                    <span>Tipo</span>
                    <span>Nombre</span>
                    <span>Acciones</span>
                </div>

                <?php if (empty($categoriasIngresos)): ?>

                    <div class="categorias-vacio">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <p>Aún no tienes categorías de ingresos.</p>
                    </div>

                <?php else: ?>

                    <?php foreach ($categoriasIngresos as $categoria): ?>

                        <div class="categorias-item">

                            <span class="categorias-tipo categorias-tipo-ingreso">
                                Ingreso
                            </span>

                            <span class="categorias-item-nombre">
                                <?= htmlspecialchars(
                                    $categoria['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                            <div class="categorias-acciones">

                                <button
                                    type="button"
                                    class="categorias-btn-editar"
                                    aria-label="Editar categoría <?= htmlspecialchars(
                                                                        $categoria['nombre'],
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>"
                                    title="Editar categoría"
                                    onclick="editarCategoria(
                            <?= (int) $categoria['id'] ?>,
                            <?= htmlspecialchars(
                                json_encode(
                                    $categoria['nombre'],
                                    JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>,
                            'ingreso'
                        )">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                </button>

                                <form
                                    method="POST"
                                    action="categorias_acciones.php"
                                    class="categorias-form-eliminar"
                                    onsubmit="return confirm('¿Seguro que quieres eliminar esta categoría?');">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                                    $tokenCsrf,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>">

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="borrar">

                                    <input
                                        type="hidden"
                                        name="categoria_id"
                                        value="<?= (int) $categoria['id'] ?>">

                                    <button
                                        type="submit"
                                        class="categorias-btn-eliminar"
                                        aria-label="Eliminar categoría <?= htmlspecialchars(
                                                                            $categoria['nombre'],
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        ) ?>"
                                        title="Eliminar categoría">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                    </button>
                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


        </section>

        <!-- CARD 2: GASTOS -->
        <section class="categorias-card categorias-card-gastos">

            <div class="categorias-card-cabecera">
                <div class="categorias-icono categorias-icono-gastos">
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                </div>

                <div class="categorias-card-titulo">
                    <h2>Gastos</h2>
                    <p>En qué utilizas tu dinero</p>
                </div>

                <span class="categorias-total">
                    <?= count($categoriasGastos) ?>
                </span>
            </div>

            <div class="categorias-lista">

                <div class="categorias-lista-cabecera">
                    <span>Tipo</span>
                    <span>Nombre</span>
                    <span>Acciones</span>
                </div>

                <?php if (empty($categoriasGastos)): ?>

                    <div class="categorias-vacio">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <p>Aún no tienes categorías de gastos.</p>
                    </div>

                <?php else: ?>

                    <?php foreach ($categoriasGastos as $categoria): ?>

                        <div class="categorias-item">

                            <span class="categorias-tipo categorias-tipo-gasto">
                                Gasto
                            </span>

                            <span class="categorias-item-nombre">
                                <?= htmlspecialchars(
                                    $categoria['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                            <div class="categorias-acciones">

                                <button
                                    type="button"
                                    class="categorias-btn-editar"
                                    aria-label="Editar categoría <?= htmlspecialchars(
                                                                        $categoria['nombre'],
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>"
                                    title="Editar categoría"
                                    onclick="editarCategoria(
                            <?= (int) $categoria['id'] ?>,
                            <?= htmlspecialchars(
                                json_encode(
                                    $categoria['nombre'],
                                    JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>,
                            'gasto'
                        )">
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                </button>

                                <form
                                    method="POST"
                                    action="categorias_acciones.php"
                                    class="categorias-form-eliminar"
                                    onsubmit="return confirm('¿Seguro que quieres eliminar esta categoría?');">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                                    $tokenCsrf,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>">

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="borrar">

                                    <input
                                        type="hidden"
                                        name="categoria_id"
                                        value="<?= (int) $categoria['id'] ?>">

                                    <button
                                        type="submit"
                                        class="categorias-btn-eliminar"
                                        aria-label="Eliminar categoría <?= htmlspecialchars(
                                                                            $categoria['nombre'],
                                                                            ENT_QUOTES,
                                                                            'UTF-8'
                                                                        ) ?>"
                                        title="Eliminar categoría">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                    </button>
                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </section>

        <!-- CARD 3: NUEVA CATEGORÍA -->
        <section class="categorias-card categorias-card-nueva">

            <div class="categorias-card-cabecera">
                <div class="categorias-icono categorias-icono-nueva">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                </div>

                <div class="categorias-card-titulo">
                    <h2>Nueva categoría</h2>
                    <p>Añade una categoría personalizada</p>
                </div>
            </div>

            <form
                method="POST"
                action="categorias_acciones.php"
                class="categorias-formulario">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                                $tokenCsrf,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <input
                    type="hidden"
                    name="accion"
                    value="crear">

                <div class="categorias-campo">
                    <label for="categoria-nombre">Nombre</label>

                    <input
                        type="text"
                        id="categoria-nombre"
                        name="nombre"
                        maxlength="100"
                        placeholder="Ej. Alimentación"
                        autocomplete="off"
                        required>
                </div>

                <div class="categorias-campo">
                    <label for="categoria-tipo">Tipo de categoría</label>

                    <select
                        id="categoria-tipo"
                        name="tipo"
                        required>
                        <option value="gasto" selected>Gasto</option>
                        <option value="ingreso">Ingreso</option>
                    </select>
                </div>

                <button
                    type="submit"
                    class="categorias-btn-guardar">
                    <i
                        class="bi bi-plus-circle"
                        aria-hidden="true"></i>

                    Crear categoría
                </button>

            </form>

        </section>

    </div>

</div>