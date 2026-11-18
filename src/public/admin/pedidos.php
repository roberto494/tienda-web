<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/pedidos.php';

$yo = requiere_rol('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $accion = (string) ($_POST['accion'] ?? '');

    if ($id && $accion === 'enviar') {
        $upd = $pdo->prepare("UPDATE pedidos SET estado = 'enviado' WHERE id = ? AND estado = 'pendiente'");
        $upd->execute([$id]);
        if ($upd->rowCount() === 1) {
            registrar_evento('ADMIN_PEDIDO_ENVIADO', $yo['usuario'], 'pedido=' . $id);
            flash('ok', 'Pedido #' . $id . ' marcado como enviado.');
        } else {
            flash('error', 'Solo se pueden enviar pedidos pendientes.');
        }
    } elseif ($id && $accion === 'cancelar') {
        if (cancelar_pedido($id)) {
            registrar_evento('ADMIN_PEDIDO_CANCELADO', $yo['usuario'], 'pedido=' . $id);
            flash('ok', 'Pedido #' . $id . ' cancelado y stock repuesto.');
        } else {
            flash('error', 'Solo se pueden cancelar pedidos pendientes.');
        }
    } else {
        flash('error', 'Acción no válida.');
    }
    redirigir('/admin/pedidos');
}

$estado = is_string($_GET['estado'] ?? null) && array_key_exists($_GET['estado'], ESTADOS) ? $_GET['estado'] : '';
$sql = 'SELECT p.id, p.fecha, p.estado, p.total, u.usuario
          FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id';
$params = [];
if ($estado !== '') {
    $sql .= ' WHERE p.estado = ?';
    $params[] = $estado;
}
$stmt = $pdo->prepare($sql . ' ORDER BY p.id DESC');
$stmt->execute($params);
$pedidos = $stmt->fetchAll();
$lineas = lineas_de_pedidos(array_column($pedidos, 'id'));

cabecera('Pedidos · Admin');
?>
<div class="admin-cab">
    <h1 class="titulo-pagina">Pedidos</h1>
    <nav class="pestanas">
        <a href="/admin/pedidos" class="<?= $estado === '' ? 'activo' : '' ?>">Todos</a>
        <?php foreach (ESTADOS as $k => $v): ?>
            <a href="/admin/pedidos?estado=<?= $k ?>" class="<?= $estado === $k ? 'activo' : '' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<section class="panel">
    <div class="tabla-scroll">
    <table class="tabla">
        <thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Productos</th><th>Total</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pedidos as $p): ?>
            <tr>
                <td class="mono">#<?= (int) $p['id'] ?></td>
                <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($p['fecha']))) ?></td>
                <td><?= e($p['usuario']) ?></td>
                <td class="celda-lineas">
                    <?php foreach ($lineas[$p['id']] ?? [] as $l): ?>
                        <div><?= (int) $l['cantidad'] ?> × <?= e($l['nombre']) ?></div>
                    <?php endforeach; ?>
                </td>
                <td class="nowrap"><strong><?= precio($p['total']) ?></strong></td>
                <td><span class="estado estado--<?= e($p['estado']) ?>"><?= e(ESTADOS[$p['estado']]) ?></span></td>
                <td class="acciones-fila">
                    <?php if ($p['estado'] === 'pendiente'): ?>
                    <form method="post" action="/admin/pedidos">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="enviar">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="btn btn--mini">Marcar enviado</button>
                    </form>
                    <form method="post" action="/admin/pedidos" data-confirmar="¿Cancelar el pedido #<?= (int) $p['id'] ?>?">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="cancelar">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="btn btn--mini btn--mini-peligro">Cancelar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pedidos): ?><tr><td colspan="7" class="texto-suave">No hay pedidos.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php pie(); ?>
