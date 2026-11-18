<?php
declare(strict_types=1);

/**
 * Conexión única a MySQL mediante PDO.
 * Las credenciales llegan por variables de entorno (definidas en .env vía compose).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'db',
        getenv('DB_NAME') ?: 'nexobit'
    );

    try {
        $pdo = new PDO($dsn, getenv('DB_USER') ?: null, getenv('DB_PASS') ?: null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // sentencias preparadas reales
        ]);
    } catch (PDOException $e) {
        // El detalle va al log del contenedor, nunca al navegador
        if (PHP_SAPI === 'cli') {
            throw $e; // en scripts de consola, salir con error
        }
        error_log('[DB] Error de conexión: ' . $e->getMessage());
        http_response_code(503);
        exit('Servicio no disponible temporalmente.');
    }

    return $pdo;
}
