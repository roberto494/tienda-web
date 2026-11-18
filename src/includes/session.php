<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

const SESSION_TIMEOUT = 1800; // 30 minutos de inactividad

/** Arranca la sesión con parámetros seguros de cookie. */
function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    // Caducidad por inactividad
    if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > SESSION_TIMEOUT) {
        $usuario = $_SESSION['usuario'] ?? null;
        cerrar_sesion();
        registrar_evento('SESSION_TIMEOUT', $usuario);
        session_start();
        $_SESSION['flash'] = ['tipo' => 'info', 'texto' => 'Tu sesión ha caducado por inactividad.'];
    }
    $_SESSION['ultima_actividad'] = time();
}

function usuario_autenticado(): bool
{
    return usuario_actual() !== null;
}

/** Inicia sesión de un usuario ya verificado. */
function login_usuario(array $usuario): void
{
    // Nuevo identificador de sesión: evita la fijación de sesión
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario']    = $usuario['usuario'];
    $_SESSION['ultima_actividad'] = time();
    unset($_SESSION['csrf']);

    $stmt = db()->prepare('UPDATE usuarios SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$usuario['id']]);
}

/**
 * Usuario de la sesión, leído SIEMPRE de la base de datos.
 * Así un cambio de rol o un borrado hecho por el admin se aplica al instante.
 */
function usuario_actual(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (isset($_SESSION['usuario_id'])) {
        $stmt = db()->prepare('SELECT id, usuario, email, rol FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['usuario_id']]);
        $cache = $stmt->fetch() ?: null;
        if ($cache === null) {
            // La cuenta ya no existe: se invalida la sesión
            cerrar_sesion();
            session_start();
        }
    }
    return $cache;
}

function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? '') === 'admin';
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}

/* ---------- Mensajes flash ---------- */

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function obtener_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Protección CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        registrar_evento('CSRF_INVALIDO', $_SESSION['usuario'] ?? null, $_SERVER['REQUEST_URI'] ?? '');
        http_response_code(400);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }
}

/* ---------- Utilidades ---------- */

/** Escapa texto para HTML (previene XSS). */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirigir(string $ruta): never
{
    header('Location: ' . $ruta, true, 303);
    exit;
}

/** Devuelve solo rutas internas de la web (evita redirecciones abiertas a otros dominios). */
function ruta_interna(string $url, string $por_defecto): string
{
    $ruta  = parse_url($url, PHP_URL_PATH) ?: '';
    $query = parse_url($url, PHP_URL_QUERY);
    if (!preg_match('#^/[a-z/]*$#', $ruta) || str_starts_with($ruta, '//')) {
        return $por_defecto;
    }
    return $ruta . ($query ? '?' . $query : '');
}

function precio(float|string $valor): string
{
    return number_format((float) $valor, 2, ',', '.') . ' €';
}

iniciar_sesion();
