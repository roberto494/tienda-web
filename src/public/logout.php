<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';

// Solo por POST y con token CSRF: un enlace externo no puede cerrar tu sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(usuario_autenticado() ? '/tienda' : '/login');
}
csrf_verificar();

$usuario = $_SESSION['usuario'] ?? null;
cerrar_sesion();
registrar_evento('LOGOUT', $usuario);

session_start();
flash('ok', 'Has cerrado sesión correctamente.');
redirigir('/login');
