
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
        hidden
    ></p>

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

        <form id="form-crear-registro" autocomplete="off">

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
                        maxlength="1000"
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

<script>
(function () {
    const botonCrear = document.getElementById('btn-crear-registro');
    const botonCerrar = document.getElementById('btn-cerrar-registro');
    const botonCancelar = document.getElementById('btn-cancelar-registro');

    const panel = document.getElementById('panel-crear-registro');
    const formulario = document.getElementById('form-crear-registro');

    const tipoRegistro = document.getElementById('tipo-registro');
    const fechaRegistro = document.getElementById('fecha-registro');
    const cantidadRegistro = document.getElementById('cantidad-registro');

    const categoriaRegistro = document.getElementById('categoria-registro');
    const objetivoRegistro = document.getElementById('objetivo-registro');
    const bancoRegistro = document.getElementById('banco-registro');

    const bancoOrigen = document.getElementById('banco-origen-traspaso');
    const bancoDestino = document.getElementById('banco-destino-traspaso');

    const observaciones = document.getElementById('observaciones-registro');
    const ayudaCategoria = document.getElementById('ayuda-categoria-registro');
    const ayudaTraspaso = document.getElementById('ayuda-traspaso');

    const mensaje = document.getElementById('mensaje-registro');
    const descripcionFormulario = document.getElementById('descripcion-formulario-registro');

    const filtroRango = document.getElementById('filtro-rango');
    const filtroPeriodo = document.getElementById('filtro-periodo');

    if (
        !botonCrear ||
        !panel ||
        !formulario ||
        formulario.dataset.inicializado === 'true'
    ) {
        return;
    }

    formulario.dataset.inicializado = 'true';

    const grupos = Array.from(
        formulario.querySelectorAll('[data-grupo]')
    );

    /*
    |--------------------------------------------------------------------------
    | Mostrar y ocultar grupos
    |--------------------------------------------------------------------------
    */

    function mostrarGrupo(nombre, visible) {
        grupos.forEach(function (grupo) {
            if (grupo.dataset.grupo !== nombre) {
                return;
            }

            grupo.hidden = !visible;

            grupo.querySelectorAll('input, select, textarea').forEach(function (campo) {
                campo.disabled = !visible;

                if (campo.name !== 'descripcion') {
                    campo.required = visible;
                }
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Actualizar los campos según el tipo
    |--------------------------------------------------------------------------
    */

    function actualizarFormulario() {
        const tipo = tipoRegistro.value;

        // Ocultar todos los campos específicos antes de mostrar los necesarios.
        mostrarGrupo('comun', Boolean(tipo));
        mostrarGrupo('categoria', false);
        mostrarGrupo('objetivo', false);
        mostrarGrupo('banco', false);
        mostrarGrupo('traspaso', false);
        mostrarGrupo('notas', Boolean(tipo));

        ayudaCategoria.textContent = '';
        ayudaTraspaso.textContent =
            'Las cuentas de origen y destino deben ser distintas.';

        categoriaRegistro.value = '';
        objetivoRegistro.value = '';
        bancoRegistro.value = '';
        bancoOrigen.value = '';
        bancoDestino.value = '';

        if (!tipo) {
            descripcionFormulario.textContent =
                'Selecciona el tipo de registro e introduce sus datos.';
            return;
        }

        switch (tipo) {
            case 'ingreso':
                mostrarGrupo('categoria', true);
                mostrarGrupo('banco', true);

                descripcionFormulario.textContent =
                    'Registra un ingreso en una de tus cuentas.';
                actualizarCategorias('ingreso');
                break;

            case 'gasto':
                mostrarGrupo('categoria', true);
                mostrarGrupo('banco', true);

                descripcionFormulario.textContent =
                    'Registra un gasto realizado desde una de tus cuentas.';
                actualizarCategorias('gasto');
                break;

            case 'ahorro':
                mostrarGrupo('objetivo', true);
                mostrarGrupo('banco', true);

                descripcionFormulario.textContent =
                    'Asocia una cantidad de ahorro a uno de tus objetivos.';
                break;

            case 'inversion':
                mostrarGrupo('banco', true);

                descripcionFormulario.textContent =
                    'Registra una inversión vinculada a una de tus cuentas.';
                break;

            case 'traspaso':
                mostrarGrupo('traspaso', true);

                descripcionFormulario.textContent =
                    'Indica las cuentas entre las que quieres realizar el traspaso.';
                break;
        }

        actualizarOpcionesBancos();
    }

    /*
    |--------------------------------------------------------------------------
    | Filtrar categorías de ingresos y gastos
    |--------------------------------------------------------------------------
    */

    function actualizarCategorias(tipo) {
        let cantidadDisponibles = 0;

        Array.from(categoriaRegistro.options).forEach(function (opcion) {
            if (!opcion.value) {
                opcion.hidden = false;
                return;
            }

            const coincide = opcion.dataset.tipo === tipo;
            opcion.hidden = !coincide;

            if (coincide) {
                cantidadDisponibles++;
            }
        });

        if (cantidadDisponibles === 0) {
            categoriaRegistro.disabled = true;
            categoriaRegistro.required = false;

            ayudaCategoria.textContent =
                'No tienes categorías de este tipo. Crea una en la sección Categorías.';
        } else {
            categoriaRegistro.disabled = false;
            categoriaRegistro.required = true;

            ayudaCategoria.textContent = '';
        }

        categoriaRegistro.options[0].textContent =
            cantidadDisponibles > 0
                ? 'Selecciona una categoría'
                : 'No hay categorías disponibles';
    }

    /*
    |--------------------------------------------------------------------------
    | Evitar seleccionar la misma cuenta en un traspaso
    |--------------------------------------------------------------------------
    */

    function actualizarOpcionesBancos() {
        const origenSeleccionado = bancoOrigen.value;
        const destinoSeleccionado = bancoDestino.value;

        Array.from(bancoDestino.options).forEach(function (opcion) {
            opcion.disabled = Boolean(
                opcion.value &&
                opcion.value === origenSeleccionado
            );
        });

        Array.from(bancoOrigen.options).forEach(function (opcion) {
            opcion.disabled = Boolean(
                opcion.value &&
                opcion.value === destinoSeleccionado
            );
        });

        if (
            origenSeleccionado &&
            origenSeleccionado === destinoSeleccionado
        ) {
            bancoDestino.value = '';
        }

        const cuentasDisponibles = bancoOrigen.options.length - 1;

        if (cuentasDisponibles < 2) {
            ayudaTraspaso.textContent =
                'Necesitas al menos dos cuentas bancarias para realizar un traspaso.';
        } else {
            ayudaTraspaso.textContent =
                'Las cuentas de origen y destino deben ser distintas.';
        }
    }

    bancoOrigen.addEventListener('change', actualizarOpcionesBancos);
    bancoDestino.addEventListener('change', actualizarOpcionesBancos);

    tipoRegistro.addEventListener('change', actualizarFormulario);

    /*
    |--------------------------------------------------------------------------
    | Abrir y cerrar el formulario
    |--------------------------------------------------------------------------
    */

    function abrirFormulario() {
        panel.hidden = false;
        botonCrear.setAttribute('aria-expanded', 'true');

        mensaje.hidden = true;
        mensaje.textContent = '';

        tipoRegistro.focus();
    }

    function cerrarFormulario() {
        panel.hidden = true;
        botonCrear.setAttribute('aria-expanded', 'false');
    }

    botonCrear.addEventListener('click', function () {
        if (panel.hidden) {
            abrirFormulario();
        } else {
            cerrarFormulario();
        }
    });

    botonCerrar.addEventListener('click', cerrarFormulario);
    botonCancelar.addEventListener('click', cerrarFormulario);

    /*
    |--------------------------------------------------------------------------
    | Filtros de período: solo presentación visual
    |--------------------------------------------------------------------------
    */

    function actualizarPeriodos() {
        const rango = filtroRango.value;
        const fechaActual = new Date();
        const anioActual = fechaActual.getFullYear();

        filtroPeriodo.innerHTML = '';

        if (rango === 'mensual') {
            const meses = [
                'enero', 'febrero', 'marzo', 'abril',
                'mayo', 'junio', 'julio', 'agosto',
                'septiembre', 'octubre', 'noviembre', 'diciembre'
            ];

            meses.forEach(function (mes, indice) {
                const opcion = document.createElement('option');

                opcion.value =
                    anioActual + '-' + String(indice + 1).padStart(2, '0');

                opcion.textContent =
                    mes.charAt(0).toUpperCase() + mes.slice(1) +
                    ' de ' + anioActual;

                if (indice === fechaActual.getMonth()) {
                    opcion.selected = true;
                }

                filtroPeriodo.appendChild(opcion);
            });
        } else {
            for (let anio = anioActual; anio >= anioActual - 4; anio--) {
                const opcion = document.createElement('option');

                opcion.value = String(anio);
                opcion.textContent = String(anio);

                filtroPeriodo.appendChild(opcion);
            }
        }
    }

    filtroRango.addEventListener('change', actualizarPeriodos);

    /*
    |--------------------------------------------------------------------------
    | Guardado pendiente
    |--------------------------------------------------------------------------
    */

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault();

        if (tipoRegistro.value === 'traspaso') {
            if (bancoOrigen.value === bancoDestino.value) {
                mensaje.textContent =
                    'Selecciona dos cuentas bancarias diferentes.';
                mensaje.hidden = false;
                bancoDestino.focus();
                return;
            }

            if (bancoOrigen.options.length < 3) {
                mensaje.textContent =
                    'Necesitas al menos dos cuentas bancarias para realizar un traspaso.';
                mensaje.hidden = false;
                return;
            }
        }

        mensaje.textContent =
            'El formulario está preparado, pero el guardado de registros todavía no está implementado.';
        mensaje.hidden = false;
    });

    actualizarFormulario();
    actualizarPeriodos();
})();
</script>
