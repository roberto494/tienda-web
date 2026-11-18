<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/layout.php';

$yo = requiere_rol('admin');
$pdo = db();

/**
 * Imágenes disponibles: solo archivos que ya existen en assets/img (lista blanca).
 * La subida de archivos está desactivada a propósito (file_uploads = Off).
 */
function imagenes_disponibles(): array
{
    $excluir = ['logo.svg', 'favicon.svg', 'carrito-vacio.svg'];
    $res = [];
    foreach (glob(__DIR__ . '/../assets/img/*.{svg,png,jpg,jpeg,webp}', GLOB_BRACE) ?: [] as $f) {
        $n = basename($f);
        if (!in_array($n, $excluir, true) && preg_match('/^[a-z0-9_-]+\.(svg|png|jpe?g|webp)$/', $n)) {
            $res[] = $n;
        }
    }
    sort($res);
    return $res;
}

/** Normaliza y valida el formulario. Devuelve [datos, errores]. */
function leer_producto(array $in): array
{
    $d = [
        'nombre'           => trim((string) ($in['nombre'] ?? '')),
        'categoria'        => (string) ($in['categoria'] ?? ''),
        'descripcion'      => trim((string) ($in['descripcion'] ?? '')),
        'especificaciones' => trim((string) ($in['especificaciones'] ?? '')),
        'precio'           => str_replace(',', '.', trim((string) ($in['precio'] ?? ''))),
        'precio_anterior'  => str_replace(',', '.', trim((string) ($in['precio_anterior'] ?? ''))),
        'stock'            => trim((string) ($in['stock'] ?? '')),
        'imagen'           => (string) ($in['imagen'] ?? ''),
    ];
    $err = [];
    $len = mb_strlen($d['nombre']);
    if ($len < 3 || $len > 120) {
        $err[] = 'El nombre debe tener entre 3 y 120 caracteres.';
    }
    if (!array_key_exists($d['categoria'], CATEGORIAS)) {
        $err[] = 'Categoría no válida.';
    }
    if (mb_strlen($d['descripcion']) < 10 || mb_strlen($d['descripcion']) > 1000) {
        $err[] = 'La descripción debe tener entre 10 y 1000 caracteres.';
    }
    // Especificaciones: una por línea en el formulario, separadas por | en la BD
    $specs = array_values(array_filter(array_map(
        fn($s) => trim(str_replace('|', '/', $s)),
        preg_split('/\R/', $d['especificaciones'])
    )));
    if (!$specs || count($specs) > 12) {
        $err[] = 'Indica entre 1 y 12 especificaciones (una por línea).';
    }
    $d['especificaciones_bd'] = implode('|', $specs);

    $precio = filter_var($d['precio'], FILTER_VALIDATE_FLOAT);
    if ($precio === false || $precio <= 0 || $precio > 99999) {
        $err[] = 'Precio no válido (mayor que 0).';
    }
    $anterior = null;
    if ($d['precio_anterior'] !== '') {
        $anterior = filter_var($d['precio_anterior'], FILTER_VALIDATE_FLOAT);
        if ($anterior === false || $precio === false || $anterior <= $precio || $anterior > 99999) {
            $err[] = 'El precio anterior debe ser mayor que el precio actual (o dejarse vacío).';
        }
    }
    $stock = filter_var($d['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]);
    if ($stock === false) {
        $err[] = 'Stock no válido (número entero, 0 o más).';
    }
    if (!in_array($d['imagen'], imagenes_disponibles(), true)) {
        $err[] = 'Selecciona una imagen de la lista.';
    }
    $d['precio_num'] = $precio === false ? 0 : round($precio, 2);
    $d['anterior_num'] = $anterior === null || $anterior === false ? null : round($anterior, 2);
    $d['stock_num'] = $stock === false ? 0 : $stock;
    return [$d, $err];
}

$errores = [];
$form = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

    if ($accion === 'crear' || ($accion === 'guardar' && $id)) {
        [$d, $errores] = leer_producto($_POST);
        if (!$errores) {
            $valores = [$d['nombre'], $d['categoria'], $d['descripcion'], $d['especificaciones_bd'],
                        $d['precio_num'], $d['anterior_num'], $d['stock_num'], $d['imagen']];
            if ($accion === 'crear') {
                $pdo->prepare('INSERT INTO productos (nombre, categoria, descripcion, especificaciones, precio, precio_anterior, stock, imagen)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($valores);
                $nuevo_id = (int) $pdo->lastInsertId();
                registrar_evento('ADMIN_PRODUCTO_CREADO', $yo['usuario'], 'id=' . $nuevo_id . ' ' . $d['nombre']);
                flash('ok', 'Producto «' . $d['nombre'] . '» creado.');
            } else {
                $upd = $pdo->prepare('UPDATE productos SET nombre = ?, categoria = ?, descripcion = ?, especificaciones = ?,
                                      precio = ?, precio_anterior = ?, stock = ?, imagen = ? WHERE id = ?');
                $upd->execute([...$valores, $id]);
                registrar_evento('ADMIN_PRODUCTO_EDITADO', $yo['usuario'],
                    'id=' . $id . ' precio=' . $d['precio_num'] . ' stock=' . $d['stock_num']);
                flash('ok', 'Producto «' . $d['nombre'] . '» actualizado.');
            }
            redirigir('/admin/productos');
        }
        $form = $d + ['id' => $accion === 'guardar' ? $id : null];

    } elseif ($accion === 'eliminar' && $id) {
        $stmt = $pdo->prepare('SELECT nombre FROM productos WHERE id = ?');
        $stmt->execute([$id]);
        $nombre = $stmt->fetchColumn();
        if ($nombre === false) {
            flash('error', 'El producto no existe.');
        } else {
            // Se borra de los carritos (CASCADE); en pedidos pasados se conserva nombre y precio (SET NULL)
            $pdo->prepare('DELETE FROM productos WHERE id = ?')->execute([$id]);
            registrar_evento('ADMIN_PRODUCTO_ELIMINADO', $yo['usuario'], 'id=' . $id . ' ' . $nombre);
            flash('ok', 'Producto «' . $nombre . '» eliminado.');
        }
        redirigir('/admin/productos');
    } else {
        flash('error', 'Acción no válida.');
        redirigir('/admin/productos');
    }
}

if ($form === null) {
    if (isset($_GET['nuevo'])) {
        $form = ['id' => null, 'nombre' => '', 'categoria' => 'graficas', 'descripcion' => '', 'especificaciones' => '',
                 'precio' => '', 'precio_anterior' => '', 'stock' => '0', 'imagen' => ''];
    } elseif ($eid = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
        $stmt->execute([$eid]);
        if ($p = $stmt->fetch()) {
            $form = $p;
            $form['especificaciones'] = str_replace('|', "\n", $p['especificaciones']);
            $form['precio_anterior'] = (string) ($p['precio_anterior'] ?? '');
        }
    }
}

$productos = $pdo->query('SELECT id, nombre, categoria, precio, precio_anterior, stock, imagen FROM productos ORDER BY id')->fetchAll();
$imagenes = imagenes_disponibles();

cabecera('Productos · Admin');
?>
<div class="admin-cab">
    <h1 class="titulo-pagina">Productos</h1>
    <a href="/admin/productos?nuevo=1" class="btn btn--primario">+ Nuevo producto</a>
</div>

<?php if ($form !== null): $editando = $form['id'] !== null; ?>
<section class="panel panel--form">
    <h2><?= $editando ? 'Editar producto #' . (int) $form['id'] : 'Nuevo producto' ?></h2>
    <?php if ($errores): ?>
        <div class="aviso aviso--error" role="alert"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/admin/productos" class="form form--rejilla" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="<?= $editando ? 'guardar' : 'crear' ?>">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>"><?php endif; ?>

        <div class="col-2">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" maxlength="120" required value="<?= e($form['nombre']) ?>">
        </div>
        <div>
            <label for="categoria">Categoría</label>
            <select id="categoria" name="categoria">
                <?php foreach (CATEGORIAS as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $form['categoria'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="imagen">Imagen</label>
            <select id="imagen" name="imagen" required>
                <option value="">— Elige una imagen —</option>
                <?php foreach ($imagenes as $img): ?>
                    <option value="<?= e($img) ?>" <?= $form['imagen'] === $img ? 'selected' : '' ?>><?= e($img) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="precio">Precio (€)</label>
            <input id="precio" name="precio" inputmode="decimal" required value="<?= e((string) $form['precio']) ?>">
        </div>
        <div>
            <label for="precio_anterior">Precio anterior (€, opcional)</label>
            <input id="precio_anterior" name="precio_anterior" inputmode="decimal" value="<?= e((string) $form['precio_anterior']) ?>">
        </div>
        <div>
            <label for="stock">Stock (unidades)</label>
            <input id="stock" name="stock" type="number" min="0" max="99999" required value="<?= e((string) $form['stock']) ?>">
        </div>
        <div class="vista-previa">
            <?php if ($form['imagen'] !== '' && in_array($form['imagen'], $imagenes, true)): ?>
                <img src="/assets/img/<?= e($form['imagen']) ?>" alt="Imagen actual">
            <?php else: ?>
                <span class="texto-suave">Sin imagen</span>
            <?php endif; ?>
        </div>
        <div class="col-2">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="1000" required><?= e($form['descripcion']) ?></textarea>
        </div>
        <div class="col-2">
            <label for="especificaciones">Especificaciones (una por línea)</label>
            <textarea id="especificaciones" name="especificaciones" rows="5" required><?= e($form['especificaciones']) ?></textarea>
        </div>
        <div class="form__acciones">
            <a href="/admin/productos" class="btn btn--texto">Cancelar</a>
            <button type="submit" class="btn btn--primario"><?= $editando ? 'Guardar cambios' : 'Crear producto' ?></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="tabla-scroll">
    <table class="tabla">
        <thead><tr><th></th><th>ID</th><th>Producto</th><th>Categoría</th><th>Precio</th><th>Stock</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($productos as $p): ?>
            <tr>
                <td><img class="miniatura" src="/assets/img/<?= e($p['imagen']) ?>" alt=""></td>
                <td class="mono">#<?= (int) $p['id'] ?></td>
                <td><a href="/producto?id=<?= (int) $p['id'] ?>"><strong><?= e($p['nombre']) ?></strong></a></td>
                <td><?= e(CATEGORIAS[$p['categoria']] ?? $p['categoria']) ?></td>
                <td class="nowrap"><strong><?= precio($p['precio']) ?></strong>
                    <?php if ($p['precio_anterior']): ?><br><s class="texto-suave"><?= precio($p['precio_anterior']) ?></s><?php endif; ?></td>
                <td><span class="chip <?= (int) $p['stock'] === 0 ? 'chip--rojo' : ($p['stock'] < 5 ? 'chip--ambar' : 'chip--verde') ?>"><?= (int) $p['stock'] ?></span></td>
                <td class="acciones-fila">
                    <a href="/admin/productos?editar=<?= (int) $p['id'] ?>" class="btn btn--mini">Editar</a>
                    <form method="post" action="/admin/productos" data-confirmar="¿Eliminar «<?= e($p['nombre']) ?>»? Se quitará de todos los carritos.">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="btn btn--mini btn--mini-peligro">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$productos): ?><tr><td colspan="7" class="texto-suave">No hay productos.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php pie(); ?>
