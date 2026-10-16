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
