<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';

redirigir(!usuario_autenticado() ? '/login' : (es_admin() ? '/admin/' : '/tienda'));
