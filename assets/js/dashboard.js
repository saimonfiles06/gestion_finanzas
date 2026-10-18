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
    const periodosMensuales = JSON.parse(
        filtroPeriodo.dataset.periodosMensuales || '[]'
    );
    const aniosConRegistros = JSON.parse(
        filtroPeriodo.dataset.anios || '[]'
    );
    const periodosMensualesSet = new Set(periodosMensuales);
    const periodoInicial = filtroPeriodo.dataset.periodoSeleccionado || '';

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
    | Filtros del periodo de las métricas del resumen
    |--------------------------------------------------------------------------
    */

    function actualizarPeriodos() {
        const rango = filtroRango.value;
        const parametrosURL = new URLSearchParams(window.location.search);
        const rangoURL = parametrosURL.get('rango');
        const periodoURL = rangoURL === rango
            ? parametrosURL.get('periodo')
            : periodoInicial;

        filtroPeriodo.innerHTML = '';
        filtroPeriodo.disabled = false;

        if (rango === 'mensual') {
            const meses = [
                'enero', 'febrero', 'marzo', 'abril',
                'mayo', 'junio', 'julio', 'agosto',
                'septiembre', 'octubre', 'noviembre', 'diciembre'
            ];

            if (periodosMensuales.length === 0) {
                agregarOpcionSinRegistros();
            } else {
                const anios = [...aniosConRegistros].sort().reverse();

                anios.forEach(function (anio) {
                    meses.forEach(function (mes, indice) {
                        const periodo = anio + '-' +
                            String(indice + 1).padStart(2, '0');
                        const opcion = document.createElement('option');
                        opcion.value = periodo;
                        opcion.textContent =
                            mes.charAt(0).toUpperCase() + mes.slice(1) +
                            ' de ' + anio;
                        opcion.disabled = !periodosMensualesSet.has(periodo);
                        opcion.selected = periodo === periodoURL;
                        filtroPeriodo.appendChild(opcion);
                    });
                });
                seleccionarPeriodoDisponible(periodoURL, periodosMensuales);
            }
        } else {
            if (aniosConRegistros.length === 0) {
                agregarOpcionSinRegistros();
            } else {
                const anios = [...aniosConRegistros].sort().reverse();

                anios.forEach(function (anio) {
                    const opcion = document.createElement('option');
                    opcion.value = anio;
                    opcion.textContent = anio;
                    opcion.selected = anio === periodoURL;
                    filtroPeriodo.appendChild(opcion);
                });
                seleccionarPeriodoDisponible(periodoURL, anios);
            }
        }

        function agregarOpcionSinRegistros() {
            const opcion = document.createElement('option');
            opcion.value = '';
            opcion.textContent = 'No hay periodos con registros';
            opcion.disabled = true;
            opcion.selected = true;
            filtroPeriodo.appendChild(opcion);
            filtroPeriodo.disabled = true;
        }

        function seleccionarPeriodoDisponible(periodoPreferido, periodosDisponibles) {
            if (periodosDisponibles.includes(periodoPreferido)) {
                filtroPeriodo.value = periodoPreferido;
            } else {
                filtroPeriodo.value = periodosDisponibles[0];
            }
        }
    }

    function navegarAlPeriodoSeleccionado() {
        const url = new URL(window.location.href);
        url.searchParams.set('seccion', 'resumen');
        url.searchParams.set('rango', filtroRango.value);
        if (filtroPeriodo.value) {
            url.searchParams.set('periodo', filtroPeriodo.value);
        } else {
            url.searchParams.delete('periodo');
        }
        window.location.assign(url.toString());
    }

    filtroRango.addEventListener('change', function () {
        actualizarPeriodos();
        navegarAlPeriodoSeleccionado();
    });
    filtroPeriodo.addEventListener('change', navegarAlPeriodoSeleccionado);

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

(function () {
    const graficos = document.querySelectorAll('.resumen-grafico-circular');

    if (graficos.length === 0) {
        return;
    }

    function dibujarGrafico(canvas) {
        const ancho = canvas.clientWidth;
        const alto = canvas.clientHeight;

        if (ancho === 0 || alto === 0) {
            return;
        }

        const escala = window.devicePixelRatio || 1;
        canvas.width = Math.round(ancho * escala);
        canvas.height = Math.round(alto * escala);

        const contexto = canvas.getContext('2d');
        if (!contexto) {
            return;
        }

        contexto.setTransform(escala, 0, 0, escala, 0, 0);
        contexto.clearRect(0, 0, ancho, alto);

        const centroX = ancho / 2;
        const centroY = alto / 2;
        const radio = Math.min(ancho, alto) / 2 - 2;
        const categorias = JSON.parse(canvas.dataset.categorias || '[]');
        const total = categorias.reduce(
            (suma, categoria) => suma + Number(categoria.importe),
            0
        );

        if (total <= 0) {
            contexto.beginPath();
            contexto.arc(centroX, centroY, radio, 0, Math.PI * 2);
            contexto.fillStyle = document.body.classList.contains('modo-oscuro')
                ? '#374151'
                : '#e5e7eb';
            contexto.fill();
            return;
        }

        let anguloInicial = -Math.PI / 2;

        categorias.forEach((categoria) => {
            const anguloFinal = anguloInicial +
                Number(categoria.importe) / total * Math.PI * 2;

            contexto.beginPath();
            contexto.moveTo(centroX, centroY);
            contexto.arc(
                centroX,
                centroY,
                radio,
                anguloInicial,
                anguloFinal
            );
            contexto.closePath();
            contexto.fillStyle = categoria.color;
            contexto.fill();

            anguloInicial = anguloFinal;
        });
    }

    const observadorTamano = new ResizeObserver((entradas) => {
        entradas.forEach((entrada) => dibujarGrafico(entrada.target));
    });

    graficos.forEach((grafico) => {
        observadorTamano.observe(grafico);
        dibujarGrafico(grafico);
    });

    const observadorModo = new MutationObserver(() => {
        graficos.forEach(dibujarGrafico);
    });
    observadorModo.observe(document.body, {
        attributes: true,
        attributeFilter: ['class']
    });
})();

(function () {
    const grafico = document.querySelector('.resumen-grafico-area');

    if (!grafico) {
        return;
    }

    function dibujarGraficoBalance() {
        const ancho = grafico.clientWidth;
        const alto = grafico.clientHeight;

        if (ancho === 0 || alto === 0) {
            return;
        }

        const escala = window.devicePixelRatio || 1;
        grafico.width = Math.round(ancho * escala);
        grafico.height = Math.round(alto * escala);

        const contexto = grafico.getContext('2d');
        if (!contexto) {
            return;
        }

        contexto.setTransform(escala, 0, 0, escala, 0, 0);
        contexto.clearRect(0, 0, ancho, alto);

        const periodos = JSON.parse(grafico.dataset.balance || '[]');
        const modoOscuro = document.body.classList.contains('modo-oscuro');
        const colorTexto = modoOscuro ? '#cbd5e1' : '#475569';
        const colorEjes = modoOscuro ? '#475569' : '#cbd5e1';
        const margen = { arriba: 18, derecha: 12, abajo: 42, izquierda: 62 };
        const anchoGrafico = ancho - margen.izquierda - margen.derecha;
        const altoGrafico = alto - margen.arriba - margen.abajo;

        contexto.font = '12px system-ui, sans-serif';
        contexto.textAlign = 'right';
        contexto.textBaseline = 'middle';
        contexto.fillStyle = colorTexto;

        const valorMaximo = Math.max(
            1,
            ...periodos.map((periodo) => Math.max(
                Number(periodo.ingresos),
                Number(periodo.gastos)
            ))
        );
        const escalaMaxima = valorMaximo * 1.1;
        const divisiones = 4;

        for (let indice = 0; indice <= divisiones; indice += 1) {
            const proporcion = indice / divisiones;
            const y = margen.arriba + altoGrafico * proporcion;
            const valor = escalaMaxima * (1 - proporcion);

            contexto.beginPath();
            contexto.strokeStyle = colorEjes;
            contexto.lineWidth = 1;
            contexto.moveTo(margen.izquierda, y);
            contexto.lineTo(ancho - margen.derecha, y);
            contexto.stroke();
            contexto.fillText(
                new Intl.NumberFormat('es-ES', {
                    maximumFractionDigits: 0
                }).format(valor) + ' €',
                margen.izquierda - 8,
                y
            );
        }

        const puntosX = periodos.map((periodo, indice) => (
            margen.izquierda + (periodos.length === 1
                ? anchoGrafico / 2
                : anchoGrafico * indice / (periodos.length - 1))
        ));
        const yBase = margen.arriba + altoGrafico;
        const colorIngresos = modoOscuro ? '#4ade80' : '#16a34a';
        const colorGastos = modoOscuro ? '#f87171' : '#dc2626';

        function dibujarArea(clave, color, relleno) {
            contexto.beginPath();
            contexto.moveTo(puntosX[0], yBase);

            periodos.forEach((periodo, indice) => {
                const y = yBase -
                    Number(periodo[clave]) / escalaMaxima * altoGrafico;
                contexto.lineTo(puntosX[indice], y);
            });

            contexto.lineTo(puntosX[puntosX.length - 1], yBase);
            contexto.closePath();
            contexto.fillStyle = relleno;
            contexto.fill();

            contexto.beginPath();
            periodos.forEach((periodo, indice) => {
                const y = yBase -
                    Number(periodo[clave]) / escalaMaxima * altoGrafico;
                if (indice === 0) {
                    contexto.moveTo(puntosX[indice], y);
                } else {
                    contexto.lineTo(puntosX[indice], y);
                }
            });
            contexto.strokeStyle = color;
            contexto.lineWidth = 2;
            contexto.stroke();
        }

        dibujarArea(
            'ingresos',
            colorIngresos,
            modoOscuro ? 'rgb(74 222 128 / 28%)' : 'rgb(22 163 74 / 20%)'
        );
        dibujarArea(
            'gastos',
            colorGastos,
            modoOscuro ? 'rgb(248 113 113 / 28%)' : 'rgb(220 38 38 / 18%)'
        );

        function dibujarPunto(x, y, color) {
            contexto.beginPath();
            contexto.arc(x, y, 4, 0, Math.PI * 2);
            contexto.fillStyle = color;
            contexto.fill();
            contexto.lineWidth = 2;
            contexto.strokeStyle = modoOscuro ? '#1f2937' : '#ffffff';
            contexto.stroke();
        }

        periodos.forEach((periodo, indice) => {
            if (Number(periodo.registros_ingresos) > 0) {
                dibujarPunto(
                    puntosX[indice],
                    yBase - Number(periodo.ingresos) / escalaMaxima * altoGrafico,
                    colorIngresos
                );
            }

            if (Number(periodo.registros_gastos) > 0) {
                dibujarPunto(
                    puntosX[indice],
                    yBase - Number(periodo.gastos) / escalaMaxima * altoGrafico,
                    colorGastos
                );
            }
        });

        periodos.forEach((periodo, indice) => {
            const x = puntosX[indice];

            const etiquetaVisible = periodos.length <= 12
                || indice % Math.ceil(periodos.length / 10) === 0
                || indice === periodos.length - 1;

            if (etiquetaVisible) {
                contexto.fillStyle = colorTexto;
                contexto.textAlign = 'center';
                contexto.textBaseline = 'top';
                contexto.fillText(
                    periodo.etiqueta,
                    x,
                    yBase + 12
                );
            }
        });

        contexto.save();
        contexto.translate(16, margen.arriba + altoGrafico / 2);
        contexto.rotate(-Math.PI / 2);
        contexto.fillStyle = colorTexto;
        contexto.textAlign = 'center';
        contexto.textBaseline = 'middle';
        contexto.fillText('Cantidad (€)', 0, 0);
        contexto.restore();
    }

    const observadorTamano = new ResizeObserver(dibujarGraficoBalance);
    observadorTamano.observe(grafico);

    const observadorModo = new MutationObserver(dibujarGraficoBalance);
    observadorModo.observe(document.body, {
        attributes: true,
        attributeFilter: ['class']
    });
})();

(function () {
    const grafico = document.querySelector('.resumen-grafico-balance');

    if (!grafico) {
        return;
    }

    function dibujarBalanceNeto() {
        const ancho = grafico.clientWidth;
        const alto = grafico.clientHeight;

        if (ancho === 0 || alto === 0) {
            return;
        }

        const escala = window.devicePixelRatio || 1;
        grafico.width = Math.round(ancho * escala);
        grafico.height = Math.round(alto * escala);

        const contexto = grafico.getContext('2d');
        if (!contexto) {
            return;
        }

        contexto.setTransform(escala, 0, 0, escala, 0, 0);
        contexto.clearRect(0, 0, ancho, alto);

        const periodos = JSON.parse(grafico.dataset.balance || '[]');
        const modoOscuro = document.body.classList.contains('modo-oscuro');
        const colorTexto = modoOscuro ? '#cbd5e1' : '#475569';
        const colorEjes = modoOscuro ? '#475569' : '#cbd5e1';
        const colorBalance = modoOscuro ? '#60a5fa' : '#2563eb';
        const rellenoBalance = modoOscuro
            ? 'rgb(96 165 250 / 28%)'
            : 'rgb(37 99 235 / 20%)';
        const margen = { arriba: 18, derecha: 12, abajo: 42, izquierda: 68 };
        const anchoGrafico = ancho - margen.izquierda - margen.derecha;
        const altoGrafico = alto - margen.arriba - margen.abajo;

        if (periodos.length === 0) {
            return;
        }

        const balances = periodos.map(
            (periodo) => Number(periodo.ingresos) - Number(periodo.gastos)
        );
        const valorMaximo = Math.max(1, ...balances.map(Math.abs)) * 1.1;
        const minimo = -valorMaximo;
        const maximo = valorMaximo;
        const yDeValor = (valor) => (
            margen.arriba + (maximo - valor) / (maximo - minimo) * altoGrafico
        );
        const yCero = yDeValor(0);
        const puntosX = periodos.map((periodo, indice) => (
            margen.izquierda + (periodos.length === 1
                ? anchoGrafico / 2
                : anchoGrafico * indice / (periodos.length - 1))
        ));

        contexto.font = '12px system-ui, sans-serif';
        contexto.textAlign = 'right';
        contexto.textBaseline = 'middle';

        for (let indice = 0; indice <= 4; indice += 1) {
            const valor = maximo - (maximo - minimo) * indice / 4;
            const y = yDeValor(valor);

            contexto.beginPath();
            contexto.strokeStyle = Math.abs(valor) < 0.000001
                ? colorTexto
                : colorEjes;
            contexto.lineWidth = Math.abs(valor) < 0.000001 ? 1.5 : 1;
            contexto.moveTo(margen.izquierda, y);
            contexto.lineTo(ancho - margen.derecha, y);
            contexto.stroke();
            contexto.fillStyle = colorTexto;
            contexto.fillText(
                new Intl.NumberFormat('es-ES', {
                    maximumFractionDigits: 0
                }).format(valor) + ' €',
                margen.izquierda - 8,
                y
            );
        }

        contexto.beginPath();
        contexto.moveTo(puntosX[0], yCero);
        balances.forEach((balance, indice) => {
            contexto.lineTo(puntosX[indice], yDeValor(balance));
        });
        contexto.lineTo(puntosX[puntosX.length - 1], yCero);
        contexto.closePath();
        contexto.fillStyle = rellenoBalance;
        contexto.fill();

        contexto.beginPath();
        balances.forEach((balance, indice) => {
            if (indice === 0) {
                contexto.moveTo(puntosX[indice], yDeValor(balance));
            } else {
                contexto.lineTo(puntosX[indice], yDeValor(balance));
            }
        });
        contexto.strokeStyle = colorBalance;
        contexto.lineWidth = 2;
        contexto.stroke();

        periodos.forEach((periodo, indice) => {
            if (
                Number(periodo.registros_ingresos) > 0
                || Number(periodo.registros_gastos) > 0
            ) {
                contexto.beginPath();
                contexto.arc(puntosX[indice], yDeValor(balances[indice]), 4, 0, Math.PI * 2);
                contexto.fillStyle = colorBalance;
                contexto.fill();
                contexto.lineWidth = 2;
                contexto.strokeStyle = modoOscuro ? '#1f2937' : '#ffffff';
                contexto.stroke();
            }

            const etiquetaVisible = periodos.length <= 12
                || indice % Math.ceil(periodos.length / 10) === 0
                || indice === periodos.length - 1;

            if (etiquetaVisible) {
                contexto.fillStyle = colorTexto;
                contexto.textAlign = 'center';
                contexto.textBaseline = 'top';
                contexto.fillText(
                    periodo.etiqueta,
                    puntosX[indice],
                    margen.arriba + altoGrafico + 12
                );
            }
        });

        contexto.save();
        contexto.translate(17, margen.arriba + altoGrafico / 2);
        contexto.rotate(-Math.PI / 2);
        contexto.fillStyle = colorTexto;
        contexto.textAlign = 'center';
        contexto.textBaseline = 'middle';
        contexto.fillText('Balance neto (€)', 0, 0);
        contexto.restore();
    }

    const observadorTamano = new ResizeObserver(dibujarBalanceNeto);
    observadorTamano.observe(grafico);

    const observadorModo = new MutationObserver(dibujarBalanceNeto);
    observadorModo.observe(document.body, {
        attributes: true,
        attributeFilter: ['class']
    });
})();

(function () {
    const grafico = document.querySelector('.resumen-grafico-ahorro-inversion');

    if (!grafico) {
        return;
    }

    function dibujarAhorroInversion() {
        const ancho = grafico.clientWidth;
        const alto = grafico.clientHeight;

        if (ancho === 0 || alto === 0) {
            return;
        }

        const escala = window.devicePixelRatio || 1;
        grafico.width = Math.round(ancho * escala);
        grafico.height = Math.round(alto * escala);

        const contexto = grafico.getContext('2d');
        if (!contexto) {
            return;
        }

        contexto.setTransform(escala, 0, 0, escala, 0, 0);
        contexto.clearRect(0, 0, ancho, alto);

        const periodos = JSON.parse(grafico.dataset.balance || '[]');
        if (periodos.length === 0) {
            return;
        }

        const modoOscuro = document.body.classList.contains('modo-oscuro');
        const colorTexto = modoOscuro ? '#cbd5e1' : '#475569';
        const colorEjes = modoOscuro ? '#475569' : '#cbd5e1';
        const colorAhorro = modoOscuro ? '#7dd3fc' : '#0284c7';
        const colorInversion = modoOscuro ? '#facc15' : '#b7791f';
        const margen = { arriba: 18, derecha: 12, abajo: 42, izquierda: 62 };
        const anchoGrafico = ancho - margen.izquierda - margen.derecha;
        const altoGrafico = alto - margen.arriba - margen.abajo;
        const valorMaximo = Math.max(
            1,
            ...periodos.map((periodo) => Math.max(
                Number(periodo.ahorro),
                Number(periodo.inversion)
            ))
        ) * 1.1;
        const yBase = margen.arriba + altoGrafico;
        const puntosX = periodos.map((periodo, indice) => (
            margen.izquierda + (periodos.length === 1
                ? anchoGrafico / 2
                : anchoGrafico * indice / (periodos.length - 1))
        ));
        const yDeImporte = (importe) => (
            yBase - Number(importe) / valorMaximo * altoGrafico
        );

        contexto.font = '12px system-ui, sans-serif';
        contexto.textAlign = 'right';
        contexto.textBaseline = 'middle';

        for (let indice = 0; indice <= 4; indice += 1) {
            const proporcion = indice / 4;
            const valor = valorMaximo * (1 - proporcion);
            const y = margen.arriba + altoGrafico * proporcion;

            contexto.beginPath();
            contexto.strokeStyle = colorEjes;
            contexto.lineWidth = 1;
            contexto.moveTo(margen.izquierda, y);
            contexto.lineTo(ancho - margen.derecha, y);
            contexto.stroke();
            contexto.fillStyle = colorTexto;
            contexto.fillText(
                new Intl.NumberFormat('es-ES', {
                    maximumFractionDigits: 0
                }).format(valor) + ' €',
                margen.izquierda - 8,
                y
            );
        }

        function dibujarArea(clave, color, relleno) {
            contexto.beginPath();
            contexto.moveTo(puntosX[0], yBase);
            periodos.forEach((periodo, indice) => {
                contexto.lineTo(puntosX[indice], yDeImporte(periodo[clave]));
            });
            contexto.lineTo(puntosX[puntosX.length - 1], yBase);
            contexto.closePath();
            contexto.fillStyle = relleno;
            contexto.fill();

            contexto.beginPath();
            periodos.forEach((periodo, indice) => {
                const y = yDeImporte(periodo[clave]);
                if (indice === 0) {
                    contexto.moveTo(puntosX[indice], y);
                } else {
                    contexto.lineTo(puntosX[indice], y);
                }
            });
            contexto.strokeStyle = color;
            contexto.lineWidth = 2;
            contexto.stroke();
        }

        dibujarArea(
            'ahorro',
            colorAhorro,
            modoOscuro ? 'rgb(125 211 252 / 28%)' : 'rgb(56 189 248 / 22%)'
        );
        dibujarArea(
            'inversion',
            colorInversion,
            modoOscuro ? 'rgb(250 204 21 / 25%)' : 'rgb(212 160 23 / 20%)'
        );

        periodos.forEach((periodo, indice) => {
            [
                ['registros_ahorro', 'ahorro', colorAhorro],
                ['registros_inversion', 'inversion', colorInversion]
            ].forEach(([campoRegistros, campoImporte, color]) => {
                if (Number(periodo[campoRegistros]) === 0) {
                    return;
                }

                contexto.beginPath();
                contexto.arc(
                    puntosX[indice],
                    yDeImporte(periodo[campoImporte]),
                    4,
                    0,
                    Math.PI * 2
                );
                contexto.fillStyle = color;
                contexto.fill();
                contexto.lineWidth = 2;
                contexto.strokeStyle = modoOscuro ? '#1f2937' : '#ffffff';
                contexto.stroke();
            });

            const etiquetaVisible = periodos.length <= 12
                || indice % Math.ceil(periodos.length / 10) === 0
                || indice === periodos.length - 1;

            if (etiquetaVisible) {
                contexto.fillStyle = colorTexto;
                contexto.textAlign = 'center';
                contexto.textBaseline = 'top';
                contexto.fillText(
                    periodo.etiqueta,
                    puntosX[indice],
                    yBase + 12
                );
            }
        });

        contexto.save();
        contexto.translate(16, margen.arriba + altoGrafico / 2);
        contexto.rotate(-Math.PI / 2);
        contexto.fillStyle = colorTexto;
        contexto.textAlign = 'center';
        contexto.textBaseline = 'middle';
        contexto.fillText('Cantidad (€)', 0, 0);
        contexto.restore();
    }

    const observadorTamano = new ResizeObserver(dibujarAhorroInversion);
    observadorTamano.observe(grafico);

    const observadorModo = new MutationObserver(dibujarAhorroInversion);
    observadorModo.observe(document.body, {
        attributes: true,
        attributeFilter: ['class']
    });
})();

(function () {
    const graficos = document.querySelectorAll(
        '.resumen-grafico-area, .resumen-grafico-balance, .resumen-grafico-ahorro-inversion'
    );

    function formatearEuros(valor) {
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(valor);
    }

    graficos.forEach((grafico) => {
        const tooltip = grafico.parentElement.querySelector(
            '.resumen-area-tooltip'
        );
        const periodos = JSON.parse(grafico.dataset.balance || '[]');
        const esBalanceNeto = grafico.classList.contains(
            'resumen-grafico-balance'
        );
        const esAhorroInversion = grafico.classList.contains(
            'resumen-grafico-ahorro-inversion'
        );
        const margenIzquierda = esBalanceNeto ? 68 : 62;
        const margenDerecha = 12;

        if (!tooltip || periodos.length === 0) {
            return;
        }

        grafico.addEventListener('mousemove', (evento) => {
            const rectangulo = grafico.getBoundingClientRect();
            const posicionX = evento.clientX - rectangulo.left;
            const anchoGrafico = rectangulo.width -
                margenIzquierda - margenDerecha;
            const fraccion = Math.max(
                0,
                Math.min(1, (posicionX - margenIzquierda) / anchoGrafico)
            );
            const indice = periodos.length === 1
                ? 0
                : Math.round(fraccion * (periodos.length - 1));
            const periodo = periodos[indice];
            const encabezado = document.createElement('strong');
            encabezado.textContent = `${periodo.etiqueta}:`;
            tooltip.replaceChildren(encabezado);

            const modoOscuro = document.body.classList.contains('modo-oscuro');
            const valores = esBalanceNeto
                ? [{
                    nombre: 'Balance',
                    valor: Number(periodo.ingresos) - Number(periodo.gastos),
                    color: modoOscuro ? '#60a5fa' : '#2563eb'
                }]
                : esAhorroInversion
                    ? [
                        {
                            nombre: 'Ahorro',
                            valor: Number(periodo.ahorro),
                            color: modoOscuro ? '#7dd3fc' : '#0284c7'
                        },
                        {
                            nombre: 'Inversión',
                            valor: Number(periodo.inversion),
                            color: modoOscuro ? '#facc15' : '#b7791f'
                        }
                    ]
                : [
                    {
                        nombre: 'Ingresos',
                        valor: Number(periodo.ingresos),
                        color: modoOscuro ? '#4ade80' : '#16a34a'
                    },
                    {
                        nombre: 'Gastos',
                        valor: Number(periodo.gastos),
                        color: modoOscuro ? '#f87171' : '#dc2626'
                    }
                ];

            valores.forEach((dato) => {
                const linea = document.createElement('span');
                const punto = document.createElement('i');
                const texto = document.createElement('span');

                linea.className = 'resumen-area-tooltip-linea';
                punto.className = 'resumen-area-tooltip-punto';
                punto.style.setProperty('--tooltip-color', dato.color);
                punto.setAttribute('aria-hidden', 'true');
                texto.textContent = `${dato.nombre}: ${formatearEuros(dato.valor)}`;

                linea.append(punto, texto);
                tooltip.appendChild(linea);
            });

            tooltip.hidden = false;
            const limiteX = tooltip.parentElement.clientWidth -
                tooltip.offsetWidth - 8;
            const limiteY = tooltip.parentElement.clientHeight -
                tooltip.offsetHeight - 8;
            tooltip.style.left = `${Math.max(
                8,
                Math.min(posicionX + 12, limiteX)
            )}px`;
            tooltip.style.top = `${Math.max(
                8,
                Math.min(evento.clientY - rectangulo.top + 12, limiteY)
            )}px`;
        });

        grafico.addEventListener('mouseleave', () => {
            tooltip.hidden = true;
        });
    });
})();