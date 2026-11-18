<?php
declare(strict_types=1);

/**
 * Crea la cuenta de administrador a partir de ADMIN_USER / ADMIN_EMAIL / ADMIN_PASSWORD (.env).
 * Se ejecuta al arrancar el contenedor (docker/entrypoint.sh).
 * Si ya existe un usuario con ese nombre, no hace nada: no sobrescribe contraseñas.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$usuario  = getenv('ADMIN_USER') ?: '';
$email    = mb_strtolower(getenv('ADMIN_EMAIL') ?: '');
$password = getenv('ADMIN_PASSWORD') ?: '';

if ($usuario === '' || $email === '' || $password === '') {
    fwrite(STDERR, "[ADMIN] ADMIN_USER, ADMIN_EMAIL o ADMIN_PASSWORD vacíos: no se crea administrador.\n");
    exit(0);
}
if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $usuario) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "[ADMIN] ADMIN_USER o ADMIN_EMAIL con formato no válido.\n");
    exit(0);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "[ADMIN] ADMIN_PASSWORD debe tener al menos 12 caracteres.\n");
    exit(0);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? OR email = ?');
$stmt->execute([$usuario, $email]);
if ($stmt->fetch()) {
    fwrite(STDERR, "[ADMIN] El administrador '$usuario' ya existe. Sin cambios.\n");
    exit(0);
}

$stmt = $pdo->prepare("INSERT INTO usuarios (usuario, email, password_hash, rol) VALUES (?, ?, ?, 'admin')");
$stmt->execute([$usuario, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDERR, "[ADMIN] Administrador '$usuario' creado.\n");
