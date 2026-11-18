// Pide confirmación antes de enviar formularios destructivos (data-confirmar="mensaje").
// Archivo externo: la política CSP no permite JavaScript en línea.
document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var msg = form.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) {
        ev.preventDefault();
    }
});
