<?php
declare(strict_types=1);

/**
 * Incluir al principio de TODA página privada.
 * - Sin sesión -> redirige al login.
 * - requiere_rol('admin' | 'usuario') -> control de acceso por rol.
 */
require_once __DIR__ . '/session.php';

if (!usuario_autenticado()) {
    registrar_evento('ACCESO_DENEGADO', null, $_SERVER['REQUEST_URI'] ?? '');
    flash('info', 'Inicia sesión para acceder a la tienda.');
    redirigir('/login');
}

/** Corta la ejecución si el usuario no tiene el rol indicado. Devuelve el usuario. */
function requiere_rol(string $rol): array
{
    $yo = usuario_actual();
    if ($yo['rol'] !== $rol) {
        registrar_evento('ACCESO_PROHIBIDO', $yo['usuario'], ($_SERVER['REQUEST_URI'] ?? '') . ' rol=' . $yo['rol']);
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        cabecera('Acceso denegado');
        echo '<div class="vacio"><p><strong>403 · No tienes permiso para ver esta página.</strong></p>'
           . '<a href="' . ($yo['rol'] === 'admin' ? '/admin/' : '/tienda') . '" class="btn btn--secundario">Volver</a></div>';
        pie();
        exit;
    }
    return $yo;
}
