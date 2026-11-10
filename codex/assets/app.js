(() => {
    'use strict';

    const boot = window.CODEX_BOOT || {};
    const el = (id) => document.getElementById(id);
    const CHAVE_SESSAO = 'codex.sessao';
    const CHAVE_RUN = 'codex.run';

    function salvarLocal(chave, valor) {
        try { if (valor) localStorage.setItem(chave, valor); else localStorage.removeItem(chave); } catch (e) { /* modo privado */ }
    }

    function lerLocal(chave) {
        try { return localStorage.getItem(chave) || ''; } catch (e) { return ''; }
    }

    const state = {
        csrf: boot.csrf || '',
        sessionId: null,
        runId: null,
        timer: null,
        sessions: [],
        eventsKey: '',
        abertos: new Set(),
        fila: [],
        busy: false,
        attachments: [],
        ultimo: null,
        buscaConteudo: [],
        apagadas: [],
        mostrarLixeira: false,
        motor: lerLocal('codex.motor') === 'dsh' ? 'dsh' : 'codex',
    };

    /** Troca a conversa aberta e lembra dela entre recarregamentos. */
    function definirSessao(id) {
        state.sessionId = id || null;
        salvarLocal(CHAVE_SESSAO, state.sessionId);
    }

    /** Motor usado no envio: conversa existente manda; nova usa o seletor. */
    function fonteDoEnvio() {
        if (state.sessionId) return String(state.sessionId).indexOf('dsh:') === 0 ? 'dsh' : 'codex';
        return state.motor === 'dsh' ? 'dsh' : 'codex';
    }

    function escapar(texto) {
        return String(texto).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /** Monta o <a> de um link, limpando pontuacao sobrando no fim. */
    function linkAncora(url, texto) {
        let sobra = '';
        const limpo = String(url).replace(/[.,;:!?]+$/, (t) => { sobra = t; return ''; });
        const href = limpo.indexOf('www.') === 0 ? 'http://' + limpo : limpo;
        return '<a class="link" href="' + href + '" target="_blank" rel="noopener noreferrer">'
            + (texto === undefined ? limpo : texto) + '</a>' + sobra;
    }

    // Dominios que valem link mesmo sem http:// na frente. A ordem importa:
    // "com.br" precisa vir antes de "com", senao o ".br" sobra fora do link.
    const TLDS_LINK = 'com\\.br|com|net|org|io|dev|app|me|br|co|online|store|shop|site|xyz';

    /** Deixa link de verdade: [texto](url), `url`, URL solta e dominio solto. */
    function linkificar(html) {
        // Uma passada so, em ordem: [texto](url), `url` (link que o modelo manda
        // entre crases), URL solta e dominio solto. Assim nenhum link nasce dentro de outro.
        const re = new RegExp(
            '\\[([^\\]\\n]+)\\]\\((https?:\\/\\/[^\\s)]+)\\)'
            + '|`((?:https?:\\/\\/|www\\.)[^\\s`<>"\'*]+)`'
            + '|((?:https?:\\/\\/|www\\.)[^\\s<>"\')`*]+)'
            + '|((?:[a-z0-9-]+\\.)+(?:' + TLDS_LINK + ')(?:\\/[^\\s<>"\')`*]*)?)',
            'gi'
        );
        // URL/dominio solto so conta se vier depois de espaco ou pontuacao de inicio,
        // para nao transformar pedaco de caminho de arquivo em link.
        const solto = (texto, url, offset, original) => {
            const antes = offset > 0 ? original[offset - 1] : '';
            if (antes !== '' && ' \t\n\r(>*;`'.indexOf(antes) === -1) return texto;
            return linkAncora(url);
        };
        return html.replace(re, (tudo, textoMd, urlMd, urlCrase, urlSolta, urlDominio, offset, original) => {
            if (urlSolta !== undefined) return solto(tudo, urlSolta, offset, original);
            if (urlDominio !== undefined) return solto(tudo, urlDominio, offset, original);
            return linkAncora(urlMd !== undefined ? urlMd : urlCrase, urlMd !== undefined ? textoMd : undefined);
        });
    }

    function markdown(texto) {
        const blocos = [];
        let html = escapar(texto).replace(/```([\s\S]*?)```/g, (m, codigo) => {
            blocos.push('<pre class="code"><button class="copy-code" type="button" title="Copiar codigo">copiar</button>'
                + '<code>' + codigo.replace(/^\n+|\n+$/g, '') + '</code></pre>');
            return '\u0001' + (blocos.length - 1) + '\u0001';
        });
        html = linkificar(html);
        html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\n/g, '<br>');
        return html.replace(/\u0001(\d+)\u0001/g, (m, i) => blocos[Number(i)]);
    }

    async function api(action, params = {}, options = {}) {
        const qs = new URLSearchParams(Object.assign({ action }, params)).toString();
        const init = { method: options.method || 'GET', headers: { 'X-CSRF-Token': state.csrf } };
        if (options.body !== undefined) {
            init.method = 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }
        const resp = await fetch('api.php?' + qs, init);
        let dados = {};
        try { dados = await resp.json(); } catch (e) { dados = {}; }
        if (resp.status === 401) { window.location.reload(); throw new Error('Sessao expirada.'); }
        if (!resp.ok || dados.ok === false) throw new Error(dados.message || ('Erro ' + resp.status));
        return dados;
    }

    // ------------------------------------------------------------- login

    if (!boot.authenticated) {
        const form = el('loginForm');
        if (form) {
            form.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const msg = el('loginMessage');
                msg.textContent = 'Entrando...';
                try {
                    await api('login', {}, {
                        method: 'POST',
                        body: { email: el('loginEmail').value, password: el('loginPassword').value },
                    });
                    window.location.reload();
                } catch (erro) {
                    msg.textContent = erro.message;
                }
            });
        }
        return;
    }

    // --------------------------------------------------------------- app

    const listaEl = el('sessionList');
    const mensagensEl = el('messages');
    const campo = el('prompt');
    const enviarBtn = el('sendBtn');
    const pararBtn = el('stopBtn');
    const anexarBtn = el('attachBtn');
    const arquivoInput = el('fileInput');
    const chipsEl = el('attachChips');
    const filaEl = el('queueList');

    function quando(iso) {
        const data = new Date(iso);
        if (isNaN(data.getTime())) return '';
        const diff = (Date.now() - data.getTime()) / 1000;
        if (diff < 90) return 'agora';
        if (diff < 3600) return Math.floor(diff / 60) + ' min';
        if (diff < 86400) return Math.floor(diff / 3600) + ' h';
        if (diff < 604800) return Math.floor(diff / 86400) + ' d';
        return data.toLocaleDateString('pt-BR');
    }

    function renderSessoes() {
        if (state.mostrarLixeira) { renderLixeira(); return; }
        const filtro = (el('sessionSearch').value || '').trim().toLowerCase();
        const itens = state.sessions.filter((s) => !filtro
            || String(s.nome || '').toLowerCase().includes(filtro)
            || String(s.cwd || '').toLowerCase().includes(filtro));
        listaEl.innerHTML = '';
        if (!itens.length && !state.buscaConteudo.length) {
            listaEl.innerHTML = '<p class="muted">Nenhuma conversa encontrada.</p>';
            return;
        }
        for (const s of itens) {
            const linha = document.createElement('div');
            linha.className = 'session-row';
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'session-item' + (s.id === state.sessionId ? ' active' : '');
            botao.dataset.id = s.id;
            const selo = '';
            botao.innerHTML = '<span class="nome">' + selo + escapar(s.nome) + '</span>'
                + '<span class="quando">' + escapar(quando(s.updatedAt)) + '</span>';
            botao.addEventListener('click', () => abrirSessao(s.id, s.nome));
            linha.appendChild(botao);
            // Renomear/apagar do Codex nao existe mais: as conversas sao do DSH
            // e sao gerenciadas pela janela do proprio app.
            listaEl.appendChild(linha);
        }
        if (filtro && state.buscaConteudo.length) {
            const titulo = document.createElement('p');
            titulo.className = 'busca-titulo';
            titulo.textContent = 'No conteudo (' + state.buscaConteudo.length + ')';
            listaEl.appendChild(titulo);
            for (const r of state.buscaConteudo) {
                const linha = document.createElement('div');
                linha.className = 'session-row';
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'session-item busca';
                botao.innerHTML = '<span class="nome">' + escapar(r.nome) + '</span>'
                    + '<span class="trecho">' + escapar(r.trecho) + '</span>';
                botao.addEventListener('click', () => abrirSessao(r.id, r.nome));
                linha.appendChild(botao);
                listaEl.appendChild(linha);
            }
        }
    }

    function renderLixeira() {
        listaEl.innerHTML = '';
        if (!state.apagadas.length) {
            listaEl.innerHTML = '<p class="muted">Nenhuma conversa apagada.</p>';
            return;
        }
        for (const s of state.apagadas) {
            const linha = document.createElement('div');
            linha.className = 'session-row';
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'session-item';
            botao.innerHTML = '<span class="nome">' + escapar(s.nome) + '</span>'
                + '<span class="quando">apagada ' + escapar(quando(s.apagadoEm)) + '</span>';
            const restaurar = document.createElement('button');
            restaurar.type = 'button';
            restaurar.className = 'session-menu';
            restaurar.textContent = '\u21a9';
            restaurar.title = 'Restaurar conversa';
            restaurar.setAttribute('aria-label', 'Restaurar conversa');
            restaurar.addEventListener('click', (ev) => { ev.stopPropagation(); restaurarConversa(s); });
            linha.appendChild(botao);
            linha.appendChild(restaurar);
            listaEl.appendChild(linha);
        }
    }

    async function carregarLixeira() {
        try {
            const dados = await api('lixeira');
            state.apagadas = dados.apagadas || [];
        } catch (erro) {
            state.apagadas = [];
        }
    }

    async function restaurarConversa(sessao) {
        try {
            await api('session-restore', {}, { method: 'POST', body: { id: sessao.id } });
        } catch (erro) {
            alert('Nao consegui restaurar: ' + erro.message);
            return;
        }
        await carregarLixeira();
        renderLixeira();
        await loadSessions();
    }

    /** Alterna a sidebar entre as conversas ativas e a lixeira. */
    async function alternarLixeira() {
        state.mostrarLixeira = !state.mostrarLixeira;
        const btn = el('trashBtn');
        if (btn) {
            btn.classList.toggle('active', state.mostrarLixeira);
            btn.textContent = state.mostrarLixeira ? 'Voltar para conversas' : 'Apagadas';
        }
        if (state.mostrarLixeira) {
            await carregarLixeira();
            renderLixeira();
        } else {
            renderSessoes();
        }
    }

    function menuSessao(sessao, linha) {
        const antigo = document.querySelector('.session-pop');
        if (antigo) antigo.remove();
        const pop = document.createElement('div');
        pop.className = 'session-pop';
        const renomear = document.createElement('button');
        renomear.type = 'button';
        renomear.textContent = 'Renomear';
        renomear.addEventListener('click', () => { pop.remove(); pedirNovoNome(sessao); });
        const apagar = document.createElement('button');
        apagar.type = 'button';
        apagar.className = 'perigo';
        apagar.textContent = 'Apagar';
        apagar.addEventListener('click', () => { pop.remove(); apagarConversa(sessao); });
        pop.appendChild(renomear);
        pop.appendChild(apagar);
        linha.appendChild(pop);
        setTimeout(() => {
            document.addEventListener('click', function fechar(ev) {
                if (!pop.contains(ev.target)) {
                    pop.remove();
                    document.removeEventListener('click', fechar);
                }
            });
        }, 0);
    }

    async function pedirNovoNome(sessao) {
        const digitado = window.prompt('Novo nome da conversa:', sessao.nome || '');
        if (digitado === null) return;
        const nome = digitado.trim();
        if (!nome || nome === sessao.nome) return;
        try {
            await api('session-rename', {}, { method: 'POST', body: { id: sessao.id, nome } });
            sessao.nome = nome;
            if (state.sessionId === sessao.id) el('chatTitle').textContent = nome;
            renderSessoes();
        } catch (erro) {
            alert('Nao consegui renomear: ' + erro.message);
        }
    }

    async function apagarConversa(sessao) {
        const certo = window.confirm('Apagar a conversa "' + (sessao.nome || '')
            + '"?\nEla sai da lista (o servidor guarda um backup).');
        if (!certo) return;
        try {
            await api('session-delete', {}, { method: 'POST', body: { id: sessao.id } });
            if (state.sessionId === sessao.id) novaConversa();
            await loadSessions();
        } catch (erro) {
            alert('Nao consegui apagar: ' + erro.message);
        }
    }

    function marcarAtiva() {
        for (const botao of listaEl.querySelectorAll('.session-item')) {
            botao.classList.toggle('active', botao.dataset.id === state.sessionId);
        }
    }

    function rolarFim() {
        const perto = mensagensEl.scrollHeight - mensagensEl.scrollTop - mensagensEl.clientHeight < 220;
        if (perto) mensagensEl.scrollTop = mensagensEl.scrollHeight;
    }

    function anexoChip(anexo, comBotao) {
        const chip = document.createElement('span');
        chip.className = 'attach-chip';
        if (anexo.thumb) {
            const img = document.createElement('img');
            img.className = 'attach-thumb';
            img.src = anexo.thumb;
            img.alt = anexo.name;
            chip.appendChild(img);
        }
        const nome = document.createElement('span');
        nome.className = 'attach-name';
        nome.textContent = anexo.name;
        chip.appendChild(nome);
        if (comBotao) {
            const remover = document.createElement('button');
            remover.type = 'button';
            remover.className = 'attach-remove';
            remover.setAttribute('aria-label', 'Remover anexo');
            remover.textContent = '\u00d7';
            remover.addEventListener('click', () => {
                state.attachments = state.attachments.filter((a) => a !== anexo);
                renderAnexos();
            });
            chip.appendChild(remover);
        }
        return chip;
    }

    function renderAnexos() {
        if (!chipsEl) return;
        chipsEl.innerHTML = '';
        if (!state.attachments.length) { chipsEl.hidden = true; return; }
        chipsEl.hidden = false;
        for (const anexo of state.attachments) chipsEl.appendChild(anexoChip(anexo, true));
    }

    async function subirArquivos(lista) {
        const arquivos = Array.from(lista || []);
        if (!arquivos.length) return;
        const previas = arquivos.map((f) => (f.type && f.type.indexOf('image/') === 0 ? URL.createObjectURL(f) : ''));
        const dados = new FormData();
        for (const f of arquivos) dados.append('arquivos[]', f, f.name);
        try {
            const resp = await fetch('upload.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': state.csrf },
                body: dados,
            });
            if (resp.status === 401 || resp.status === 419) { window.location.reload(); return; }
            const json = await resp.json().catch(() => ({}));
            if (!json.ok) throw new Error(json.message || ('Erro ' + resp.status));
            (json.attachments || []).forEach((anexo, i) => {
                if (anexo.isImage) anexo.thumb = previas[i] || '';
                state.attachments.push(anexo);
            });
            if (json.errors && json.errors.length) console.warn('Anexos ignorados:', json.errors);
            renderAnexos();
        } catch (erro) {
            alert('Nao consegui anexar: ' + erro.message);
        }
    }

    function anexosBolha(anexos) {
        const wrapper = document.createElement('div');
        wrapper.className = 'msg-chips';
        for (const anexo of anexos) wrapper.appendChild(anexoChip(anexo, false));
        return wrapper;
    }

    function bolha(role, texto) {
        const div = document.createElement('div');
        div.className = 'msg ' + (role === 'user' ? 'user' : 'assistant');
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.innerHTML = markdown(texto);
        div.appendChild(bubble);
        return div;
    }

    function eventoEl(ev) {
        const div = document.createElement('div');
        if (ev.kind === 'agent') {
            div.className = 'msg assistant';
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.innerHTML = markdown(ev.text || '') + (ev.done ? '' : ' <span class="muted">...</span>');
            div.appendChild(bubble);
            return div;
        }
        if (ev.kind === 'error') {
            div.className = 'ev erro';
            div.textContent = ev.text || 'erro';
            return div;
        }
        div.className = 'ev status';
        div.textContent = ev.text || '';
        return div;
    }

    /** Item dentro do bloco cinza de processo (raciocinio, comando, arquivo, status). */
    function eventoProcesso(ev) {
        const div = document.createElement('div');
        if (ev.kind === 'command') {
            div.className = 'ev tool';
            const cmd = document.createElement('div');
            cmd.className = 'cmd';
            cmd.textContent = '$ ' + (ev.command || '');
            div.appendChild(cmd);
            if (ev.output) {
                const saida = document.createElement('pre');
                saida.textContent = ev.output;
                div.appendChild(saida);
            }
            if (ev.done && typeof ev.exitCode === 'number' && ev.exitCode !== 0) {
                const codigo = document.createElement('div');
                codigo.className = 'muted';
                codigo.textContent = 'saiu com codigo ' + ev.exitCode;
                div.appendChild(codigo);
            }
            return div;
        }
        if (ev.kind === 'reasoning') {
            div.className = 'ev pensamento';
            div.textContent = ev.text || '';
            return div;
        }
        if (ev.kind === 'file') {
            div.className = 'ev tool';
            div.textContent = 'arquivos: ' + (ev.text || '');
            return div;
        }
        div.className = 'ev status';
        div.textContent = ev.text || '';
        return div;
    }

    function blocoProcesso(eventos, chave, abertos) {
        const det = document.createElement('details');
        det.className = 'processo';
        det.dataset.chave = chave;
        det.open = abertos.has(chave);
        const resumo = document.createElement('summary');
        resumo.textContent = 'raciocinio e passos (' + eventos.length + ')';
        det.appendChild(resumo);
        for (const ev of eventos) det.appendChild(eventoProcesso(ev));
        det.addEventListener('toggle', () => {
            if (det.open) abertos.add(chave); else abertos.delete(chave);
        });
        return det;
    }

    function renderEventos(live, eventos) {
        const chave = JSON.stringify(eventos);
        if (chave === state.eventsKey) return;
        state.eventsKey = chave;
        if (!live.dataset.chave) live.dataset.chave = 'live_' + Math.random().toString(36).slice(2);
        // guarda o que estava aberto para nao fechar a cada atualizacao
        for (const det of live.querySelectorAll('details.processo[open]')) state.abertos.add(det.dataset.chave);
        for (const det of live.querySelectorAll('details.processo:not([open])')) state.abertos.delete(det.dataset.chave);

        live.innerHTML = '';
        let fila = [];
        let indice = 0;
        const despejar = () => {
            if (!fila.length) return;
            live.appendChild(blocoProcesso(fila, live.dataset.chave + ':p' + (indice++), state.abertos));
            fila = [];
        };
        for (const ev of eventos) {
            if (ev.kind === 'agent' || ev.kind === 'error') {
                despejar();
                live.appendChild(eventoEl(ev));
            } else {
                fila.push(ev);
            }
        }
        despejar();
        rolarFim();
    }

    function botaoEnviar(ocupado) {
        enviarBtn.disabled = false;
        enviarBtn.textContent = ocupado ? 'Enviar (fila)' : 'Enviar';
        enviarBtn.title = ocupado ? 'Entra na fila e roda quando o turno atual terminar' : '';
        pararBtn.hidden = !ocupado;
    }

    function setBridge(online, info) {
        el('bridgeDot').className = 'dot ' + (online ? 'on' : 'off');
        el('bridgeText').textContent = online ? 'ponte ativa' : 'ponte offline';
        el('bridgeText').title = info || '';
        if (online && info) el('chatMeta').textContent = info;
    }

    function autoResize() {
        campo.style.height = 'auto';
        campo.style.height = Math.min(campo.scrollHeight, 180) + 'px';
    }

    async function loadSessions() {
        try {
            const dados = await api('sessions');
            state.sessions = dados.sessions || [];
            setBridge(true);
        } catch (erro) {
            state.sessions = [];
            setBridge(false, erro.message);
            listaEl.innerHTML = '<p class="muted">Nao consegui carregar as sessoes.</p>';
            return;
        }
        renderSessoes();
    }

    async function abrirSessao(id, nome) {
        definirSessao(id);
        state.eventsKey = '';
        state.fila = [];
        renderFila();
        alternarMenu(false);
        el('chatTitle').textContent = nome || String(id).slice(0, 8);
        marcarAtiva();
        mensagensEl.innerHTML = '<p class="muted">Carregando...</p>';
        try {
            const dados = await api('session', { id });
            const sessaao = dados.session || {};
            mensagensEl.innerHTML = '';
            const mensagens = sessaao.messages || [];
            if (!mensagens.length) mensagensEl.innerHTML = '<p class="muted">Conversa sem mensagens de texto.</p>';
            for (const m of mensagens) mensagensEl.appendChild(bolha(m.role, m.text));
            el('chatMeta').textContent = sessaao.cwd || '';
            mensagensEl.scrollTop = mensagensEl.scrollHeight;
            campo.focus();
        } catch (erro) {
            mensagensEl.innerHTML = '<p class="erro">' + escapar(erro.message) + '</p>';
        }
    }

    function novaConversa() {
        definirSessao(null);
        state.eventsKey = '';
        state.fila = [];
        renderFila();
        state.ultimo = null;
        alternarMenu(false);
        el('chatTitle').textContent = 'Nova conversa';
        el('chatMeta').textContent = '';
        mensagensEl.innerHTML = '';
        marcarAtiva();
        campo.focus();
    }

    async function recarregarHistorico() {
        if (!state.sessionId) return;
        try {
            const dados = await api('session', { id: state.sessionId });
            const mensagens = (dados.session || {}).messages || [];
            if (!mensagens.length) return;
            mensagensEl.innerHTML = '';
            for (const m of mensagens) mensagensEl.appendChild(bolha(m.role, m.text));
        } catch (erro) { /* mantem o que ja esta na tela */ }
    }

    /** Atualiza a bolha ao vivo; devolve true quando o turno terminou. */
    function aplicarRun(live, run) {
        renderEventos(live, run.eventos || []);
        return run.status !== 'running' && run.status !== 'queued';
    }

    function anexarRetry(live) {
        if (!state.ultimo) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'retry-btn';
        btn.textContent = 'Tentar de novo';
        btn.addEventListener('click', () => {
            btn.remove();
            if (!state.busy && state.ultimo) despachar(state.ultimo);
        });
        live.appendChild(btn);
    }

    async function finalizarRun(live, run) {
        state.timer = null;
        state.busy = false;
        botaoEnviar(false);
        salvarLocal(CHAVE_RUN, '');

        if (run.status === 'error') {
            if (run.erro && !(run.eventos || []).some((e) => e.kind === 'error')) {
                live.appendChild(eventoEl({ kind: 'error', text: run.erro }));
            }
            anexarRetry(live);
        }

        if (!state.sessionId && run.threadId) {
            definirSessao(run.threadId);
            await loadSessions();
            const meta = state.sessions.find((s) => s.id === run.threadId);
            if (meta) el('chatTitle').textContent = meta.nome;
            marcarAtiva();
        } else if (state.sessionId) {
            const meta = state.sessions.find((s) => s.id === state.sessionId);
            if (meta) meta.updatedAt = new Date().toISOString();
        }
        // Se a resposta nao veio no stream, busca o historico gravado.
        if (!live.querySelector('.msg.assistant')) await recarregarHistorico();
        rolarFim();
        soltarFila();
    }

    /** Plano B: polling no /run quando o SSE nao esta disponivel. */
    function seguirPorPolling(live, runId, terminar) {
        let falhas = 0;
        const passo = async () => {
            try {
                const dados = await api('run', { id: runId });
                falhas = 0;
                const run = dados.run || {};
                if (!aplicarRun(live, run)) {
                    state.timer = setTimeout(passo, 1300);
                    return;
                }
                terminar(run);
            } catch (erro) {
                falhas += 1;
                const sumiu = /404|nao encontrada|não encontrada/i.test(erro.message || '');
                if (!sumiu && falhas <= 4) {
                    state.timer = setTimeout(passo, 1500);
                    return;
                }
                state.timer = null;
                state.busy = false;
                botaoEnviar(false);
                if (sumiu) {
                    // A ponte reiniciou no meio do turno: busca o que ficou gravado.
                    await recarregarHistorico();
                    rolarFim();
                    soltarFila();
                    return;
                }
                anexarRetry(live);
                live.appendChild(eventoEl({ kind: 'error', text: erro.message }));
                soltarFila();
            }
        };
        passo();
    }

    function acompanhar(live, runId) {
        const id = runId || state.runId;
        clearTimeout(state.timer);
        let finalizado = false;
        let fonte = null;
        let timeoutSinal = null;

        const encerrarStream = () => {
            clearTimeout(timeoutSinal);
            timeoutSinal = null;
            if (fonte) { try { fonte.close(); } catch (e) { /* ja fechado */ } fonte = null; }
        };

        const terminar = (run) => {
            if (finalizado) return undefined;
            finalizado = true;
            encerrarStream();
            return finalizarRun(live, run);
        };

        const irParaPolling = () => {
            if (finalizado || !fonte) return;
            encerrarStream();
            seguirPorPolling(live, id, terminar);
        };

        const sinal = () => {
            clearTimeout(timeoutSinal);
            timeoutSinal = setTimeout(irParaPolling, 8000);
        };

        if (typeof EventSource !== 'undefined') {
            try {
                fonte = new EventSource('api.php?action=stream&id=' + encodeURIComponent(id));
            } catch (e) {
                fonte = null;
            }
        }
        if (!fonte) {
            seguirPorPolling(live, id, terminar);
            return;
        }

        let erros = 0;
        sinal();
        fonte.onmessage = (ev) => {
            sinal();
            let dados = {};
            try { dados = JSON.parse(ev.data); } catch (e) { return; }
            const run = dados.run || {};
            if (!run.status) return; // resposta inesperada; o fallback assume
            if (aplicarRun(live, run)) terminar(run);
        };
        fonte.addEventListener('fim', (ev) => {
            let dados = {};
            try { dados = JSON.parse(ev.data); } catch (e) { /* sem corpo */ }
            terminar(dados.run || {});
        });
        fonte.onerror = () => {
            if (finalizado) return;
            erros += 1;
            if (erros >= 2) irParaPolling();
        };
    }

    function bolhaUsuario(prompt, anexos) {
        const div = bolha('user', prompt || '(anexos)');
        if (anexos.length) div.appendChild(anexosBolha(anexos));
        return div;
    }

    /** Envia um item (prompt + anexos) para a ponte e comeca a acompanhar. */
    async function despachar(item) {
        if (!state.sessionId) mensagensEl.innerHTML = '';
        if (!item.no || !item.no.isConnected) mensagensEl.appendChild(bolhaUsuario(item.prompt, item.anexos));
        state.ultimo = { prompt: item.prompt, anexos: item.anexos };

        const imagens = item.anexos.filter((a) => a.isImage).map((a) => a.path);
        const legenda = item.anexos.length
            ? item.prompt + '\n\n[anexos]\n' + item.anexos.map((a) => '- ' + a.name + ': ' + a.path).join('\n')
            : item.prompt;

        const live = document.createElement('div');
        live.className = 'live';
        mensagensEl.appendChild(live);
        mensagensEl.scrollTop = mensagensEl.scrollHeight;

        state.busy = true;
        state.eventsKey = '';
        botaoEnviar(true);

        try {
            const dados = await api('send', {}, { method: 'POST', body: { prompt: legenda.trim(), threadId: state.sessionId, images: imagens } });
            state.runId = dados.runId;
            salvarLocal(CHAVE_RUN, dados.runId);
            acompanhar(live, dados.runId);
        } catch (erro) {
            live.appendChild(eventoEl({ kind: 'error', text: erro.message }));
            state.busy = false;
            botaoEnviar(false);
            soltarFila();
        }
    }

    /** Se o turno atual esta rodando, empilha o prompt; senao envia direto. */
    function enviar() {
        const prompt = campo.value.trim();
        const anexos = state.attachments.slice();
        if (!prompt && !anexos.length) return;

        campo.value = '';
        autoResize();
        state.attachments = [];
        renderAnexos();

        const item = { prompt, anexos };
        if (state.busy) {
            // Vai para a lista de espera, com editar e apagar.
            state.fila.push(item);
            renderFila();
            rolarFim();
            return;
        }
        despachar(item);
    }

    /** Desenha a fila de prompts que esperam o turno atual terminar. */
    function renderFila() {
        if (!filaEl) return;
        filaEl.innerHTML = '';
        if (!state.fila.length) { filaEl.hidden = true; return; }
        filaEl.hidden = false;

        const titulo = document.createElement('div');
        titulo.className = 'queue-title';
        titulo.textContent = 'Na fila · ' + state.fila.length
            + (state.fila.length > 1 ? ' prompts esperando' : ' prompt esperando')
            + ' o turno atual terminar';
        filaEl.appendChild(titulo);

        state.fila.forEach((item, indice) => {
            const linha = document.createElement('div');
            linha.className = 'queue-item';

            const num = document.createElement('span');
            num.className = 'queue-num';
            num.textContent = String(indice + 1);
            linha.appendChild(num);

            const txt = document.createElement('div');
            txt.className = 'queue-text';
            txt.textContent = item.prompt || '(somente anexos)';
            linha.appendChild(txt);

            if (item.anexos && item.anexos.length) {
                const an = document.createElement('span');
                an.className = 'queue-anex';
                an.textContent = item.anexos.length + ' anexo' + (item.anexos.length > 1 ? 's' : '');
                linha.appendChild(an);
            }

            const acoes = document.createElement('div');
            acoes.className = 'queue-actions';

            const editar = document.createElement('button');
            editar.type = 'button';
            editar.className = 'q-btn';
            editar.textContent = '✏️ Editar';
            editar.title = 'Editar este prompt antes de rodar';
            editar.addEventListener('click', () => editarFila(indice, linha, txt));
            acoes.appendChild(editar);

            const apagar = document.createElement('button');
            apagar.type = 'button';
            apagar.className = 'q-btn q-del';
            apagar.textContent = '🗑 Apagar';
            apagar.title = 'Tirar este prompt da fila';
            apagar.addEventListener('click', () => removerFila(indice));
            acoes.appendChild(apagar);

            linha.appendChild(acoes);
            filaEl.appendChild(linha);
        });
    }

    /** Edicao do prompt direto na fila. */
    function editarFila(indice, linha, txt) {
        const item = state.fila[indice];
        if (!item) return;

        const area = document.createElement('textarea');
        area.className = 'queue-edit';
        area.value = item.prompt || '';
        linha.innerHTML = '';
        linha.appendChild(area);

        const salvar = document.createElement('button');
        salvar.type = 'button';
        salvar.className = 'q-btn q-ok';
        salvar.textContent = '✔ Salvar';
        salvar.addEventListener('click', () => {
            item.prompt = area.value.trim();
            if (!item.prompt && !(item.anexos && item.anexos.length)) { removerFila(indice); return; }
            renderFila();
        });

        const cancelar = document.createElement('button');
        cancelar.type = 'button';
        cancelar.className = 'q-btn';
        cancelar.textContent = 'Cancelar';
        cancelar.addEventListener('click', renderFila);

        const acoes = document.createElement('div');
        acoes.className = 'queue-actions';
        acoes.appendChild(salvar);
        acoes.appendChild(cancelar);
        linha.appendChild(acoes);

        area.focus();
        area.setSelectionRange(area.value.length, area.value.length);
        void txt;
    }

    /** Tira um prompt da fila (nao mexe no que esta rodando). */
    function removerFila(indice) {
        const item = state.fila[indice];
        if (item && item.no && item.no.parentNode) item.no.remove();
        state.fila.splice(indice, 1);
        renderFila();
    }

    /** Roda o proximo prompt da fila, se houver. */
    function soltarFila() {
        if (state.busy || !state.fila.length) return;
        const proximo = state.fila.shift();
        renderFila();
        despachar(proximo);
    }

    async function parar() {
        // Para apenas o turno atual; os prompts da fila continuam na fila.
        if (!state.runId) return;
        try {
            await api('cancel', {}, { method: 'POST', body: { runId: state.runId } });
        } catch (erro) { /* silencioso */ }
    }

    async function sair() {
        salvarLocal(CHAVE_SESSAO, '');
        salvarLocal(CHAVE_RUN, '');
        try {
            await api('logout', {}, { method: 'POST', body: {} });
        } catch (erro) { /* silencioso */ }
        window.location.reload();
    }

    function menuLateral() {
        return document.querySelector('.sidebar');
    }

    function alternarMenu(abrir) {
        const barra = menuLateral();
        const scrim = el('scrim');
        if (!barra || !scrim) return;
        barra.classList.toggle('open', abrir);
        scrim.hidden = !abrir;
    }

    /** Reabre a conversa (e o turno) que estava em andamento antes do reload. */
    async function restaurarEstado() {
        const sessaoSalva = lerLocal(CHAVE_SESSAO);
        if (sessaoSalva) {
            const meta = state.sessions.find((s) => s.id === sessaoSalva);
            if (meta) await abrirSessao(meta.id, meta.nome);
            else salvarLocal(CHAVE_SESSAO, '');
        }

        const runSalvo = lerLocal(CHAVE_RUN);
        if (!runSalvo) return;
        try {
            const dados = await api('run', { id: runSalvo });
            const run = dados.run || {};
            if (run.status !== 'running' && run.status !== 'queued') {
                salvarLocal(CHAVE_RUN, '');
                return;
            }
            if (run.threadId && !state.sessionId) definirSessao(run.threadId);
            state.runId = runSalvo;
            const live = document.createElement('div');
            live.className = 'live';
            mensagensEl.appendChild(live);
            state.busy = true;
            botaoEnviar(true);
            acompanhar(live, runSalvo);
        } catch (erro) {
            salvarLocal(CHAVE_RUN, '');
        }
    }

    async function iniciar() {
        campo.addEventListener('input', autoResize);
        el('menuBtn').addEventListener('click', () => {
            alternarMenu(!menuLateral().classList.contains('open'));
        });
        el('scrim').addEventListener('click', () => alternarMenu(false));
        // Ao voltar para a aba, sincroniza o historico (o Safari pausa o poll em segundo plano).
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && !state.busy) recarregarHistorico();
        });
        // O Enter nunca envia: ele so quebra a linha dentro do campo.
        // O envio acontece somente pelo botao "Enviar" (nem Ctrl+Enter envia).
        campo.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) ev.preventDefault();
        });
        enviarBtn.addEventListener('click', enviar);
        pararBtn.addEventListener('click', parar);
        el('newChat').addEventListener('click', novaConversa);
        anexarBtn.addEventListener('click', () => arquivoInput.click());
        arquivoInput.addEventListener('change', (ev) => { subirArquivos(ev.target.files); ev.target.value = ''; });
        campo.addEventListener('paste', (ev) => {
            const colados = ev.clipboardData && ev.clipboardData.files ? Array.from(ev.clipboardData.files) : [];
            if (colados.length) { ev.preventDefault(); subirArquivos(colados); }
        });
        const buscaEl = el('sessionSearch');
        let buscaTimer = null;
        buscaEl.addEventListener('input', () => {
            clearTimeout(buscaTimer);
            const termo = (buscaEl.value || '').trim();
            if (termo.length < 3) state.buscaConteudo = [];
            renderSessoes();
            if (termo.length < 3) return;
            buscaTimer = setTimeout(async () => {
                try {
                    const dados = await api('search', { q: termo });
                    state.buscaConteudo = dados.resultados || [];
                } catch (erro) {
                    state.buscaConteudo = [];
                }
                renderSessoes();
            }, 450);
        });
        const lixoBtn = el('trashBtn');
        if (lixoBtn) lixoBtn.addEventListener('click', alternarLixeira);
        document.addEventListener('click', async (ev) => {
            const alvo = ev.target instanceof Element ? ev.target.closest('.copy-code') : null;
            if (!alvo) return;
            const codigo = alvo.parentElement ? alvo.parentElement.querySelector('code') : null;
            if (!codigo) return;
            try {
                await navigator.clipboard.writeText(codigo.textContent || '');
                alvo.textContent = 'copiado!';
            } catch (erro) {
                alvo.textContent = 'falhou';
            }
            setTimeout(() => { alvo.textContent = 'copiar'; }, 1500);
        });
        // No navegador de dentro do WhatsApp/Instagram o target="_blank" costuma
        // nao abrir nada no toque. Aqui a gente assume o clique e garante que o
        // link abra, caindo para a mesma aba quando o WebView bloqueia janela nova.
        document.addEventListener('click', (ev) => {
            const alvo = ev.target instanceof Element ? ev.target.closest('a.link') : null;
            if (!alvo) return;
            const url = alvo.getAttribute('href') || '';
            if (!url) return;
            ev.preventDefault();
            const nova = window.open(url, '_blank');
            if (nova) {
                try { nova.opener = null; } catch (erro) { /* ignora */ }
            } else {
                window.location.href = url;
            }
        });

        el('logoutBtn').addEventListener('click', sair);

        try {
            const dados = await api('bootstrap');
            setBridge(Boolean(dados.bridge && dados.bridge.ok), dados.bridge && dados.bridge.workdir);
        } catch (erro) {
            setBridge(false, erro.message);
        }
        await loadSessions();
        await restaurarEstado();
        campo.focus();
    }

    iniciar();
})();
