<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$yo = requiere_rol('usuario');   // el admin no compra
$uid = (int) $yo['id'];

const MAX_UNIDADES = 10;
const ENVIO_GRATIS = 50.0;
const COSTE_ENVIO  = 4.99;

/** Líneas del carrito del usuario con los datos actuales del producto. */
function lineas_carrito(int $uid): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.nombre, p.precio, p.imagen, p.stock, c.cantidad
           FROM carrito c JOIN productos p ON p.id = c.producto_id
          WHERE c.usuario_id = ? ORDER BY c.anadido_en, p.id'
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();

    $accion   = (string) ($_POST['accion'] ?? '');
    $id       = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $cantidad = filter_var($_POST['cantidad'] ?? null, FILTER_VALIDATE_INT,
                           ['options' => ['min_range' => 1, 'max_range' => MAX_UNIDADES]]);
    $volver   = '/carrito';

    switch ($accion) {
        case 'anadir':
        case 'actualizar':
            if (!$id || !$cantidad) {
                flash('error', 'Cantidad no válida (entre 1 y ' . MAX_UNIDADES . ').');
                break;
            }
            $stmt = db()->prepare('SELECT nombre, stock FROM productos WHERE id = ?');
            $stmt->execute([$id]);
            $prod = $stmt->fetch();
            if (!$prod) {
                flash('error', 'El producto no existe.');
                break;
            }
            if ((int) $prod['stock'] === 0) {
                flash('error', '«' . $prod['nombre'] . '» está agotado.');
                break;
            }
            $stmt = db()->prepare('SELECT cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?');
            $stmt->execute([$uid, $id]);
            $actual = $stmt->fetchColumn();

            $nueva = $accion === 'anadir' ? (int) $actual + $cantidad : $cantidad;
            $nueva = min($nueva, MAX_UNIDADES, (int) $prod['stock']);

            if ($actual === false) {
                $stmt = db()->prepare('INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)');
                $stmt->execute([$uid, $id, $nueva]);
            } else {
                $stmt = db()->prepare('UPDATE carrito SET cantidad = ? WHERE usuario_id = ? AND producto_id = ?');
                $stmt->execute([$nueva, $uid, $id]);
            }
            flash('ok', $accion === 'anadir' ? '«' . $prod['nombre'] . '» añadido al carrito.' : 'Carrito actualizado.');
            if ($accion === 'anadir') {
                $volver = ruta_interna($_SERVER['HTTP_REFERER'] ?? '', '/tienda');
            }
            break;

        case 'eliminar':
            if ($id) {
                $stmt = db()->prepare('DELETE FROM carrito WHERE usuario_id = ? AND producto_id = ?');
                $stmt->execute([$uid, $id]);
                flash('ok', 'Producto eliminado del carrito.');
            }
            break;

        case 'vaciar':
            db()->prepare('DELETE FROM carrito WHERE usuario_id = ?')->execute([$uid]);
            flash('ok', 'Carrito vaciado.');
            break;

        case 'comprar':
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $lineas = lineas_carrito($uid);
                if (!$lineas) {
                    throw new RuntimeException('Tu carrito está vacío.');
                }
                $subtotal = 0.0;
                foreach ($lineas as $l) {
                    // Descuento atómico: solo si queda stock suficiente
                    $upd = $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?');
                    $upd->execute([$l['cantidad'], $l['id'], $l['cantidad']]);
                    if ($upd->rowCount() !== 1) {
                        throw new RuntimeException('No hay stock suficiente de «' . $l['nombre'] . '». Ajusta la cantidad.');
                    }
                    $subtotal += $l['cantidad'] * (float) $l['precio'];
                }
                $total = $subtotal + ($subtotal >= ENVIO_GRATIS ? 0 : COSTE_ENVIO);

                $pdo->prepare('INSERT INTO pedidos (usuario_id, fecha, total) VALUES (?, ?, ?)')
                    ->execute([$uid, date('Y-m-d H:i:s'), round($total, 2)]);
                $pedido_id = (int) $pdo->lastInsertId();

                $ins = $pdo->prepare(
                    'INSERT INTO pedido_lineas (pedido_id, producto_id, nombre, imagen, precio_unitario, cantidad)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                foreach ($lineas as $l) {
                    $ins->execute([$pedido_id, $l['id'], $l['nombre'], $l['imagen'], $l['precio'], $l['cantidad']]);
                }
                $pdo->prepare('DELETE FROM carrito WHERE usuario_id = ?')->execute([$uid]);
                $pdo->commit();

                registrar_evento('PEDIDO_CREADO', $yo['usuario'], 'pedido=' . $pedido_id . ' total=' . round($total, 2));
                flash('ok', 'Pedido #' . $pedido_id . ' realizado. Compra simulada: no se ha cobrado nada.');
                $volver = '/pedidos';
            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                flash('error', $ex->getMessage());
            }
            break;

        default:
            flash('error', 'Acción no válida.');
    }

    redirigir($volver);   // patrón POST-Redirect-GET
}

$lineas = lineas_carrito($uid);
$subtotal = 0.0;
foreach ($lineas as $l) {
    $subtotal += $l['cantidad'] * (float) $l['precio'];
}
$envio = ($subtotal >= ENVIO_GRATIS || $subtotal == 0) ? 0 : COSTE_ENVIO;

cabecera('Carrito');
?>
<h1 class="titulo-pagina">Tu carrito</h1>

<?php if (!$lineas): ?>
    <div class="vacio">
        <img src="/assets/img/carrito-vacio.svg" alt="" width="140" height="140">
        <p>Tu carrito está vacío.</p>
        <a href="/tienda" class="btn btn--primario">Ir a la tienda</a>
    </div>
<?php else: ?>
<div class="carrito">
    <div class="carrito__lista">
        <?php foreach ($lineas as $l):
            $sin_stock = $l['cantidad'] > $l['stock']; ?>
        <div class="linea">
            <img src="/assets/img/<?= e($l['imagen']) ?>" alt="">
            <div class="linea__info">
                <a href="/producto?id=<?= (int) $l['id'] ?>"><?= e($l['nombre']) ?></a>
                <span><?= precio($l['precio']) ?> / ud.</span>
                <?php if ($sin_stock): ?><span class="linea__aviso">Solo quedan <?= (int) $l['stock'] ?> unidades</span><?php endif; ?>
            </div>
            <form method="post" action="/carrito" class="linea__cantidad">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <input name="cantidad" type="number" min="1" max="<?= max(1, min(MAX_UNIDADES, (int) $l['stock'])) ?>"
                       value="<?= (int) $l['cantidad'] ?>" aria-label="Cantidad">
                <button type="submit" class="btn btn--mini">Actualizar</button>
            </form>
            <strong class="linea__sub"><?= precio($l['cantidad'] * (float) $l['precio']) ?></strong>
            <form method="post" action="/carrito">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                <button type="submit" class="btn-icono" aria-label="Eliminar">✕</button>
            </form>
        </div>
        <?php endforeach; ?>

        <form method="post" action="/carrito" class="carrito__vaciar">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="vaciar">
            <button type="submit" class="btn btn--texto">Vaciar carrito</button>
        </form>
    </div>

    <aside class="resumen">
        <h2>Resumen</h2>
        <dl>
            <dt>Subtotal</dt><dd><?= precio($subtotal) ?></dd>
            <dt>Envío</dt><dd><?= $envio ? precio($envio) : 'Gratis' ?></dd>
            <dt class="resumen__total">Total</dt><dd class="resumen__total"><?= precio($subtotal + $envio) ?></dd>
        </dl>
        <form method="post" action="/carrito">
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="comprar">
            <button type="submit" class="btn btn--primario btn--bloque">Tramitar pedido</button>
        </form>
        <p class="resumen__nota">Tu carrito se guarda aunque cierres sesión.<br>Compra simulada: no se piden datos de pago.</p>
    </aside>
</div>
<?php endif; ?>
<?php pie(); ?>
