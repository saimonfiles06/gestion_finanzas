<?php
session_start();

// Vaciar las variables de sesión.
$_SESSION = [];

// Eliminar la cookie de sesión si se utilizan cookies.
if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

// Destruir la sesión.
session_destroy();

//Volver a página inicio (index.html)
header('Location: index.html');
exit;
