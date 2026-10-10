/* Plugin Identificar Computador - execucao remota (sessoes ativas, upload, execucao e logs). */
(function () {
    'use strict';

    var CFG = window.identificarcomputadorExec;
    if (!CFG) { return; }
    var csrf = CFG.csrf || '';
    var CHUNK = 512 * 1024;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function toast(msg, ok) {
        var cx = document.querySelector('.identificarcomputador-toasts');
        if (!cx) { cx = document.createElement('div'); cx.className = 'identificarcomputador-toasts'; document.body.appendChild(cx); }
        var t = document.createElement('div');
        t.className = 'identificarcomputador-toast ' + (ok ? 'ok' : 'err');
        t.textContent = msg;
        cx.appendChild(t);
        setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; }, 2800);
        setTimeout(function () { t.remove(); }, 3300);
    }
    function ajax(dados) {
        var corpo = new URLSearchParams();
        Object.keys(dados).forEach(function (k) { corpo.append(k, dados[k]); });
        return fetch(CFG.url_ajax, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: corpo.toString(), credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (txt) {
                var j;
                try { j = JSON.parse(txt); } catch (e) { var m = txt.match(/\{[\s\S]*\}\s*$/); j = m ? JSON.parse(m[0]) : { success: false, message: 'Resposta inválida.' }; }
                if (j && j.new_token) { csrf = j.new_token; }
                return j;
            });
    }
    function randHex(n) {
        var a = new Uint8Array(n); (window.crypto || window.msCrypto).getRandomValues(a);
        return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }
    function bufToB64(buf) {
        var bytes = new Uint8Array(buf), bin = '', chunk = 0x8000;
        for (var i = 0; i < bytes.length; i += chunk) {
            bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
        }
        return btoa(bin);
    }
    function fmtTam(b) {
        b = Number(b) || 0;
        if (b < 1024) { return b + ' B'; }
        var u = ['KB', 'MB', 'GB'], i = -1;
        do { b /= 1024; i++; } while (b >= 1024 && i < u.length - 1);
        return b.toFixed(b < 10 ? 1 : 0) + ' ' + u[i];
    }
    function fmtTempo(seg) {
        seg = Math.max(0, Number(seg) || 0);
        var m = Math.floor(seg / 60), s = seg % 60;
        return m + 'min ' + (s < 10 ? '0' : '') + s + 's';
    }

    // ----------------------------------------------------------------- abas
    function initAbas() {
        document.querySelectorAll('.identificarcomputador-exec .identificarcomputador-aba').forEach(function (b) {
            b.addEventListener('click', function () {
                var aba = b.getAttribute('data-aba');
                document.querySelectorAll('.identificarcomputador-exec .identificarcomputador-aba').forEach(function (x) { x.classList.toggle('ativa', x === b); });
                document.querySelectorAll('.identificarcomputador-exec .identificarcomputador-aba-corpo').forEach(function (c) { c.classList.toggle('ativa', c.getAttribute('data-corpo') === aba); });
                if (aba === 'logs') { carregarLogs(); }
            });
        });
    }

    // ----------------------------------------------------------------- sessoes ativas
    var selecionada = 0;
    var sessoesCache = [];

    function carregarSessoes() {
        ajax({ action: 'ponte_online' }).then(function (r) {
            if (!r.success) { return; }
            sessoesCache = r.sessoes || [];
            var el = document.getElementById('ic-exec-sessoes');
            var cont = document.getElementById('ic-exec-cont');
            cont.textContent = sessoesCache.length;
            cont.className = 'identificarcomputador-pill ' + (sessoesCache.length ? 'ok' : 'off');
            if (sessoesCache.length === 0) {
                el.innerHTML = '<div class="identificarcomputador-vazio">Nenhuma máquina com o canal aberto agora.</div>';
            } else {
                el.innerHTML = sessoesCache.map(function (s) {
                    return '<div class="identificarcomputador-exec-maq' + (s.id === selecionada ? ' sel' : '') + '" data-sessao="' + s.id + '">'
                        + '<div class="nome"><i class="ti ti-device-desktop"></i> ' + esc(s.hostname || ('#' + s.id)) + '</div>'
                        + '<div class="sub">' + esc(s.usuario) + '</div>'
                        + '<div class="sub">' + esc(s.so) + '</div>'
                        + '<div class="sub"><i class="ti ti-clock"></i> ' + fmtTempo(s.segundos_restantes) + ' restantes</div>'
                        + '</div>';
                }).join('');
                el.querySelectorAll('[data-sessao]').forEach(function (c) {
                    c.addEventListener('click', function () { selecionar(parseInt(c.getAttribute('data-sessao'), 10)); });
                });
            }
            // Se a selecionada saiu, avisa
            if (selecionada && !sessoesCache.some(function (s) { return s.id === selecionada; })) {
                var p = document.getElementById('ic-exec-painel');
                if (p && !p.getAttribute('data-desconectada')) {
                    p.setAttribute('data-desconectada', '1');
                    p.innerHTML = '<div class="identificarcomputador-vazio-lista"><i class="ti ti-plug-connected-x"></i> Esta máquina se desconectou (canal encerrado).</div>';
                }
            }
        });
    }

    function selecionar(id) {
        selecionada = id;
        document.querySelectorAll('.identificarcomputador-exec-maq').forEach(function (c) { c.classList.toggle('sel', parseInt(c.getAttribute('data-sessao'), 10) === id); });
        var p = document.getElementById('ic-exec-painel');
        p.removeAttribute('data-desconectada');
        var s = sessoesCache.filter(function (x) { return x.id === id; })[0] || {};
        p.innerHTML = '<div class="identificarcomputador-exec-cab"><i class="ti ti-device-desktop"></i> <b>' + esc(s.hostname || ('#' + id)) + '</b>'
            + (s.computadores_id ? ' <a class="identificarcomputador-ico" title="Ver detalhes" href="' + esc(CFG.url_detalhe) + '?id=' + s.computadores_id + '"><i class="ti ti-eye"></i></a>' : '')
            + '</div>'
            + '<div class="identificarcomputador-exec-upl" id="ic-upl"><i class="ti ti-upload"></i> Arraste arquivos aqui ou <button type="button" class="btn identificarcomputador-btn" id="ic-upl-btn">escolher arquivos</button>'
            + '<div class="identificarcomputador-exec-prog" id="ic-upl-prog"></div></div>'
            + '<div class="identificarcomputador-exec-arqs" id="ic-arqs"></div>'
            + '<div class="identificarcomputador-exec-saida" id="ic-saida" style="display:none"></div>';
        document.getElementById('ic-upl-btn').addEventListener('click', function () { document.getElementById('ic-exec-file').click(); });
        ligarArrastar(document.getElementById('ic-upl'));
        carregarArquivos();
    }

    function ligarArrastar(zona) {
        ['dragenter', 'dragover'].forEach(function (ev) { zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('arrastando'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.remove('arrastando'); }); });
        zona.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) { enviarArquivos(e.dataTransfer.files); } });
    }

    function carregarArquivos() {
        if (!selecionada) { return; }
        ajax({ action: 'ponte_arquivos', sessao: selecionada }).then(function (r) {
            var el = document.getElementById('ic-arqs');
            if (!el) { return; }
            var arqs = (r && r.arquivos) || [];
            if (arqs.length === 0) { el.innerHTML = '<div class="identificarcomputador-vazio">Nenhum arquivo enviado ainda.</div>'; return; }
            el.innerHTML = '<div class="identificarcomputador-exec-sub"><i class="ti ti-files"></i> Arquivos enviados</div>'
                + arqs.map(function (a) {
                    return '<div class="identificarcomputador-exec-arq">'
                        + '<span class="ext ext-' + esc(a.formato) + '">.' + esc(a.formato) + '</span>'
                        + '<span class="nm">' + esc(a.nome) + '</span>'
                        + '<span class="tm">' + fmtTam(a.tamanho) + '</span>'
                        + '<button type="button" class="btn identificarcomputador-btn-principal identificarcomputador-btn-mini" data-exec="' + a.id + '" data-nome="' + esc(a.nome) + '"><i class="ti ti-player-play"></i> Executar</button>'
                        + '</div>';
                }).join('');
            el.querySelectorAll('[data-exec]').forEach(function (b) {
                b.addEventListener('click', function () { executar(parseInt(b.getAttribute('data-exec'), 10), b.getAttribute('data-nome'), b); });
            });
        });
    }

    function enviarArquivos(fileList) {
        var files = Array.prototype.slice.call(fileList);
        if (!files.length || !selecionada) { return; }
        var maxBytes = CFG.maxUploadMb * 1024 * 1024;
        var permitidos = CFG.formatos || [];
        var prog = document.getElementById('ic-upl-prog');
        (function proxima() {
            if (!files.length) { carregarArquivos(); return; }
            var file = files.shift();
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            if (permitidos.indexOf(ext) < 0) { toast('Formato não permitido: ' + file.name, false); return proxima(); }
            if (file.size > maxBytes) { toast(file.name + ' passa de ' + CFG.maxUploadMb + ' MB.', false); return proxima(); }
            var uploadId = randHex(16), sent = 0, indice = 0;
            prog.innerHTML = '<div class="l">Enviando <b>' + esc(file.name) + '</b> <span class="p">0%</span></div><div class="barra"><div class="fill" style="width:0%"></div></div>';
            function etapa() {
                if (sent >= file.size) {
                    ajax({ action: 'ponte_finalizar', upload_id: uploadId, sessao: selecionada, nome: file.name, _glpi_csrf_token: csrf }).then(function (r) {
                        if (r && r.success) { toast('Enviado: ' + file.name, true); } else { toast((r && r.message) || 'Falha no envio.', false); }
                        prog.innerHTML = '';
                        proxima();
                    });
                    return;
                }
                var slice = file.slice(sent, sent + CHUNK);
                var fr = new FileReader();
                fr.onload = function () {
                    ajax({ action: 'ponte_chunk', upload_id: uploadId, indice: indice, dados: bufToB64(fr.result) }).then(function (r) {
                        if (!r || !r.success) { toast((r && r.message) || 'Falha ao enviar parte.', false); prog.innerHTML = ''; return proxima(); }
                        sent += CHUNK; indice++;
                        var pct = Math.min(100, Math.round((sent / file.size) * 100));
                        var pe = prog.querySelector('.p'), fi = prog.querySelector('.fill');
                        if (pe) { pe.textContent = pct + '%'; } if (fi) { fi.style.width = pct + '%'; }
                        etapa();
                    });
                };
                fr.readAsArrayBuffer(slice);
            }
            etapa();
        })();
    }

    function executar(arquivoId, nome, botao) {
        var saida = document.getElementById('ic-saida');
        saida.style.display = 'block';
        saida.innerHTML = '<div class="cab"><i class="ti ti-loader"></i> Executando <b>' + esc(nome) + '</b>...</div><pre class="out">Enviando para a máquina...</pre>';
        if (botao) { botao.disabled = true; }
        ajax({ action: 'ponte_executar', arquivo: arquivoId, _glpi_csrf_token: csrf }).then(function (r) {
            if (!r || !r.success) { saida.innerHTML = '<div class="cab err"><i class="ti ti-x"></i> ' + esc((r && r.message) || 'Falha.') + '</div>'; if (botao) { botao.disabled = false; } return; }
            var jobId = r.id;
            (function espera() {
                ajax({ action: 'ponte_job', id: jobId }).then(function (j) {
                    if (!j || !j.success) { return; }
                    if (j.status === 'pendente' || j.status === 'executando') {
                        saida.querySelector('.out').textContent = j.status === 'pendente' ? 'Aguardando a máquina pegar o arquivo...' : 'Executando na máquina...';
                        setTimeout(espera, 1500);
                        return;
                    }
                    if (botao) { botao.disabled = false; }
                    var ok = j.status === 'concluido';
                    saida.innerHTML = '<div class="cab ' + (ok ? 'ok' : 'err') + '"><i class="ti ti-' + (ok ? 'circle-check' : 'alert-triangle') + '"></i> '
                        + esc(nome) + ' — ' + (ok ? 'concluído' : j.status) + ' (código ' + (j.exit === null ? '?' : j.exit) + ', ' + fmtTempo(j.duracao) + ')</div>'
                        + '<pre class="out">' + esc(j.saida || '(sem saída)') + '</pre>';
                    carregarLogs();
                });
            })();
        });
    }

    // ----------------------------------------------------------------- logs
    var logsDados = [];
    var logOrdem = { col: 'id', dir: 'desc' };

    function carregarLogs() {
        ajax({ action: 'ponte_logs', busca: (document.getElementById('ic-logs-busca') || {}).value || '' }).then(function (r) {
            if (!r || !r.success) { return; }
            logsDados = r.linhas || [];
            renderLogs();
        });
    }
    function pillStatus(s) {
        var cls = s === 'concluido' ? 'ok' : (s === 'erro' ? 'remoto' : (s === 'executando' || s === 'pendente' ? 'warn' : 'off'));
        var txt = { concluido: 'Concluído', erro: 'Erro', executando: 'Executando', pendente: 'Pendente', cancelado: 'Cancelado' }[s] || s;
        return '<span class="identificarcomputador-pill ' + cls + '">' + esc(txt) + '</span>';
    }
    function renderLogs() {
        var corpo = document.getElementById('ic-logs-corpo');
        var vazio = document.getElementById('ic-logs-vazio');
        var arr = logsDados.slice();
        var c = logOrdem.col, dir = logOrdem.dir === 'asc' ? 1 : -1;
        arr.sort(function (a, b) {
            var x = a[c], y = b[c];
            if (c === 'tamanho' || c === 'exit') { return ((Number(x) || 0) - (Number(y) || 0)) * dir; }
            return String(x || '').localeCompare(String(y || ''), 'pt', { numeric: true }) * dir;
        });
        vazio.style.display = arr.length === 0 ? 'block' : 'none';
        corpo.innerHTML = arr.map(function (l) {
            var comp = l.computadores_id ? '<a href="' + esc(CFG.url_detalhe) + '?id=' + l.computadores_id + '">' + esc(l.hostname) + '</a>' : esc(l.hostname);
            return '<tr>'
                + '<td>' + esc(l.usuario) + '</td>'
                + '<td>' + esc(l.arquivo) + '</td>'
                + '<td><span class="ext ext-' + esc(l.formato) + '">.' + esc(l.formato) + '</span></td>'
                + '<td>' + fmtTam(l.tamanho) + '</td>'
                + '<td>' + pillStatus(l.status) + '</td>'
                + '<td>' + (l.exit === '' ? '-' : l.exit) + '</td>'
                + '<td>' + esc(l.envio) + '</td>'
                + '<td>' + esc(l.inicio || '-') + '</td>'
                + '<td>' + esc(l.duracao) + '</td>'
                + '<td>' + comp + '</td>'
                + '<td class="identificarcomputador-no-export"><button type="button" class="identificarcomputador-ico" data-saida="' + l.id + '" title="Ver saída"><i class="ti ti-file-text"></i></button></td>'
                + '</tr>';
        }).join('');
        corpo.querySelectorAll('[data-saida]').forEach(function (b) {
            b.addEventListener('click', function () { verSaida(parseInt(b.getAttribute('data-saida'), 10)); });
        });
    }
    function verSaida(id) {
        ajax({ action: 'ponte_saida', id: id }).then(function (r) {
            var fundo = document.createElement('div');
            fundo.className = 'identificarcomputador-confirm-fundo';
            fundo.innerHTML = '<div class="identificarcomputador-confirm-cx identificarcomputador-saida-cx">'
                + '<div class="cab"><i class="ti ti-file-text"></i> Saída da execução #' + id + '</div>'
                + '<pre class="out">' + esc((r && r.saida) || '(sem saída)') + '</pre>'
                + '<div class="acoes"><button type="button" class="btn identificarcomputador-btn" data-fechar>Fechar</button></div></div>';
            document.body.appendChild(fundo);
            fundo.addEventListener('click', function (e) { if (e.target === fundo || e.target.hasAttribute('data-fechar')) { fundo.remove(); } });
        });
    }
    function initLogsUI() {
        var th = document.querySelector('#ic-logs-tabela thead tr');
        if (th) {
            var busca = document.createElement('div');
            busca.className = 'identificarcomputador-tbl-busca';
            busca.innerHTML = '<i class="ti ti-search"></i><input type="text" id="ic-logs-busca" placeholder="Buscar nos logs..."><button type="button" class="identificarcomputador-ico" id="ic-logs-refresh" title="Atualizar"><i class="ti ti-refresh"></i></button>';
            var tbl = document.getElementById('ic-logs-tabela');
            var cx = tbl ? tbl.closest('.identificarcomputador-tabela-cx') : null;
            if (cx) { cx.parentNode.insertBefore(busca, cx); }
            var tmr;
            busca.querySelector('#ic-logs-busca').addEventListener('keyup', function () { clearTimeout(tmr); tmr = setTimeout(carregarLogs, 300); });
            busca.querySelector('#ic-logs-refresh').addEventListener('click', carregarLogs);
        }
        document.querySelectorAll('#ic-logs-tabela thead th[data-sort]').forEach(function (h) {
            h.classList.add('identificarcomputador-th-sort');
            h.addEventListener('click', function () {
                var col = h.getAttribute('data-sort');
                if (logOrdem.col === col) { logOrdem.dir = logOrdem.dir === 'asc' ? 'desc' : 'asc'; } else { logOrdem.col = col; logOrdem.dir = 'asc'; }
                document.querySelectorAll('#ic-logs-tabela thead th').forEach(function (x) { x.classList.remove('sorted-asc', 'sorted-desc'); });
                h.classList.add(logOrdem.dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                renderLogs();
            });
        });
    }

    // ----------------------------------------------------------------- inicio
    function iniciar() {
        if (!document.getElementById('ic-exec-sessoes')) { return; }
        initLogsUI();
        var inp = document.getElementById('ic-exec-file');
        if (inp) { inp.addEventListener('change', function () { enviarArquivos(inp.files); inp.value = ''; }); }
        carregarSessoes();
        carregarLogs();
        setInterval(carregarSessoes, 3000);
        // Chamado pela troca de aba em computador.php ao abrir "Execuções remotas"
        window.identificarcomputadorExecApi = { abrir: function () { carregarSessoes(); carregarLogs(); } };
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }
})();
