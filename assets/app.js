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

/*
 * Traceroute ao vivo (página /trace). Consome o endpoint SSE /trace/stream e
 * anexa cada salto numa tabela. Só ativa se o formulário existir na página.
 */
(function () {
    'use strict';

    var form = document.getElementById('trace-form');
    if (!form || typeof EventSource === 'undefined') {
        return;
    }

    var input    = document.getElementById('trace-host');
    var runBtn   = document.getElementById('trace-run');
    var stopBtn  = document.getElementById('trace-stop');
    var statusEl = document.getElementById('trace-status');
    var table    = document.getElementById('trace-table');
    var body     = document.getElementById('trace-body');
    var selfIp      = form.getAttribute('data-self-ip') || '';
    var allowCustom = form.getAttribute('data-allow-custom') === '1';

    var es = null;
    var finished = false;
    var lastError = '';

    function setStatus(txt, cls) {
        statusEl.hidden = false;
        statusEl.textContent = txt;
        statusEl.className = 'trace-status' + (cls ? ' ' + cls : '');
    }

    function running(on) {
        runBtn.disabled = on;
        runBtn.classList.toggle('busy', on);
        stopBtn.hidden = !on;
        if (input && allowCustom) {
            input.readOnly = on;
        }
    }

    function closeStream() {
        if (es) {
            es.close();
            es = null;
        }
        running(false);
    }

    function td(text, cls) {
        var d = document.createElement('td');
        if (cls) { d.className = cls; }
        d.textContent = text;
        return d;
    }

    function addHop(h) {
        table.hidden = false;
        var tr = document.createElement('tr');
        tr.appendChild(td(String(h.hop), 'c-hop'));
        if (h.timeout || !h.ip) {
            tr.appendChild(td('* * *', 'c-host muted'));
            tr.appendChild(td('', 'c-rtt'));
        } else {
            tr.appendChild(td(h.ip, 'c-host'));
            tr.appendChild(td(h.ms ? h.ms + ' ms' : '', 'c-rtt'));
        }
        body.appendChild(tr);
    }

    function parse(ev) {
        try { return JSON.parse(ev.data); } catch (e) { return {}; }
    }

    function start(host) {
        closeStream();
        body.innerHTML = '';
        table.hidden = true;
        finished = false;
        lastError = '';
        setStatus('Traçando rota…', 'busy');
        running(true);

        var url = '/trace/stream' + (host ? '?host=' + encodeURIComponent(host) : '');
        es = new EventSource(url);

        es.addEventListener('start', function (ev) {
            var d = parse(ev);
            setStatus('Traçando até ' + (d.target || host || 'você') + ' …', 'busy');
        });
        es.addEventListener('hop', function (ev) {
            addHop(parse(ev));
        });
        es.addEventListener('error', function (ev) {
            var d = parse(ev);
            if (d && d.message) {
                lastError = d.message;
                setStatus(d.message, 'err');
            }
        });
        es.addEventListener('done', function (ev) {
            finished = true;
            var d = parse(ev);
            if (d && d.ok === false) {
                if (lastError) { setStatus(lastError, 'err'); }
            } else {
                var n = (d && typeof d.hops === 'number') ? d.hops : body.children.length;
                setStatus('Concluído — ' + n + (n === 1 ? ' salto.' : ' saltos.'), 'ok');
            }
            closeStream();
        });
        // Disparado também quando o servidor encerra o stream. Só é erro real
        // se ainda não recebemos o evento 'done'.
        es.onerror = function () {
            if (!finished) {
                setStatus(lastError || 'Conexão encerrada antes de concluir.', 'err');
            }
            closeStream();
        };
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var host = allowCustom && input ? input.value.trim() : '';
        start(host);
    });

    stopBtn.addEventListener('click', function () {
        closeStream();
        setStatus('Interrompido.', '');
    });

    // Atalhos de preenchimento (chips "usar meu IP", 8.8.8.8, …).
    form.addEventListener('click', function (ev) {
        var fill = ev.target.closest('[data-fill]');
        if (fill && input) {
            input.value = fill.getAttribute('data-fill');
            input.focus();
            return;
        }
        if (ev.target.closest('[data-fill-self]') && input) {
            input.value = selfIp;
            input.focus();
        }
    });
})();
