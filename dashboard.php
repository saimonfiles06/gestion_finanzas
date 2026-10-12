<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/includes/conexion.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Finanzas</title>
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/dashboard.js" defer></script>
</head>

<body class="dashboard-page">

    <div class="dashboard-layout">

        <!-- Menú lateral -->
        <aside class="sidebar" id="sidebar">

            <!-- Logo: no es una opción de navegación -->
            <div class="sidebar-logo">
                <img
                    src="assets/img/logo_oscuro.png"
                    alt="Gestión Finanzas"
                    id="logo-aplicacion">
            </div>

            <!-- Opciones principales -->
            <nav class="sidebar-nav" aria-label="Navegación principal">

                <button
                    type="button"
                    class="nav-item activo"
                    data-seccion="resumen"
                    aria-current="page"
                    title="Resumen">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-grid-1x2"></i>
                    </span>
                    <span class="nav-text">Resumen</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="ingresos-gastos"
                    title="Ingresos y gastos">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-arrow-left-right"></i>
                    </span>
                    <span class="nav-text">Ingresos y gastos</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="registro"
                    title="Registro">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-journal-text"></i>
                    </span>
                    <span class="nav-text">Registro</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="categorias"
                    title="Categorías">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-tags"></i>
                    </span>
                    <span class="nav-text">Categorías</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="bancos"
                    title="Bancos">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-bank"></i>
                    </span>
                    <span class="nav-text">Bancos</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="objetivos"
                    title="Objetivos">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-bullseye"></i>
                    </span>
                    <span class="nav-text">Objetivos</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="presupuestos"
                    title="Presupuestos">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-arrow-left-right"></i>
                    </span>
                    <span class="nav-text">Presupuestos</span>
                </button>

                <button
                    type="button"
                    class="nav-item"
                    data-seccion="cuenta"
                    title="Cuenta">
                    <span class="nav-icon" aria-hidden="true">
                        <i class="bi bi-person-circle"></i>
                    </span>
                    <span class="nav-text">Cuenta</span>
                </button>

            </nav>

            <!-- Opciones inferiores -->
            <div class="sidebar-footer">

                <button
                    type="button"
                    class="nav-item"
                    id="boton-modo"
                    aria-pressed="false"
                    title="Cambiar a modo oscuro">
                    <span class="nav-icon" id="icono-modo" aria-hidden="true">
                        <i class="bi bi-moon"></i>
                    </span>
                    <span class="nav-text" id="texto-modo">Modo oscuro</span>
                </button>

                <a
                    href="logout.php"
                    class="nav-item cerrar-sesion"
                    title="Cerrar sesión">
                    <span class="nav-icon" aria-hidden="true">⇥</span>
                    <span class="nav-text">Cerrar sesión</span>
                </a>

                <button
                    type="button"
                    class="nav-item"
                    id="boton-ocultar"
                    aria-expanded="true"
                    aria-controls="sidebar"
                    title="Ocultar menú">
                    <span class="nav-icon" id="icono-ocultar" aria-hidden="true">
                        <i class="bi bi-layout-sidebar-inset-reverse"></i>
                    </span>
                    <span class="nav-text" id="texto-ocultar">Ocultar</span>
                </button>
            </div>
        </aside>
        <!-- Área de contenido: se desarrollará apartado por apartado -->
        <main class="dashboard-main" id="dashboard-main">
            <section
                id="seccion-resumen"
                class="dashboard-seccion"
                hidden>
                <?php require __DIR__ . '/resumen.php'; ?>
            </section>
            <section id="seccion-categorias" class="dashboard-seccion" hidden>
                <?php require __DIR__ . '/categorias.php'; ?>
            </section>
            <section
                id="seccion-bancos"
                class="dashboard-seccion"
                hidden>
                <?php require __DIR__ . '/bancos.php'; ?>
            </section>
            <section
                id="seccion-bancos"
                class="dashboard-seccion"
                hidden>
                <?php require __DIR__ . '/bancos.php'; ?>
            </section>
        </main>
    </div>
</body>

</html>