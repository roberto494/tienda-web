<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ESTADOS = [
    'pendiente' => 'Pendiente de envío',
    'enviado'   => 'Enviado',
    'cancelado' => 'Cancelado',
];

/**
 * Cancela un pedido pendiente y devuelve las unidades al stock.
 * Si se pasa $usuario_id, solo cancela pedidos de ese usuario (evita IDOR).
 * Devuelve true si se ha cancelado.
 */
function cancelar_pedido(int $pedido_id, ?int $usuario_id = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();

    $sql = "UPDATE pedidos SET estado = 'cancelado' WHERE id = ? AND estado = 'pendiente'";
    $params = [$pedido_id];
    if ($usuario_id !== null) {
        $sql .= ' AND usuario_id = ?';
        $params[] = $usuario_id;
    }
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    if ($upd->rowCount() !== 1) {
        $pdo->rollBack();
        return false;
    }

    $lin = $pdo->prepare('SELECT producto_id, cantidad FROM pedido_lineas WHERE pedido_id = ? AND producto_id IS NOT NULL');
    $lin->execute([$pedido_id]);
    $stock = $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?');
    foreach ($lin->fetchAll() as $l) {
        $stock->execute([$l['cantidad'], $l['producto_id']]);
    }
    $pdo->commit();
    return true;
}

/** Líneas de varios pedidos, agrupadas por pedido_id. */
function lineas_de_pedidos(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT pedido_id, producto_id, nombre, imagen, precio_unitario, cantidad
                             FROM pedido_lineas WHERE pedido_id IN ($marcas) ORDER BY id");
    $stmt->execute(array_values($ids));
    $res = [];
    foreach ($stmt->fetchAll() as $l) {
        $res[$l['pedido_id']][] = $l;
    }
    return $res;
}
