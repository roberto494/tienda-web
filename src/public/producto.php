<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$producto = null;

if ($id) {
    $stmt = db()->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
}

if (!$producto) {
    http_response_code(404);
    cabecera('Producto no encontrado');
    echo '<div class="vacio"><p>El producto no existe.</p><a href="/tienda" class="btn btn--secundario">Volver a la tienda</a></div>';
    pie();
    exit;
}

$specs = array_filter(explode('|', $producto['especificaciones']));
$descuento = $producto['precio_anterior']
    ? round(100 - $producto['precio'] * 100 / $producto['precio_anterior']) : 0;

cabecera($producto['nombre']);
?>
<nav class="migas" aria-label="Ruta">
    <a href="/tienda">Inicio</a> ›
    <a href="/tienda?cat=<?= e(urlencode($producto['categoria'])) ?>"><?= e(CATEGORIAS[$producto['categoria']] ?? '') ?></a> ›
    <span><?= e($producto['nombre']) ?></span>
</nav>

<article class="ficha">
    <div class="ficha__galeria">
        <?php if ($descuento > 0): ?><span class="tarjeta__dto">-<?= (int) $descuento ?>%</span><?php endif; ?>
        <img src="/assets/img/<?= e($producto['imagen']) ?>" alt="<?= e($producto['nombre']) ?>">
    </div>

    <div class="ficha__info">
        <span class="tarjeta__cat"><?= e(CATEGORIAS[$producto['categoria']] ?? '') ?></span>
        <h1><?= e($producto['nombre']) ?></h1>
        <div class="valoracion">★★★★<span>★</span> <small>4,5 · 128 opiniones</small></div>
        <p class="ficha__desc"><?= e($producto['descripcion']) ?></p>

        <ul class="ficha__specs">
            <?php foreach ($specs as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
        </ul>

        <div class="ficha__compra">
            <div class="precio precio--grande">
                <strong><?= precio($producto['precio']) ?></strong>
                <?php if ($producto['precio_anterior']): ?><s><?= precio($producto['precio_anterior']) ?></s><?php endif; ?>
            </div>
            <p class="ficha__iva">IVA incluido</p>
            <p class="stock <?= (int) $producto['stock'] === 0 ? 'stock--agotado' : ($producto['stock'] < 10 ? 'stock--bajo' : '') ?>">
                <?= (int) $producto['stock'] === 0 ? 'Sin stock' : (int) $producto['stock'] . ' unidades disponibles' ?>
            </p>

            <?php if (es_admin()): ?>
                <a href="/admin/productos?editar=<?= (int) $producto['id'] ?>" class="btn btn--secundario">Editar producto</a>
            <?php elseif ((int) $producto['stock'] === 0): ?>
                <button class="btn btn--bloque" disabled>Agotado</button>
            <?php else: ?>
            <form method="post" action="/carrito" class="ficha__form">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="anadir">
                <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                <label for="cantidad">Cantidad</label>
                <input id="cantidad" name="cantidad" type="number" min="1" max="<?= min(10, (int) $producto['stock']) ?>" value="1" required>
                <button type="submit" class="btn btn--primario">Añadir al carrito</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php pie(); ?>
