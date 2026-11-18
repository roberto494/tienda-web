<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/layout.php';

http_response_code(404);
cabecera('Página no encontrada', false);
?>
<div class="error-pagina">
    <span class="error-pagina__codigo">404</span>
    <h1>Página no encontrada</h1>
    <p>La dirección que buscas no existe o se ha movido.</p>
    <a href="/" class="btn btn--primario">Volver al inicio</a>
</div>
<?php pie(false); ?>
