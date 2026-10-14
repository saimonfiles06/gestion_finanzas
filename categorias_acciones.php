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

// Todas las acciones terminan redirigiendo a la sección Categorías.
function redirigirCategorias(): void
{
    header('Location: dashboard.php?seccion=categorias');
    exit;
}

// Comprobar que la petición procede de un formulario.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigirCategorias();
}

// Validar el token CSRF.
$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($tokenRecibido) ||
    !is_string($tokenSesion) ||
    $tokenSesion === '' ||
    !hash_equals($tokenSesion, $tokenRecibido)
) {
    $_SESSION['categorias_error'] =
        'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.';

    redirigirCategorias();
}

$accion = $_POST['accion'] ?? '';

try {
    /*
    |--------------------------------------------------------------------------
    | CREAR CATEGORÍA
    |--------------------------------------------------------------------------
    */
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? '';

        // Validar el nombre.
        if ($nombre === '') {
            throw new RuntimeException(
                'Introduce el nombre de la categoría.'
            );
        }

        if (mb_strlen($nombre) > 100) {
            throw new RuntimeException(
                'El nombre no puede superar los 100 caracteres.'
            );
        }

        // Validar el tipo.
        if (!in_array($tipo, ['ingreso', 'gasto'], true)) {
            throw new RuntimeException(
                'Selecciona un tipo de categoría válido.'
            );
        }

        // Comprobar si ya existe una categoría con ese nombre y tipo.
        $consulta = $conexion->prepare(
            'SELECT id
             FROM categorias
             WHERE usuario_id = :usuario_id
               AND tipo = :tipo
               AND LOWER(nombre) = LOWER(:nombre)
             LIMIT 1'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'nombre' => $nombre
        ]);

        if ($consulta->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Ya existe una categoría con ese nombre y tipo.'
            );
        }

        // Insertar la categoría.
        $consulta = $conexion->prepare(
            'INSERT INTO categorias (usuario_id, nombre, tipo)
             VALUES (:usuario_id, :nombre, :tipo)'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'nombre' => $nombre,
            'tipo' => $tipo
        ]);

        $_SESSION['categorias_mensaje'] =
            'La categoría se ha creado correctamente.';

    /*
    |--------------------------------------------------------------------------
    | EDITAR CATEGORÍA
    |--------------------------------------------------------------------------
    */
    } elseif ($accion === 'editar') {
        // Validar el identificador.
        $categoriaId = filter_var(
            $_POST['categoria_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $categoriaId === false ||
            $categoriaId === null ||
            $categoriaId < 1
        ) {
            throw new RuntimeException(
                'La categoría seleccionada no es válida.'
            );
        }

        // Obtener y validar los nuevos datos.
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? '';

        if ($nombre === '') {
            throw new RuntimeException(
                'Introduce el nombre de la categoría.'
            );
        }

        if (mb_strlen($nombre) > 100) {
            throw new RuntimeException(
                'El nombre no puede superar los 100 caracteres.'
            );
        }

        if (!in_array($tipo, ['ingreso', 'gasto'], true)) {
            throw new RuntimeException(
                'Selecciona un tipo de categoría válido.'
            );
        }

        // Comprobar que la categoría existe y pertenece al usuario.
        $consulta = $conexion->prepare(
            'SELECT id, nombre, tipo
             FROM categorias
             WHERE id = :id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );

        $consulta->execute([
            'id' => $categoriaId,
            'usuario_id' => $usuarioId
        ]);

        $categoriaActual = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$categoriaActual) {
            throw new RuntimeException(
                'La categoría no existe o no tienes permiso para modificarla.'
            );
        }

        // Comprobar si ya existe otra categoría con el mismo nombre y tipo.
        $consulta = $conexion->prepare(
            'SELECT id
             FROM categorias
             WHERE usuario_id = :usuario_id
               AND tipo = :tipo
               AND LOWER(nombre) = LOWER(:nombre)
               AND id != :id
             LIMIT 1'
        );

        $consulta->execute([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'nombre' => $nombre,
            'id' => $categoriaId
        ]);

        if ($consulta->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'Ya existe otra categoría con ese nombre y tipo.'
            );
        }

        // Comprobar si hay registros asociados a la categoría.
        $consulta = $conexion->prepare(
            'SELECT id
             FROM registros
             WHERE categoria_id = :categoria_id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );

        $consulta->execute([
            'categoria_id' => $categoriaId,
            'usuario_id' => $usuarioId
        ]);

        $tieneRegistros = (bool) $consulta->fetch(PDO::FETCH_ASSOC);

        // No permitir cambiar el tipo si hay registros asociados.
        if ($tieneRegistros && $tipo !== $categoriaActual['tipo']) {
            throw new RuntimeException(
                'No puedes cambiar el tipo de una categoría que tiene registros asociados. Puedes modificar su nombre.'
            );
        }

        // Actualizar únicamente la categoría del usuario autenticado.
        $consulta = $conexion->prepare(
            'UPDATE categorias
             SET nombre = :nombre,
                 tipo = :tipo
             WHERE id = :id
               AND usuario_id = :usuario_id'
        );

        $consulta->execute([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'id' => $categoriaId,
            'usuario_id' => $usuarioId
        ]);

        $_SESSION['categorias_mensaje'] =
            'La categoría se ha actualizado correctamente.';

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR CATEGORÍA
    |--------------------------------------------------------------------------
    */
    } elseif ($accion === 'borrar') {
        // Validar el identificador.
        $categoriaId = filter_var(
            $_POST['categoria_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $categoriaId === false ||
            $categoriaId === null ||
            $categoriaId < 1
        ) {
            throw new RuntimeException(
                'La categoría seleccionada no es válida.'
            );
        }

        // Comprobar que la categoría pertenece al usuario.
        $consulta = $conexion->prepare(
            'SELECT id
             FROM categorias
             WHERE id = :id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );

        $consulta->execute([
            'id' => $categoriaId,
            'usuario_id' => $usuarioId
        ]);

        if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'La categoría no existe o no tienes permiso para eliminarla.'
            );
        }

        // No permitir eliminar categorías utilizadas en registros.
        $consulta = $conexion->prepare(
            'SELECT id
             FROM registros
             WHERE categoria_id = :categoria_id
               AND usuario_id = :usuario_id
             LIMIT 1'
        );

        $consulta->execute([
            'categoria_id' => $categoriaId,
            'usuario_id' => $usuarioId
        ]);

        if ($consulta->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'No puedes eliminar una categoría que tiene registros asociados.'
            );
        }

        // Eliminar únicamente la categoría del usuario autenticado.
        $consulta = $conexion->prepare(
            'DELETE FROM categorias
             WHERE id = :id
               AND usuario_id = :usuario_id'
        );

        $consulta->execute([
            'id' => $categoriaId,
            'usuario_id' => $usuarioId
        ]);

        $_SESSION['categorias_mensaje'] =
            'La categoría se ha eliminado correctamente.';

    } else {
        throw new RuntimeException(
            'La operación solicitada no es válida.'
        );
    }

} catch (RuntimeException $e) {
    $_SESSION['categorias_error'] = $e->getMessage();

} catch (PDOException $e) {
    error_log('Error en categorías: ' . $e->getMessage());

    $_SESSION['categorias_error'] =
        'No se ha podido completar la operación. Inténtalo de nuevo.';
}

// Evitar reenviar el formulario al actualizar la página.
redirigirCategorias();
?>