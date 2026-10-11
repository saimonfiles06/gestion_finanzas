
document.addEventListener('DOMContentLoaded', () => {
    const layout = document.querySelector('.dashboard-layout');
    const botonesMenu = document.querySelectorAll('[data-seccion]');

    const logoAplicacion = document.getElementById('logo-aplicacion');

    const botonModo = document.getElementById('boton-modo');
    const iconoModo = document.getElementById('icono-modo');
    const textoModo = document.getElementById('texto-modo');

    const botonOcultar = document.getElementById('boton-ocultar');
    const iconoOcultar = document.getElementById('icono-ocultar');
    const textoOcultar = document.getElementById('texto-ocultar');

    // Seleccionar un apartado del menú.
    botonesMenu.forEach((boton) => {
        boton.addEventListener('click', () => {
            botonesMenu.forEach((elemento) => {
                elemento.classList.remove('activo');
                elemento.removeAttribute('aria-current');
            });

            boton.classList.add('activo');
            boton.setAttribute('aria-current', 'page');
        });
    });

    // Cambiar entre modo claro y oscuro.
    botonModo.addEventListener('click', () => {
        const modoOscuro = document.body.classList.toggle('modo-oscuro');

        botonModo.setAttribute('aria-pressed', String(modoOscuro));

        if (modoOscuro) {
            iconoModo.innerHTML = '<i class="bi bi-sun"></i>';
            textoModo.textContent = 'Modo claro';
            logoAplicacion.src = 'assets/img/logo_claro.png';
        } else {
            iconoModo.innerHTML = '<i class="bi bi-moon"></i>';
            textoModo.textContent = 'Modo oscuro';
            logoAplicacion.src = 'assets/img/logo_oscuro.png';
        }
    });

    // Contraer o expandir el menú lateral.
    botonOcultar.addEventListener('click', () => {
        const oculto = layout.classList.toggle('sidebar-oculto');

        botonOcultar.setAttribute('aria-expanded', String(!oculto));

        if (oculto) {
            iconoOcultar.innerHTML = '<i class="bi bi-layout-sidebar-inset-reverse"></i>';
            textoOcultar.textContent = 'Mostrar';
            botonOcultar.title = 'Mostrar menú';
        } else {
            iconoOcultar.innerHTML = '<i class="bi bi-layout-sidebar-inset"></i>';
            textoOcultar.textContent = 'Ocultar';
            botonOcultar.title = 'Ocultar menú';
        }
    });
});
