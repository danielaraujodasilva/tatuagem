(() => {
    'use strict';

    const boot = window.CODEX_BOOT || {};
    const el = (id) => document.getElementById(id);
    const CHAVE_SESSAO = 'codex.sessao';
    const CHAVE_RUN = 'codex.run';
    const CHAVE_MIGRADA = 'codex.aba.migrada';

    // Cada aba tem a propria conversa aberta. O localStorage era compartilhado
    // entre todas as abas: abrir a conversa B numa aba trocava a conversa da aba
    // A e os turnos iam parar no lugar errado. sessionStorage e por aba (e
    // sobrevive ao F5), entao duas abas podem cuidar de duas conversas.
    function salvarLocal(chave, valor) {
        try { if (valor) sessionStorage.setItem(chave, valor); else sessionStorage.removeItem(chave); }
        catch (e) { /* modo privado: fica so na memoria da pagina */ }
    }

    function lerLocal(chave) {
        try { return sessionStorage.getItem(chave) || ''; } catch (e) { return ''; }
    }

    /** Herda a conversa que estava aberta antes desta correcao (uma vez por aba). */
    function migrarConversaAntiga() {
        try {
            if (sessionStorage.getItem(CHAVE_MIGRADA)) return;
            sessionStorage.setItem(CHAVE_MIGRADA, '1');
            const sessao = localStorage.getItem(CHAVE_SESSAO) || '';
            const run = localStorage.getItem(CHAVE_RUN) || '';
            if (sessao && !sessionStorage.getItem(CHAVE_SESSAO)) sessionStorage.setItem(CHAVE_SESSAO, sessao);
            if (run && !sessionStorage.getItem(CHAVE_RUN)) sessionStorage.setItem(CHAVE_RUN, run);
            localStorage.removeItem(CHAVE_SESSAO);
            localStorage.removeItem(CHAVE_RUN);
        } catch (e) { /* sem storage */ }
    }

    migrarConversaAntiga();

    const state = {
        csrf: boot.csrf || '',
        // Cada conversa tem a propria tela, fila, turno e estado. "views" guarda
        // isso por conversa; os campos abaixo sao o espelho da conversa aberta.
        views: new Map(),
        atual: null,
        rascunhoSeq: 0,
        carregandoSessoes: 0,
        assinaturaSessoes: '',
        // espelho da view atual
        sessionId: null,
        runId: null,
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
        // metadados da mensagem / modal de informacoes
        sessaoMeta: null,
        runAtual: null,
        motor: lerLocal('codex.motor') === 'dsh' ? 'dsh' : 'codex',
        // voz: push-to-talk + leitura automatica das respostas
        vozInteracao: false,
        vozIniciando: false,
        vozGravando: false,
        vozRec: null,
        vozStream: null,
        vozChunks: [],
        vozTimer: null,
        vozCancelar: false,
        vozTranscrevendo: false,
        vozTocando: false,
        vozAudio: null,
        vozAudioEl: null,
        vozResolve: null,
        vozSeq: 0,
        // fila de fala (ack + resposta em blocos)
        vozFilaFala: [],
        vozFilaRodando: false,
        vozFalaRunId: null,
        vozFalaBase: 0,
        vozFalaBuffer: '',
        vozAckTimer: null,
        // maos livres (VAD no navegador)
        vozMaosLivres: false,
        vozHFStream: null,
        vozHFCtx: null,
        vozHFSource: null,
        vozHFProc: null,
        vozHFGain: null,
        vozHFBuffer: [],
        vozHFBase: 0,
        vozHFTotal: 0,
        vozHFInicio: 0,
        vozHFGravando: false,
        vozHFFalaMs: 0,
        vozHFSilencioMs: 0,
        vozHFCalibrando: 0,
        vozHFCalibSoma: 0,
        vozHFCalibN: 0,
        vozHFNivel: 0.018,
        vozHFIgnorarAte: 0,
        vozHFEnviando: false,
        vozHFInicioTs: 0,
        vozHFBargeIn: false,
        vozEstado: 'off',
    };

    function novaLinhaDeRascunho() {
        state.rascunhoSeq += 1;
        return 'rascunho:' + state.rascunhoSeq + ':' + Date.now().toString(36);
    }

    function criarView(chave, id) {
        return {
            chave: chave,
            id: id || (String(chave).indexOf('rascunho:') === 0 ? null : String(chave)),
            nome: '',
            nodes: null,          // tela guardada (DocumentFragment) quando nao esta aberta
            meta: null,
            runId: null,
            run: null,
            busy: false,
            fila: [],
            eventsKey: '',
            ultimo: null,
            carregando: false,
            cargaSeq: 0,
            temMensagens: false,
            precisaAtualizar: false,
            forcarRecarga: false,
            // rascunho do composer e anexos ficam presos a conversa: trocar de
            // conversa nao pode levar o prompt de uma para a outra.
            rascunho: '',
            attachments: [],
        };
    }

    function garantirView(chave, id) {
        let view = state.views.get(chave);
        if (!view) {
            view = criarView(chave, id);
            state.views.set(chave, view);
        } else if (id && !view.id) {
            view.id = id;
        }
        return view;
    }

    function viewAtual() {
        if (state.atual && state.views.has(state.atual)) return state.views.get(state.atual);
        const chave = novaLinhaDeRascunho();
        const view = criarView(chave, null);
        state.views.set(chave, view);
        state.atual = chave;
        return view;
    }

    /** Container onde a conversa desenha (a tela aberta ou a tela guardada). */
    function containerDaView(view) {
        if (state.atual === view.chave) return mensagensEl;
        if (!view.nodes) view.nodes = document.createDocumentFragment();
        return view.nodes;
    }

    function limparContainer(view) {
        if (state.atual === view.chave) {
            mensagensEl.innerHTML = '';
        } else {
            view.nodes = document.createDocumentFragment();
        }
        view.temMensagens = false;
    }

    /** Tira a tela atual do DOM sem perder o turno que estiver rodando nela. */
    function estacionarTela(view) {
        if (!view || state.atual !== view.chave) return;
        view.rascunho = campo.value;
        view.attachments = state.attachments.slice();
        const fragmento = document.createDocumentFragment();
        while (mensagensEl.firstChild) fragmento.appendChild(mensagensEl.firstChild);
        view.nodes = fragmento;
        view.eventsKey = state.eventsKey;
    }

    /** Leva o rascunho e os anexos da conversa de volta para o composer. */
    function carregarRascunho(view) {
        campo.value = (view && view.rascunho) || '';
        state.attachments = view && Array.isArray(view.attachments) ? view.attachments.slice() : [];
        autoResize();
        renderAnexos();
    }

    function mostrarTela(view) {
        mensagensEl.innerHTML = '';
        if (view.nodes) {
            mensagensEl.appendChild(view.nodes);
            view.nodes = null;
        }
        view.eventsKey = state.eventsKey;
        mensagensEl.scrollTop = mensagensEl.scrollHeight;
    }

    /** Atualiza os campos globais para a conversa que esta aberta. */
    function sincronizarEspelhos(view) {
        state.sessionId = view ? view.id : null;
        state.runId = view ? view.runId : null;
        state.busy = view ? view.busy : false;
        state.fila = view ? view.fila : [];
        state.eventsKey = view ? view.eventsKey : '';
        state.ultimo = view ? view.ultimo : null;
        state.sessaoMeta = view ? view.meta : null;
        state.runAtual = view ? view.run : null;
        botaoEnviar(state.busy);
        renderFila();
        marcarAtiva();
    }

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

    /** Uma linha de tabela vira celulas: tira os pipes das pontas e divide. */
    function celulasTabela(linha) {
        let t = String(linha).trim();
        if (t.charAt(0) === '|') t = t.slice(1);
        if (t.charAt(t.length - 1) === '|') t = t.slice(0, -1);
        return t.split('|').map((c) => c.trim());
    }

    /** Linha separadora do GFM: |---|:---:|---:| */
    function ehSeparadorTabela(linha) {
        const c = celulasTabela(linha);
        return c.length >= 2 && c.every((x) => /^:?-{2,}:?$/.test(x));
    }

    function alinhamentoTabela(celula) {
        if (/^:-+:$/.test(celula)) return 'center';
        if (/^-+:$/.test(celula)) return 'right';
        return '';
    }

    /**
     * Troca tabela markdown (| a | b |) por <table> de verdade.
     * Roda depois do negrito/codigo e antes de transformar \n em <br>,
     * senao o <br> entra no meio da tabela e quebra tudo.
     */
    function extrairTabelas(html, blocos) {
        const linhas = html.split('\n');
        const saida = [];
        for (let i = 0; i < linhas.length; i++) {
            const cabecalho = linhas[i];
            const separador = linhas[i + 1];
            if (cabecalho.indexOf('|') === -1 || separador === undefined || !ehSeparadorTabela(separador)) {
                saida.push(cabecalho);
                continue;
            }
            const th = celulasTabela(cabecalho);
            if (th.length < 2) { saida.push(cabecalho); continue; }
            let j = i + 2;
            const corpo = [];
            while (j < linhas.length && linhas[j].trim() !== '' && linhas[j].indexOf('|') !== -1) {
                corpo.push(celulasTabela(linhas[j]));
                j++;
            }
            // Cabecalho + separador sem nenhuma linha de dados nao e tabela.
            if (!corpo.length) { saida.push(cabecalho); continue; }
            const alinhamentos = celulasTabela(separador).map(alinhamentoTabela);
            const celula = (tag, texto, k) => {
                const al = alinhamentos[k] ? ' style="text-align:' + alinhamentos[k] + '"' : '';
                return '<' + tag + al + '>' + (texto || '') + '</' + tag + '>';
            };
            let tabela = '<div class="md-table-wrap"><table class="md-table">';
            tabela += '<thead><tr>' + th.map((c, k) => celula('th', c, k)).join('') + '</tr></thead><tbody>';
            for (const linha of corpo) {
                tabela += '<tr>';
                for (let k = 0; k < th.length; k++) tabela += celula('td', linha[k], k);
                tabela += '</tr>';
            }
            tabela += '</tbody></table></div>';
            blocos.push(tabela);
            saida.push('\u0001' + (blocos.length - 1) + '\u0001');
            i = j - 1;
        }
        return saida.join('\n');
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
        html = extrairTabelas(html, blocos);
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
        if (!resp.ok || dados.ok === false) {
            const erro = new Error(dados.message || dados.error || ('Erro ' + resp.status));
            erro.status = resp.status;
            erro.dados = dados;
            throw erro;
        }
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
    const micBtn = el('micBtn');
    const handsFreeBtn = el('handsFreeBtn');
    const vozStatusEl = el('voiceStatus');
    const vozStatusTexto = el('voiceStatusText');
    const vozOrb = el('voiceOrb');
    const vozStopBtn = el('voiceStop');
    const vozMuteBtn = el('voiceMute');

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
            const selo = s.running
                ? '<span class="session-running" title="Turno em andamento nesta conversa"></span>'
                : '';
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

    /** Data/hora curta no fuso do navegador (ex.: 11/11 12:34). */
    function dataHora(ts) {
        const data = new Date(Number(ts));
        if (isNaN(data.getTime())) return '';
        const dd = String(data.getDate()).padStart(2, '0');
        const mm = String(data.getMonth() + 1).padStart(2, '0');
        const hh = String(data.getHours()).padStart(2, '0');
        const min = String(data.getMinutes()).padStart(2, '0');
        const ano = data.getFullYear() === new Date().getFullYear() ? '' : '/' + data.getFullYear();
        return dd + '/' + mm + ano + ' ' + hh + ':' + min;
    }

    // ------------------------------------------------ informacoes da mensagem

    const modalState = { el: null, timer: null, msgEl: null, meta: null, run: null, foco: null };

    function formatarNumero(valor) {
        const numero = Number(valor);
        if (!Number.isFinite(numero)) return '—';
        return numero.toLocaleString('pt-BR');
    }

    function totalDoUso(uso) {
        if (!uso || typeof uso !== 'object') return null;
        const total = Number(uso.totalTokens);
        if (Number.isFinite(total)) return total;
        return (Number(uso.inputTokens) || 0) + (Number(uso.outputTokens) || 0)
            + (Number(uso.cacheReadTokens) || 0) + (Number(uso.cacheWriteTokens) || 0);
    }

    function formatarDuracao(ms) {
        if (ms === null || ms === undefined || ms === '') return '';
        const valor = Number(ms);
        if (!Number.isFinite(valor) || valor < 0) return '';
        if (valor < 1000) return Math.round(valor) + ' ms';
        if (valor < 60000) {
            return (valor / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' s';
        }
        const total = Math.floor(valor / 1000);
        const horas = Math.floor(total / 3600);
        const minutos = Math.floor((total % 3600) / 60);
        const segundos = total % 60;
        if (horas) return horas + ' h ' + String(minutos).padStart(2, '0') + ' min';
        return minutos + ' min ' + String(segundos).padStart(2, '0') + ' s';
    }

    function dataHoraCompleta(ts) {
        const data = new Date(Number(ts));
        if (isNaN(data.getTime())) return '';
        try {
            return data.toLocaleString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
            });
        } catch (e) {
            return dataHora(ts);
        }
    }

    function fusoHorario() {
        try { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) { return ''; }
    }

    function estimarTokens(texto) {
        const limpo = String(texto || '').trim();
        if (!limpo) return 0;
        return Math.max(1, Math.round(limpo.length / 4));
    }

    function contarTexto(texto) {
        const t = String(texto || '');
        const blocos = (t.match(/```/g) || []).length;
        return {
            caracteres: t.length,
            palavras: (t.match(/\S+/g) || []).length,
            linhas: t ? t.split(/\r?\n/).length : 0,
            blocosCodigo: Math.floor(blocos / 2),
            links: (t.match(/https?:\/\/|www\./gi) || []).length,
        };
    }

    function contarAnexosNoTexto(texto) {
        const achado = String(texto || '').match(/\[anexos\]\s*([\s\S]*)$/i);
        if (!achado) return 0;
        return (achado[1].match(/^\s*-\s+.+?:\s+.+$/gm) || []).length;
    }

    function infoValor(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '<span class="info-vazio">não disponível</span>';
        }
        return escapar(String(valor));
    }

    function linhaInfo(rotulo, valor) {
        return '<div class="info-row"><span class="info-label">' + escapar(rotulo)
            + '</span><span class="info-value">' + (valor || '<span class="info-vazio">não disponível</span>')
            + '</span></div>';
    }

    function secaoInfo(titulo, linhas) {
        return '<section class="info-section"><h4>' + escapar(titulo) + '</h4>'
            + '<div class="info-grid">' + linhas.filter(Boolean).join('') + '</div></section>';
    }

    function origemDoAutor(autor) {
        if (!autor) return 'Não registrada';
        if (autor.fonte === 'site') return 'Site Codex';
        if (autor.fonte === 'app') return 'App DeepSeek Harness';
        return autor.fonte ? String(autor.fonte) : 'Não registrada';
    }

    function statusDaMensagem(meta, run, papel) {
        if (run && run.status === 'queued') return 'Na fila';
        if (run && run.status === 'running') return 'Gerando agora';
        if (meta.interrupted) return 'Interrompida';
        if (run && run.status === 'error') return 'Erro';
        const status = meta.turno && meta.turno.status;
        if (status === 'erro') return 'Erro no turno';
        if (status === 'cancelada') return 'Cancelada';
        if (status === 'aberto') return 'Sem registro de fim';
        if (papel === 'user') return 'Enviada';
        return 'Concluída';
    }

    function resumoRun(run) {
        if (!run) return null;
        return {
            id: run.id || null,
            status: run.status || null,
            threadId: run.threadId || null,
            novo: Boolean(run.novo),
            inicio: run.inicio || null,
            inicioExecucao: run.inicioExecucao || null,
            fim: run.fim || null,
            turno: run.turno ?? null,
            etapa: run.etapa ?? null,
            inicioTurno: run.inicioTurno || null,
            inicioEtapa: run.inicioEtapa || null,
            fimTurno: run.fimTurno || null,
            motivo: run.motivo || null,
            uso: run.uso || null,
            passos: Array.isArray(run.passos) ? run.passos : [],
            modelo: run.modelo || null,
            provedor: run.provedor || null,
            usuario: run.usuario || null,
            ip: run.ip || '',
            userAgent: run.userAgent || '',
        };
    }

    function garantirModalInfo() {
        if (modalState.el) return modalState.el;
        const wrap = document.createElement('div');
        wrap.className = 'msg-modal';
        wrap.id = 'msgModal';
        wrap.hidden = true;
        wrap.innerHTML = '<div class="msg-modal-scrim" data-fechar="1"></div>'
            + '<section class="msg-modal-card" role="dialog" aria-modal="true" aria-labelledby="msgModalTitulo">'
            + '<header class="msg-modal-head"><div class="msg-modal-title">'
            + '<h3 id="msgModalTitulo">Informações da mensagem</h3>'
            + '<p class="muted" id="msgModalSub"></p></div>'
            + '<button class="msg-modal-close" id="msgModalClose" type="button" aria-label="Fechar">&times;</button>'
            + '</header><div class="msg-modal-body" id="msgModalBody"></div></section>';
        document.body.appendChild(wrap);

        wrap.addEventListener('click', (ev) => {
            if (ev.target === wrap || (ev.target instanceof Element && ev.target.closest('[data-fechar]'))) {
                fecharModalInfo();
                return;
            }
            const copiar = ev.target instanceof Element ? ev.target.closest('[data-copiar-json]') : null;
            if (!copiar) return;
            const pre = wrap.querySelector('.msg-modal-raw pre');
            if (!pre) return;
            navigator.clipboard.writeText(pre.textContent || '').then(() => {
                copiar.textContent = 'copiado!';
                setTimeout(() => { copiar.textContent = 'copiar JSON'; }, 1500);
            }).catch(() => {
                copiar.textContent = 'falhou';
                setTimeout(() => { copiar.textContent = 'copiar JSON'; }, 1500);
            });
        });
        wrap.querySelector('#msgModalClose').addEventListener('click', fecharModalInfo);
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && !wrap.hidden) fecharModalInfo();
        });
        modalState.el = wrap;
        return wrap;
    }

    function fecharModalInfo() {
        if (!modalState.el) return;
        modalState.el.hidden = true;
        document.body.classList.remove('msg-modal-aberto');
        clearInterval(modalState.timer);
        modalState.timer = null;
        if (modalState.foco && typeof modalState.foco.focus === 'function') {
            try { modalState.foco.focus(); } catch (e) { /* elemento sumiu */ }
        }
        modalState.foco = null;
    }

    function renderModalInfo() {
        const modal = modalState.el;
        if (!modal) return;
        const msgEl = modalState.msgEl;
        const meta = modalState.meta || (msgEl && msgEl._msgMeta) || {};
        const metaAtual = (msgEl && msgEl._msgMeta) || meta;
        let run = modalState.run || null;
        if (state.runAtual) {
            const runId = meta.runId || metaAtual.runId || null;
            const sessaoId = meta.sessionId || metaAtual.sessionId || null;
            if (runId && state.runAtual.id === runId) {
                run = state.runAtual;
            } else if (!run && metaAtual.live && sessaoId && state.runAtual.threadId === sessaoId) {
                run = state.runAtual;
            }
        }
        const bubble = msgEl ? msgEl.querySelector('.bubble') : null;
        const texto = bubble ? textoDaBolha(bubble) : '';
        const papel = meta.role || (msgEl && msgEl.classList.contains('user') ? 'user' : 'assistant');
        const contagem = meta.texto || contarTexto(texto);
        const autor = meta.author || (run && run.usuario) || null;
        const turno = meta.turno || null;
        const uso = meta.usage || null;
        const runAtivo = Boolean(run && (run.status === 'running' || run.status === 'queued'));
        const aoVivo = runAtivo || (Boolean(meta.live) && !run);
        const ts = Number(meta.time) || Number(run && (run.inicioTurno || run.inicioExecucao || run.inicio)) || Date.now();
        const data = new Date(ts);
        const agora = Date.now();

        const status = statusDaMensagem(meta, run, papel);
        const dataLocal = dataHoraCompleta(ts);
        const iso = !isNaN(data.getTime()) ? data.toISOString() : '';
        const fuso = fusoHorario();

        let geracaoMs = meta.durationMs ?? meta.generationMs ?? null;
        if (aoVivo && meta.startedAt) geracaoMs = Math.max(0, agora - Number(meta.startedAt));
        const turnoInicio = turno?.inicio || (run && run.inicioTurno) || null;
        const turnoFim = turno?.fim || (run && run.fimTurno) || null;
        let turnoDuracao = turno?.duracaoMs ?? null;
        if (turnoDuracao === null && turnoInicio && turnoFim) {
            turnoDuracao = Math.max(0, turnoFim - turnoInicio);
        }
        if (aoVivo && turnoInicio && !turnoFim) {
            turnoDuracao = Math.max(0, agora - turnoInicio);
        }
        const tempoAteResposta = meta.tempoAteRespostaMs ?? turno?.primeiraRespostaMs ?? null;
        const usoDoTurno = turno?.uso || (run && run.uso) || null;
        const totalUso = totalDoUso(uso);
        const totalTurno = totalDoUso(usoDoTurno);
        const tokensTexto = Number(contagem.tokensEstimados) || estimarTokens(texto);
        const velocidade = uso && Number(uso.outputTokens) > 0 && geracaoMs > 0
            ? (Number(uso.outputTokens) / (geracaoMs / 1000)) : null;

        const linhasResumo = [
            linhaInfo('Papel', papel === 'user' ? 'Você' : 'Codex'),
            linhaInfo('Estado', '<span class="info-status' + (aoVivo ? ' ao-vivo' : '') + '">' + escapar(status) + '</span>'),
            linhaInfo('Login / autor', infoValor(autor?.nome || autor?.email || 'Não registrado')),
            linhaInfo('E-mail / login', infoValor(autor?.email)),
            linhaInfo('Origem', infoValor(origemDoAutor(autor))),
            linhaInfo('Modelo', infoValor([meta.model, meta.provider].filter(Boolean).join(' · ') || (run && [run.modelo, run.provedor].filter(Boolean).join(' · ')))),
            linhaInfo('Data e hora', infoValor(dataLocal + (fuso ? ' (' + fuso + ')' : ''))),
            linhaInfo('Há quanto tempo', infoValor(quando(ts))),
            linhaInfo('ISO 8601 (UTC)', infoValor(iso)),
        ];

        const linhasTempo = [];
        if (papel === 'assistant') {
            linhasTempo.push(linhaInfo('Tempo de geração', infoValor(formatarDuracao(geracaoMs))));
            linhasTempo.push(linhaInfo('Tempo até o 1º token', infoValor(formatarDuracao(meta.firstTokenMs))));
            linhasTempo.push(linhaInfo('Velocidade de saída', velocidade
                ? velocidade.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' tokens/s'
                : infoValor('')));
        } else {
            linhasTempo.push(linhaInfo('Tempo até a 1ª resposta', infoValor(formatarDuracao(tempoAteResposta))));
        }
        linhasTempo.push(linhaInfo('Duração do turno', infoValor(formatarDuracao(turnoDuracao))));
        linhasTempo.push(linhaInfo('Início do turno', infoValor(turnoInicio ? dataHoraCompleta(turnoInicio) : '')));
        linhasTempo.push(linhaInfo('Fim do turno', infoValor(turnoFim ? dataHoraCompleta(turnoFim) : '')));

        const linhasTokens = [];
        if (papel === 'assistant' && uso) {
            linhasTokens.push(linhaInfo('Entrada (input)', formatarNumero(uso.inputTokens)));
            linhasTokens.push(linhaInfo('Saída (output)', formatarNumero(uso.outputTokens)));
            linhasTokens.push(linhaInfo('Cache · leitura', formatarNumero(uso.cacheReadTokens)));
            linhasTokens.push(linhaInfo('Cache · escrita', formatarNumero(uso.cacheWriteTokens)));
            if (Number.isFinite(Number(uso.thoughtTokens))) {
                linhasTokens.push(linhaInfo('Raciocínio (thought)', formatarNumero(uso.thoughtTokens)));
            }
            linhasTokens.push(linhaInfo('Total deste passo', formatarNumero(totalUso)));
        }
        if (papel === 'user') {
            linhasTokens.push(linhaInfo('Consumo do turno', totalTurno !== null ? formatarNumero(totalTurno) + ' tokens' : infoValor('')));
        } else if (totalTurno !== null) {
            linhasTokens.push(linhaInfo('Total do turno', formatarNumero(totalTurno) + ' tokens'));
        }
        if (aoVivo && run && run.uso) {
            const parcial = totalDoUso(run.uso);
            if (parcial !== null) {
                linhasTokens.push(linhaInfo('Consumo parcial do turno', formatarNumero(parcial) + ' tokens'));
            }
        }
        linhasTokens.push(linhaInfo('Texto desta mensagem (estimativa)', formatarNumero(tokensTexto) + ' tokens'));
        linhasTokens.push(linhaInfo('Observação', '<span class="info-nota">'
            + (papel === 'user'
                ? 'Mensagens do usuário não têm cobrança separada: o consumo do provedor entra no turno que respondeu.'
                : 'O uso é informado por passo. Entrada, saída e cache são os números exatos devolvidos pelo provedor.')
            + '</span>'));

        const ferramentas = (Array.isArray(meta.toolCalls) ? meta.toolCalls.map((t) => t.nome) : [])
            .concat(Array.isArray(turno?.nomesFerramentas) ? turno.nomesFerramentas : []);
        const ferramentasUnicas = [...new Set(ferramentas.filter(Boolean))];
        const linhasConteudo = [
            linhaInfo('Caracteres', formatarNumero(contagem.caracteres ?? texto.length)),
            linhaInfo('Palavras', formatarNumero(contagem.palavras ?? contarTexto(texto).palavras)),
            linhaInfo('Linhas', formatarNumero(contagem.linhas ?? contarTexto(texto).linhas)),
            linhaInfo('Blocos de código', formatarNumero(contagem.blocosCodigo ?? 0)),
            linhaInfo('Links', formatarNumero(contagem.links ?? 0)),
            linhaInfo('Anexos citados no texto', formatarNumero(contarAnexosNoTexto(texto))),
            linhaInfo('Tipos de conteúdo', infoValor((meta.contentTypes || []).join(', ') || (papel === 'user' ? 'text' : ''))),
            linhaInfo('Ferramentas usadas', infoValor(ferramentasUnicas.join(', '))),
            linhaInfo('Raciocínio (caracteres)', meta.reasoningChars !== undefined ? formatarNumero(meta.reasoningChars) : infoValor('')),
            linhaInfo('Interrompida?', meta.interrupted ? 'Sim' : 'Não'),
        ];

        const sessao = state.sessaoMeta || {};
        const linhasAutor = [
            linhaInfo('Nome', infoValor(autor?.nome)),
            linhaInfo('E-mail / login', infoValor(autor?.email)),
            linhaInfo('ID do usuário', autor?.id !== null && autor?.id !== undefined ? formatarNumero(autor.id) : infoValor('')),
            linhaInfo('Origem', infoValor(origemDoAutor(autor))),
            linhaInfo('IP', infoValor(autor?.ip || (run && run.ip))),
            linhaInfo('Navegador', infoValor(autor?.userAgent || (run && run.userAgent))),
            linhaInfo('Prompt enviado em', infoValor(autor?.enviadoEm ? dataHoraCompleta(autor.enviadoEm) : '')),
            linhaInfo('Usuário da máquina', infoValor(sessao.maquina || (run && run.maquina))),
        ];
        if (!autor) {
            linhasAutor.push(linhaInfo('Observação', '<span class="info-nota">Mensagem anterior ao registro de autor ou criada direto na janela do app. O login do site só passa a ser gravado nos envios feitos por esta página.</span>'));
        }

        const linhasSessao = [
            linhaInfo('ID da sessão', infoValor(meta.sessionId || (run && run.threadId) || sessao.id || state.sessionId)),
            linhaInfo('Título', infoValor(sessao.titulo)),
            linhaInfo('Pasta (cwd)', infoValor(sessao.cwd || (run && run.cwd))),
            linhaInfo('Criada em', infoValor(sessao.criadoEm ? dataHoraCompleta(sessao.criadoEm) : '')),
            linhaInfo('Atualizada em', infoValor(sessao.atualizadoEm ? dataHoraCompleta(sessao.atualizadoEm) : '')),
            linhaInfo('Turnos', sessao.turnos !== undefined ? formatarNumero(sessao.turnos) : infoValor('')),
            linhaInfo('Mensagens', sessao.mensagens !== undefined ? formatarNumero(sessao.mensagens) : infoValor('')),
            linhaInfo('Ferramentas', sessao.ferramentas !== undefined ? formatarNumero(sessao.ferramentas) : infoValor('')),
            linhaInfo('Consumo total da sessão', totalDoUso(sessao.uso) !== null ? formatarNumero(totalDoUso(sessao.uso)) + ' tokens' : infoValor('')),
            linhaInfo('Duração da sessão', infoValor(formatarDuracao(sessao.duracaoMs))),
            linhaInfo('Arquivo da sessão', infoValor(sessao.arquivo)),
        ];

        const motivo = turno?.motivo;
        const motivoTexto = motivo
            ? [motivo.kind, motivo.error?.message].filter(Boolean).join(' · ')
            : '';
        const linhasTecnicas = [
            linhaInfo('ID da mensagem', infoValor(meta.messageId)),
            linhaInfo('Sequência (seq)', meta.seq !== undefined && meta.seq !== null ? formatarNumero(meta.seq) : infoValor('')),
            linhaInfo('Turno / passo', meta.turn !== undefined && meta.turn !== null
                ? 'turno ' + meta.turn + (meta.step !== undefined && meta.step !== null ? ' · passo ' + meta.step : '')
                : infoValor('')),
            linhaInfo('Fonte da mensagem', infoValor(meta.sourceKind || meta.role)),
            linhaInfo('Provedor / modelo', infoValor([meta.provider, meta.model].filter(Boolean).join(' · '))),
            linhaInfo('Status do turno', infoValor(turno?.status)),
            linhaInfo('Motivo do fim', infoValor(motivoTexto)),
            linhaInfo('ID da execução', infoValor(run && run.id)),
            linhaInfo('Status da execução', infoValor(run && run.status)),
            linhaInfo('Início da execução', infoValor(run && run.inicio ? dataHoraCompleta(run.inicio) : '')),
            linhaInfo('Fim da execução', infoValor(run && run.fim ? dataHoraCompleta(run.fim) : '')),
            linhaInfo('Ao vivo agora?', aoVivo ? 'Sim' : 'Não'),
        ];

        const bruto = {
            mensagem: { ...meta, role: papel, time: ts, texto: contagem },
            sessao: state.sessaoMeta || null,
            execucao: resumoRun(run),
        };
        const brutoTexto = JSON.stringify(bruto, null, 2);

        const abertos = new Set(
            [...modal.querySelectorAll('details[data-k][open]')].map((d) => d.dataset.k)
        );

        modal.querySelector('#msgModalSub').textContent =
            (papel === 'user' ? 'Mensagem sua' : 'Mensagem do Codex')
            + ' · ' + (dataLocal || quando(ts))
            + (aoVivo ? ' · ao vivo' : '');

        modal.querySelector('#msgModalBody').innerHTML =
            secaoInfo('Resumo', linhasResumo)
            + secaoInfo('Tempo', linhasTempo)
            + secaoInfo('Tokens', linhasTokens)
            + secaoInfo('Conteúdo', linhasConteudo)
            + secaoInfo('Autor / login', linhasAutor)
            + secaoInfo('Sessão', linhasSessao)
            + secaoInfo('Técnico', linhasTecnicas)
            + '<details class="msg-modal-raw" data-k="raw"><summary>Dados brutos (JSON)</summary>'
            + '<div class="msg-modal-raw-head"><button type="button" data-copiar-json>copiar JSON</button></div>'
            + '<pre>' + escapar(brutoTexto) + '</pre></details>';

        for (const detalhe of modal.querySelectorAll('details[data-k]')) {
            if (abertos.has(detalhe.dataset.k)) detalhe.open = true;
        }
    }

    function abrirModalMensagem(msgEl, meta) {
        const modal = garantirModalInfo();
        let info = meta && typeof meta === 'object' ? { ...meta } : null;
        if (!info && msgEl && msgEl._msgMeta) info = { ...msgEl._msgMeta };
        const bubble = msgEl ? msgEl.querySelector('.bubble') : null;
        const texto = bubble ? textoDaBolha(bubble) : '';
        if (!info) {
            const horario = msgEl ? msgEl.querySelector('time') : null;
            info = {
                role: msgEl && msgEl.classList.contains('user') ? 'user' : 'assistant',
                time: horario && horario.dateTime ? new Date(horario.dateTime).getTime() : Date.now(),
                texto: contarTexto(texto),
            };
        }
        let run = null;
        if (state.runAtual) {
            const mesmoRun = info.runId && state.runAtual.id === info.runId;
            const mesmaSessao = info.sessionId && state.runAtual.threadId === info.sessionId;
            if (mesmoRun || (info.live && (!info.sessionId || mesmaSessao))) run = state.runAtual;
        }
        modalState.msgEl = msgEl || null;
        modalState.meta = info;
        modalState.run = run;
        modalState.foco = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('msg-modal-aberto');
        renderModalInfo();
        const fechar = modal.querySelector('#msgModalClose');
        if (fechar) fechar.focus();
        clearInterval(modalState.timer);
        modalState.timer = null;
        const runAtivo = run && (run.status === 'running' || run.status === 'queued');
        if ((info.live && !run) || runAtivo) {
            modalState.timer = setInterval(() => {
                if (!modalState.el || modalState.el.hidden) return;
                renderModalInfo();
            }, 1000);
        }
    }

    /** Texto limpo do balão (sem os botões de copiar/ouvir). */
    function textoDaBolha(bubble) {
        if (!bubble) return '';
        const clone = bubble.cloneNode(true);
        clone.querySelectorAll('.copy-code, .msg-copy, .msg-info, .msg-listen').forEach((el) => el.remove());
        return (clone.innerText || clone.textContent || '').trim();
    }

    /** Rodapé do balão: hora + copiar + informações (+ ouvir, nas respostas). */
    function rodapeMensagem(ts, bubble, podeOuvir, meta, msgEl) {
        const rodape = document.createElement('div');
        rodape.className = 'msg-foot';

        const hora = document.createElement('time');
        hora.className = 'msg-time';
        const data = new Date(Number(ts));
        if (!isNaN(data.getTime())) {
            hora.dateTime = data.toISOString();
            hora.textContent = dataHora(ts);
            hora.title = data.toLocaleString('pt-BR');
        } else {
            hora.hidden = true;
        }
        rodape.appendChild(hora);

        const copiar = document.createElement('button');
        copiar.type = 'button';
        copiar.className = 'msg-copy';
        copiar.textContent = 'copiar';
        copiar.title = 'Copiar o texto deste balão';
        rodape.appendChild(copiar);

        const informacoes = document.createElement('button');
        informacoes.type = 'button';
        informacoes.className = 'msg-info';
        informacoes.textContent = 'i';
        informacoes.title = 'Informações desta mensagem (tokens, tempo, autor…)';
        informacoes.setAttribute('aria-label', 'Informações desta mensagem');
        informacoes.addEventListener('click', (ev) => {
            ev.stopPropagation();
            abrirModalMensagem(msgEl, meta);
        });
        rodape.appendChild(informacoes);

        if (podeOuvir) {
            const ouvir = document.createElement('button');
            ouvir.type = 'button';
            ouvir.className = 'msg-listen';
            ouvir.textContent = 'ouvir';
            ouvir.title = 'Ouvir este balão de novo';
            ouvir.addEventListener('click', () => falarTexto(textoDaBolha(bubble)));
            rodape.appendChild(ouvir);
        }
        return rodape;
    }

    function bolha(role, texto, ts, meta) {
        const div = document.createElement('div');
        div.className = 'msg ' + (role === 'user' ? 'user' : 'assistant');
        div._msgMeta = meta || null;
        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.innerHTML = markdown(texto);
        div.appendChild(bubble);
        div.appendChild(rodapeMensagem(ts, bubble, role !== 'user', meta, div));
        return div;
    }

    function eventoEl(ev, inicio) {
        const div = document.createElement('div');
        if (ev.kind === 'agent') {
            div.className = 'msg assistant';
            div._msgMeta = ev.meta || null;
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            bubble.innerHTML = markdown(ev.text || '') + (ev.done ? '' : ' <span class="muted">...</span>');
            div.appendChild(bubble);
            const ts = typeof ev.time === 'number' ? ev.time : inicio;
            div.appendChild(rodapeMensagem(ts, bubble, true, ev.meta || null, div));
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

    function renderEventos(live, eventos, inicio, view) {
        const chave = JSON.stringify(eventos);
        if (chave === view.eventsKey) return;
        view.eventsKey = chave;
        if (state.atual === view.chave) state.eventsKey = chave;
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
                live.appendChild(eventoEl(ev, inicio));
            } else {
                fila.push(ev);
            }
        }
        despejar();
        if (state.atual === view.chave) rolarFim();
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

    async function loadSessions(silencioso) {
        const sequencia = ++state.carregandoSessoes;
        try {
            const dados = await api('sessions');
            if (sequencia !== state.carregandoSessoes) return;
            state.sessions = dados.sessions || [];
            setBridge(true);
        } catch (erro) {
            if (sequencia !== state.carregandoSessoes) return;
            if (silencioso) return; // mantem a lista que ja esta na tela
            state.sessions = [];
            setBridge(false, erro.message);
            listaEl.innerHTML = '<p class="muted">Nao consegui carregar as sessoes.</p>';
            return;
        }
        // Nao redesenha a sidebar a cada 20 s se nada mudou (evita piscar e
        // roubar o clique no meio).
        const assinatura = state.sessions.map((s) => [s.id, s.nome, s.updatedAt, s.runId || '']).join('|');
        const mudou = assinatura !== state.assinaturaSessoes;
        state.assinaturaSessoes = assinatura;
        if (mudou || !silencioso) {
            const rolagem = listaEl.scrollTop;
            renderSessoes();
            listaEl.scrollTop = rolagem;
        }

        // Se a conversa aberta foi renomeada/atualizada no app do DSH, reflete.
        const aberta = state.atual ? state.views.get(state.atual) : null;
        if (aberta && aberta.id) {
            const meta = state.sessions.find((s) => s.id === aberta.id);
            if (meta && meta.nome && meta.nome !== aberta.nome) {
                aberta.nome = meta.nome;
                if (state.atual === aberta.chave) el('chatTitle').textContent = aberta.nome;
            }
        }
    }

    /** Adota o id real da sessao quando o DSH responde com o thread da run. */
    function adotarThread(view, threadId) {
        const novo = String(threadId || '').trim();
        if (!view || !novo || view.id === novo) return;
        const antiga = view.chave;
        const existente = state.views.get(novo);
        if (existente && existente !== view) {
            // Ja havia uma tela para essa sessao (outra aba/reload). A tela com
            // o turno vivo e a que manda; a antiga e descartada.
            if (state.atual === existente.chave && existente !== view) {
                existente.nodes = null;
                mensagensEl.innerHTML = '';
            }
            state.views.delete(novo);
        }
        view.id = novo;
        view.chave = novo;
        state.views.delete(antiga);
        state.views.set(novo, view);
        if (state.atual === antiga) {
            state.atual = novo;
            definirSessao(novo);
            sincronizarEspelhos(view);
        }
    }

    /** Anexa um turno que ja esta rodando na sessao (outra aba, F5 ou DSH). */
    function anexarRunAtiva(view, runAtiva) {
        if (!view || view.busy || !runAtiva || !runAtiva.id) return;
        if (runAtiva.status !== 'running' && runAtiva.status !== 'queued') return;
        view.busy = true;
        view.runId = runAtiva.id;
        // Turno adotado de outra aba/reload: ao terminar, recarrega o historico
        // inteiro para aparecer tambem o prompt que esta aba nao viu.
        view.forcarRecarga = true;
        const live = document.createElement('div');
        live.className = 'live';
        containerDaView(view).appendChild(live);
        if (state.atual === view.chave) {
            sincronizarEspelhos(view);
            salvarLocal(CHAVE_RUN, runAtiva.id);
        }
        acompanhar(live, runAtiva.id, view);
    }

    async function carregarView(view, nome) {
        if (!view || !view.id) return;
        const seq = ++view.cargaSeq;
        view.carregando = true;
        // So mostra "Carregando" quando nao ha nada na tela; atualizar por tras
        // nao pode apagar a conversa que o usuario esta lendo.
        const mostrarCarregando = state.atual === view.chave
            && !mensagensEl.querySelector('.msg, .live');
        if (mostrarCarregando) mensagensEl.innerHTML = '<p class="muted">Carregando...</p>';
        try {
            const dados = await api('session', { id: view.id });
            if (seq !== view.cargaSeq) return;
            const sessao = dados.session || {};
            view.meta = sessao.meta || view.meta;
            view.nome = nome || view.nome || (view.meta && view.meta.titulo) || '';
            // O usuario pode ter mandado um prompt enquanto o historico vinha:
            // nesse caso nao apaga o turno vivo.
            if (view.busy) { view.precisaAtualizar = true; return; }
            limparContainer(view);
            const container = containerDaView(view);
            const mensagens = sessao.messages || [];
            if (!mensagens.length) {
                container.innerHTML = '<p class="muted">Conversa sem mensagens de texto.</p>';
            } else {
                for (const m of mensagens) container.appendChild(bolha(m.role, m.text, m.time, m.meta));
                view.temMensagens = true;
            }
            if (state.atual === view.chave) {
                el('chatTitle').textContent = view.nome || String(view.id).replace(/^dsh:/, '').slice(0, 8);
                el('chatMeta').textContent = sessao.cwd || '';
                mensagensEl.scrollTop = mensagensEl.scrollHeight;
                campo.focus();
            }
            view.precisaAtualizar = false;
            anexarRunAtiva(view, sessao.runAtiva);
        } catch (erro) {
            if (seq === view.cargaSeq && state.atual === view.chave) {
                mensagensEl.innerHTML = '<p class="erro">' + escapar(erro.message) + '</p>';
            }
        } finally {
            if (seq === view.cargaSeq) view.carregando = false;
        }
    }

    async function abrirSessao(id, nome) {
        if (!id) return;
        if (state.vozMaosLivres) desligarMaosLivres();
        const chave = String(id);
        if (state.atual === chave) {
            const aberta = state.views.get(chave);
            if (aberta) {
                if (nome) aberta.nome = nome;
                el('chatTitle').textContent = aberta.nome || String(chave).replace(/^dsh:/, '').slice(0, 8);
                if (!aberta.busy) await carregarView(aberta, nome);
            }
            alternarMenu(false);
            return;
        }
        if (state.vozGravando) pararGravacao(true);
        pararFala();
        state.vozInteracao = false;

        const anterior = state.atual ? state.views.get(state.atual) : null;
        if (anterior) estacionarTela(anterior);

        const view = garantirView(chave, chave);
        view.id = chave;
        if (nome) view.nome = nome;
        state.atual = chave;
        definirSessao(chave);
        sincronizarEspelhos(view);
        carregarRascunho(view);
        alternarMenu(false);
        el('chatTitle').textContent = view.nome || String(chave).replace(/^dsh:/, '').slice(0, 8);
        el('chatMeta').textContent = (view.meta && view.meta.cwd) || '';
        mostrarTela(view);
        // Se a conversa ja estava carregada, mostra na hora e atualiza por tras.
        if (view.busy) {
            renderFila();
            return;
        }
        await carregarView(view, nome);
    }

    function novaConversa() {
        if (state.vozMaosLivres) desligarMaosLivres();
        if (state.vozGravando) pararGravacao(true);
        pararFala();
        state.vozInteracao = false;

        const anterior = state.atual ? state.views.get(state.atual) : null;
        if (anterior) estacionarTela(anterior);
        // Rascunho vazio nao precisa ficar ocupando memoria.
        if (anterior && !anterior.busy && !anterior.id && !anterior.runId
            && !anterior.temMensagens && !(anterior.fila && anterior.fila.length)) {
            state.views.delete(anterior.chave);
        }

        const chave = novaLinhaDeRascunho();
        const view = criarView(chave, null);
        state.views.set(chave, view);
        state.atual = chave;
        definirSessao(null);
        sincronizarEspelhos(view);
        carregarRascunho(view);
        alternarMenu(false);
        el('chatTitle').textContent = 'Nova conversa';
        el('chatMeta').textContent = '';
        mensagensEl.innerHTML = '';
        campo.focus();
    }

    async function recarregarHistorico(view) {
        const alvo = view || (state.atual ? state.views.get(state.atual) : null);
        if (!alvo || !alvo.id || alvo.busy) return;
        await carregarView(alvo, alvo.nome);
    }

    /** Atualiza a bolha ao vivo; devolve true quando o turno terminou. */
    function aplicarRun(live, run, view) {
        view.run = run;
        if (state.atual === view.chave) state.runAtual = run;
        if (state.atual === view.chave && state.vozInteracao) falarStreaming(run);
        renderEventos(live, run.eventos || [], run.inicio, view);
        return run.status !== 'running' && run.status !== 'queued';
    }

    function anexarRetry(live, view) {
        if (!view.ultimo) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'retry-btn';
        btn.textContent = 'Tentar de novo';
        btn.addEventListener('click', () => {
            btn.remove();
            if (!view.busy && view.ultimo) despachar(view, view.ultimo);
        });
        live.appendChild(btn);
    }

    async function finalizarRun(live, run, view) {
        cancelarAck();
        view.busy = false;
        view.runId = null;
        view.run = run;
        if (!live.querySelector('.msg.assistant')) view.precisaAtualizar = true;

        if (run.status === 'error' && !/cancelad/i.test(String(run.erro || ''))) {
            if (run.erro && !(run.eventos || []).some((e) => e.kind === 'error')) {
                live.appendChild(eventoEl({ kind: 'error', text: run.erro }));
            }
            anexarRetry(live, view);
        }

        // Conversa nova: o DSH devolve o id so no fim do primeiro turno. Adota
        // o id nesta view e joga a run para a conversa certa (nao para a que
        // o usuario estiver olhando na hora).
        if (!view.id && run.threadId) {
            adotarThread(view, run.threadId);
            await loadSessions();
            const meta = state.sessions.find((s) => s.id === view.id);
            if (meta) view.nome = meta.nome;
        } else if (view.id) {
            const meta = state.sessions.find((s) => s.id === view.id);
            if (meta) meta.updatedAt = new Date().toISOString();
        }

        if (state.atual === view.chave) {
            state.runAtual = run;
            state.runId = null;
            botaoEnviar(false);
            salvarLocal(CHAVE_RUN, '');
            el('chatTitle').textContent = view.nome
                || String(view.id || '').replace(/^dsh:/, '').slice(0, 8)
                || 'Nova conversa';
            // Se a resposta nao veio no stream (ou o turno foi adotado de outra
            // aba), busca o historico gravado, que inclui o prompt e a resposta.
            if (view.forcarRecarga || !live.querySelector('.msg.assistant')) {
                view.forcarRecarga = false;
                await recarregarHistorico(view);
            }
            // Atualiza os totais da sessao para o modal de informacoes.
            if (view.id) {
                try {
                    const dados = await api('session', { id: view.id });
                    if (state.atual === view.chave) {
                        view.meta = (dados.session || {}).meta || view.meta;
                        state.sessaoMeta = view.meta;
                    }
                } catch (erro) { /* mantem os dados que ja estao na tela */ }
            }
            rolarFim();
            // Fecha a fala do turno: o que ja foi falado no streaming nao repete.
            if (!view.fila.length) finalizarFalaRun(run, live);
        } else {
            view.precisaAtualizar = true;
        }
        soltarFila(view);
    }

    /** Plano B: polling no /run quando o SSE nao esta disponivel. */
    function seguirPorPolling(live, runId, terminar, view) {
        let falhas = 0;
        const passo = async () => {
            try {
                const dados = await api('run', { id: runId });
                falhas = 0;
                const run = dados.run || {};
                if (!aplicarRun(live, run, view)) {
                    view.timer = setTimeout(passo, 1300);
                    return;
                }
                terminar(run);
            } catch (erro) {
                falhas += 1;
                const sumiu = /404|nao encontrada|não encontrada/i.test(erro.message || '');
                if (!sumiu && falhas <= 4) {
                    view.timer = setTimeout(passo, 1500);
                    return;
                }
                view.timer = null;
                view.busy = false;
                if (state.atual === view.chave) {
                    state.busy = false;
                    botaoEnviar(false);
                }
                if (sumiu) {
                    // A ponte reiniciou no meio do turno: busca o que ficou gravado.
                    await recarregarHistorico(view);
                    rolarFim();
                    soltarFila(view);
                    return;
                }
                anexarRetry(live, view);
                live.appendChild(eventoEl({ kind: 'error', text: erro.message }));
                soltarFila(view);
            }
        };
        passo();
    }

    function acompanhar(live, runId, view) {
        const id = runId || view.runId;
        clearTimeout(view.timer);
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
            return finalizarRun(live, run, view);
        };

        const irParaPolling = () => {
            if (finalizado || !fonte) return;
            encerrarStream();
            seguirPorPolling(live, id, terminar, view);
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
            seguirPorPolling(live, id, terminar, view);
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
            if (aplicarRun(live, run, view)) terminar(run);
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

    function metaUsuarioLocal(prompt, ts) {
        const texto = String(prompt || '');
        const usuario = boot.user || {};
        return {
            versao: 1,
            role: 'user',
            live: true,
            time: ts,
            sourceKind: 'user',
            author: {
                nome: usuario.name || '',
                email: usuario.email || '',
                fonte: 'site',
            },
            texto: { ...contarTexto(texto), tokensEstimados: estimarTokens(texto) },
            turno: null,
            tempoAteRespostaMs: null,
            runId: null,
        };
    }

    function bolhaUsuario(prompt, anexos, ts, meta) {
        const div = bolha('user', prompt || '(anexos)', ts, meta);
        if (anexos.length) {
            const chips = anexosBolha(anexos);
            const rodape = div.querySelector('.msg-foot');
            if (rodape) div.insertBefore(chips, rodape);
            else div.appendChild(chips);
        }
        return div;
    }

    /** Envia um item (prompt + anexos) para a ponte e comeca a acompanhar. */
    async function despachar(view, item) {
        if (!view.id && !view.temMensagens) limparContainer(view);
        const container = containerDaView(view);
        const ts = item.ts || Date.now();
        let bolhaDoUsuario = item.no;
        if (!bolhaDoUsuario || !bolhaDoUsuario.parentNode) {
            const meta = item.meta
                ? { ...item.meta, time: ts, runId: null, live: true }
                : metaUsuarioLocal(item.prompt, ts);
            bolhaDoUsuario = bolhaUsuario(item.prompt, item.anexos, ts, meta);
            container.appendChild(bolhaDoUsuario);
            view.temMensagens = true;
            item.no = bolhaDoUsuario;
        }
        view.ultimo = { prompt: item.prompt, anexos: item.anexos, ts, meta: bolhaDoUsuario._msgMeta };

        const imagens = item.anexos.filter((a) => a.isImage).map((a) => a.path);
        const legenda = item.anexos.length
            ? item.prompt + '\n\n[anexos]\n' + item.anexos.map((a) => '- ' + a.name + ': ' + a.path).join('\n')
            : item.prompt;

        const live = document.createElement('div');
        live.className = 'live';
        container.appendChild(live);
        if (state.atual === view.chave) {
            mensagensEl.scrollTop = mensagensEl.scrollHeight;
            state.busy = true;
            state.eventsKey = '';
            botaoEnviar(true);
        }
        view.busy = true;
        view.eventsKey = '';
        if (item.voz && state.vozInteracao) {
            vozEstado('pensando');
            agendarAck(view);
        }

        try {
            const dados = await api('send', {}, {
                method: 'POST',
                body: { prompt: legenda.trim(), threadId: view.id, images: imagens },
            });
            view.runId = dados.runId;
            if (bolhaDoUsuario && bolhaDoUsuario._msgMeta) {
                bolhaDoUsuario._msgMeta.runId = dados.runId;
                bolhaDoUsuario._msgMeta.live = true;
                bolhaDoUsuario._msgMeta.time = bolhaDoUsuario._msgMeta.time || ts;
            }
            if (state.atual === view.chave) {
                state.runId = dados.runId;
                salvarLocal(CHAVE_RUN, dados.runId);
            }
            acompanhar(live, dados.runId, view);
        } catch (erro) {
            // Outra aba ja esta rodando esta conversa: adota o turno e deixa o
            // prompt na fila dessa conversa, em vez de perder o texto.
            if (erro.status === 409 && erro.dados && erro.dados.runId) {
                cancelarAck();
                if (bolhaDoUsuario && bolhaDoUsuario.parentNode) bolhaDoUsuario.remove();
                if (live.parentNode) live.remove();
                item.no = null;
                item.meta = null;
                view.fila.unshift(item);
                view.busy = false;
                view.runId = null;
                if (state.atual === view.chave) {
                    state.busy = false;
                    state.runId = null;
                    botaoEnviar(false);
                    renderFila();
                }
                anexarRunAtiva(view, {
                    id: erro.dados.runId,
                    status: erro.dados.status || 'running',
                });
                return;
            }
            cancelarAck();
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            live.appendChild(eventoEl({ kind: 'error', text: erro.message }));
            view.busy = false;
            if (state.atual === view.chave) {
                state.busy = false;
                botaoEnviar(false);
            }
            soltarFila(view);
        }
    }

    /** Se o turno atual esta rodando, empilha o prompt; senao envia direto. */
    function enviar(opcoes) {
        const prompt = campo.value.trim();
        const anexos = state.attachments.slice();
        if (!prompt && !anexos.length) return;
        const view = viewAtual();

        // Interrompeu a fala: cancela o turno atual para a nova fala valer.
        if (opcoes && opcoes.bargeIn && view.busy) parar();
        if (state.vozTocando || state.vozFilaRodando || state.vozFilaFala.length) pararFala();
        campo.value = '';
        autoResize();
        state.attachments = [];
        view.rascunho = '';
        view.attachments = [];
        renderAnexos();

        const item = { prompt, anexos, ts: Date.now(), voz: Boolean(opcoes && opcoes.voz) };
        if (view.busy) {
            // Vai para a lista de espera da conversa aberta, com editar e apagar.
            view.fila.push(item);
            renderFila();
            rolarFim();
            return;
        }
        despachar(view, item);
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
        const fila = viewAtual().fila;
        const item = fila[indice];
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
        const fila = viewAtual().fila;
        const item = fila[indice];
        if (item && item.no && item.no.parentNode) item.no.remove();
        fila.splice(indice, 1);
        renderFila();
    }

    /** Roda o proximo prompt da fila da conversa, se houver. */
    function soltarFila(view) {
        const alvo = view || viewAtual();
        if (alvo.busy || !alvo.fila.length) return;
        const proximo = alvo.fila.shift();
        if (state.atual === alvo.chave) renderFila();
        despachar(alvo, proximo);
    }

    async function parar() {
        // Para apenas o turno atual; os prompts da fila continuam na fila.
        pararFala();
        const view = viewAtual();
        if (!view.runId) return;
        try {
            await api('cancel', {}, { method: 'POST', body: { runId: view.runId } });
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
            await abrirSessao(sessaoSalva, meta ? meta.nome : '');
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
            let view = viewAtual();
            // Se o turno pertence a outra sessao (o usuario trocou de conversa
            // antes do reload), reabre a sessao certa antes de anexar o turno.
            if (run.threadId) {
                if (!view.id) {
                    adotarThread(view, run.threadId);
                } else if (view.id !== run.threadId) {
                    await abrirSessao(run.threadId, '');
                }
                view = viewAtual();
            }
            if (view.busy && view.runId === runSalvo) return;
            view.runId = runSalvo;
            view.busy = true;
            const live = document.createElement('div');
            live.className = 'live';
            containerDaView(view).appendChild(live);
            if (state.atual === view.chave) {
                state.runId = runSalvo;
                state.busy = true;
                botaoEnviar(true);
                salvarLocal(CHAVE_RUN, runSalvo);
            }
            acompanhar(live, runSalvo, view);
        } catch (erro) {
            salvarLocal(CHAVE_RUN, '');
        }
    }

    // ---------------------------------------------------------------- voz

    let vozStatusTimer = null;

    /** Aviso curto acima do campo; ms=0 deixa fixo ate outro aviso. */
    function vozStatus(texto, ms = 0, falando = false) {
        if (!vozStatusEl) return;
        clearTimeout(vozStatusTimer);
        const temTexto = Boolean(texto);
        vozStatusEl.hidden = !temTexto && state.vozEstado === 'off';
        if (vozStatusTexto) vozStatusTexto.textContent = texto || '';
        if (vozStopBtn) vozStopBtn.hidden = !(temTexto && falando);
        if (vozMuteBtn) vozMuteBtn.hidden = !(temTexto && falando);
        if (!temTexto) return;
        if (ms > 0) {
            vozStatusTimer = setTimeout(() => {
                if (vozStatusTexto && vozStatusTexto.textContent === texto) vozStatus('');
            }, ms);
        }
    }

    /** Troca o desenho animado: idle, ouvindo, pensando ou falando. */
    function vozEstado(estado) {
        state.vozEstado = estado || 'off';
        if (vozOrb) {
            vozOrb.hidden = state.vozEstado === 'off';
            vozOrb.dataset.estado = state.vozEstado;
        }
        if (vozStatusEl) {
            if (state.vozEstado === 'off') {
                if (!vozStatusTexto || !vozStatusTexto.textContent) vozStatusEl.hidden = true;
            } else {
                vozStatusEl.hidden = false;
            }
        }
    }

    function base64ParaBlob(b64, mime) {
        const bin = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i += 1) bytes[i] = bin.charCodeAt(i);
        return new Blob([bytes], { type: mime || 'audio/wav' });
    }

    /** Tira codigo, markdown, link e caminho de arquivo do texto que vira voz. */
    function textoParaFala(texto) {
        let limpo = String(texto || '')
            .replace(/```[\s\S]*?```/g, ' ')
            .replace(/`([^`]*)`/g, '$1')
            .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
            .replace(/https?:\/\/\S+/gi, ' ')
            .replace(/[A-Za-z]:\\[^\s]+/g, ' ')
            .replace(/[*_#>|]/g, ' ')
            .replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}]/gu, ' ')
            .replace(/\s+/g, ' ')
            .trim();
        if (limpo.length > 1400) {
            const ponto = limpo.lastIndexOf('.', 1400);
            limpo = limpo.slice(0, ponto > 700 ? ponto + 1 : 1400).trim();
        }
        return limpo;
    }

    /**
     * Junta frases em blocos para o TTS. O primeiro bloco e curto de proposito:
     * o edge comeca a responder mais rapido e o resto vem no prefetch.
     */
    function dividirFala(texto) {
        const frases = texto.match(/[^.!?…]+[.!?…]+|[^.!?…]+$/g) || [texto];
        const blocos = [];
        let atual = '';
        for (let frase of frases) {
            frase = frase.trim();
            if (!frase) continue;
            let limite = blocos.length === 0 ? 110 : 260;
            if (atual && (atual.length + frase.length + 1) > limite) {
                blocos.push(atual);
                atual = '';
                limite = 260;
            }
            atual = atual ? atual + ' ' + frase : frase;
            while (atual.length > limite) {
                const corte = atual.lastIndexOf(' ', limite - 20);
                const pos = corte > limite * 0.5 ? corte : limite;
                blocos.push(atual.slice(0, pos).trim());
                atual = atual.slice(pos).trim();
            }
        }
        if (atual) blocos.push(atual);
        return blocos.filter(Boolean).slice(0, 12);
    }

    function pararFala() {
        state.vozSeq += 1;
        state.vozTocando = false;
        state.vozFilaFala = [];
        state.vozFilaRodando = false;
        if (state.vozMaosLivres) state.vozHFIgnorarAte = Date.now() + 250;
        if (state.vozAudio) {
            try { state.vozAudio.pause(); } catch (e) { /* ja parado */ }
            state.vozAudio = null;
        }
        if (state.vozResolve) {
            const resolver = state.vozResolve;
            state.vozResolve = null;
            resolver();
        }
        vozStatus('');
        vozEstado(state.vozMaosLivres ? 'idle' : 'off');
    }

    async function gerarAudioFala(texto) {
        try {
            const dados = await api('falar', {}, {
                method: 'POST',
                body: { texto, voz: 'pf_dora', velocidade: 1.0 },
            });
            if (!dados || !dados.ok || !dados.audio) return null;
            return base64ParaBlob(dados.audio, dados.mime || 'audio/wav');
        } catch (erro) {
            return null;
        }
    }

    /**
     * O iPhone/Safari só libera audio depois de um toque. Tocamos um WAV
     * silencioso no clique do microfone e reaproveitamos o mesmo elemento
     * para as respostas (inclusive as que chegam depois, por fetch).
     */
    function desbloquearAudio() {
        if (!state.vozAudioEl) {
            state.vozAudioEl = new Audio();
            state.vozAudioEl.preload = 'auto';
            state.vozAudioEl.setAttribute('playsinline', '');
        }
        const audio = state.vozAudioEl;
        if (audio.dataset.desbloqueado === '1') return;
        audio.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=';
        audio.play().then(() => {
            audio.pause();
            audio.currentTime = 0;
            audio.dataset.desbloqueado = '1';
        }).catch(() => { /* tenta de novo no proximo toque */ });
    }

    function tocarBlobFala(blob, seq) {
        return new Promise((resolve) => {
            if (!blob || seq !== state.vozSeq) { resolve(); return; }
            const url = URL.createObjectURL(blob);
            const audio = state.vozAudioEl || new Audio();
            state.vozAudioEl = audio;
            let terminou = false;
            const fim = () => {
                if (terminou) return;
                terminou = true;
                audio.onended = null;
                audio.onerror = null;
                URL.revokeObjectURL(url);
                if (state.vozAudio === audio) state.vozAudio = null;
                if (state.vozResolve === fim) state.vozResolve = null;
                resolve();
            };
            state.vozAudio = audio;
            state.vozResolve = fim;
            audio.onended = fim;
            audio.onerror = fim;
            audio.src = url;
            audio.play().catch(() => {
                vozStatus('O navegador bloqueou o áudio. Toque na tela e tente de novo.', 5000);
                fim();
            });
        });
    }

    /** Poe um texto na fila de fala (nao corta o que ja esta tocando). */
    function enfileirarFala(texto) {
        const limpo = textoParaFala(texto);
        if (!limpo) return;
        const blocos = dividirFala(limpo);
        if (!blocos.length) return;
        for (const bloco of blocos) state.vozFilaFala.push({ texto: bloco, promessa: null });
        if (!state.vozFilaRodando) rodarFilaFala();
    }

    /** Fala a fila em ordem, pedindo o proximo bloco enquanto o atual toca. */
    async function rodarFilaFala() {
        if (state.vozFilaRodando) return;
        state.vozFilaRodando = true;
        const seq = state.vozSeq;
        let tocou = false;
        try {
            while (seq === state.vozSeq) {
                const item = state.vozFilaFala.shift();
                if (!item) break;
                if (!item.promessa) item.promessa = gerarAudioFala(item.texto);
                const proximo = state.vozFilaFala[0];
                if (proximo && !proximo.promessa) proximo.promessa = gerarAudioFala(proximo.texto);
                vozEstado('falando');
                vozStatus('Preparando a voz…', 0, true);
                const blob = await item.promessa;
                if (seq !== state.vozSeq) break;
                if (!blob) continue;
                tocou = true;
                state.vozTocando = true;
                if (state.vozMaosLivres) state.vozHFIgnorarAte = Date.now() + 700;
                vozStatus('Falando…', 0, true);
                await tocarBlobFala(blob, seq);
                if (seq === state.vozSeq) state.vozTocando = false;
            }
        } finally {
            if (seq === state.vozSeq) {
                state.vozFilaRodando = false;
                state.vozTocando = false;
                if (state.vozFilaFala.length) {
                    rodarFilaFala();
                } else {
                    vozEstado(state.vozMaosLivres ? 'idle' : 'off');
                    if (state.vozMaosLivres) {
                        vozStatus('Mãos livres ligado — pode falar.', 0);
                    } else {
                        vozStatus(tocou ? '' : 'Não consegui gerar a voz.', tocou ? 0 : 5000);
                    }
                }
            }
        }
    }

    /** Le um texto em voz alta agora (corta o que estiver tocando). */
    function falarTexto(texto) {
        pararFala();
        enfileirarFala(texto);
    }

    function textoAgenteRun(run) {
        const eventos = (run && run.eventos) || [];
        for (let i = eventos.length - 1; i >= 0; i -= 1) {
            const ev = eventos[i];
            if (ev && ev.kind === 'agent' && ev.text) return String(ev.text);
        }
        return '';
    }

    /** Separa o que ja terminou em frase do resto que ainda esta chegando. */
    function separarFrasesCompletas(texto) {
        const achado = texto.match(/^([\s\S]*[.!?…])(\s+|$)/);
        if (!achado) return { prontas: '', resto: texto };
        return { prontas: achado[1], resto: texto.slice(achado[0].length) };
    }

    function cancelarAck() {
        clearTimeout(state.vozAckTimer);
        state.vozAckTimer = null;
    }

    /** Se o turno demorar, fala uma coisinha para nao parecer travado. */
    function agendarAck(view) {
        cancelarAck();
        const frases = ['Deixa eu ver isso.', 'Já vou ver.', 'Um instante.'];
        const frase = frases[Math.floor(Math.random() * frases.length)];
        state.vozAckTimer = setTimeout(() => {
            state.vozAckTimer = null;
            if (!state.vozInteracao || state.atual !== view.chave || !view.busy) return;
            if (state.vozHFGravando || state.vozHFEnviando) return;
            if (textoAgenteRun(view.run)) return;
            enfileirarFala(frase);
        }, 1400);
    }

    /** Vai falando as frases que o agente ja mandou, sem esperar o turno fechar. */
    function falarStreaming(run) {
        if (!state.vozInteracao || !run || state.vozHFGravando) return;
        const texto = textoAgenteRun(run);
        if (!texto) return;
        cancelarAck();
        const id = String(run.id || run.threadId || 'run');
        if (state.vozFalaRunId !== id) {
            state.vozFalaRunId = id;
            state.vozFalaBase = 0;
            state.vozFalaBuffer = '';
        }
        if (texto.length <= state.vozFalaBase) return;
        state.vozFalaBuffer += texto.slice(state.vozFalaBase);
        state.vozFalaBase = texto.length;
        const partes = separarFrasesCompletas(state.vozFalaBuffer);
        state.vozFalaBuffer = partes.resto;
        if (partes.prontas.trim()) enfileirarFala(partes.prontas);
    }

    /**
     * Fecha a fala do turno: enfileira o resto que nao virou frase completa.
     * Se o texto ja foi todo falado no streaming, nao repete nada.
     */
    function finalizarFalaRun(run, live) {
        if (!state.vozInteracao) return;
        if (state.vozHFGravando) {
            setTimeout(() => finalizarFalaRun(run, live), 400);
            return;
        }
        cancelarAck();
        if (run && run.status === 'error') {
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            return;
        }
        let texto = textoAgenteRun(run);
        if (!texto && live) {
            const bolha = live.querySelector('.msg.assistant .bubble');
            if (bolha) texto = bolha.innerText || '';
        }
        texto = String(texto || '').trim();
        if (!texto) {
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            return;
        }
        const id = String((run && (run.id || run.threadId)) || state.vozFalaRunId || 'run');
        if (state.vozFalaRunId !== id) {
            state.vozFalaRunId = id;
            state.vozFalaBase = 0;
            state.vozFalaBuffer = '';
        }
        if (texto.length > state.vozFalaBase) {
            state.vozFalaBuffer += texto.slice(state.vozFalaBase);
        }
        state.vozFalaBase = texto.length;
        const resto = state.vozFalaBuffer.trim();
        state.vozFalaBuffer = '';
        state.vozFalaRunId = null;
        if (resto) enfileirarFala(resto);
    }

    /** Le a ultima resposta do agente (nao le erro nem comando). */
    function falarResposta(live, run) {
        finalizarFalaRun(run, live);
    }

    function tipoAudioGravacao() {
        if (typeof MediaRecorder === 'undefined' || !MediaRecorder.isTypeSupported) return '';
        const tipos = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/mp4', 'audio/webm'];
        for (const tipo of tipos) {
            try { if (MediaRecorder.isTypeSupported(tipo)) return tipo; } catch (e) { /* tenta o proximo */ }
        }
        return '';
    }

    async function iniciarGravacao() {
        if (state.vozMaosLivres) return;
        if (state.vozIniciando || state.vozGravando || state.vozTranscrevendo) return;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
            vozStatus('Este navegador não deixa gravar áudio.', 5000);
            return;
        }
        state.vozIniciando = true;
        desbloquearAudio();
        pararFala();
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            });
            const tipo = tipoAudioGravacao();
            const rec = tipo ? new MediaRecorder(stream, { mimeType: tipo }) : new MediaRecorder(stream);
            state.vozStream = stream;
            state.vozRec = rec;
            state.vozChunks = [];
            rec.ondataavailable = (ev) => { if (ev.data && ev.data.size) state.vozChunks.push(ev.data); };
            rec.onstop = () => finalizarGravacao(rec);
            rec.start(250);
            state.vozGravando = true;
            vozEstado('ouvindo');
            if (micBtn) {
                micBtn.classList.add('recording');
                micBtn.setAttribute('aria-pressed', 'true');
                micBtn.title = 'Parar e transcrever';
            }
            vozStatus('Gravando… toque no microfone para parar.');
            clearTimeout(state.vozTimer);
            state.vozTimer = setTimeout(() => { if (state.vozGravando) pararGravacao(); }, 60000);
        } catch (erro) {
            if (state.vozStream) {
                state.vozStream.getTracks().forEach((t) => t.stop());
                state.vozStream = null;
            }
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            vozStatus('Não consegui usar o microfone: ' + erro.message, 7000);
        } finally {
            state.vozIniciando = false;
        }
    }

    function pararGravacao(cancelar) {
        state.vozCancelar = Boolean(cancelar);
        clearTimeout(state.vozTimer);
        state.vozTimer = null;
        state.vozGravando = false;
        if (micBtn) {
            micBtn.classList.remove('recording');
            micBtn.setAttribute('aria-pressed', 'false');
            micBtn.title = 'Falar: grava, transcreve e envia';
        }
        const rec = state.vozRec;
        if (rec && rec.state !== 'inactive') {
            try { rec.stop(); } catch (e) { /* ja parou */ }
        }
        if (state.vozStream) {
            state.vozStream.getTracks().forEach((t) => t.stop());
            state.vozStream = null;
        }
        if (!cancelar) vozEstado('pensando');
    }

    async function finalizarGravacao(rec) {
        const cancelar = state.vozCancelar;
        state.vozCancelar = false;
        const tipo = rec.mimeType || 'audio/webm';
        const blob = new Blob(state.vozChunks, { type: tipo });
        state.vozChunks = [];
        state.vozRec = null;
        if (cancelar) return;
        if (!blob.size) {
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            vozStatus('Não gravei nada.', 4000);
            return;
        }
        if (blob.size > 20 * 1024 * 1024) {
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            vozStatus('Áudio grande demais.', 5000);
            return;
        }

        state.vozTranscrevendo = true;
        vozEstado('pensando');
        if (micBtn) micBtn.disabled = true;
        vozStatus('Transcrevendo…');
        const avisoLento = setTimeout(() => {
            if (state.vozTranscrevendo) {
                vozStatus('Transcrevendo… o serviço de voz estava desligado e está ligando.', 0);
            }
        }, 4000);
        const ext = tipo.indexOf('mp4') >= 0 ? 'mp4' : (tipo.indexOf('ogg') >= 0 ? 'ogg' : 'webm');
        const dados = new FormData();
        dados.append('audio', blob, 'audio.' + ext);
        try {
            const resp = await fetch('api.php?action=voz', {
                method: 'POST',
                headers: { 'X-CSRF-Token': state.csrf },
                body: dados,
            });
            if (resp.status === 401 || resp.status === 419) { window.location.reload(); return; }
            const json = await resp.json().catch(() => ({}));
            if (!resp.ok || !json.ok) throw new Error(json.message || ('Erro ' + resp.status));
            const texto = String(json.texto || '').trim();
            if (!texto) { vozStatus('Não entendi o áudio. Tenta de novo.', 5000); return; }
            state.vozInteracao = true;
            campo.value = texto;
            autoResize();
            vozStatus('Você: ' + texto, 3500);
            enviar({ voz: true });
        } catch (erro) {
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            vozStatus('Não consegui transcrever: ' + erro.message, 7000);
        } finally {
            clearTimeout(avisoLento);
            state.vozTranscrevendo = false;
            if (micBtn && !state.vozMaosLivres) micBtn.disabled = false;
            if (micBtn && state.vozMaosLivres) micBtn.disabled = true;
        }
    }

    function alternarGravacao() {
        if (state.vozMaosLivres) return;
        if (state.vozGravando) pararGravacao();
        else iniciarGravacao();
    }

    // ------------------------------------------------------- maos livres

    /** WAV 16-bit mono a partir das amostras Float32 do microfone. */
    function wavDeFloat32(amostras, taxa) {
        const buffer = new ArrayBuffer(44 + amostras.length * 2);
        const view = new DataView(buffer);
        const texto = (pos, valor) => {
            for (let i = 0; i < valor.length; i += 1) view.setUint8(pos + i, valor.charCodeAt(i));
        };
        texto(0, 'RIFF');
        view.setUint32(4, 36 + amostras.length * 2, true);
        texto(8, 'WAVE');
        texto(12, 'fmt ');
        view.setUint32(16, 16, true);
        view.setUint16(20, 1, true);
        view.setUint16(22, 1, true);
        view.setUint32(24, taxa, true);
        view.setUint32(28, taxa * 2, true);
        view.setUint16(32, 2, true);
        view.setUint16(34, 16, true);
        texto(36, 'data');
        view.setUint32(40, amostras.length * 2, true);
        let pos = 44;
        for (let i = 0; i < amostras.length; i += 1) {
            const s = Math.max(-1, Math.min(1, amostras[i]));
            view.setInt16(pos, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
            pos += 2;
        }
        return new Blob([view], { type: 'audio/wav' });
    }

    function hfPodar(limiteSegundos = 90) {
        const taxa = state.vozHFCtx ? state.vozHFCtx.sampleRate : 48000;
        const limite = taxa * limiteSegundos;
        while (state.vozHFTotal - state.vozHFBase > limite && state.vozHFBuffer.length) {
            const bloco = state.vozHFBuffer.shift();
            state.vozHFBase += bloco.length;
        }
    }

    /** Guarda so o rabicho para o proximo comeco nao perder a primeira silaba. */
    function hfManterUltimos(ms) {
        const taxa = state.vozHFCtx ? state.vozHFCtx.sampleRate : 48000;
        const alvo = Math.max(1, Math.floor(taxa * ms / 1000));
        while (state.vozHFTotal - state.vozHFBase > alvo && state.vozHFBuffer.length) {
            const bloco = state.vozHFBuffer.shift();
            state.vozHFBase += bloco.length;
        }
    }

    function hfExtrair(inicio, fim) {
        const partes = [];
        let pos = state.vozHFBase;
        for (const bloco of state.vozHFBuffer) {
            const ini = pos;
            const fimBloco = pos + bloco.length;
            const a = Math.max(inicio, ini);
            const b = Math.min(fim, fimBloco);
            if (b > a) partes.push(bloco.subarray(a - ini, b - ini));
            pos = fimBloco;
        }
        const total = partes.reduce((soma, parte) => soma + parte.length, 0);
        const saida = new Float32Array(total);
        let off = 0;
        for (const parte of partes) { saida.set(parte, off); off += parte.length; }
        return saida;
    }

    async function enviarSegmentoHF(amostras, bargeIn) {
        if (state.vozHFEnviando || !state.vozMaosLivres || !amostras.length) return;
        const taxa = state.vozHFCtx ? state.vozHFCtx.sampleRate : 48000;
        if (amostras.length < taxa * 0.25) return;

        state.vozHFEnviando = true;
        state.vozHFIgnorarAte = Date.now() + 2500;
        if (micBtn) micBtn.disabled = true;
        vozStatus('Transcrevendo…');
        const dados = new FormData();
        dados.append('audio', wavDeFloat32(amostras, taxa), 'audio.wav');
        try {
            const resp = await fetch('api.php?action=voz', {
                method: 'POST',
                headers: { 'X-CSRF-Token': state.csrf },
                body: dados,
            });
            if (resp.status === 401 || resp.status === 419) { window.location.reload(); return; }
            const json = await resp.json().catch(() => ({}));
            if (!resp.ok || !json.ok) throw new Error(json.message || ('Erro ' + resp.status));
            const texto = String(json.texto || '').trim();
            if (!texto) {
                vozEstado('idle');
                vozStatus('Não entendi o áudio. Tenta de novo.', 5000);
                return;
            }
            if (!state.vozMaosLivres) return;
            state.vozInteracao = true;
            campo.value = texto;
            autoResize();
            vozStatus('Você: ' + texto, 3000);
            enviar({ voz: true, bargeIn: Boolean(bargeIn) });
        } catch (erro) {
            vozEstado(state.vozMaosLivres ? 'idle' : 'off');
            vozStatus('Não consegui transcrever: ' + erro.message, 7000);
        } finally {
            state.vozHFEnviando = false;
            if (micBtn && !state.vozMaosLivres) micBtn.disabled = false;
            if (state.vozMaosLivres) state.vozHFIgnorarAte = Date.now() + 2500;
        }
    }

    function finalizarSegmentoHF() {
        if (!state.vozHFGravando) return;
        const taxa = state.vozHFCtx ? state.vozHFCtx.sampleRate : 48000;
        const fim = state.vozHFTotal - Math.floor(taxa * 0.15);
        const inicio = Math.max(state.vozHFBase, Math.min(state.vozHFInicio, fim));
        state.vozHFGravando = false;
        state.vozHFFalaMs = 0;
        state.vozHFSilencioMs = 0;
        if (handsFreeBtn) handsFreeBtn.classList.remove('ouvindo');
        vozEstado('pensando');
        const amostras = hfExtrair(inicio, fim);
        const bargeIn = state.vozHFBargeIn;
        state.vozHFBargeIn = false;
        hfManterUltimos(350);
        if (amostras.length) {
            enviarSegmentoHF(amostras, bargeIn);
        } else if (state.vozMaosLivres) {
            vozEstado('idle');
            vozStatus('Mãos livres ligado — pode falar.', 0);
        }
    }

    function processarAudioHF(evento) {
        if (!state.vozMaosLivres || !state.vozHFCtx || state.vozHFEnviando) return;
        const entrada = evento.inputBuffer.getChannelData(0);
        const bloco = new Float32Array(entrada.length);
        bloco.set(entrada);
        state.vozHFBuffer.push(bloco);
        state.vozHFTotal += bloco.length;
        hfPodar();

        let soma = 0;
        for (let i = 0; i < bloco.length; i += 1) soma += bloco[i] * bloco[i];
        const rms = Math.sqrt(soma / Math.max(1, bloco.length));
        const taxa = state.vozHFCtx.sampleRate;
        const ms = (bloco.length / taxa) * 1000;

        if (state.vozHFCalibrando > 0) {
            state.vozHFCalibSoma += rms;
            state.vozHFCalibN += 1;
            state.vozHFCalibrando -= 1;
            if (state.vozHFCalibrando === 0) {
                const media = state.vozHFCalibSoma / Math.max(1, state.vozHFCalibN);
                state.vozHFNivel = Math.max(0.012, media * 2.6);
                vozEstado('idle');
                vozStatus('Mãos livres ligado — pode falar.', 0);
            }
            return;
        }

        if (Date.now() < state.vozHFIgnorarAte) {
            state.vozHFFalaMs = 0;
            state.vozHFSilencioMs = 0;
            return;
        }

        const limiar = state.vozHFNivel * (state.vozTocando ? 3.2 : 1);
        const temFala = rms > limiar;
        if (temFala) {
            state.vozHFFalaMs += ms;
            state.vozHFSilencioMs = 0;
        } else {
            state.vozHFSilencioMs += ms;
            state.vozHFFalaMs = Math.max(0, state.vozHFFalaMs - ms * 0.4);
        }

        const necessario = state.vozTocando ? 450 : 180;
        if (!state.vozHFGravando && state.vozHFFalaMs >= necessario) {
            const falandoOuPreparando = state.vozTocando
                || state.vozFilaRodando
                || state.vozFilaFala.length > 0;
            if (falandoOuPreparando) {
                state.vozHFBargeIn = true;
                pararFala();
                state.vozHFIgnorarAte = 0;
            }
            state.vozHFGravando = true;
            const amostrasFala = Math.floor((state.vozHFFalaMs / 1000) * taxa);
            state.vozHFInicio = Math.max(
                state.vozHFBase,
                state.vozHFTotal - amostrasFala - Math.floor(taxa * 0.35)
            );
            state.vozHFInicioTs = Date.now();
            state.vozHFSilencioMs = 0;
            if (handsFreeBtn) handsFreeBtn.classList.add('ouvindo');
            vozEstado('ouvindo');
            vozStatus('Ouvindo…', 0);
        }

        if (state.vozHFGravando) {
            if (state.vozHFSilencioMs >= 550 || (Date.now() - state.vozHFInicioTs) > 30000) {
                finalizarSegmentoHF();
            }
        }
    }

    async function ligarMaosLivres() {
        if (state.vozMaosLivres) return;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            vozStatus('Este navegador não deixa usar o microfone.', 5000);
            return;
        }
        state.vozMaosLivres = true;
        desbloquearAudio();
        pararFala();
        let ctx = null;
        try {
            // Cria/resume o AudioContext ainda dentro do clique: no iPhone o
            // contexto criado depois do await do getUserMedia fica suspenso.
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) throw new Error('AudioContext indisponível');
            try {
                // 16 kHz ja e o que o Whisper usa: upload 3x menor e menos ffmpeg.
                ctx = new Ctx({ sampleRate: 16000, latencyHint: 'interactive' });
            } catch (e) {
                ctx = new Ctx();
            }
            try { await ctx.resume(); } catch (e) { /* tenta de novo abaixo */ }
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            });
            try { await ctx.resume(); } catch (e) { /* segue; o audio ainda pode rodar */ }
            const source = ctx.createMediaStreamSource(stream);
            const proc = ctx.createScriptProcessor(1024, 1, 1);
            const gain = ctx.createGain();
            gain.gain.value = 0;
            source.connect(proc);
            proc.connect(gain);
            gain.connect(ctx.destination);

            state.vozHFStream = stream;
            state.vozHFCtx = ctx;
            state.vozHFSource = source;
            state.vozHFProc = proc;
            state.vozHFGain = gain;
            state.vozHFBuffer = [];
            state.vozHFBase = 0;
            state.vozHFTotal = 0;
            state.vozHFGravando = false;
            state.vozHFFalaMs = 0;
            state.vozHFSilencioMs = 0;
            state.vozHFCalibrando = Math.max(8, Math.round((ctx.sampleRate * 0.5) / 1024));
            state.vozHFCalibSoma = 0;
            state.vozHFCalibN = 0;
            state.vozHFNivel = 0.018;
            state.vozHFBargeIn = false;
            state.vozHFIgnorarAte = Date.now() + 700;
            proc.onaudioprocess = processarAudioHF;

            if (micBtn) micBtn.disabled = true;
            if (handsFreeBtn) {
                handsFreeBtn.classList.add('on');
                handsFreeBtn.setAttribute('aria-pressed', 'true');
            }
            vozEstado('pensando');
            vozStatus('Calibrando o microfone…', 0);
        } catch (erro) {
            state.vozMaosLivres = false;
            if (state.vozHFStream) {
                state.vozHFStream.getTracks().forEach((t) => t.stop());
                state.vozHFStream = null;
            }
            if (state.vozHFCtx) {
                try { state.vozHFCtx.close(); } catch (e) { /* ja fechado */ }
                state.vozHFCtx = null;
            } else if (ctx) {
                try { ctx.close(); } catch (e) { /* ja fechado */ }
            }
            if (micBtn) micBtn.disabled = false;
            vozStatus('Não consegui ligar o mãos livres: ' + erro.message, 7000);
        }
    }

    function desligarMaosLivres() {
        if (!state.vozMaosLivres && !state.vozHFStream) return;
        state.vozMaosLivres = false;
        state.vozHFGravando = false;
        state.vozHFFalaMs = 0;
        state.vozHFSilencioMs = 0;
        state.vozHFEnviando = false;
        state.vozHFBargeIn = false;
        if (state.vozHFProc) {
            state.vozHFProc.onaudioprocess = null;
            try { state.vozHFProc.disconnect(); } catch (e) { /* ja solto */ }
            state.vozHFProc = null;
        }
        if (state.vozHFSource) {
            try { state.vozHFSource.disconnect(); } catch (e) { /* ja solto */ }
            state.vozHFSource = null;
        }
        if (state.vozHFGain) {
            try { state.vozHFGain.disconnect(); } catch (e) { /* ja solto */ }
            state.vozHFGain = null;
        }
        if (state.vozHFStream) {
            state.vozHFStream.getTracks().forEach((t) => t.stop());
            state.vozHFStream = null;
        }
        if (state.vozHFCtx) {
            try { state.vozHFCtx.close(); } catch (e) { /* ja fechado */ }
            state.vozHFCtx = null;
        }
        state.vozHFBuffer = [];
        state.vozHFBase = 0;
        state.vozHFTotal = 0;
        if (micBtn) micBtn.disabled = false;
        if (handsFreeBtn) {
            handsFreeBtn.classList.remove('on', 'ouvindo');
            handsFreeBtn.setAttribute('aria-pressed', 'false');
        }
        vozEstado('off');
        vozStatus('Mãos livres desligado.', 2500);
    }

    async function alternarMaosLivres() {
        if (state.vozMaosLivres) {
            desligarMaosLivres();
            return;
        }
        if (state.vozGravando) pararGravacao(true);
        await ligarMaosLivres();
    }

    function pararVoz() {
        pararFala();
    }

    function mutarVoz() {
        state.vozInteracao = false;
        pararFala();
        vozEstado(state.vozMaosLivres ? 'idle' : 'off');
        vozStatus('Leitura desligada. Fale de novo para reativar.', 4000);
    }

    async function iniciar() {
        campo.addEventListener('input', autoResize);
        el('menuBtn').addEventListener('click', () => {
            alternarMenu(!menuLateral().classList.contains('open'));
        });
        el('scrim').addEventListener('click', () => alternarMenu(false));
        // Ao voltar para a aba, sincroniza o historico (o Safari pausa o poll em segundo plano).
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible') {
                if (state.vozMaosLivres) desligarMaosLivres();
                return;
            }
            const view = state.atual ? state.views.get(state.atual) : null;
            if (view && !view.busy) recarregarHistorico(view);
            loadSessions(true);
        });
        // Mantem a lista e a conversa aberta sincronizadas com o app do DSH
        // (conversas criadas la aparecem aqui sem precisar de F5).
        setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            loadSessions(true);
            const view = state.atual ? state.views.get(state.atual) : null;
            if (!view || !view.id || view.busy || view.carregando) return;
            if (document.body.classList.contains('msg-modal-aberto')) return;
            const perto = mensagensEl.scrollHeight - mensagensEl.scrollTop - mensagensEl.clientHeight < 220;
            if (perto) carregarView(view, view.nome);
        }, 20000);
        // O Enter nunca envia: ele so quebra a linha dentro do campo.
        // O envio acontece somente pelo botao "Enviar" (nem Ctrl+Enter envia).
        campo.addEventListener('keydown', (ev) => {
            if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) ev.preventDefault();
        });
        enviarBtn.addEventListener('click', enviar);
        pararBtn.addEventListener('click', parar);
        if (micBtn) micBtn.addEventListener('click', alternarGravacao);
        if (handsFreeBtn) handsFreeBtn.addEventListener('click', alternarMaosLivres);
        if (vozStopBtn) vozStopBtn.addEventListener('click', pararVoz);
        if (vozMuteBtn) vozMuteBtn.addEventListener('click', mutarVoz);
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
        // Copia o balão inteiro (texto + código), sem os botões internos.
        document.addEventListener('click', async (ev) => {
            const alvo = ev.target instanceof Element ? ev.target.closest('.msg-copy') : null;
            if (!alvo) return;
            const msg = alvo.closest('.msg');
            const bubble = msg ? msg.querySelector('.bubble') : null;
            if (!bubble) return;
            try {
                await navigator.clipboard.writeText(textoDaBolha(bubble));
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
        // Deixa o worker de voz pronto antes do primeiro microfone.
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            api('voz-aquecer', {}, { method: 'POST', body: {} }).catch(() => {});
        }
        await loadSessions();
        await restaurarEstado();
        campo.focus();
    }

    if (window.CODEX_DEBUG) window.__codexState = state;
    iniciar();
})();
