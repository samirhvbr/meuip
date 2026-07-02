/*
 * MEUIP.RS — interações client-side: tema (dark/light) + copiar.
 * Zero dependências, sem framework. O data-theme inicial vem do anti-flash
 * inline no <head>; aqui só tratamos o clique no toggle e nos botões de copiar.
 */
(function () {
    'use strict';

    // Ícone de check (feedback do copiar). Os demais SVGs vêm do servidor.
    var ICON_CHECK = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>';

    // Reflete o tema atual na <meta name="theme-color"> (chrome do mobile).
    function updateThemeUi(theme) {
        var meta = document.getElementById('meta-theme');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#10151b' : '#f5f7fa');
        }
    }

    function toggleTheme() {
        var html = document.documentElement;
        var next = html.dataset.theme === 'dark' ? 'light' : 'dark';
        html.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) { /* modo privado */ }
        updateThemeUi(next);
    }

    // Copia um texto e dá feedback no botão: check verde (+ "Copiado!" se
    // data-label) por ~1,5s, depois reverte. Se o clipboard falhar/indisponível,
    // mostra o feedback mesmo assim (a página não é a fonte da verdade do IP).
    function copyText(btn, text) {
        function feedback() {
            var old = btn.innerHTML;
            btn.innerHTML = ICON_CHECK + (btn.dataset.label ? '<span class="copy-btn-txt">Copiado!</span>' : '');
            btn.classList.add('ok');
            setTimeout(function () {
                btn.innerHTML = old;
                btn.classList.remove('ok');
            }, 1500);
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(feedback, feedback);
        } else {
            feedback();
        }
    }

    // Delegação de eventos: [data-copy] copia; [data-theme-toggle] alterna tema.
    document.addEventListener('click', function (ev) {
        var copyBtn = ev.target.closest('[data-copy]');
        if (copyBtn) {
            copyText(copyBtn, copyBtn.getAttribute('data-copy'));
            return;
        }
        if (ev.target.closest('[data-theme-toggle]')) {
            toggleTheme();
        }
    });

    // Sincroniza o theme-color no carregamento (data-theme já veio do anti-flash).
    updateThemeUi(document.documentElement.dataset.theme || 'light');
})();
