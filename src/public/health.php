<?php
declare(strict_types=1);

// Endpoint de salud: no abre sesión ni muestra datos internos
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    db()->query('SELECT 1');
    echo json_encode(['status' => 'ok', 'database' => 'ok']);
} catch (Throwable $e) {
    error_log('[HEALTH] ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'error', 'database' => 'unreachable']);
}
