<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';   // página privada
require_once __DIR__ . '/../includes/layout.php';

$cat = is_string($_GET['cat'] ?? null) ? $_GET['cat'] : '';
$q   = is_string($_GET['q'] ?? null) ? trim(mb_substr($_GET['q'], 0, 60)) : '';

// La categoría solo se acepta si está en la lista blanca
if ($cat !== '' && !array_key_exists($cat, CATEGORIAS)) {
    $cat = '';
}

$sql = 'SELECT id, nombre, categoria, precio, precio_anterior, stock, imagen FROM productos WHERE 1=1';
$params = [];
if ($cat !== '') {
    $sql .= ' AND categoria = ?';
    $params[] = $cat;
}
if ($q !== '') {
    $sql .= ' AND nombre LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
$sql .= ' ORDER BY id';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$titulo = $cat !== '' ? CATEGORIAS[$cat] : 'Catálogo';
cabecera($titulo);
?>

<?php if ($cat === '' && $q === ''): ?>
<section class="hero">
    <div class="hero__texto">
        <span class="etiqueta">Black Week · Hasta -15 %</span>
        <h1>Potencia tu equipo<br>con lo último en hardware</h1>
        <p>Gráficas, procesadores, memoria y periféricos con envío en 24 horas.</p>
        <a href="#catalogo" class="btn btn--primario">Ver ofertas</a>
    </div>
    <img class="hero__img" src="/assets/img/gpu.svg" alt="">
</section>
<?php endif; ?>

<section id="catalogo">
    <div class="seccion-cabecera">
        <h2>
            <?php if ($q !== ''): ?>
                Resultados para «<?= e($q) ?>»
            <?php else: ?>
                <?= e($titulo) ?>
            <?php endif; ?>
        </h2>
        <span class="contador"><?= count($productos) ?> producto<?= count($productos) === 1 ? '' : 's' ?></span>
    </div>

    <?php if (!$productos): ?>
        <div class="vacio">
            <p>No hemos encontrado productos.</p>
            <a href="/tienda" class="btn btn--secundario">Ver todo el catálogo</a>
        </div>
    <?php else: ?>
    <div class="rejilla">
        <?php foreach ($productos as $p):
            $descuento = $p['precio_anterior'] ? round(100 - $p['precio'] * 100 / $p['precio_anterior']) : 0;
        ?>
        <article class="tarjeta">
            <?php if ($descuento > 0): ?><span class="tarjeta__dto">-<?= (int) $descuento ?>%</span><?php endif; ?>
            <a href="/producto?id=<?= (int) $p['id'] ?>" class="tarjeta__img">
                <img src="/assets/img/<?= e($p['imagen']) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy">
            </a>
            <div class="tarjeta__cuerpo">
                <span class="tarjeta__cat"><?= e(CATEGORIAS[$p['categoria']] ?? $p['categoria']) ?></span>
                <h3><a href="/producto?id=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></a></h3>
                <div class="valoracion" aria-label="Valoración 4,5 de 5">★★★★<span>★</span></div>
                <div class="precio">
                    <strong><?= precio($p['precio']) ?></strong>
                    <?php if ($p['precio_anterior']): ?><s><?= precio($p['precio_anterior']) ?></s><?php endif; ?>
                </div>
                <?php $st = (int) $p['stock']; ?>
                <p class="stock <?= $st === 0 ? 'stock--agotado' : ($st < 10 ? 'stock--bajo' : '') ?>">
                    <?= $st === 0 ? 'Sin stock' : ($st < 10 ? '¡Últimas ' . $st . ' unidades!' : 'En stock · Envío en 24 h') ?>
                </p>
                <?php if (es_admin()): ?>
                <a href="/admin/productos?editar=<?= (int) $p['id'] ?>" class="btn btn--secundario btn--bloque tarjeta__form">Editar producto</a>
                <?php elseif ((int) $p['stock'] === 0): ?>
                <button class="btn btn--bloque tarjeta__form" disabled>Agotado</button>
                <?php else: ?>
                <form method="post" action="/carrito" class="tarjeta__form">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="anadir">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="cantidad" value="1">
                    <button type="submit" class="btn btn--primario btn--bloque">Añadir al carrito</button>
                </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php pie(); ?>
