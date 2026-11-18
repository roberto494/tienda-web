<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/layout.php';

const MAX_INTENTOS = 5;      // intentos fallidos permitidos…
const VENTANA_MIN  = 15;     // …por IP en estos minutos

if (usuario_autenticado()) {
    redirigir(es_admin() ? '/admin/' : '/tienda');
}

/** Número de fallos recientes desde esta IP (freno básico contra fuerza bruta). */
function fallos_recientes(string $ip): int
{
    $desde = date('Y-m-d H:i:s', time() - VENTANA_MIN * 60);
    $stmt = db()->prepare("SELECT COUNT(*) FROM eventos WHERE tipo = 'LOGIN_FALLIDO' AND ip = ? AND fecha >= ?");
    $stmt->execute([$ip, $desde]);
    return (int) $stmt->fetchColumn();
}

$error = '';
$identificador = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();

    $identificador = trim((string) ($_POST['identificador'] ?? ''));
    $password      = (string) ($_POST['password'] ?? '');
    $ip            = $_SERVER['REMOTE_ADDR'] ?? '';

    if (fallos_recientes($ip) >= MAX_INTENTOS) {
        registrar_evento('LOGIN_BLOQUEADO', $identificador);
        $error = 'Demasiados intentos fallidos. Espera ' . VENTANA_MIN . ' minutos e inténtalo de nuevo.';
    } elseif ($identificador === '' || $password === '' || strlen($identificador) > 120 || strlen($password) > 128) {
        $error = 'Introduce tu usuario o email y tu contraseña.';
    } else {
        $stmt = db()->prepare('SELECT id, usuario, password_hash, rol FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1');
        $stmt->execute([$identificador, mb_strtolower($identificador)]);
        $usuario = $stmt->fetch();

        // Si el usuario no existe se verifica contra un hash ficticio para tardar lo mismo
        $hash = $usuario['password_hash'] ?? '$2y$10$vq0/ah9WasyMxhcsrBygR.VosKQihNFlJEY8pIVYIykVd6EIMd2gi';
        $valida = password_verify($password, $hash);

        if ($usuario && $valida) {
            if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
                $upd = db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
                $upd->execute([password_hash($password, PASSWORD_DEFAULT), $usuario['id']]);
            }
            login_usuario($usuario);
            registrar_evento('LOGIN_OK', $usuario['usuario'], 'rol=' . $usuario['rol']);
            flash('ok', '¡Bienvenido de nuevo, ' . $usuario['usuario'] . '!');
            redirigir($usuario['rol'] === 'admin' ? '/admin/' : '/tienda');
        }

        registrar_evento('LOGIN_FALLIDO', $identificador);
        // Mensaje genérico: no revela si el usuario existe
        $error = 'Usuario o contraseña incorrectos.';
    }
}

cabecera('Iniciar sesión', false);
?>
<div class="auth-layout">
    <section class="auth-promo">
        <a href="/login" class="logo logo--grande">
            <img src="/assets/img/logo.svg" alt="" width="48" height="48">
            <span>Nexo<strong>Bit</strong></span>
        </a>
        <h1>Tu próximo setup<br>empieza aquí.</h1>
        <p>Componentes y periféricos seleccionados para jugar, crear y trabajar sin límites.</p>
        <ul class="auth-ventajas">
            <li>Envío en 24 h</li>
            <li>Garantía de 3 años</li>
            <li>30 días de devolución</li>
        </ul>
        <img class="auth-promo__img" src="/assets/img/gpu.svg" alt="">
    </section>

    <section class="auth-card">
        <h2>Iniciar sesión</h2>
        <p class="auth-sub">Accede con tu cuenta para ver el catálogo.</p>

        <?php if ($error): ?>
            <div class="aviso aviso--error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" class="form" novalidate>
            <?= csrf_campo() ?>
            <label for="identificador">Usuario o email</label>
            <input id="identificador" name="identificador" type="text" autocomplete="username"
                   maxlength="120" required autofocus value="<?= e($identificador) ?>">

            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password"
                   maxlength="128" required>

            <button type="submit" class="btn btn--primario btn--bloque">Entrar</button>
        </form>

        <p class="auth-cambio">¿Aún no tienes cuenta? <a href="/registro">Crea una gratis</a></p>
    </section>
</div>
<?php pie(false); ?>
