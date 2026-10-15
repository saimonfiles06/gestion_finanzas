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

    const panelFormulario = document.getElementById('panel-crear-registro');
    const formulario = document.getElementById('form-crear-registro');

    const tipoRegistro = document.getElementById('tipo-registro');
    const categoriaRegistro = document.getElementById('categoria-registro');
    const ayudaCategoria = document.getElementById('ayuda-categoria-registro');

    const mensajeRegistro = document.getElementById('mensaje-registro');

    const filtroRango = document.getElementById('filtro-rango');
    const filtroPeriodo = document.getElementById('filtro-periodo');

    if (!botonCrear || !panelFormulario || !formulario) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Mostrar y ocultar el formulario
    |--------------------------------------------------------------------------
    */

    function abrirFormulario() {
        panelFormulario.hidden = false;
        botonCrear.setAttribute('aria-expanded', 'true');

        mensajeRegistro.hidden = true;
        mensajeRegistro.textContent = '';

        tipoRegistro.focus();
    }

    function cerrarFormulario() {
        panelFormulario.hidden = true;
        botonCrear.setAttribute('aria-expanded', 'false');
    }

    botonCrear.addEventListener('click', function () {
        if (panelFormulario.hidden) {
            abrirFormulario();
        } else {
            cerrarFormulario();
        }
    });

    botonCerrar.addEventListener('click', cerrarFormulario);
    botonCancelar.addEventListener('click', cerrarFormulario);

    /*
    |--------------------------------------------------------------------------
    | Filtrar categorías según el tipo de registro
    |--------------------------------------------------------------------------
    */

    function actualizarCategorias() {
        const tipoSeleccionado = tipoRegistro.value;

        categoriaRegistro.value = '';

        Array.from(categoriaRegistro.options).forEach(function (opcion) {
            if (!opcion.value) {
                opcion.hidden = false;
                return;
            }

            opcion.hidden = opcion.dataset.tipo !== tipoSeleccionado;
        });

        if (tipoSeleccionado === 'ingreso' || tipoSeleccionado === 'gasto') {
            const categoriasDisponibles = Array.from(categoriaRegistro.options)
                .filter(function (opcion) {
                    return opcion.value &&
                        opcion.dataset.tipo === tipoSeleccionado;
                });

            categoriaRegistro.disabled = categoriasDisponibles.length === 0;

            if (categoriasDisponibles.length > 0) {
                categoriaRegistro.options[0].textContent = 'Selecciona una categoría';
                ayudaCategoria.textContent = '';
            } else {
                categoriaRegistro.options[0].textContent = 'No hay categorías disponibles';
                ayudaCategoria.textContent =
                    'Crea primero una categoría de este tipo en la sección Categorías.';
            }
        } else if (tipoSeleccionado) {
            categoriaRegistro.disabled = true;
            categoriaRegistro.options[0].textContent = 'No disponible todavía';
            ayudaCategoria.textContent =
                'Las categorías para ahorro, inversión y traspaso se configurarán en una fase posterior.';
        } else {
            categoriaRegistro.disabled = true;
            categoriaRegistro.options[0].textContent = 'Selecciona primero el tipo';
            ayudaCategoria.textContent =
                'Selecciona el tipo de registro para ver las categorías disponibles.';
        }
    }

    tipoRegistro.addEventListener('change', actualizarCategorias);

    /*
    |--------------------------------------------------------------------------
    | Generar opciones visuales para los filtros de período
    |--------------------------------------------------------------------------
    | Los filtros aún no modifican los datos del resumen.
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

            // Se muestran los meses del año actual.
            meses.forEach(function (mes, indice) {
                const opcion = document.createElement('option');

                opcion.value = anioActual + '-' + String(indice + 1).padStart(2, '0');
                opcion.textContent =
                    mes.charAt(0).toUpperCase() + mes.slice(1) + ' de ' + anioActual;

                if (indice === fechaActual.getMonth()) {
                    opcion.selected = true;
                }

                filtroPeriodo.appendChild(opcion);
            });
        } else {
            // Se muestran los últimos cinco años, incluido el actual.
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
    | Guardado pendiente de implementar
    |--------------------------------------------------------------------------
    */

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault();

        mensajeRegistro.textContent =
            'El guardado de registros todavía no está implementado.';
        mensajeRegistro.hidden = false;
    });

    actualizarCategorias();
    actualizarPeriodos();
})();