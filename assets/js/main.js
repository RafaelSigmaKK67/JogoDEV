/**
 * DEV SURVIVOR - main.js
 * Pequenos comportamentos das paginas do site (fora do jogo).
 */
(function () {
    'use strict';

    // Mensagens flash somem sozinhas apos 5 segundos
    document.querySelectorAll('.flash').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.6s, transform 0.6s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-8px)';
            setTimeout(function () { el.remove(); }, 700);
        }, 5000);
    });
})();
