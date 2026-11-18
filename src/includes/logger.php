<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const LOG_FILE = __DIR__ . '/../logs/seguridad.log';

/**
 * Registra un evento de seguridad en tres sitios:
 *  - stderr  -> visible con "docker compose logs web"
 *  - fichero -> logs/seguridad.log (volumen app_logs, persiste)
 *  - MySQL   -> tabla eventos (consultas para la gestión de incidentes)
 * Nunca se registran contraseñas.
 */
function registrar_evento(string $tipo, ?string $usuario = null, string $detalle = ''): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $usuario = $usuario !== null ? substr($usuario, 0, 120) : null;
    $detalle = substr($detalle, 0, 255);

    $linea = json_encode([
        'fecha'   => date('c'),
        'tipo'    => $tipo,
        'usuario' => $usuario,
        'ip'      => $ip,
        'detalle' => $detalle,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    error_log('[SEGURIDAD] ' . $linea);
    @file_put_contents(LOG_FILE, $linea . PHP_EOL, FILE_APPEND | LOCK_EX);

    try {
        $stmt = db()->prepare(
            'INSERT INTO eventos (fecha, tipo, usuario, ip, user_agent, detalle) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $tipo, $usuario, $ip, $ua, $detalle]);
    } catch (Throwable $e) {
        error_log('[SEGURIDAD] No se pudo guardar el evento en BD: ' . $e->getMessage());
    }
}
