<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layout.php';

$yo = requiere_rol('admin');
$pdo = db();

const ROLES = ['usuario' => 'Usuario', 'admin' => 'Administrador'];

function num_admins(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'")->fetchColumn();
}

/** Valida los datos del formulario. Devuelve lista de errores. */
function validar_usuario(array $d, bool $nuevo, ?int $id = null): array
{
    $err = [];
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $d['usuario'])) {
        $err[] = 'Usuario: 3-20 caracteres, letras, números o guion bajo.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 120) {
        $err[] = 'Email no válido.';
    }
    if (!array_key_exists($d['rol'], ROLES)) {
        $err[] = 'Rol no válido.';
    }
    if ($nuevo || $d['password'] !== '') {
        if (strlen($d['password']) < 8 || strlen($d['password']) > 128
            || !preg_match('/[A-Za-z]/', $d['password']) || !preg_match('/\d/', $d['password'])) {
            $err[] = 'Contraseña: 8-128 caracteres con letras y números.';
        }
    }
    if (!$err) {
        $stmt = db()->prepare('SELECT 1 FROM usuarios WHERE (usuario = ? OR email = ?) AND id <> ?');
        $stmt->execute([$d['usuario'], $d['email'], $id ?? 0]);
        if ($stmt->fetch()) {
            $err[] = 'Ya existe otra cuenta con ese usuario o email.';
        }
    }
    return $err;
}

$errores = [];
$form = null;   // datos del formulario abierto (crear/editar)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

    $d = [
        'usuario'  => trim((string) ($_POST['usuario'] ?? '')),
        'email'    => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'rol'      => (string) ($_POST['rol'] ?? 'usuario'),
        'password' => (string) ($_POST['password'] ?? ''),
    ];

    if ($accion === 'crear') {
        $errores = validar_usuario($d, true);
        if (!$errores) {
            $pdo->prepare('INSERT INTO usuarios (usuario, email, password_hash, rol) VALUES (?, ?, ?, ?)')
                ->execute([$d['usuario'], $d['email'], password_hash($d['password'], PASSWORD_DEFAULT), $d['rol']]);
            registrar_evento('ADMIN_USUARIO_CREADO', $yo['usuario'], 'usuario=' . $d['usuario'] . ' rol=' . $d['rol']);
            flash('ok', 'Usuario «' . $d['usuario'] . '» creado.');
            redirigir('/admin/usuarios');
        }
        $form = $d + ['id' => null];

    } elseif ($accion === 'guardar' && $id) {
        $stmt = $pdo->prepare('SELECT id, usuario, rol FROM usuarios WHERE id = ?');
        $stmt->execute([$id]);
        $actual = $stmt->fetch();
        if (!$actual) {
            flash('error', 'El usuario no existe.');
            redirigir('/admin/usuarios');
        }
        $errores = validar_usuario($d, false, $id);
        // Protección: no quitarse el rol a uno mismo ni dejar la tienda sin administradores
        if ($actual['rol'] === 'admin' && $d['rol'] !== 'admin') {
            if ($id === (int) $yo['id']) {
                $errores[] = 'No puedes quitarte a ti mismo el rol de administrador.';
            } elseif (num_admins() <= 1) {
                $errores[] = 'Debe quedar al menos un administrador.';
            }
        }
        if (!$errores) {
            if ($d['password'] !== '') {
                $pdo->prepare('UPDATE usuarios SET usuario = ?, email = ?, rol = ?, password_hash = ? WHERE id = ?')
                    ->execute([$d['usuario'], $d['email'], $d['rol'], password_hash($d['password'], PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare('UPDATE usuarios SET usuario = ?, email = ?, rol = ? WHERE id = ?')
                    ->execute([$d['usuario'], $d['email'], $d['rol'], $id]);
            }
            $cambios = 'id=' . $id . ' usuario=' . $d['usuario'] . ' rol=' . $actual['rol'] . '->' . $d['rol']
                     . ($d['password'] !== '' ? ' password=cambiada' : '');
            registrar_evento('ADMIN_USUARIO_EDITADO', $yo['usuario'], $cambios);
            flash('ok', 'Usuario «' . $d['usuario'] . '» actualizado.');
            redirigir('/admin/usuarios');
        }
        $form = $d + ['id' => $id];

    } elseif ($accion === 'eliminar' && $id) {
        if ($id === (int) $yo['id']) {
            flash('error', 'No puedes eliminar tu propia cuenta.');
        } else {
            $stmt = $pdo->prepare('SELECT usuario, rol FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if (!$u) {
                flash('error', 'El usuario no existe.');
            } elseif ($u['rol'] === 'admin' && num_admins() <= 1) {
                flash('error', 'Debe quedar al menos un administrador.');
            } else {
                // Borra en cascada su carrito y sus pedidos (claves foráneas)
                $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                registrar_evento('ADMIN_USUARIO_ELIMINADO', $yo['usuario'], 'id=' . $id . ' usuario=' . $u['usuario']);
                flash('ok', 'Usuario «' . $u['usuario'] . '» eliminado.');
            }
        }
        redirigir('/admin/usuarios');
    } else {
        flash('error', 'Acción no válida.');
        redirigir('/admin/usuarios');
    }
}

// Abrir formulario por GET
if ($form === null) {
    if (isset($_GET['nuevo'])) {
        $form = ['id' => null, 'usuario' => '', 'email' => '', 'rol' => 'usuario', 'password' => ''];
    } elseif ($eid = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $stmt = $pdo->prepare('SELECT id, usuario, email, rol FROM usuarios WHERE id = ?');
        $stmt->execute([$eid]);
        $form = $stmt->fetch() ?: null;
        if ($form) {
            $form['password'] = '';
        }
    }
}

$q = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 60)) : '';
$sql = 'SELECT u.id, u.usuario, u.email, u.rol, u.creado_en, u.ultimo_acceso,
               (SELECT COUNT(*) FROM pedidos p WHERE p.usuario_id = u.id) AS num_pedidos
          FROM usuarios u';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.usuario LIKE ? OR u.email LIKE ?';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $params = [$like, $like];
}
$stmt = $pdo->prepare($sql . ' ORDER BY u.rol, u.id');
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

cabecera('Usuarios · Admin');
?>
<div class="admin-cab">
    <h1 class="titulo-pagina">Usuarios</h1>
    <form class="filtro" method="get" action="/admin/usuarios">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar usuario o email" maxlength="60">
        <button class="btn btn--mini" type="submit">Buscar</button>
    </form>
    <a href="/admin/usuarios?nuevo=1" class="btn btn--primario">+ Nuevo usuario</a>
</div>

<?php if ($form !== null): $editando = $form['id'] !== null; ?>
<section class="panel panel--form">
    <h2><?= $editando ? 'Editar usuario #' . (int) $form['id'] : 'Nuevo usuario' ?></h2>
    <?php if ($errores): ?>
        <div class="aviso aviso--error" role="alert"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/admin/usuarios" class="form form--rejilla" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="<?= $editando ? 'guardar' : 'crear' ?>">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><?php endif; ?>
        <div>
            <label for="usuario">Usuario</label>
            <input id="usuario" name="usuario" maxlength="20" required value="<?= e($form['usuario']) ?>">
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="120" required value="<?= e($form['email']) ?>">
        </div>
        <div>
            <label for="rol">Rol</label>
            <select id="rol" name="rol">
                <?php foreach (ROLES as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $form['rol'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="password"><?= $editando ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
            <input id="password" name="password" type="password" autocomplete="new-password" maxlength="128"
                   <?= $editando ? 'placeholder="Déjalo vacío para no cambiarla"' : 'required' ?>>
        </div>
        <div class="form__acciones">
            <a href="/admin/usuarios" class="btn btn--texto">Cancelar</a>
            <button type="submit" class="btn btn--primario"><?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="tabla-scroll">
    <table class="tabla">
        <thead>
            <tr><th>ID</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Pedidos</th><th>Alta</th><th>Último acceso</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): $soy_yo = (int) $u['id'] === (int) $yo['id']; ?>
            <tr>
                <td class="mono">#<?= (int) $u['id'] ?></td>
                <td><strong><?= e($u['usuario']) ?></strong><?= $soy_yo ? ' <small class="texto-suave">(tú)</small>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="chip <?= $u['rol'] === 'admin' ? 'chip--naranja' : '' ?>"><?= e(ROLES[$u['rol']] ?? $u['rol']) ?></span></td>
                <td><?= (int) $u['num_pedidos'] ?></td>
                <td class="nowrap"><?= e(date('d/m/Y', strtotime($u['creado_en']))) ?></td>
                <td class="nowrap"><?= $u['ultimo_acceso'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_acceso']))) : '—' ?></td>
                <td class="acciones-fila">
                    <a href="/admin/usuarios?editar=<?= (int) $u['id'] ?>" class="btn btn--mini">Editar</a>
                    <?php if (!$soy_yo): ?>
                    <form method="post" action="/admin/usuarios" data-confirmar="¿Eliminar al usuario «<?= e($u['usuario']) ?>»? Se borrarán también su carrito y sus pedidos.">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn btn--mini btn--mini-peligro">Eliminar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$usuarios): ?><tr><td colspan="8" class="texto-suave">Sin resultados.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php pie(); ?>
