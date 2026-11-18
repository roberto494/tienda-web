<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/layout.php';

if (usuario_autenticado()) {
    redirigir('/tienda');
}

$errores = [];
$usuario = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();

    $usuario   = trim((string) ($_POST['usuario'] ?? ''));
    $email     = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password  = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    // Validación en servidor (la del navegador se puede saltar)
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $usuario)) {
        $errores[] = 'El usuario debe tener entre 3 y 20 caracteres: letras, números o guion bajo.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $errores[] = 'Introduce un email válido.';
    }
    if (strlen($password) < 8 || strlen($password) > 128
        || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errores[] = 'La contraseña debe tener entre 8 y 128 caracteres e incluir letras y números.';
    }
    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $stmt = db()->prepare('SELECT 1 FROM usuarios WHERE usuario = ? OR email = ?');
        $stmt->execute([$usuario, $email]);
        if ($stmt->fetch()) {
            $errores[] = 'Ese usuario o email ya está registrado.';
        }
    }

    if (!$errores) {
        try {
            $stmt = db()->prepare('INSERT INTO usuarios (usuario, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$usuario, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $nuevo_id = (int) db()->lastInsertId();   // antes de registrar el evento (que también inserta)
        } catch (PDOException $ex) {
            // Dos registros simultáneos con el mismo usuario: lo frena el índice UNIQUE
            error_log('[REGISTRO] ' . $ex->getMessage());
            $errores[] = 'Ese usuario o email ya está registrado.';
        }
    }

    if (!$errores) {
        registrar_evento('REGISTRO', $usuario);
        login_usuario(['id' => $nuevo_id, 'usuario' => $usuario]);
        flash('ok', 'Cuenta creada. ¡Bienvenido a NexoBit, ' . $usuario . '!');
        redirigir('/tienda');
    }
}

cabecera('Crear cuenta', false);
?>
<div class="auth-layout">
    <section class="auth-promo">
        <a href="/login" class="logo logo--grande">
            <img src="/assets/img/logo.svg" alt="" width="48" height="48">
            <span>Nexo<strong>Bit</strong></span>
        </a>
        <h1>Únete a<br>NexoBit.</h1>
        <p>Crea tu cuenta en segundos y accede a ofertas exclusivas en hardware.</p>
        <ul class="auth-ventajas">
            <li>Precios exclusivos para miembros</li>
            <li>Carrito guardado en tu sesión</li>
            <li>Contraseñas cifradas con bcrypt</li>
        </ul>
        <img class="auth-promo__img" src="/assets/img/teclado.svg" alt="">
    </section>

    <section class="auth-card">
        <h2>Crear cuenta</h2>
        <p class="auth-sub">Solo datos ficticios: esto es un laboratorio.</p>

        <?php if ($errores): ?>
            <div class="aviso aviso--error" role="alert">
                <ul><?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/registro" class="form" novalidate>
            <?= csrf_campo() ?>
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" type="text" autocomplete="username"
                   minlength="3" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" required value="<?= e($usuario) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" maxlength="120" required value="<?= e($email) ?>">

            <div class="form__doble">
                <div>
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required>
                </div>
                <div>
                    <label for="password2">Repetir contraseña</label>
                    <input id="password2" name="password2" type="password" autocomplete="new-password" minlength="8" maxlength="128" required>
                </div>
            </div>
            <p class="form__ayuda">Mínimo 8 caracteres, con letras y números.</p>

            <button type="submit" class="btn btn--primario btn--bloque">Crear cuenta</button>
        </form>

        <p class="auth-cambio">¿Ya tienes cuenta? <a href="/login">Inicia sesión</a></p>
    </section>
</div>
<?php pie(false); ?>
