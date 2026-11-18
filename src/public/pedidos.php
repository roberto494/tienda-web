<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pedidos.php';

$yo = requiere_rol('usuario');
$uid = (int) $yo['id'];

// Cancelar pedido: solo el propio, y solo si sigue pendiente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (($_POST['accion'] ?? '') === 'cancelar' && $id) {
        // cancelar_pedido() exige que el pedido sea de este usuario (evita IDOR)
        if (cancelar_pedido($id, $uid)) {
            registrar_evento('PEDIDO_CANCELADO', $yo['usuario'], 'pedido=' . $id);
            flash('ok', 'Pedido #' . $id . ' cancelado. Las unidades vuelven al stock.');
        } else {
            registrar_evento('CANCELACION_RECHAZADA', $yo['usuario'], 'pedido=' . $id);
            flash('error', 'Ese pedido no se puede cancelar.');
        }
    }
    redirigir('/pedidos');
}

$stmt = db()->prepare('SELECT id, fecha, estado, total FROM pedidos WHERE usuario_id = ? ORDER BY id DESC');
$stmt->execute([$uid]);
$pedidos = $stmt->fetchAll();

$lineas = lineas_de_pedidos(array_column($pedidos, 'id'));

cabecera('Mis pedidos');
?>
<h1 class="titulo-pagina">Mis pedidos</h1>

<?php if (!$pedidos): ?>
    <div class="vacio">
        <img src="/assets/img/carrito-vacio.svg" alt="" width="140" height="140">
        <p>Todavía no has hecho ningún pedido.</p>
        <a href="/tienda" class="btn btn--primario">Ir a la tienda</a>
    </div>
<?php else: ?>
<div class="pedidos">
    <?php foreach ($pedidos as $p): ?>
    <article class="pedido">
        <header class="pedido__cab">
            <div>
                <span class="pedido__num">Pedido #<?= (int) $p['id'] ?></span>
                <span class="pedido__fecha"><?= e(date('d/m/Y H:i', strtotime($p['fecha']))) ?></span>
            </div>
            <span class="estado estado--<?= e($p['estado']) ?>"><?= e(ESTADOS[$p['estado']] ?? $p['estado']) ?></span>
        </header>

        <ul class="pedido__lineas">
            <?php foreach ($lineas[$p['id']] ?? [] as $l): ?>
            <li>
                <img src="/assets/img/<?= e($l['imagen']) ?>" alt="">
                <span class="pedido__prod">
                    <?php if ($l['producto_id']): ?>
                        <a href="/producto?id=<?= (int) $l['producto_id'] ?>"><?= e($l['nombre']) ?></a>
                    <?php else: ?>
                        <?= e($l['nombre']) ?> <small>(ya no disponible)</small>
                    <?php endif; ?>
                </span>
                <span class="pedido__cant">× <?= (int) $l['cantidad'] ?></span>
                <span class="pedido__precio"><?= precio($l['precio_unitario'] * $l['cantidad']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>

        <footer class="pedido__pie">
            <span>Total <strong><?= precio($p['total']) ?></strong></span>
            <?php if ($p['estado'] === 'pendiente'): ?>
            <form method="post" action="/pedidos" data-confirmar="¿Seguro que quieres cancelar el pedido #<?= (int) $p['id'] ?>?">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="cancelar">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="btn btn--peligro">Cancelar pedido</button>
            </form>
            <?php endif; ?>
        </footer>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php pie(); ?>
