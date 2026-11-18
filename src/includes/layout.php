<?php
declare(strict_types=1);

/** Cabecera común. $titulo: título de la página; $privada: muestra la barra de la tienda. */
function cabecera(string $titulo, bool $privada = true): void
{
    $flash = obtener_flash();
    $yo = usuario_actual();
    $admin = ($yo['rol'] ?? '') === 'admin';
    $unidades = 0;
    if ($yo && !$admin) {
        $stmt = db()->prepare('SELECT COALESCE(SUM(cantidad), 0) FROM carrito WHERE usuario_id = ?');
        $stmt->execute([$yo['id']]);
        $unidades = (int) $stmt->fetchColumn();
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $actual = basename($script, '.php');
    $en_admin = str_starts_with($script, '/admin/');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · NexoBit</title>
    <link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body class="<?= $privada ? 'app' : 'auth' ?>">
<?php if ($privada): ?>
    <div class="topbar">
        <span>Envío gratis a partir de 50 €</span>
        <span>Laboratorio local · Datos ficticios</span>
    </div>
    <header class="header">
        <div class="contenedor header__fila">
            <a href="<?= $admin ? '/admin/' : '/tienda' ?>" class="logo" aria-label="NexoBit, inicio">
                <img src="/assets/img/logo.svg" alt="" width="36" height="36">
                <span>Nexo<strong>Bit</strong></span>
            </a>

            <form class="buscador" action="/tienda" method="get" role="search">
                <input type="search" name="q" placeholder="Busca gráficas, procesadores, SSD…"
                       maxlength="60" value="<?= e(is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>">
                <button type="submit" aria-label="Buscar">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M16.5 16.5 21 21" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                </button>
            </form>

            <nav class="acciones">
                <span class="saludo">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Hola, <strong><?= e($yo['usuario'] ?? '') ?></strong>
                    <?php if ($admin): ?><span class="rol-chip">ADMIN</span><?php endif; ?>
                </span>
                <?php if ($admin): ?>
                <a href="/admin/" class="btn-carrito <?= $en_admin ? 'activo' : '' ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m8.5 12 2.5 2.5 4.5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Panel admin
                </a>
                <?php else: ?>
                <a href="/pedidos" class="enlace-cab <?= $actual === 'pedidos' ? 'activo' : '' ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z M3 8l9 5 9-5 M12 13v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                    Mis pedidos
                </a>
                <a href="/carrito" class="btn-carrito <?= $actual === 'carrito' ? 'activo' : '' ?>">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20.5" r="1.4" fill="currentColor"/><circle cx="17" cy="20.5" r="1.4" fill="currentColor"/></svg>
                    Carrito
                    <?php if ($unidades > 0): ?><span class="badge"><?= (int) $unidades ?></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <form action="/logout" method="post" class="form-logout">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn-salir">Salir</button>
                </form>
            </nav>
        </div>
        <nav class="categorias <?= $en_admin ? 'categorias--admin' : '' ?>">
            <div class="contenedor">
                <?php if ($en_admin):
                    $secciones = ['/admin/' => ['index', 'Resumen'], '/admin/usuarios' => ['usuarios', 'Usuarios'],
                                  '/admin/productos' => ['productos', 'Productos'], '/admin/pedidos' => ['pedidos', 'Pedidos']];
                    foreach ($secciones as $href => [$clave, $nombre]): ?>
                    <a href="<?= $href ?>" class="<?= $actual === $clave ? 'activo' : '' ?>"><?= $nombre ?></a>
                <?php endforeach; ?>
                    <a href="/tienda" class="categorias__dcha">Ver tienda →</a>
                <?php else:
                $cat = is_string($_GET['cat'] ?? null) ? $_GET['cat'] : '';
                $enlaces = ['' => 'Todo'] + CATEGORIAS;
                foreach ($enlaces as $clave => $nombre):
                    $href = $clave === '' ? '/tienda' : '/tienda?cat=' . urlencode($clave);
                    $act = ($actual === 'tienda' && $cat === $clave) ? 'activo' : '';
                ?>
                    <a href="<?= e($href) ?>" class="<?= $act ?>"><?= e($nombre) ?></a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </nav>
    </header>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="contenedor">
        <div class="aviso aviso--<?= e($flash['tipo']) ?>" role="status"><?= e($flash['texto']) ?></div>
    </div>
<?php endif; ?>
<main class="<?= $privada ? 'contenedor principal' : '' ?>">
<?php
}

function pie(bool $privada = true): void
{
    ?>
</main>
<?php if ($privada): ?>
<footer class="footer">
    <div class="contenedor footer__fila">
        <div>
            <a href="/tienda" class="logo logo--pie">
                <img src="/assets/img/logo.svg" alt="" width="28" height="28">
                <span>Nexo<strong>Bit</strong></span>
            </a>
            <p>Tienda ficticia creada para la práctica de Puesta en Producción Segura.</p>
        </div>
        <ul>
            <li>Pago seguro (simulado)</li>
            <li>Devolución 30 días</li>
            <li>Garantía 3 años</li>
        </ul>
        <p class="footer__legal">© <?= date('Y') ?> NexoBit · Proyecto educativo · Solo en localhost</p>
    </div>
</footer>
<?php endif; ?>
</body>
</html>
<?php
}

const CATEGORIAS = [
    'graficas'       => 'Tarjetas gráficas',
    'procesadores'   => 'Procesadores',
    'memoria'        => 'Memoria RAM',
    'almacenamiento' => 'Almacenamiento',
    'perifericos'    => 'Periféricos',
    'monitores'      => 'Monitores',
];
