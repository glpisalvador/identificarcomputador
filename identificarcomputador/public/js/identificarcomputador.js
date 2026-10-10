/* Plugin Identificar Computador - frontend (painel, detalhe e configuracao). */
(function () {
    'use strict';

    var CFG = window.identificarcomputadorCfg || {};
    var csrf = CFG.csrf || '';

    // ----------------------------------------------------------------- utilidades
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
        setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; }, 2600);
        setTimeout(function () { t.remove(); }, 3100);
    }
    function confirmar(msg, aoConfirmar) {
        var fundo = document.createElement('div');
        fundo.className = 'identificarcomputador-confirm-fundo';
        fundo.innerHTML = '<div class="identificarcomputador-confirm-cx"><div class="msg">' + esc(msg) + '</div>'
            + '<div class="acoes"><button type="button" class="btn identificarcomputador-btn" data-c="nao">Cancelar</button>'
            + '<button type="button" class="btn identificarcomputador-btn-perigo" data-c="sim">Excluir</button></div></div>';
        document.body.appendChild(fundo);
        fundo.addEventListener('click', function (e) {
            if (e.target === fundo || e.target.getAttribute('data-c') === 'nao') { fundo.remove(); }
            if (e.target.getAttribute('data-c') === 'sim') { fundo.remove(); aoConfirmar(); }
        });
    }
    // Modal com formulario; aoConfirmar(fundo, fechar) le os campos e decide quando fechar.
    function modal(titulo, corpoHtml, textoOk, aoConfirmar) {
        var fundo = document.createElement('div');
        fundo.className = 'identificarcomputador-confirm-fundo';
        fundo.innerHTML = '<div class="identificarcomputador-confirm-cx identificarcomputador-modal-cx">'
            + '<div class="identificarcomputador-modal-tit">' + esc(titulo) + '</div>'
            + '<div class="identificarcomputador-modal-corpo">' + corpoHtml + '</div>'
            + '<div class="acoes"><button type="button" class="btn identificarcomputador-btn" data-c="nao">Cancelar</button>'
            + '<button type="button" class="btn identificarcomputador-btn-salvar" data-c="sim">' + esc(textoOk) + '</button></div></div>';
        document.body.appendChild(fundo);
        var fechar = function () { fundo.remove(); };
        fundo.addEventListener('click', function (e) {
            if (e.target === fundo || e.target.getAttribute('data-c') === 'nao') { fechar(); }
            if (e.target.getAttribute('data-c') === 'sim') { aoConfirmar(fundo, fechar); }
        });
        return fundo;
    }
    function ajax(dados) {
        var corpo = new URLSearchParams();
        Object.keys(dados).forEach(function (k) {
            var v = dados[k];
            if (Array.isArray(v)) { v.forEach(function (x) { corpo.append(k + '[]', x); }); }
            else { corpo.append(k, v); }
        });
        return fetch(CFG.url_ajax, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: corpo.toString(), credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (txt) {
                var j;
                try { j = JSON.parse(txt); } catch (e) { var m = txt.match(/\{[\s\S]*\}\s*$/); j = m ? JSON.parse(m[0]) : { success: false, message: 'Resposta inválida.' }; }
                if (j && j.new_token) { csrf = j.new_token; }
                return j;
            });
    }

    // ----------------------------------------------------------------- configuracao: busca de perfis
    function initBuscaPerfis() {
        document.querySelectorAll('.identificarcomputador-busca-perfil').forEach(function (inp) {
            inp.addEventListener('keyup', function () {
                var termo = inp.value.toLowerCase();
                inp.closest('.identificarcomputador-multi').querySelectorAll('.identificarcomputador-perfil').forEach(function (lb) {
                    lb.style.display = lb.getAttribute('data-busca').indexOf(termo) >= 0 ? 'flex' : 'none';
                });
            });
        });
    }

    // Busca em tempo real + ordenacao por clique no cabecalho, em cada tabela com dados
    function enhanceTabela(tbl) {
        var isKv = tbl.classList.contains('identificarcomputador-kv');
        var corpo = tbl.tBodies[0] || tbl;
        var dataRows = Array.prototype.slice.call(corpo.rows).filter(function (r) { return r.cells.length > 0; });
        if (dataRows.length < 5) { return; }

        var tb = document.createElement('div');
        tb.className = 'identificarcomputador-tbl-busca';
        tb.innerHTML = '<i class="ti ti-search"></i><input type="text" placeholder="Buscar nesta lista...">';
        tbl.parentNode.insertBefore(tb, tbl);
        var inp = tb.querySelector('input');
        var cont = document.createElement('span');
        cont.className = 'identificarcomputador-tbl-cont';
        tb.appendChild(cont);
        function atualizarCont() {
            var vis = dataRows.filter(function (r) { return r.style.display !== 'none'; }).length;
            cont.textContent = vis + ' / ' + dataRows.length;
        }
        inp.addEventListener('keyup', function () {
            var t = inp.value.toLowerCase();
            dataRows.forEach(function (r) { r.style.display = r.textContent.toLowerCase().indexOf(t) >= 0 ? '' : 'none'; });
            atualizarCont();
        });
        atualizarCont();

        if (!isKv && tbl.tHead) {
            var ths = Array.prototype.slice.call(tbl.tHead.rows[0].cells);
            ths.forEach(function (th, idx) {
                th.classList.add('identificarcomputador-th-sort');
                th.addEventListener('click', function () {
                    var dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
                    ths.forEach(function (x) { x.removeAttribute('data-dir'); x.classList.remove('sorted-asc', 'sorted-desc'); });
                    th.setAttribute('data-dir', dir);
                    th.classList.add(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                    var rows = Array.prototype.slice.call(corpo.rows);
                    rows.sort(function (a, b) {
                        var x = (a.cells[idx] ? a.cells[idx].textContent : '').trim();
                        var y = (b.cells[idx] ? b.cells[idx].textContent : '').trim();
                        var nx = parseFloat(x.replace(/\./g, '').replace(',', '.')), ny = parseFloat(y.replace(/\./g, '').replace(',', '.'));
                        if (!isNaN(nx) && !isNaN(ny) && /^[\d.,\s]+/.test(x)) { return (nx - ny) * (dir === 'asc' ? 1 : -1); }
                        return x.localeCompare(y, 'pt', { numeric: true }) * (dir === 'asc' ? 1 : -1);
                    });
                    rows.forEach(function (r) { corpo.appendChild(r); });
                });
            });
        }
    }
    function enhanceTabelasDetalhe() {
        document.querySelectorAll('.identificarcomputador-detalhe table').forEach(enhanceTabela);
    }

    // ----------------------------------------------------------------- detalhe: abas e excluir
    function initDetalhe() {
        enhanceTabelasDetalhe();
        document.querySelectorAll('.identificarcomputador-aba').forEach(function (b) {
            b.addEventListener('click', function () {
                var aba = b.getAttribute('data-aba');
                document.querySelectorAll('.identificarcomputador-aba').forEach(function (x) { x.classList.toggle('ativa', x === b); });
                document.querySelectorAll('.identificarcomputador-aba-corpo').forEach(function (c) { c.classList.toggle('ativa', c.getAttribute('data-corpo') === aba); });
            });
        });
        document.querySelectorAll('[data-ic-excluir]').forEach(function (b) {
            b.addEventListener('click', function () {
                var id = b.getAttribute('data-ic-excluir');
                confirmar('Excluir este computador e todo o histórico de coletas dele?', function () {
                    ajax({ action: 'excluir', id: id, _glpi_csrf_token: csrf }).then(function (r) {
                        toast(r.message || (r.success ? 'Removido.' : 'Falha.'), !!r.success);
                        if (r.success) { setTimeout(function () { window.location.href = CFG.url_lista; }, 700); }
                    });
                });
            });
        });

        // Enviar resumo para chamado / problema / mudanca
        var bItil = document.getElementById('ic-enviar-itil');
        if (bItil) {
            bItil.addEventListener('click', function () {
                var corpo = '<label class="identificarcomputador-modal-lb">Tipo de item</label>'
                    + '<select id="ic-m-tipo" class="form-control form-control-sm"><option value="Ticket">Chamado</option><option value="Problem">Problema</option><option value="Change">Mudança</option></select>'
                    + '<label class="identificarcomputador-modal-lb">Número do item</label>'
                    + '<input type="number" id="ic-m-id" class="form-control form-control-sm" min="1" placeholder="Ex.: 1234">'
                    + '<p class="identificarcomputador-ajuda">Um acompanhamento com o resumo deste computador será adicionado ao item informado.</p>';
                modal('Enviar resumo para um item', corpo, 'Enviar', function (fundo, fechar) {
                    var tipo = (fundo.querySelector('#ic-m-tipo') || {}).value || 'Ticket';
                    var itemId = parseInt((fundo.querySelector('#ic-m-id') || {}).value, 10);
                    if (!itemId || itemId < 1) { toast('Informe o número do item.', false); return; }
                    var ok = fundo.querySelector('[data-c="sim"]'); if (ok) { ok.disabled = true; }
                    ajax({ action: 'enviar_itil', id: CFG.computador_id, tipo: tipo, item_id: itemId, _glpi_csrf_token: csrf }).then(function (r) {
                        toast(r.message || (r.success ? 'Enviado.' : 'Falha.'), !!r.success);
                        if (r.success) { fechar(); } else if (ok) { ok.disabled = false; }
                    });
                });
            });
        }

        // Converter o registro do plugin num ativo (Computer) nativo do GLPI
        var bConv = document.getElementById('ic-converter-ativo');
        if (bConv) {
            bConv.addEventListener('click', function () {
                var opts = (CFG.entidades || []).map(function (en) { return '<option value="' + en.id + '">' + esc(en.nome) + '</option>'; }).join('');
                if (!opts) { opts = '<option value="0">Entidade raiz</option>'; }
                var corpo = '<label class="identificarcomputador-modal-lb">Entidade de destino</label>'
                    + '<select id="ic-m-ent" class="form-control form-control-sm">' + opts + '</select>'
                    + '<p class="identificarcomputador-ajuda">Será criado um computador nos ativos do GLPI (Ativos &gt; Computadores) com os dados correspondentes já coletados.</p>';
                modal('Converter em ativo do GLPI', corpo, 'Converter', function (fundo, fechar) {
                    var ent = (fundo.querySelector('#ic-m-ent') || {}).value;
                    var ok = fundo.querySelector('[data-c="sim"]'); if (ok) { ok.disabled = true; }
                    ajax({ action: 'converter_ativo', id: CFG.computador_id, entidade: ent, _glpi_csrf_token: csrf }).then(function (r) {
                        toast(r.message || (r.success ? 'Criado.' : 'Falha.'), !!r.success);
                        if (r.success) {
                            var corpoOk = '<p>' + esc(r.message || 'Computador criado nos ativos.') + '</p>';
                            if (r.url) { corpoOk += '<p><a class="btn identificarcomputador-btn" href="' + esc(r.url) + '"><i class="ti ti-external-link"></i> Abrir computador nos ativos</a></p>'; }
                            fundo.querySelector('.identificarcomputador-modal-corpo').innerHTML = corpoOk;
                            if (ok) { ok.style.display = 'none'; }
                            var nao = fundo.querySelector('[data-c="nao"]'); if (nao) { nao.textContent = 'Fechar'; }
                        } else if (ok) { ok.disabled = false; }
                    });
                });
            });
        }
    }

    // ----------------------------------------------------------------- painel
    var dados = [];
    var pagina = 1;
    var porPagina = 50;
    var ordem = { col: 'ultima', dir: 'desc' };
    var filtrosSel = { so: new Set(), fabricante: new Set(), antivirus: new Set(), firewall: new Set(), dominio: new Set() };
    var graficos = {};

    function pill(txt, tipo) { return '<span class="identificarcomputador-pill ' + tipo + '">' + esc(txt) + '</span>'; }

    function avClasse(estado) {
        if (!estado) { return 'off'; }
        if (/atualizado/i.test(estado) && !/desatualizado/i.test(estado)) { return 'ok'; }
        if (/desatualizado/i.test(estado)) { return 'warn'; }
        return 'off';
    }
    function fwClasse(fw) { return fw === 'Ativo' ? 'ok' : (fw === 'Parcial' ? 'warn' : 'off'); }

    function render() {
        var corpo = document.getElementById('identificarcomputador-corpo');
        var vazio = document.getElementById('identificarcomputador-vazio');
        var filtrados = aplicarFiltroLocal(dados);
        ordenar(filtrados);

        var total = filtrados.length;
        var paginas = Math.max(1, Math.ceil(total / porPagina));
        if (pagina > paginas) { pagina = paginas; }
        var ini = (pagina - 1) * porPagina;
        var pag = filtrados.slice(ini, ini + porPagina);

        vazio.style.display = total === 0 ? 'block' : 'none';
        corpo.innerHTML = pag.map(function (r) {
            var remoto = r.acesso_remoto ? pill(r.acesso_remoto, 'remoto') : pill('—', 'off');
            var av = r.antivirus ? pill(r.antivirus + (r.antivirus_estado ? '' : ''), avClasse(r.antivirus_estado)) : pill('Nenhum', 'off');
            return '<tr>'
                + '<td><a href="' + esc(CFG.url_detalhe) + '?id=' + r.id + '"><b>' + esc(r.hostname || ('#' + r.id)) + '</b></a><div style="font-size:10px;color:#999">' + esc(r.ips) + '</div></td>'
                + '<td>' + esc(r.usuario) + '</td>'
                + '<td>' + esc(r.so) + '</td>'
                + '<td>' + esc(r.fabricante) + '<div style="font-size:10px;color:#999">' + esc(r.modelo) + '</div></td>'
                + '<td>' + (r.ram_gb ? r.ram_gb + ' GB' : '-') + '</td>'
                + '<td>' + (r.disco_gb ? r.disco_gb + ' GB' : '-') + '</td>'
                + '<td>' + av + '</td>'
                + '<td>' + pill(r.firewall || '—', fwClasse(r.firewall)) + '</td>'
                + '<td>' + remoto + '</td>'
                + '<td>' + esc(fmtData(r.ultima)) + '</td>'
                + '<td class="identificarcomputador-no-export"><span class="identificarcomputador-acoes-linha">'
                + '<a class="identificarcomputador-ico" href="' + esc(CFG.url_detalhe) + '?id=' + r.id + '" title="Ver detalhes"><i class="ti ti-eye"></i></a>'
                + (CFG.podeExcluir ? '<button type="button" class="identificarcomputador-ico perigo" data-excluir="' + r.id + '" title="Excluir"><i class="ti ti-trash"></i></button>' : '')
                + '</span></td>'
                + '</tr>';
        }).join('');

        corpo.querySelectorAll('[data-excluir]').forEach(function (b) {
            b.addEventListener('click', function () {
                var id = b.getAttribute('data-excluir');
                confirmar('Excluir este computador e todo o histórico de coletas dele?', function () {
                    ajax({ action: 'excluir', id: id, _glpi_csrf_token: csrf }).then(function (res) {
                        toast(res.message || '', !!res.success);
                        if (res.success) { carregar(); }
                    });
                });
            });
        });

        renderPaginacao(paginas, total);
    }

    function fmtData(s) {
        if (!s) { return '-'; }
        var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        return m ? (m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5]) : s;
    }

    function renderPaginacao(paginas, total) {
        var el = document.getElementById('identificarcomputador-paginacao');
        if (paginas <= 1) { el.innerHTML = '<span style="font-size:11px;color:#999">' + total + ' computador(es)</span>'; return; }
        var h = '<button ' + (pagina <= 1 ? 'disabled' : '') + ' data-p="' + (pagina - 1) + '">‹</button>';
        var ini = Math.max(1, pagina - 2), fim = Math.min(paginas, ini + 4);
        ini = Math.max(1, fim - 4);
        if (ini > 1) { h += '<button data-p="1">1</button>' + (ini > 2 ? '<span>…</span>' : ''); }
        for (var i = ini; i <= fim; i++) { h += '<button class="' + (i === pagina ? 'ativa' : '') + '" data-p="' + i + '">' + i + '</button>'; }
        if (fim < paginas) { h += (fim < paginas - 1 ? '<span>…</span>' : '') + '<button data-p="' + paginas + '">' + paginas + '</button>'; }
        h += '<button ' + (pagina >= paginas ? 'disabled' : '') + ' data-p="' + (pagina + 1) + '">›</button>';
        h += '<span style="font-size:11px;color:#999;margin-left:8px">' + total + ' computador(es)</span>';
        el.innerHTML = h;
        el.querySelectorAll('button[data-p]').forEach(function (b) {
            b.addEventListener('click', function () { pagina = parseInt(b.getAttribute('data-p'), 10); render(); });
        });
    }

    function ordenar(arr) {
        var c = ordem.col, dir = ordem.dir === 'asc' ? 1 : -1;
        arr.sort(function (a, b) {
            var x = a[c], y = b[c];
            if (typeof x === 'number' && typeof y === 'number') { return (x - y) * dir; }
            return String(x || '').localeCompare(String(y || ''), 'pt', { numeric: true }) * dir;
        });
    }

    function aplicarFiltroLocal(arr) {
        // O servidor ja filtra; aqui so reaplicamos os selects marcados (defesa extra e resposta imediata)
        return arr.filter(function (r) {
            return (filtrosSel.so.size === 0 || filtrosSel.so.has(r.so))
                && (filtrosSel.fabricante.size === 0 || filtrosSel.fabricante.has(r.fabricante))
                && (filtrosSel.antivirus.size === 0 || filtrosSel.antivirus.has(r.antivirus))
                && (filtrosSel.firewall.size === 0 || filtrosSel.firewall.has(r.firewall))
                && (filtrosSel.dominio.size === 0 || filtrosSel.dominio.has(r.dominio));
        });
    }

    function renderCards(r) {
        var el = document.getElementById('identificarcomputador-cards');
        if (!el || !r) { return; }
        function card(n, t, icone, alerta) {
            return '<div class="identificarcomputador-card-num' + (alerta && n > 0 ? ' alerta' : '') + '"><div class="n">' + n + '</div><div class="t"><i class="' + icone + '"></i> ' + t + '</div></div>';
        }
        el.innerHTML = card(r.total, 'Computadores', 'ti ti-device-desktop')
            + card(r.hoje, 'Coletados hoje', 'ti ti-calendar')
            + card(r.com_remoto, 'Com acesso remoto', 'ti ti-plug-connected', true)
            + card(r.sem_antivirus, 'Sem antivírus', 'ti ti-shield-off', true);
    }

    // Barras horizontais em HTML puro (sem dependencia externa)
    var CORES = ['#5b9bd5', '#e5a54b', '#70ad94', '#c98a8a', '#9b8cce', '#8b95a5', '#d6b85a', '#7fb3b3'];
    function barras(elId, lista) {
        var el = document.getElementById(elId);
        if (!el) { return; }
        lista = lista || [];
        if (lista.length === 0) { el.innerHTML = '<div class="identificarcomputador-vazio">Sem dados.</div>'; return; }
        var max = lista.reduce(function (m, x) { return Math.max(m, x.total); }, 0) || 1;
        el.innerHTML = lista.slice(0, 8).map(function (x, i) {
            var pct = Math.round((x.total / max) * 100);
            var rot = String(x.rotulo).replace(/^Microsoft /, '');
            return '<div class="identificarcomputador-barra" title="' + esc(x.rotulo) + ': ' + x.total + '">'
                + '<div class="identificarcomputador-barra-rot">' + esc(rot) + '</div>'
                + '<div class="identificarcomputador-barra-trilho"><div class="identificarcomputador-barra-fill" style="width:' + pct + '%;background:' + CORES[i % CORES.length] + '"></div></div>'
                + '<div class="identificarcomputador-barra-num">' + x.total + '</div></div>';
        }).join('');
    }

    function renderGraficos(g) {
        if (!g) { return; }
        barras('identificarcomputador-g-so', g.por_so);
        barras('identificarcomputador-g-fab', g.por_fabricante);
        barras('identificarcomputador-g-av', g.por_antivirus);
        barras('identificarcomputador-g-fw', g.por_firewall);
    }

    function filtrosAtuais() {
        return {
            action: 'listar',
            busca: (document.getElementById('ic-f-busca') || {}).value || '',
            so: [].concat.apply([], Array.from(filtrosSel.so)),
            fabricante: Array.from(filtrosSel.fabricante),
            antivirus: Array.from(filtrosSel.antivirus),
            firewall: Array.from(filtrosSel.firewall),
            dominio: Array.from(filtrosSel.dominio),
            com_acesso_remoto: (document.getElementById('ic-f-remoto') || {}).checked ? 1 : 0,
            data_de: (document.getElementById('ic-f-de') || {}).value || '',
            data_ate: (document.getElementById('ic-f-ate') || {}).value || ''
        };
    }

    function carregar() {
        ajax(filtrosAtuais()).then(function (r) {
            if (!r.success) { toast(r.message || 'Falha ao carregar.', false); return; }
            dados = r.linhas || [];
            renderCards(r.resumo);
            renderGraficos(r.graficos);
            render();
        });
    }

    // Multiselect customizado dos filtros
    function montarMulti(selectId, chave, opcoes) {
        var original = document.getElementById(selectId);
        if (!original) { return; }
        var ms = document.createElement('div');
        ms.className = 'identificarcomputador-ms';
        ms.innerHTML = '<div class="identificarcomputador-ms-cab"><span class="rot ph">Todos</span><i class="ti ti-chevron-down"></i></div>'
            + '<div class="identificarcomputador-ms-drop"><div class="identificarcomputador-ms-busca"><input type="text" class="form-control form-control-sm" placeholder="Buscar..."></div>'
            + '<div class="identificarcomputador-ms-lista"></div></div>';
        original.parentNode.insertBefore(ms, original);
        original.style.display = 'none';

        var cab = ms.querySelector('.identificarcomputador-ms-cab');
        var drop = ms.querySelector('.identificarcomputador-ms-drop');
        var lista = ms.querySelector('.identificarcomputador-ms-lista');
        var busca = ms.querySelector('.identificarcomputador-ms-busca input');
        var rot = ms.querySelector('.rot');

        function atualizarRot() {
            var n = filtrosSel[chave].size;
            rot.textContent = n === 0 ? 'Todos' : (n + ' selecionado(s)');
            rot.classList.toggle('ph', n === 0);
        }
        function montarLista() {
            var sel = [], nao = [];
            (opcoes || []).forEach(function (op) { (filtrosSel[chave].has(op) ? sel : nao).push(op); });
            lista.innerHTML = sel.concat(nao).map(function (op) {
                return '<label class="identificarcomputador-ms-op' + (filtrosSel[chave].has(op) ? ' sel' : '') + '" data-b="' + esc(op.toLowerCase()) + '">'
                    + '<input type="checkbox" ' + (filtrosSel[chave].has(op) ? 'checked' : '') + ' value="' + esc(op) + '"><span>' + esc(op) + '</span></label>';
            }).join('') || '<div class="identificarcomputador-vazio">Sem opções.</div>';
            lista.querySelectorAll('input').forEach(function (chk) {
                chk.addEventListener('change', function () {
                    if (chk.checked) { filtrosSel[chave].add(chk.value); } else { filtrosSel[chave].delete(chk.value); }
                    busca.value = '';
                    montarLista(); atualizarRot(); pagina = 1; carregar();
                });
            });
        }
        cab.addEventListener('click', function () {
            document.querySelectorAll('.identificarcomputador-ms-drop.aberto').forEach(function (d) { if (d !== drop) { d.classList.remove('aberto'); } });
            drop.classList.toggle('aberto');
            if (drop.classList.contains('aberto')) { busca.focus(); }
        });
        busca.addEventListener('keyup', function () {
            var t = busca.value.toLowerCase();
            lista.querySelectorAll('.identificarcomputador-ms-op').forEach(function (o) { o.style.display = o.getAttribute('data-b').indexOf(t) >= 0 ? 'flex' : 'none'; });
        });
        montarLista(); atualizarRot();
    }

    function initFiltros() {
        ajax({ action: 'opcoes' }).then(function (r) {
            if (!r.success) { return; }
            var o = r.opcoes || {};
            montarMulti('ic-f-so', 'so', o.so);
            montarMulti('ic-f-fabricante', 'fabricante', o.fabricante);
            montarMulti('ic-f-antivirus', 'antivirus', o.antivirus);
            montarMulti('ic-f-firewall', 'firewall', o.firewall);
            montarMulti('ic-f-dominio', 'dominio', o.dominio);
        });

        var busca = document.getElementById('ic-f-busca');
        var tmr;
        if (busca) { busca.addEventListener('keyup', function () { clearTimeout(tmr); tmr = setTimeout(function () { pagina = 1; carregar(); }, 300); }); }
        ['ic-f-remoto', 'ic-f-de', 'ic-f-ate'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('change', function () { pagina = 1; carregar(); }); }
        });
        var limpar = document.getElementById('ic-limpar');
        if (limpar) {
            limpar.addEventListener('click', function () {
                Object.keys(filtrosSel).forEach(function (k) { filtrosSel[k].clear(); });
                if (busca) { busca.value = ''; }
                ['ic-f-de', 'ic-f-ate'].forEach(function (id) { var e = document.getElementById(id); if (e) { e.value = ''; } });
                var rem = document.getElementById('ic-f-remoto'); if (rem) { rem.checked = false; }
                document.querySelectorAll('.identificarcomputador-ms').forEach(function (m) { m.remove(); });
                document.querySelectorAll('.identificarcomputador-sel').forEach(function (s) { s.style.display = ''; });
                initFiltros();
                pagina = 1; carregar();
            });
        }

        document.querySelectorAll('.identificarcomputador-tabela th[data-sort]').forEach(function (th) {
            th.addEventListener('click', function () {
                var c = th.getAttribute('data-sort');
                if (ordem.col === c) { ordem.dir = ordem.dir === 'asc' ? 'desc' : 'asc'; } else { ordem.col = c; ordem.dir = 'asc'; }
                document.querySelectorAll('.identificarcomputador-tabela th').forEach(function (x) { x.classList.remove('sorted-asc', 'sorted-desc'); });
                th.classList.add(ordem.dir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                render();
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.identificarcomputador-ms')) {
                document.querySelectorAll('.identificarcomputador-ms-drop.aberto').forEach(function (d) { d.classList.remove('aberto'); });
            }
        });
    }

    // Exportacao (sem dependencia externa)
    function linhasVisiveis() {
        return aplicarFiltroLocal(dados);
    }
    function colunas() {
        return [
            ['Computador', 'hostname'], ['IPs', 'ips'], ['Usuário', 'usuario'], ['Domínio', 'dominio'],
            ['Sistema', 'so'], ['Fabricante', 'fabricante'], ['Modelo', 'modelo'], ['Processador', 'processador'],
            ['RAM (GB)', 'ram_gb'], ['Disco (GB)', 'disco_gb'], ['Antivírus', 'antivirus'], ['Estado AV', 'antivirus_estado'],
            ['Firewall', 'firewall'], ['Acesso remoto', 'acesso_remoto'], ['MACs', 'macs'], ['Coletas', 'coletas'],
            ['Primeira', 'primeira'], ['Última', 'ultima']
        ];
    }
    function exportarCSV() {
        var cols = colunas();
        var linhas = linhasVisiveis();
        var txt = '﻿' + cols.map(function (c) { return '"' + c[0] + '"'; }).join(';') + '\n';
        linhas.forEach(function (r) {
            txt += cols.map(function (c) { return '"' + String(r[c[1]] == null ? '' : r[c[1]]).replace(/"/g, '""') + '"'; }).join(';') + '\n';
        });
        baixarArquivo(txt, 'computadores.csv', 'text/csv;charset=utf-8');
    }
    function exportarExcel() {
        var cols = colunas();
        var linhas = linhasVisiveis();
        var h = '<table border="1"><tr>' + cols.map(function (c) { return '<th>' + esc(c[0]) + '</th>'; }).join('') + '</tr>';
        linhas.forEach(function (r) {
            h += '<tr>' + cols.map(function (c) { return '<td>' + esc(r[c[1]] == null ? '' : r[c[1]]) + '</td>'; }).join('') + '</tr>';
        });
        h += '</table>';
        var doc = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>' + h + '</body></html>';
        baixarArquivo(doc, 'computadores.xls', 'application/vnd.ms-excel');
    }
    function baixarArquivo(conteudo, nome, tipo) {
        var blob = new Blob([conteudo], { type: tipo });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = nome;
        document.body.appendChild(a); a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    }

    function initPainel() {
        document.querySelectorAll('[data-ic-exportar]').forEach(function (b) {
            b.addEventListener('click', function () { b.getAttribute('data-ic-exportar') === 'excel' ? exportarExcel() : exportarCSV(); });
        });
        var baixar = document.getElementById('identificarcomputador-baixar');
        if (baixar) {
            baixar.addEventListener('click', function () {
                toast('Gerando o script... o download vai começar em instantes.', true);
            });
        }
        initFiltros();
        carregar();
    }

    // ----------------------------------------------------------------- inicio
    // Abas de topo de computador.php: Computadores identificados / Execuções remotas
    function initTopAbas() {
        var abas = document.querySelectorAll('[data-topaba]');
        if (!abas.length) { return; }
        function mostrar(nome) {
            document.querySelectorAll('[data-topaba]').forEach(function (b) { b.classList.toggle('ativa', b.getAttribute('data-topaba') === nome); });
            document.querySelectorAll('[data-toppanel]').forEach(function (p) { p.style.display = p.getAttribute('data-toppanel') === nome ? '' : 'none'; });
            if (nome === 'execucoes' && window.identificarcomputadorExecApi) { window.identificarcomputadorExecApi.abrir(); }
        }
        abas.forEach(function (b) { b.addEventListener('click', function () { mostrar(b.getAttribute('data-topaba')); }); });
        try {
            var p = new URLSearchParams(window.location.search).get('aba');
            if (p === 'execucoes' && document.querySelector('[data-topaba="execucoes"]')) { mostrar('execucoes'); }
        } catch (e) { /* sem URLSearchParams */ }
    }

    function iniciar() {
        if (document.querySelector('.identificarcomputador-busca-perfil')) { initBuscaPerfis(); }
        if (CFG.detalhe) { initDetalhe(); return; }
        initTopAbas();
        if (document.getElementById('identificarcomputador-tabela')) { initPainel(); }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', iniciar); } else { iniciar(); }
})();
