<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/pedidos.php';

requiere_rol('admin');

$pdo = db();
$stats = [
    'usuarios'   => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario'")->fetchColumn(),
    'productos'  => (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn(),
    'pendientes' => (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'")->fetchColumn(),
    'ventas'     => (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado <> 'cancelado'")->fetchColumn(),
];
$sin_stock = $pdo->query('SELECT id, nombre, stock FROM productos WHERE stock < 5 ORDER BY stock, nombre')->fetchAll();
$eventos = $pdo->query('SELECT fecha, tipo, usuario, ip, detalle FROM eventos ORDER BY id DESC LIMIT 12')->fetchAll();

cabecera('Panel de administración');
?>
<h1 class="titulo-pagina">Panel de administración</h1>

<div class="stats">
    <a href="/admin/usuarios" class="stat"><span>Clientes</span><strong><?= $stats['usuarios'] ?></strong></a>
    <a href="/admin/productos" class="stat"><span>Productos</span><strong><?= $stats['productos'] ?></strong></a>
    <a href="/admin/pedidos" class="stat"><span>Pedidos pendientes</span><strong><?= $stats['pendientes'] ?></strong></a>
    <div class="stat stat--acento"><span>Ventas (sin cancelados)</span><strong><?= precio($stats['ventas']) ?></strong></div>
</div>

<div class="admin-dos">
    <section class="panel">
        <h2>Actividad de seguridad reciente</h2>
        <?php if (!$eventos): ?>
            <p class="texto-suave">Sin eventos registrados.</p>
        <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla tabla--compacta">
            <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td class="nowrap"><?= e(date('d/m H:i:s', strtotime($ev['fecha']))) ?></td>
                    <td><span class="evento evento--<?= e(strtolower(strtok($ev['tipo'], '_'))) ?>"><?= e($ev['tipo']) ?></span></td>
                    <td><?= e($ev['usuario'] ?? '—') ?></td>
                    <td class="mono"><?= e($ev['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Stock bajo</h2>
        <?php if (!$sin_stock): ?>
            <p class="texto-suave">Todos los productos tienen 5 unidades o más.</p>
        <?php else: ?>
        <ul class="lista-stock">
            <?php foreach ($sin_stock as $p): ?>
            <li>
                <a href="/admin/productos?editar=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></a>
                <span class="chip <?= (int) $p['stock'] === 0 ? 'chip--rojo' : 'chip--ambar' ?>"><?= (int) $p['stock'] ?> uds.</span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
<?php pie(); ?>
