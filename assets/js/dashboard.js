document.addEventListener('DOMContentLoaded', () => {
    const layout = document.querySelector('.dashboard-layout');
    const botonesMenu = document.querySelectorAll('[data-seccion]');
    const secciones = document.querySelectorAll('.dashboard-seccion');

    const logoAplicacion = document.getElementById('logo-aplicacion');

    const botonModo = document.getElementById('boton-modo');
    const iconoModo = document.getElementById('icono-modo');
    const textoModo = document.getElementById('texto-modo');

    const botonOcultar = document.getElementById('boton-ocultar');
    const iconoOcultar = document.getElementById('icono-ocultar');
    const textoOcultar = document.getElementById('texto-ocultar');

    /**
     * Muestra el apartado seleccionado y oculta los demás.
     */
    function mostrarSeccion(nombreSeccion) {
        const seccionActiva = document.getElementById(
            `seccion-${nombreSeccion}`
        );

        // No cambiar de apartado si no existe la sección solicitada.
        if (!seccionActiva || !seccionActiva.classList.contains('dashboard-seccion')) {
            return;
        }

        secciones.forEach((seccion) => {
            const activa = seccion === seccionActiva;

            seccion.hidden = !activa;
            seccion.classList.toggle('activa', activa);
        });

        botonesMenu.forEach((boton) => {
            const activo = boton.dataset.seccion === nombreSeccion;

            boton.classList.toggle('activo', activo);

            if (activo) {
                boton.setAttribute('aria-current', 'page');
            } else {
                boton.removeAttribute('aria-current');
            }
        });
    }

    // Navegación entre los apartados del menú.
    botonesMenu.forEach((boton) => {
        boton.addEventListener('click', () => {
            mostrarSeccion(boton.dataset.seccion);
        });
    });

    // Cambiar entre modo claro y oscuro.
    if (botonModo) {
        botonModo.addEventListener('click', () => {
            const modoOscuro = document.body.classList.toggle('modo-oscuro');

            botonModo.setAttribute('aria-pressed', String(modoOscuro));

            if (iconoModo) {
                iconoModo.innerHTML = modoOscuro
                    ? '<i class="bi bi-sun" aria-hidden="true"></i>'
                    : '<i class="bi bi-moon" aria-hidden="true"></i>';
            }

            if (textoModo) {
                textoModo.textContent = modoOscuro
                    ? 'Modo claro'
                    : 'Modo oscuro';
            }

            if (logoAplicacion) {
                logoAplicacion.src = modoOscuro
                    ? 'assets/img/logo_claro.png'
                    : 'assets/img/logo_oscuro.png';
            }
        });
    }

    // Contraer o expandir el menú lateral.
    if (botonOcultar && layout) {
        botonOcultar.addEventListener('click', () => {
            const oculto = layout.classList.toggle('sidebar-oculto');

            botonOcultar.setAttribute('aria-expanded', String(!oculto));
            botonOcultar.setAttribute(
                'aria-label',
                oculto ? 'Mostrar menú lateral' : 'Ocultar menú lateral'
            );

            if (iconoOcultar) {
                iconoOcultar.innerHTML = oculto
                    ? '<i class="bi bi-layout-sidebar-inset-reverse" aria-hidden="true"></i>'
                    : '<i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>';
            }

            if (textoOcultar) {
                textoOcultar.textContent = oculto ? 'Mostrar' : 'Ocultar';
            }

            botonOcultar.title = oculto
                ? 'Mostrar menú'
                : 'Ocultar menú';
        });
    }

    // Mostrar el apartado indicado en la URL o, por defecto, el resumen.
    const parametrosURL = new URLSearchParams(window.location.search);
    const seccionInicial = parametrosURL.get('seccion') || 'resumen';

    mostrarSeccion(seccionInicial);
});

window.editarCategoria = (categoriaId, nombreActual, tipo) => {
    const nuevoNombre = window.prompt(
        'Introduce el nuevo nombre de la categoría:',
        nombreActual
    );

    if (nuevoNombre === null) {
        return;
    }

    const nombre = nuevoNombre.trim();

    if (nombre === '') {
        window.alert('Introduce el nombre de la categoría.');
        return;
    }

    if (nombre.length > 100) {
        window.alert('El nombre no puede superar los 100 caracteres.');
        return;
    }

    if (nombre === nombreActual) {
        return;
    }

    const tokenCsrf = document.querySelector(
        '#seccion-categorias input[name="csrf_token"]'
    );

    if (!tokenCsrf) {
        window.alert(
            'No se ha podido validar la sesión. Recarga la página e inténtalo de nuevo.'
        );
        return;
    }

    const formulario = document.createElement('form');
    formulario.method = 'POST';
    formulario.action = 'categorias_acciones.php';
    formulario.hidden = true;

    const campos = {
        csrf_token: tokenCsrf.value,
        accion: 'editar',
        categoria_id: String(categoriaId),
        nombre,
        tipo
    };

    Object.entries(campos).forEach(([nombreCampo, valor]) => {
        const campo = document.createElement('input');
        campo.type = 'hidden';
        campo.name = nombreCampo;
        campo.value = valor;
        formulario.appendChild(campo);
    });

    document.body.appendChild(formulario);
    formulario.submit();
};
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
    | Validación previa al envío
    |--------------------------------------------------------------------------
    | Si los datos son válidos, el formulario se envía por POST a
    | guardar_registro.php. La validación definitiva se realiza en PHP.
    */

    formulario.addEventListener('submit', function (evento) {
        if (tipoRegistro.value === 'traspaso') {
            if (!bancoOrigen.value || !bancoDestino.value ||
                bancoOrigen.value === bancoDestino.value) {
                evento.preventDefault();
                mensaje.textContent =
                    'Selecciona dos cuentas bancarias diferentes.';
                mensaje.hidden = false;
                bancoDestino.focus();
                return;
            }

            if (bancoOrigen.options.length < 3) {
                evento.preventDefault();
                mensaje.textContent =
                    'Necesitas al menos dos cuentas bancarias para realizar un traspaso.';
                mensaje.hidden = false;
                return;
            }
        }

        // Sin preventDefault(): se envía el formulario a guardar_registro.php.
    });

    actualizarFormulario();
    actualizarPeriodos();
})();