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
 * anexa cada salto na tabela conforme chega. A página dispara sozinha
 * (data-autostart) com o alvo da URL (?host= ou /trace/<host>) ou o IP do
 * próprio visitante. Com o "modo contínuo" marcado (estilo mtr), o servidor
 * repete passadas e cada salto acumula estatísticas: perda %, enviados,
 * último, média, melhor e pior RTT. Só ativa se o formulário existir.
 */
(function () {
    'use strict';

    var form = document.getElementById('trace-form');
    if (!form || typeof EventSource === 'undefined') {
        return;
    }

    var input     = document.getElementById('trace-host');
    var runBtn    = document.getElementById('trace-run');
    var stopBtn   = document.getElementById('trace-stop');
    var statusEl  = document.getElementById('trace-status');
    var table     = document.getElementById('trace-table');
    var head      = document.getElementById('trace-head');
    var body      = document.getElementById('trace-body');
    var persistCb = document.getElementById('trace-persist');
    var selfIp      = form.getAttribute('data-self-ip') || '';
    var allowCustom = form.getAttribute('data-allow-custom') === '1';

    var es = null;
    var finished = false;
    var lastError = '';
    var targetIp = '';
    var targetTxt = '';
    var persist = false;   // modo da execução atual
    var passNo = 1;
    var curHost = '';
    var stats = {};        // por salto: {ip, sent, recv, last, best, worst, sum, tr, cells}

    var HEAD_ONESHOT = '<tr><th class="h-hop">#</th><th class="h-host">IP</th><th class="h-rtt">RTT</th></tr>';
    var HEAD_PERSIST = '<tr><th class="h-hop">#</th><th class="h-host">IP</th>'
        + '<th class="h-num">Perda</th><th class="h-num">Env</th><th class="h-rtt">Último</th>'
        + '<th class="h-num h-xtra">Méd</th><th class="h-num h-xtra">Melhor</th><th class="h-num h-xtra">Pior</th></tr>';

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

    function fmtMs(v) {
        return isFinite(v) ? v.toFixed(1) + ' ms' : '';
    }

    // Barra de latência (250 ms ≈ barra cheia).
    function bar(v) {
        var b = document.createElement('div');
        b.className = 'rtt-bar';
        var i = document.createElement('i');
        i.style.width = Math.max(2, Math.min(100, v / 2.5)) + '%';
        b.appendChild(i);
        return b;
    }

    // Linha-fantasma "sondando…" — mostra o próximo salto sendo testado (cada
    // um leva até ~1 s), dando a sensação de progresso contínuo.
    function clearProbing() {
        var p = body.querySelector('.probing');
        if (p) { p.parentNode.removeChild(p); }
    }

    function showProbing(nextHop) {
        clearProbing();
        var tr = document.createElement('tr');
        tr.className = 'probing';
        tr.appendChild(td(String(nextHop), 'c-hop'));
        var rest = td('sondando…', 'c-host');
        rest.colSpan = persist ? 7 : 2;
        tr.appendChild(rest);
        body.appendChild(tr);
    }

    // ---- Modo one-shot: uma linha por salto, na ordem de chegada ----------
    function addHop(h) {
        table.hidden = false;
        clearProbing();
        var tr = document.createElement('tr');
        tr.appendChild(td(String(h.hop), 'c-hop'));
        if (h.timeout || !h.ip) {
            tr.appendChild(td('* * *', 'c-host muted'));
            tr.appendChild(td('', 'c-rtt'));
        } else {
            if (targetIp && h.ip === targetIp) {
                tr.className = 'is-dest'; // chegou ao destino
            }
            tr.appendChild(td(h.ip, 'c-host'));
            var rtt = td(h.ms ? h.ms + ' ms' : '', 'c-rtt');
            var v = parseFloat(h.ms);
            if (isFinite(v)) {
                rtt.appendChild(bar(v));
            }
            tr.appendChild(rtt);
        }
        body.appendChild(tr);
        if (!(targetIp && h.ip === targetIp)) {
            showProbing(h.hop + 1); // destino alcançado não tem próximo salto
        }
    }

    // ---- Modo contínuo: linha fixa por salto, estatísticas atualizando ----
    function upsertHop(h) {
        table.hidden = false;
        var s = stats[h.hop];
        if (!s) {
            clearProbing();
            s = stats[h.hop] = { ip: '', sent: 0, recv: 0, best: Infinity, worst: 0, sum: 0, tr: null, cells: null };
            var cells = {
                hop:   td(String(h.hop), 'c-hop'),
                host:  td('* * *', 'c-host muted'),
                loss:  td('', 'c-num c-loss'),
                snt:   td('0', 'c-num'),
                last:  td('', 'c-rtt'),
                avg:   td('', 'c-num c-xtra'),
                best:  td('', 'c-num c-xtra'),
                worst: td('', 'c-num c-xtra')
            };
            var tr = document.createElement('tr');
            tr.appendChild(cells.hop);
            tr.appendChild(cells.host);
            tr.appendChild(cells.loss);
            tr.appendChild(cells.snt);
            tr.appendChild(cells.last);
            tr.appendChild(cells.avg);
            tr.appendChild(cells.best);
            tr.appendChild(cells.worst);
            s.tr = tr;
            s.cells = cells;
            // Insere ordenado pelo número do salto (ECMP pode revelar saltos
            // novos em passadas seguintes).
            var next = null;
            var rows = body.children;
            for (var i = 0; i < rows.length; i++) {
                var hn = parseInt(rows[i].firstChild && rows[i].firstChild.textContent, 10);
                if (isFinite(hn) && hn > h.hop) { next = rows[i]; break; }
            }
            body.insertBefore(tr, next);
            if (passNo === 1 && !(targetIp && h.ip === targetIp)) {
                showProbing(h.hop + 1); // destino alcançado não tem próximo salto
            }
        }
        s.sent++;
        if (!h.timeout && h.ip) {
            s.recv++;
            if (s.ip !== h.ip) {
                s.ip = h.ip;
                s.cells.host.textContent = h.ip;
                s.cells.host.className = 'c-host';
            }
            if (targetIp && h.ip === targetIp) {
                s.tr.className = 'is-dest';
            }
            var v = parseFloat(h.ms);
            if (isFinite(v)) {
                s.sum += v;
                if (v < s.best) { s.best = v; }
                if (v > s.worst) { s.worst = v; }
                s.cells.last.textContent = fmtMs(v);
                s.cells.last.appendChild(bar(v));
                s.cells.avg.textContent = fmtMs(s.sum / s.recv);
                s.cells.best.textContent = fmtMs(s.best);
                s.cells.worst.textContent = fmtMs(s.worst);
            }
        }
        s.cells.snt.textContent = String(s.sent);
        var loss = s.sent > 0 ? (100 * (s.sent - s.recv) / s.sent) : 0;
        s.cells.loss.textContent = loss.toFixed(0) + '%';
        s.cells.loss.className = 'c-num c-loss' + (loss >= 50 ? ' loss-bad' : (loss > 0 ? ' loss-warn' : ''));
    }

    function parse(ev) {
        try { return JSON.parse(ev.data); } catch (e) { return {}; }
    }

    function start(host) {
        closeStream();
        persist = !!(persistCb && persistCb.checked);
        head.innerHTML = persist ? HEAD_PERSIST : HEAD_ONESHOT;
        table.classList.toggle('is-persist', persist);
        body.innerHTML = '';
        stats = {};
        passNo = 1;
        table.hidden = true;
        finished = false;
        lastError = '';
        targetIp = '';
        targetTxt = '';
        setStatus(persist ? 'Iniciando monitoramento…' : 'Traçando rota…', 'busy');
        running(true);

        var q = [];
        if (host) { q.push('host=' + encodeURIComponent(host)); }
        if (persist) { q.push('loop=1'); }
        es = new EventSource('/trace/stream' + (q.length ? '?' + q.join('&') : ''));

        es.addEventListener('start', function (ev) {
            var d = parse(ev);
            targetIp = d.ip || '';
            targetTxt = d.target || host || 'você';
            setStatus((persist ? 'Monitorando até ' : 'Traçando até ') + targetTxt + ' …', 'busy');
            table.hidden = false;
            showProbing(1);
        });
        es.addEventListener('pass', function (ev) {
            var d = parse(ev);
            passNo = (d && d.n) || (passNo + 1);
            if (passNo > 1) {
                clearProbing();
                setStatus('Monitorando até ' + targetTxt + ' — passada ' + passNo + '…', 'busy');
            }
        });
        es.addEventListener('hop', function (ev) {
            var h = parse(ev);
            if (persist) {
                upsertHop(h);
            } else {
                addHop(h);
            }
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
            clearProbing();
            var d = parse(ev);
            if (d && d.ok === false) {
                if (lastError) { setStatus(lastError, 'err'); }
            } else {
                var txt;
                if (persist) {
                    var p = (d && d.passes) || passNo;
                    txt = 'Monitoramento encerrado — ' + p + (p === 1 ? ' passada.' : ' passadas.');
                } else {
                    var n = (d && typeof d.hops === 'number') ? d.hops : body.children.length;
                    txt = 'Concluído — ' + n + (n === 1 ? ' salto.' : ' saltos.');
                }
                if (d && d.note) { txt += ' ' + d.note; }
                setStatus(txt, 'ok');
            }
            closeStream();
        });
        // Disparado também quando o servidor encerra o stream. Só é erro real
        // se ainda não recebemos o evento 'done'.
        es.onerror = function () {
            if (!finished) {
                clearProbing();
                setStatus(lastError || 'Conexão encerrada antes de concluir.', 'err');
            }
            closeStream();
        };
    }

    // Mantém a URL compartilhável (/trace?host=…&loop=1) sem recarregar.
    function syncUrl(host, loop) {
        try {
            var q = [];
            if (host) { q.push('host=' + encodeURIComponent(host)); }
            if (loop) { q.push('loop=1'); }
            history.replaceState(null, '', '/trace' + (q.length ? '?' + q.join('&') : ''));
        } catch (e) { /* browsers antigos */ }
    }

    function run(host) {
        host = (host || '').trim();
        curHost = host;
        if (input) { input.value = host || selfIp; }
        syncUrl(host, persistCb && persistCb.checked);
        start(host);
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var host = allowCustom && input ? input.value.trim() : '';
        if (host === selfIp) { host = ''; } // campo com o próprio IP = rota até você
        run(host);
    });

    stopBtn.addEventListener('click', function () {
        closeStream();
        clearProbing();
        setStatus(persist ? 'Monitoramento interrompido.' : 'Interrompido.', '');
    });

    // Ligar/desligar o contínuo no meio de uma execução reinicia no novo modo.
    if (persistCb) {
        persistCb.addEventListener('change', function () {
            if (es) {
                run(curHost);
            } else {
                syncUrl(curHost, persistCb.checked);
            }
        });
    }

    // Atalhos: preenchem o campo e já disparam o trace.
    form.addEventListener('click', function (ev) {
        var fill = ev.target.closest('[data-fill]');
        if (fill) {
            run(fill.getAttribute('data-fill'));
            return;
        }
        if (ev.target.closest('[data-fill-self]')) {
            run('');
        }
    });

    // Autostart: alvo vindo da URL ou o IP do próprio visitante.
    if (form.getAttribute('data-autostart') === '1') {
        run(form.getAttribute('data-initial-host') || '');
    }
})();
