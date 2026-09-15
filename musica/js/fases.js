/* ==========================================================================
   Primeira Voz — fases.js
   As 15 fases: conteúdo, modos e mecânica de cada uma.

   Régua de dificuldade (adaptada do ranking do Hooktheory + grade curricular):
     1-3   iniciais   — percepção pura, nada de nome
     4-6   básicas    — maior/menor, tônica, tom e semitom
     7-9   intermediárias — intervalos, armaduras
     10-12 médio      — acordes, campo harmônico, função
     13-15 avançadas  — cadências, modos, análise real
     16-20 CAPÍTULO DA ESCRITA — ler e escrever partitura (o símbolo, no fim)

   Regra que vale para TODAS as fases: errar nunca é alarme. A resposta errada
   soa como a vizinha de meio-tom. O ouvido corrige o que está QUASE certo.
   ========================================================================== */

const Fases = (() => {

  /* ---------------------------------------------------------------------
     Definição das 15 fases
     id        número
     nome      título curto
     nivel     'inicial' | 'básico' | 'intermediário' | 'médio' | 'avançado'
     ensina    o que a pessoa realmente aprende
     ouve      o que o ouvido treina
     mecanica  como se joga
     modos     quais modos estão disponíveis
     curio     fato curioso mostrado ao dominar
     ref       música real que usa o conceito (o jogo SINTETIZA, não usa áudio)
     --------------------------------------------------------------------- */
  const LISTA = [
    {
      id: 1, nome: 'A Pulsação', nivel: 'inicial',
      ensina: 'O tempo existe antes das notas. Compasso de 4 tempos, e onde cai o "1".',
      ouve: 'Onde a música começa e recomeça.',
      mecanica: 'O jogo pulsa. Você bate no tempo, primeiro livre, depois com um alvo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Quase toda música ocidental é feita em grupos de 4. Seu coração também bate em pulsos, não em notas.',
      ref: 'Qualquer marcha ou hino — o pulso é o esqueleto da música.'
    },
    {
      id: 2, nome: 'Grave e Agudo', nivel: 'inicial',
      ensina: 'A altura é uma linha: existe para cima e para baixo, e tem distância.',
      ouve: 'Direção do movimento e tamanho do salto.',
      mecanica: 'Uma bola luminosa sobe e desce seguindo o som. Você acompanha com o dedo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Grave e agudo são literalmente lentidão e rapidez do ar vibrando. Dó central pulsa 261 vezes por segundo.',
      ref: 'O começo de "Ode à Alegria" é o movimento mais simples que existe: passo vizinho.'
    },
    {
      id: 3, nome: 'Altura Aproximada', nivel: 'inicial',
      ensina: 'As notas têm lugares fixos, e a memória curta já consegue guardar um contorno.',
      ouve: 'Contorno melódico: subiu, desceu, voltou.',
      mecanica: 'O jogo toca 3 notas. Você desenha a forma que ouviu. Sem nome, só forma.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A memória de altura dura poucos segundos. Repetir em voz alta é o que segura ela.',
      ref: 'Os três primeiros segundos de qualquer jingle — você lembra da forma, não das notas.'
    },
    {
      id: 4, nome: 'Maior e Menor', nivel: 'básico',
      ensina: 'Duas escalas, dois humores. A diferença está em UMA nota.',
      ouve: 'Alegre contra triste, antes de saber o nome disso.',
      mecanica: 'O jogo toca uma escala. Você diz qual das duas cores é.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A única diferença entre maior e menor é a terceira nota: meio-tom abaixo já vira tristeza.',
      ref: 'Mesma melodia, dois humores: quase toda balada tem versão maior e menor na internet.'
    },
    {
      id: 5, nome: 'Dó Maior no teclado', nivel: 'básico',
      ensina: 'As 7 notas brancas, tônica e casa. Onde a escala mora.',
      ouve: 'A casa de repouso (tônica) contra as notas que pedem continuação.',
      mecanica: 'Teclado visual. O jogo mostra, depois esconde, e você acha a nota.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Dó maior é só as teclas brancas. É a escala que a humanidade inteira aprendeu primeiro.',
      ref: 'A marcha de casamento que todo mundo conhece é Dó maior do começo ao fim.'
    },
    {
      id: 6, nome: 'Tom e Semitom', nivel: 'básico',
      ensina: 'O degrau tem dois tamanhos: o passo curto e o passo longo.',
      ouve: 'A diferença entre o vizinho colado e o vizinho pulado.',
      mecanica: 'Dois alvos lado a lado. Você escolhe qual distância ouviu.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Dó para Dó# é 100 cents. Dó para Ré é 200. Escala musical é uma receita de passos: 2-2-1-2-2-2-1.',
      ref: 'A receita 2-2-1-2-2-2-1 constrói toda escala maior que existe.'
    },
    {
      id: 7, nome: 'Intervalos', nivel: 'intermediário',
      ensina: '2ª até 8ª. Cada distância tem um caráter próprio.',
      ouve: 'O mesmo salto reconhecido de vários pontos de partida.',
      mecanica: 'Encaixe: você arrasta a nota até a distância certa. Erra se colar demais.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A 5ª justa parece "vazia" porque suas ondas se encontram em proporção simples: 3 para 2.',
      ref: 'O tema de "Star Wars" e a afinação de violino usam o mesmo salto de 5ª.'
    },
    {
      id: 8, nome: 'Solfejo', nivel: 'intermediário',
      ensina: 'Dar nome às notas: 1 a 7, dó ré mi. Nome é etiqueta, não obrigação.',
      ouve: 'O grau dentro da escala, que é o que importa de verdade.',
      mecanica: 'O jogo toca a nota e você escolhe o nome certo no campo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Antes de existirem nomes, monges usavam a primeira sílaba de cada verso de um hino. Daí veio dó ré mi.',
      ref: 'O hino a São João Batista, do século XI, é a origem literal do solfejo.'
    },
    {
      id: 9, nome: 'Armaduras', nivel: 'intermediário',
      ensina: 'Sustenidos e bemóis. A escala se deforma e muda de cor.',
      ouve: 'A tonalidade: a mesma melodia em outro lugar tem outro peso.',
      mecanica: 'Roda da armadura. Você completa os sustenidos na ordem correta.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Os sustenidos aparecem sempre na mesma ordem: Fá Dó Sol Ré Lá Mi Si. Nunca muda.',
      ref: 'A ordem Fá-Dó-Sol-Ré-Lá-Mi-Si é a mesma da subida de quintas: dá a volta em 12 casas.'
    },
    {
      id: 10, nome: 'Acordes I–IV–V', nivel: 'médio',
      ensina: 'Três acordes sustentam quase todo o pop. Tônica, subdominante, dominante.',
      ouve: 'A mudança de acorde e a saudade de casa.',
      mecanica: 'Encaixe: você empilha três vogais e a roda fecha quando o acorde está certo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'I–IV–V existe em praticamente toda música que você ouviu hoje.',
      ref: 'A progressão I–V–vi–IV é a mais usada do pop mundial: um milhão de músicas, três acordes.'
    },
    {
      id: 11, nome: 'Círculo das Quintas', nivel: 'médio',
      ensina: 'O mapa completo das tonalidades. Cada casa é um salto de 5ª.',
      ouve: 'A relação de parentesco entre tons próximos.',
      mecanica: 'Mapa circular. Você escolhe a rota mais curta entre dois tons.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Vizinhos no círculo dividem todas as notas menos uma. Por isso modular pra vizinho é suave.',
      ref: 'O ciclo I–vi–IV–V do jazz dá quase uma volta inteira no círculo antes de voltar.'
    },
    {
      id: 12, nome: 'Função', nivel: 'médio',
      ensina: 'Tônica repousa, subdominante prepara, dominante empurra.',
      ouve: 'A função de cada acorde, não só o nome.',
      mecanica: 'Você rotula a progressão que ouviu: repouso, preparação ou tensão.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'O dominante é a única função que pede pra resolver. Ele cria a expectativa inteira da música.',
      ref: 'O "V" que empurra de volta pro "I" é o motor do rock, do samba e do funk.'
    },
    {
      id: 13, nome: 'Cadências', nivel: 'avançado',
      ensina: 'Como a música termina: I–V–I, ii–V–I, plagal.',
      ouve: 'A frase que ficou no ar e a frase que fechou.',
      mecanica: 'Complete a frase: o jogo para em cima e você escolhe o desfecho.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'O ii–V–I é tão eficiente que virou a pontuação padrão do jazz.',
      ref: 'A cadência plagal (IV–I) é o "amém" dos cultos — e o final de muito gospel.'
    },
    {
      id: 14, nome: 'Modos', nivel: 'avançado',
      ensina: 'A mesma escala, sete humores. Eólio, dórico, mixolídio, harmônica.',
      ouve: 'A "cor" do modo sem precisar pensar nela.',
      mecanica: 'Rádio de modos: você ouve e diz qual cor é.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Tocar a escala de Dó começando em Ré já é outro modo. Nada mudou além do ponto de partida.',
      ref: 'Todo solo de guitarra em rock usa mixolídio e pentatônica menor sem saber.'
    },
    {
      id: 15, nome: 'Harmonia', nivel: 'avançado',
      ensina: 'Sétimas, modulação e tensão. Analisar música de verdade.',
      ouve: 'A estrutura completa: onde repousa, onde tensiona, onde vira.',
      mecanica: 'Análise real: 4 compassos, você identifica os graus e o movimento.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Você começou sem saber o que era um compasso. Agora ouve a arquitetura de uma música inteira.',
      ref: 'A modulação pra relativa menor é a virada de página mais usada da música popular.'
    },

    /* ---------- CAPÍTULO DA ESCRITA ----------
       Aqui o símbolo aparece — por último, como manda o método. Você já sabe
       ouvir tudo isso; agora aprende a escrever e a ler.
       ------------------------------------------------------------------- */
    {
      id: 16, nome: 'A Pauta', nivel: 'escrita',
      ensina: 'Cinco linhas, quatro espaços e uma clave. Onde a música mora escrita.',
      ouve: 'O som que corresponde a cada linha, começando pelo Mi da linha de baixo.',
      mecanica: 'A nota toca e acende na pauta. Você liga o que ouviu ao lugar onde ele mora.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A clave de sol é a letra G desenhada mil vezes até virar esse enfeite. Ela marca onde mora o Sol.',
      ref: 'Toda partitura de música popular começa com a clave de sol. É a assinatura do que vem depois.'
    },
    {
      id: 17, nome: 'Ler e Ouvir', nivel: 'escrita',
      ensina: 'Ler de verdade: ver o símbolo e saber o som, sem passar pelo nome.',
      ouve: 'A ligação direta entre altura e posição na pauta.',
      mecanica: 'Aparece uma nota escrita e três sons. Você escolhe qual é aquele.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Músico experiente não decora a posição: ele reconhece o intervalo de olho. É atalho de padrão, não de memória.',
      ref: 'Ler à primeira vista é o que permite tocar uma música que você nunca ouviu.'
    },
    {
      id: 18, nome: 'Figuras de Tempo', nivel: 'escrita',
      ensina: 'Quanto cada nota dura: semibreve, mínima, semínima, colcheia.',
      ouve: 'A diferença entre uma nota longa e duas curtas no mesmo espaço.',
      mecanica: 'Você ouve o ritmo e escolhe a figura que o escreve. Ou toca no tempo certo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A semibreve vale 4, a mínima 2, a semínima 1 e a colcheia meio. Cada haste e bandeirola dobra ou corta pela metade.',
      ref: 'A marcha de casamento é semínima atrás de semínima. O ritmo mais simples que existe, escrito.'
    },
    {
      id: 19, nome: 'Escrever a Melodia', nivel: 'escrita',
      ensina: 'Escrever de verdade: ouvir uma melodia curta e marcar na pauta.',
      ouve: 'A melodia completa, nota por nota, com a direção e o tamanho dos saltos.',
      mecanica: 'Você ouve e arrasta cada nota até a linha certa. A pauta é a resposta.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Antes de existir gravação, era assim que a música sobrevivia: alguém ouvindo e escrevendo os pontos.',
      ref: 'Composição escrita é o motivo de uma música do século XVIII ainda poder ser tocada exatamente igual hoje.'
    },
    {
      id: 20, nome: 'Ler e Tocar', nivel: 'escrita',
      ensina: 'A prova final: ler uma frase escrita que você nunca ouviu e saber como ela soa.',
      ouve: 'A estrutura escrita: melodias com graus repetidos, saltos e retorno à tônica.',
      mecanica: 'Uma frase aparece na pauta. Você monta a melodia do jeito que está escrita.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Você começou sem saber o que era um compasso. Agora lê uma partitura e ouve ela na cabeça antes de tocar.',
      ref: 'Ler e ouvir viraram a mesma coisa. É o fim da jornada — e o começo de tocar qualquer coisa.'
    },

/* ---------- CAPÍTULO DO RITMO E MÉTRICA ----------
       Base: Método Bona. Começa SÓ no quaternário simples e só depois abre
       para os outros compassos — é a ordem que o método usa porque funciona.
       ------------------------------------------------------------------- */
    {
      id: 21, nome: 'Compasso 4/4', nivel: 'ritmo',
      ensina: 'A fórmula de compasso diz quantos tempos cabem e qual figura é um tempo. Em 4/4 são quatro semínimas, com o forte no 1.',
      ouve: 'Onde cai o tempo forte e como os outros três se organizam em volta dele.',
      mecanica: 'O jogo marca o compasso. Você bate no forte e conta os tempos fracos.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'O 4/4 é tão comum que virou o padrão da música ocidental. O símbolo C no lugar de 4/4 vem de "tempus imperfectum", do tempo dos monges.',
      ref: 'O Bona começa a ensinar ritmo usando SÓ o compasso quaternário simples. Todo o resto vem depois.'
    },
    {
      id: 22, nome: 'Binário e Ternário', nivel: 'ritmo',
      ensina: '2/4 e 3/4. A marcha e a valsa. O que muda não é a velocidade, é o agrupamento.',
      ouve: 'Se os tempos andam em grupos de dois ou de três.',
      mecanica: 'Ouça e diga se é marcha (2) ou valsa (3).',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A valsa é ternária e a marcha é binária. Você sente isso no corpo antes de contar — é o balanço que denuncia.',
      ref: 'Hino e marcha em 2/4; valsa em 3/4. O mesmo pulso, agrupado de jeitos diferentes.'
    },
    {
      id: 23, nome: 'Compassos Compostos', nivel: 'ritmo',
      ensina: '6/8, 9/8 e 12/8. Aqui cada tempo se divide em TRÊS, não em dois. É o que faz a música ondular.',
      ouve: 'A subdivisão ternária: três colcheias onde caberiam duas.',
      mecanica: 'Ouça e diga se o compasso é simples (divide em 2) ou composto (divide em 3).',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: '2/4 e 6/8 duram o mesmo, mas soam completamente diferentes. A diferença é só como o tempo se divide por dentro.',
      ref: 'O baião e o blues usam 6/8 e 12/8. É a ondulação que dá o gingado.'
    },
    {
      id: 24, nome: 'Pausas', nivel: 'ritmo',
      ensina: 'Silêncio também tem duração. Cada figura tem uma pausa com o mesmo valor.',
      ouve: 'O silêncio como parte do ritmo, não como ausência de música.',
      mecanica: 'Você ouve o ritmo com faltas e escolhe a pausa que preenche o espaço.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'A pausa de semibreve é um compasso 4/4 inteiro em silêncio. Música é feita tanto do que soa quanto do que cala.',
      ref: 'A batida do funk é feita de silêncios precisos. Sem as pausas, a levada some.'
    },
    {
      id: 25, nome: 'Ponto e Ligadura', nivel: 'ritmo',
      ensina: 'O ponto soma metade à figura. A ligadura soma uma nota à outra, atravessando a barra do compasso.',
      ouve: 'A diferença entre um som que dura exatamente um tempo e um que dura uma vez e meia.',
      mecanica: 'Ouça a duração e escolha a escrita: nota simples, pontuada ou ligada.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Semínima pontuada = 1,5 tempos. É exatamente o tempo dos compassos compostos — por isso 6/8 se escreve com pontuação.',
      ref: 'A valsa escrita em 6/8 usa ponto o tempo todo. É a grafia que casa com a sensação.'
    },
    {
      id: 26, nome: 'Síncope', nivel: 'ritmo',
      ensina: 'O jeito de a música contrariar o compasso: começar no fraco e prolongar NO forte. O acento troca de lugar.',
      ouve: 'O acento deslocado. A batida que não cai onde você esperava.',
      mecanica: 'Você ouve o padrão e marca onde estão os ataques, não onde está o tempo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Síncope, contratempo e anacruse são os três jeitos de a música não acentuar o tempo forte. Alfred\'s agrupa os três pela mesma razão.',
      ref: 'Samba, funk e reggae vivem de síncope. O contratempo do reggae é o som da guitarra no "e", nunca no tempo.'
    },
    {
      id: 27, nome: 'Tercinas', nivel: 'ritmo',
      ensina: 'Três notas no espaço de duas. Não é um tempo novo: é uma subdivisão diferente dentro do mesmo tempo.',
      ouve: 'O agrupamento de três contra o pulso de dois.',
      mecanica: 'Você ouve e diz quantas notas cabem no espaço dado.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Tercina é 3 no espaço de 2. Quiáltera é 5 no espaço de 4. A regra geral: quantas cabem no lugar de quantas.',
      ref: 'O blues e o jazz usam tercina sem parar. É o swing escrito.'
    },

    /* ---------- CAPÍTULO DA HARMONIA ----------
       Escalas, acordes, arpejos e os modos gregos de verdade.
       O foco não é decorar nomes: é entender de onde cada coisa nasce.
       ------------------------------------------------------------------- */
    {
      id: 28, nome: 'A Escala', nivel: 'harmonia',
      ensina: 'A escala é uma receita de passos, não uma lista de notas. Decore a receita e você constrói qualquer escala, em qualquer tom.',
      ouve: 'A diferença entre o passo curto (meio-tom) e o longo (tom), em sequência.',
      mecanica: 'Ouça a receita e diga em que ponto ela se desvia da escala maior.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Escala maior = 2-2-1-2-2-2-1. Essa sequência de tons e semitons constrói toda escala maior que existe, do Dó ao Fá#.',
      ref: 'Toda escala que você já cantou sem saber é uma receita de sete passos. Muda o começo, não a lógica.'
    },
    {
      id: 29, nome: 'Empilhar Terças', nivel: 'harmonia',
      ensina: 'O acorde não é um bloco misterioso: é a escala pulando de duas em duas. Tônica, terça, quinta.',
      ouve: 'A diferença entre a nota sozinha e a mesma nota com as outras duas juntas.',
      mecanica: 'Você constrói o acorde empilhando as terças a partir da nota que ouviu.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Pegue 1, 3 e 5 graus da escala e você tem um acorde. Use a 3ª da escala maior e ele é maior; a da menor e ele é menor.',
      ref: 'Todo acorde de toda música é uma pilha de terças. É por isso que só existem poucos tipos de acorde e infinitas músicas.'
    },
    {
      id: 30, nome: 'Arpejo', nivel: 'harmonia',
      ensina: 'As notas do acorde uma depois da outra. A ponte entre a harmonia e a melodia.',
      ouve: 'O acorde quebrado: as mesmas notas, agora em fila.',
      mecanica: 'Você ouve o arpejo e monta a ordem em que as notas subiram.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Todo solo de guitarra é arpejo disfarçado. O guitarrista está tocando o acorde, só que em sequência.',
      ref: 'A introdução de quase toda balada é um arpejo: o acorde se apresentando devagar antes de virar bloco.'
    },
    {
      id: 31, nome: 'Inversões', nivel: 'harmonia',
      ensina: 'O mesmo acorde com outra nota no baixo. Muda o peso, não o nome.',
      ouve: 'A mesma harmonia soando mais leve ou mais séria conforme o baixo.',
      mecanica: 'Você ouve o acorde e diz qual das três notas está embaixo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'C, C/Mi e C/Sol são o mesmo acorde. A nota que fica embaixo é que decide se ele soa pesado ou suspenso.',
      ref: 'É por isso que o baixo é o instrumento mais importante depois da melodia: ele escolhe o peso de tudo.'
    },
    {
      id: 32, nome: 'Os Sete Modos', nivel: 'harmonia',
      ensina: 'Os modos gregos não são sete escalas. São UMA escala começando de sete pontos diferentes. Nada muda além do ponto de partida.',
      ouve: 'A mesma coleção de notas soando diferente conforme onde ela começa.',
      mecanica: 'Você ouve as notas brancas e diz de qual grau elas estão partindo.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Dórico, frígio, lídio, mixolídio: toque todas as teclas brancas começando de Ré, Mi, Fá e Sol. É só isso. Nenhuma nota foi adicionada.',
      ref: 'O flamenco é frígio. O rock é mixolídio. A trilha de sonho é lídio. Mesmas notas, pontos de partida diferentes.'
    },
    {
      id: 33, nome: 'Modo e Humor', nivel: 'harmonia',
      ensina: 'Por que cada modo soa diferente: uma única nota muda de lugar. É ali que mora o caráter.',
      ouve: 'O que muda entre um modo e o modo vizinho.',
      mecanica: 'Você ouve dois modos e aponta em que passo eles divergem.',
      modos: ['aprender', 'praticar', 'dominar'],
      curio: 'Dórico e eólio diferem em UMA nota: a sexta. Essa única nota é a diferença entre tristeza e esperança.',
      ref: 'Trocar uma nota de um modo é o truque mais antigo da composição. É como o cinema muda o clima de uma cena.'
    },


  ];

  const porId = (id) => LISTA.find(f => f.id === id);
  const total = LISTA.length;

  /* ---------------------------------------------------------------------
     Geradores de exercício. Cada fase produz uma lista de rodadas.
     O formato é sempre o mesmo, pra main.js só desenhar:
       { tipo, pergunta, opcoes[], correta, dica, oQueSoa }
     --------------------------------------------------------------------- */

  const rnd = (a, b) => a + Math.random() * (b - a);
  const escolher = (arr) => arr[Math.floor(Math.random() * arr.length)];
  const embaralhar = (a) => { const c = a.slice(); for (let i = c.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [c[i], c[j]] = [c[j], c[i]]; } return c; };
  const unicos = (a) => [...new Set(a)];

  /* --- Fase 1: pulsação --- */
  function fase1(n = 6) {
    const bpm = 76;
    return {
      tipo: 'tempo', bpm, compasso: 4, rodadas: n,
      instrucao: 'Bata no tempo. O acento do primeiro tempo é mais forte.',
      dica: 'Não persiga o som. Antecipe: bata junto com o pulso.'
    };
  }

  /* --- Fase 2: grave/agudo --- */
  function fase2(n = 8) {
    const rodadas = [];
    const base = 60;
    for (let i = 0; i < n; i++) {
      const a = Teoria.naZona(base + escolher([-7, -5, -3, 0, 2, 4, 5, 7]), 'medio');
      const delta = escolher([1, 2, 3, 4, 5, 7, -1, -2, -3, -4, -5, -7]);
      const b = a + delta;
      rodadas.push({
        tipo: 'direcao',
        a, b, delta,
        correta: delta > 0 ? 'sobe' : 'desce',
        opcoes: ['sobe', 'desce'],
        oQueSoa: [Teoria.hzDoMidi(a), Teoria.hzDoMidi(b)],
        dica: 'Feche os olhos. O corpo sente a direção antes do nome.'
      });
    }
    return { tipo: 'direcao', rodadas, instrucao: 'Ouça as duas notas. A segunda subiu ou desceu?' };
  }

  /* --- Fase 3: contorno --- */
  function fase3(n = 6) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      let cur = Teoria.naZona(60 + escolher([-5, -2, 0, 2, 5]), 'medio');
      const notas = [cur];
      const passos = [];
      for (let k = 0; k < 2; k++) {
        const d = escolher([2, 3, 4, -2, -3, -4, 5, -5]);
        cur += d; passos.push(Math.sign(d));
        notas.push(cur);
      }
      const contorno = passos.map(Math.sign).join(',');
      rodadas.push({
        tipo: 'contorno',
        notas,
        correta: contorno,
        opcoes: unicos(['1,1', '1,-1', '-1,-1', '-1,1', '1,0', '-1,0'].concat(contorno)).slice(0, 4),
        oQueSoa: notas.map(Teoria.hzDoMidi),
        dica: 'Não tente nomear. Só sinta a forma: sobe, sobe, desce...'
      });
      // garante que a correta está entre as opções
      const r = rodadas[rodadas.length - 1];
      if (!r.opcoes.includes(contorno)) r.opcoes[Math.floor(Math.random() * r.opcoes.length)] = contorno;
      r.opcoes = embaralhar(unicos(r.opcoes));
    }
    return { tipo: 'contorno', rodadas, instrucao: 'Ouça três notas e escolha o desenho que elas fazem.' };
  }

  /* --- Fase 4: maior/menor --- */
  function fase4(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const maior = Math.random() < 0.5;
      const tonica = Teoria.naZona(60 + escolher([-5, -3, 0, 2, 4, 5, 7]), 'medio');
      rodadas.push({
        tipo: 'cor',
        tonica, modo: maior ? 'maior' : 'menorNat',
        correta: maior ? 'alegre' : 'triste',
        opcoes: ['alegre', 'triste'],
        oQueSoa: Teoria.escala(tonica, maior ? 'maior' : 'menorNat').map(Teoria.hzDoMidi),
        dica: 'Procure a terceira nota. É ela que decide a cor.'
      });
    }
    return { tipo: 'cor', rodadas, instrucao: 'Ouça a escala. Ela é alegre (maior) ou triste (menor)?' };
  }

  /* --- Fase 5: notas de Dó maior --- */
  function fase5(n = 10) {
    const rodadas = [];
    const brancas = [60, 62, 64, 65, 67, 69, 71, 72];
    for (let i = 0; i < n; i++) {
      const m = escolher(brancas);
      rodadas.push({
        tipo: 'teclado',
        midi: m,
        correta: m,
        opcoes: brancas,
        oQueSoa: [Teoria.hzDoMidi(m)],
        dica: 'Dó é a nota de repouso. Ela é a casa, não a passagem.'
      });
    }
    return { tipo: 'teclado', rodadas, instrucao: 'Toque a nota que você ouviu no teclado.' };
  }

  /* --- Fase 6: tom e semitom --- */
  function fase6(n = 10) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const base = Teoria.naZona(60 + escolher([-4, -2, 0, 2, 4, 5]), 'medio');
      const semitom = Math.random() < 0.5;
      const b = base + (semitom ? 1 : 2);
      rodadas.push({
        tipo: 'degrau',
        a: base, b,
        correta: semitom ? 'curto' : 'longo',
        opcoes: ['curto', 'longo'],
        oQueSoa: [Teoria.hzDoMidi(base), Teoria.hzDoMidi(b)],
        dica: 'O passo curto quase encosta. O longo tem folga.'
      });
    }
    return { tipo: 'degrau', rodadas, instrucao: 'O salto é curto (meio-tom) ou longo (tom inteiro)?' };
  }

  /* --- Fase 7: intervalos --- */
  function fase7(n = 10) {
    const rodadas = [];
    const alvos = [2, 3, 4, 5, 7, 9, 12];
    for (let i = 0; i < n; i++) {
      const base = Teoria.naZona(60, 'medio');
      const s = escolher(alvos);
      const interv = Teoria.INTERVALOS[s];
      const distratores = alvos.filter(x => x !== s).map(x => Teoria.INTERVALOS[x].curto);
      rodadas.push({
        tipo: 'intervalo',
        base, s,
        correta: interv.curto,
        opcoes: embaralhar(unicos([interv.curto, ...embaralhar(distratores).slice(0, 3)])),
        oQueSoa: [Teoria.hzDoMidi(base), Teoria.hzDoMidi(base + s)],
        dica: interv.cor,
        interv
      });
    }
    return { tipo: 'intervalo', rodadas, instrucao: 'Que intervalo é esse?', alvos };
  }

  /* --- Fase 8: solfejo --- */
  function fase8(n = 10) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const g = Math.floor(rnd(0, 7));
      const tonica = Teoria.naZona(60, 'medio');
      const escala = Teoria.escala(tonica, 'maior');
      const midi = escala[g];
      rodadas.push({
        tipo: 'solfejo',
        midi, grau: g + 1, tonica,
        correta: Teoria.SOLFEJO[g],
        opcoes: Teoria.SOLFEJO.slice(),
        oQueSoa: [Teoria.hzDoMidi(midi)],
        dica: `É o ${g + 1}º degrau da escala.`,
        escala: escala.map(Teoria.hzDoMidi)
      });
    }
    return { tipo: 'solfejo', rodadas, instrucao: 'Que nota da escala é essa?' };
  }

  /* --- Fase 9: armaduras ---
     ATENÇÃO: a resposta é uma CONTAGEM de alterações (0..6) e o tipo
     (sustenido/bemol) é um campo separado. Antes o campo `correta` carregava
     o sinal negativo dos bemóis, o que fazia a resposta nunca existir entre
     as opções — o jogador não tinha como acertar. */
  function fase9(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const cifra = escolher(['C', 'G', 'D', 'A', 'E', 'F', 'Bb', 'Eb', 'Ab']);
      const arm = Teoria.armadura(cifra);
      const qtd = arm.sustenidos || arm.bemois;   // contagem pura, sem sinal
      const tipoAlt = arm.sustenidos ? 'sustenidos' : 'bemois';
      const opcoesQtd = ['0', '1', '2', '3', '4', '5', '6'];
      rodadas.push({
        tipo: 'armadura',
        cifra,
        correta: String(qtd),
        qtd, tipoAlt,
        opcoes: embaralhar(opcoesQtd).slice(0, 5).concat([String(qtd)])
                    .filter((v, i, a) => a.indexOf(v) === i).sort(),
        oQueSoa: Teoria.campoHarmonico(Teoria.tonicaDe(cifra), 'maior').slice(0, 3).map(a => a.hz[0]),
        dica: arm.texto
      });
      const r = rodadas[i];
      if (!r.opcoes.includes(r.correta)) r.opcoes.push(r.correta);
      r.opcoes = r.opcoes.map(Number).sort((a, b) => a - b).map(String);
    }
    return { tipo: 'armadura', rodadas, instrucao: 'Quantas alterações tem essa armadura?' };
  }

  /* --- Fase 10: acordes I IV V --- */
  function fase10(n = 8) {
    const rodadas = [];
    const grausAlvo = [1, 4, 5];
    for (let i = 0; i < n; i++) {
      const tonica = Teoria.naZona(60, 'medio');
      const campo = Teoria.campoHarmonico(tonica, 'maior');
      const g = escolher(grausAlvo);
      const ac = campo[g - 1];
      rodadas.push({
        tipo: 'acorde',
        acorde: ac, grau: g,
        correta: ac.nome,
        opcoes: embaralhar(campo.map(a => a.nome)).slice(0, 4),
        oQueSoa: ac.hz,
        dica: `Grau ${g} do campo de ${Teoria.doMidi(tonica).nome}.`,
        campo: campo.map(a => ({ nome: a.nome, hz: a.hz[0], grau: a.grau }))
      });
      const r = rodadas[i];
      if (!r.opcoes.includes(r.correta)) r.opcoes[Math.floor(Math.random() * r.opcoes.length)] = r.correta;
    }
    return { tipo: 'acorde', rodadas, instrucao: 'Que acorde é esse?', grausAlvo };
  }

  /* --- Fase 11: círculo das quintas --- */
  function fase11(n = 8) {
    const rodadas = [];
    const circ = Teoria.CIRCULO_QUINTAS;
    for (let i = 0; i < n; i++) {
      const idx = Math.floor(rnd(0, 12));
      const de = circ[idx];
      const passo = escolher([1, -1, 2, -2]);
      const para = circ[(idx + passo + 12) % 12];
      rodadas.push({
        tipo: 'rota',
        de, para, passo,
        correta: String(Math.abs(passo)),
        opcoes: ['1', '2', '3', '4', '6'],
        oQueSoa: [Teoria.hzDoMidi(Teoria.tonicaDe(de)), Teoria.hzDoMidi(Teoria.tonicaDe(para))],
        dica: passo > 0 ? 'Andar para a direita no círculo é subir quintas.' : 'Andar para a esquerda é descer quintas.'
      });
    }
    return { tipo: 'rota', rodadas, instrucao: 'Quantos passos no círculo separam estas duas tonalidades?', circulo: circ };
  }

  /* --- Fase 12: função --- */
  function fase12(n = 9) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const tonica = Teoria.naZona(60, 'medio');
      const campo = Teoria.campoHarmonico(tonica, 'maior');
      const g = Math.floor(rnd(0, 7)) + 1;
      const ac = campo[g - 1];
      rodadas.push({
        tipo: 'funcao',
        acorde: ac, grau: g,
        correta: ac.funcao.toLowerCase(),
        opcoes: ['tônica', 'subdominante', 'dominante'],
        oQueSoa: ac.hz,
        dica: ac.funcao === 'Tônica' ? 'Repousa, parece casa.'
            : ac.funcao === 'Subdominante' ? 'Prepara, não resolve.'
            : 'Empurra, pede pra voltar.',
        campo: campo.map(a => ({ nome: a.nome, grau: a.grau, funcao: a.funcao }))
      });
    }
    return { tipo: 'funcao', rodadas, instrucao: 'Qual a função desse acorde?' };
  }

  /* --- Fase 13: cadências --- */
  const CADENCIAS = [
    { nome: 'I–V–I',  graus: [1, 5, 1],  tipo: 'autêntica',  desc: 'A mais direta. Fecha sem discussão.' },
    { nome: 'ii–V–I', graus: [2, 5, 1],  tipo: 'jazz',       desc: 'Prepara, tensiona, resolve. Padrão do jazz.' },
    { nome: 'IV–I',   graus: [4, 1],     tipo: 'plagal',     desc: 'O "amém". Suave, sem tensão.' },
    { nome: 'I–vi–ii–V', graus: [1, 6, 2, 5], tipo: 'suspensa', desc: 'Fica no ar de propósito: quase termina.' }
  ];

  function fase13(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const cad = escolher(CADENCIAS);
      const tonica = Teoria.naZona(60, 'medio');
      const acs = Teoria.progressao(cad.graus, tonica, 'maior');
      rodadas.push({
        tipo: 'cadencia',
        cad, tonica,
        correta: cad.tipo,
        opcoes: embaralhar(unicos(CADENCIAS.map(c => c.tipo))),
        oQueSoa: acs.flatMap(a => a.hz),
        progressaoHz: acs.map(a => a.hz),
        nomes: acs.map(a => a.nome),
        dica: cad.desc
      });
    }
    return { tipo: 'cadencia', rodadas, instrucao: 'Que tipo de cadência é essa?', todas: CADENCIAS };
  }

  /* --- Fase 14: modos --- */
  const MODOS_FASE = ['menorNat', 'dorico', 'frigio', 'lidio', 'mixolidio', 'menorHarm'];

  function fase14(n = 9) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const tipo = escolher(MODOS_FASE);
      const tonica = Teoria.naZona(60, 'medio');
      const def = Teoria.ESCALAS[tipo];
      rodadas.push({
        tipo: 'modo',
        modo: tipo, tonica,
        correta: def.nome,
        opcoes: embaralhar(MODOS_FASE.map(t => Teoria.ESCALAS[t].nome)),
        oQueSoa: Teoria.escala(tonica, tipo).map(Teoria.hzDoMidi),
        dica: def.cor
      });
    }
    return { tipo: 'modo', rodadas, instrucao: 'Que cor de escala é essa?' };
  }

  /* --- Fase 15: análise real --- */
  function fase15(n = 6) {
    const rodadas = [];
    const progressoes = [
      { graus: [1, 5, 6, 4],  nome: 'I–V–vi–IV',   desc: 'A mais usada do pop mundial.' },
      { graus: [6, 4, 1, 5],  nome: 'vi–IV–I–V',   desc: 'A mesma roda, começando do sexto.' },
      { graus: [1, 6, 2, 5],  nome: 'I–vi–ii–V',   desc: 'Ciclo clássico, fica no ar.' },
      { graus: [2, 5, 1, 6],  nome: 'ii–V–I–vi',   desc: 'Jazz que desemboca na relativa.' },
      { graus: [1, 4, 5, 4],  nome: 'I–IV–V–IV',   desc: 'Roda sem repouso final.' }
    ];
    for (let i = 0; i < n; i++) {
      const p = escolher(progressoes);
      const tonica = Teoria.naZona(60, 'medio');
      const acs = Teoria.progressao(p.graus, tonica, 'maior');
      rodadas.push({
        tipo: 'analise',
        p, tonica,
        correta: p.nome,
        opcoes: embaralhar(unicos(progressoes.map(x => x.nome))),
        oQueSoa: acs.flatMap(a => a.hz),
        progressaoHz: acs.map(a => a.hz),
        nomes: acs.map(a => a.nome),
        dica: p.desc
      });
    }
    return { tipo: 'analise', rodadas, instrucao: 'Identifique a progressão, grau por grau.' };
  }

  /* ====================================================================
     CAPÍTULO DA ESCRITA (16-20)
     O símbolo por último: a pessoa já ouve tudo isso, agora aprende a ler.
     ==================================================================== */

  // Região de leitura confortável na pauta da clave de sol.
  // Grau 0 = Mi4 (linha de baixo) .. grau 8 = Dó5.
  const ZONA_PAUTA = [-3, -2, -1, 0, 1, 2, 3, 4, 5, 6, 7, 8];

  /* --- Fase 16: a pauta e a linha de baixo (Mi) --- */
  function fase16(n = 8) {
    const rodadas = [];
    // Começa pelas linhas e espaços de baixo, onde a leitura é mais intuitiva.
    const alvos = [0, 1, 2, 3, 4];   // Mi, Fá, Sol, Lá, Si
    for (let i = 0; i < n; i++) {
      const grau = escolher(alvos);
      const midi = Partitura.midiDoGrau(grau);
      rodadas.push({
        tipo: 'ler_pauta',
        grau, midi,
        correta: String(midi),
        opcoes: embaralhar(unicos(alvos.map(g => String(Partitura.midiDoGrau(g))))),
        oQueSoa: [Teoria.hzDoMidi(midi)],
        dica: 'A linha de baixo é Mi. Cada linha e cada espaço sobem uma nota.',
        nomeNota: Partitura.nomeDoGrau(grau)
      });
      const r = rodadas[i];
      if (!r.opcoes.includes(r.correta)) r.opcoes[Math.floor(Math.random() * r.opcoes.length)] = r.correta;
    }
    return { tipo: 'ler_pauta', rodadas, instrucao: 'Ouça a nota. Qual ponto da pauta é ela?', zona: alvos };
  }

  /* --- Fase 17: ler e ouvir (o símbolo vira som) --- */
  function fase17(n = 10) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const grau = escolher(ZONA_PAUTA);
      const midi = Partitura.midiDoGrau(grau);
      // três candidatos sonoros: a certa e duas vizinhas
      const vizinhos = [-2, -1, 1, 2].map(d => Partitura.midiDoGrau(grau + d))
        .filter(m => m > 50 && m < 88);
      const opcoesMidi = embaralhar([midi, ...embaralhar(vizinhos).slice(0, 2)]);
      rodadas.push({
        tipo: 'ouvir_pauta',
        grau, midi,
        correta: String(midi),
        opcoes: opcoesMidi.map(String),
        opcoesHz: opcoesMidi.map(Teoria.hzDoMidi),
        oQueSoa: [Teoria.hzDoMidi(midi)],
        dica: 'Compare com a linha de baixo: Mi. Suba de linha em linha.',
        nomeNota: Partitura.nomeDoGrau(grau)
      });
    }
    return { tipo: 'ouvir_pauta', rodadas, instrucao: 'Qual som corresponde à nota escrita?' };
  }

  /* --- Fase 18: figuras de tempo --- */
  const FIGURAS = [
    { nome: 'Semibreve', apelido: 'a inteira', valor: 4, desenho: 'inteira', desc: 'Uma nota que dura o compasso todo.' },
    { nome: 'Mínima',    apelido: 'a de duas',  valor: 2, desenho: 'minima',   desc: 'Metade do compasso. Uma para cada lado.' },
    { nome: 'Semínima',  apelido: 'a de uma',   valor: 1, desenho: 'seminima', desc: 'Um tempo. É a unidade do compasso.' },
    { nome: 'Colcheia',  apelido: 'a de meio',  valor: 0.5, desenho: 'colcheia', desc: 'Meio tempo. Duas cabem num tempo.' }
  ];

  function fase18(n = 8) {
    const rodadas = [];
    // Cada padrão dura UM COMPASSO de 4 tempos e é feito de figuras iguais ou
    // de pares claros. Assim a pergunta ("qual figura escreve isso?") tem uma
    // resposta óbvia e o ritmo faz sentido musical.
    const padroes = [
      { figuras: ['seminima', 'seminima', 'seminima', 'seminima'], desc: 'Quatro semínimas: um tempo cada.' },
      { figuras: ['minima', 'minima'],                            desc: 'Duas mínimas: metade do compasso cada.' },
      { figuras: ['colcheia', 'colcheia', 'colcheia', 'colcheia', 'colcheia', 'colcheia', 'colcheia', 'colcheia'], desc: 'Oito colcheias: meio tempo cada.' },
      { figuras: ['minima', 'seminima', 'seminima'],              desc: 'Metade longa, depois dois tempos curtos.' },
      { figuras: ['colcheia', 'colcheia', 'colcheia', 'colcheia', 'minima'], desc: 'Dois tempos picados e um longo.' }
    ];
    for (let i = 0; i < n; i++) {
      const p = escolher(padroes);
      const figuras = p.figuras.map(nome => FIGURAS.find(f => f.desenho === nome));
      const t = 0.42;  // duração de uma semínima em segundos
      const sequencia = [];
      figuras.forEach(f => {
        sequencia.push([Teoria.hzDoMidi(67), f.valor * t * 0.9]);
      });
      // A resposta é a figura PREDOMINANTE do padrão (a que mais aparece).
      // É inequívoca porque os padrões são construídos com uma figura-base.
      const contagem = {};
      figuras.forEach(f => { contagem[f.desenho] = (contagem[f.desenho] || 0) + 1; });
      const predominante = Object.keys(contagem).sort((a, b) => contagem[b] - contagem[a])[0];
      const figPred = FIGURAS.find(f => f.desenho === predominante);

      rodadas.push({
        tipo: 'figura',
        figuras,
        padrao: p.figuras.slice(),
        correta: predominante,
        opcoes: embaralhar(unicos(FIGURAS.map(f => f.desenho))),
        oQueSoa: sequencia.map(x => x[0]),
        sequencia,
        bpm: Math.round(60 / t),
        dica: p.desc + ' A figura que manda aqui é a ' + figPred.nome.toLowerCase() + '.',
        todasFiguras: FIGURAS
      });
      const r = rodadas[i];
      if (!r.opcoes.map(String).includes(String(r.correta))) {
        r.opcoes[Math.floor(Math.random() * r.opcoes.length)] = r.correta;
      }
    }
    return { tipo: 'figura', rodadas, instrucao: 'Qual figura escreve essa duração?', figuras: FIGURAS };
  }

  /* --- Fase 19: escrever a melodia na pauta --- */
  function fase19(n = 6) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      // melodia curta: 3 notas dentro da zona, começando perto do repouso
      let grau = escolher([0, 2, 4]);
      const graus = [grau];
      for (let k = 0; k < 2; k++) {
        const salto = escolher([2, 2, 4, -2, -4, 1, -1]);
        let novo = graus[graus.length - 1] + salto;
        if (novo < -3) novo = -3;
        if (novo > 8) novo = 8;
        graus.push(novo);
      }
      const midis = graus.map(Partitura.midiDoGrau);
      rodadas.push({
        tipo: 'escrever',
        graus,
        midis,
        correta: graus.join(','),
        opcoes: [],
        oQueSoa: midis.map(Teoria.hzDoMidi),
        dica: 'Repita o que ouviu: subiu, desceu, ficou. Cada nota na sua linha.',
        nomes: graus.map(Partitura.nomeDoGrau)
      });
    }
    return { tipo: 'escrever', rodadas, instrucao: 'Ouça a melodia e coloque as três notas na pauta.' };
  }

  /* --- Fase 20: ler e tocar (a prova final) --- */
  function fase20(n = 6) {
    const rodadas = [];
    const frases = [
      { nome: 'Escada subindo',   graus: [0, 1, 2, 3, 4],      desc: 'Uma linha atrás da outra, sem pular nada.' },
      { nome: 'Escada descendo',  graus: [4, 3, 2, 1, 0],      desc: 'A mesma coisa ao contrário.' },
      { nome: 'Salto e volta',    graus: [0, 4, 0],            desc: 'Sobe longe e volta pra casa.' },
      { nome: 'Arco no meio',     graus: [2, 5, 2],            desc: 'Sai do meio, sobe e volta.' },
      { nome: 'Toca e repete',    graus: [2, 2, 2, 0],         desc: 'Três iguais e um degrau abaixo.' },
      { nome: 'Vai e volta',      graus: [1, 3, 1, 3],         desc: 'Duas notas alternadas, como um balanço.' }
    ];
    for (let i = 0; i < n; i++) {
      const f = escolher(frases);
      const midis = f.graus.map(Partitura.midiDoGrau);
      rodadas.push({
        tipo: 'ler_tocar',
        frase: f,
        graus: f.graus,
        midis,
        correta: f.nome,
        opcoes: embaralhar(unicos(frases.map(x => x.nome))),
        oQueSoa: midis.map(Teoria.hzDoMidi),
        dica: f.desc,
        todasFrases: frases
      });
    }
    return { tipo: 'ler_tocar', rodadas, instrucao: 'Leia a frase escrita. O que ela toca?', frases };
  }

  /* ====================================================================
     CAPÍTULO DA HARMONIA (21-26)
     Escalas, acordes, arpejos e modos gregos — construídos, não decorados.
     ==================================================================== */

  /* --- Fase 21: a escala como receita de passos --- */
  const RECEITAS = [
    { nome: 'Maior',            passos: [2,2,1,2,2,2,1], escala: 'maior',     desc: 'A receita mãe. Toda escala maior usa esta.' },
    { nome: 'Menor natural',    passos: [2,1,2,2,1,2,2], escala: 'menorNat',  desc: 'A 3ª, a 6ª e a 7ª descem. É o que entristece.' },
    { nome: 'Menor harmônica',  passos: [2,1,2,2,1,3,1], escala: 'menorHarm', desc: 'A 7ª sobe um tom e meio. O salto que dá drama.' },
    { nome: 'Dórico',           passos: [2,1,2,2,2,1,2], escala: 'dorico',    desc: 'Menor, mas com a 6ª maior. Daí a esperança.' },
    { nome: 'Mixolídio',        passos: [2,2,1,2,2,1,2], escala: 'mixolidio', desc: 'Maior, mas com a 7ª menor. Daí o blues.' },
    { nome: 'Pentatônica maior', passos: [2,2,3,2,3],     escala: 'pentMaior', desc: 'A escala maior sem a 4ª e a 7ª. Não tem nota feia.' }
  ];

  function fase21(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const r = escolher(RECEITAS);
      const tonica = Teoria.naZona(60, 'medio');
      const notas = Teoria.escala(tonica, r.escala);
      rodadas.push({
        tipo: 'receita',
        receita: r.passos,
        nomeReceita: r.nome,
        tonica, escala: r.escala,
        notas, hz: notas.map(Teoria.hzDoMidi),
        correta: r.nome,
        opcoes: embaralhar(unicos(RECEITAS.map(x => x.nome))),
        oQueSoa: notas.map(Teoria.hzDoMidi),
        dica: r.desc,
        todas: RECEITAS
      });
    }
    return { tipo: 'receita', rodadas, instrucao: 'Qual receita de passos é essa escala?' };
  }

  /* --- Fase 22: empilhar terças para formar o acorde --- */
  function fase22(n = 8) {
    const rodadas = [];
    const tipos = [
      { tipo: 'maior', nome: 'Maior',        regra: 'raiz + 3ª maior + 5ª justa' },
      { tipo: 'menor', nome: 'Menor',        regra: 'raiz + 3ª menor + 5ª justa' },
      { tipo: 'dim',   nome: 'Diminuto',     regra: 'raiz + 3ª menor + 5ª diminuta' },
      { tipo: 'dom7',  nome: 'Sétima (dominante)', regra: 'raiz + 3ª maior + 5ª justa + 7ª menor' }
    ];
    for (let i = 0; i < n; i++) {
      const def = escolher(tipos);
      const raiz = Teoria.naZona(60 + escolher([0, 2, 4, 5, 7]), 'medio');
      const ac = Teoria.acorde(raiz, def.tipo);
      rodadas.push({
        tipo: 'empilhar',
        raiz, acorde: ac, tipoAcorde: def.tipo,
        correta: def.nome,
        opcoes: embaralhar(unicos(tipos.map(t => t.nome))),
        oQueSoa: ac.hz,
        notasNomes: ac.notas.map(m => Teoria.grafiaDe(((m % 12) + 12) % 12)),
        dica: def.regra,
        todosTipos: tipos
      });
    }
    return { tipo: 'empilhar', rodadas, instrucao: 'Que acorde essas terças empilhadas formam?' };
  }

  /* --- Fase 23: arpejo, as notas do acorde em fila --- */
  function fase23(n = 8) {
    const rodadas = [];
    const tipos = ['maior', 'menor', 'dom7', 'm7', 'sus4'];
    for (let i = 0; i < n; i++) {
      const tipo = escolher(tipos);
      const raiz = Teoria.naZona(60 + escolher([0, 2, 4, 5, 7, 9]), 'medio');
      const arp = Teoria.arpejo(raiz, tipo);
      const ac = arp.acorde;
      // a pergunta: qual acorde esse arpejo está tocando?
      rodadas.push({
        tipo: 'arpejo',
        acorde: ac, tipoAcorde: tipo,
        arpejo: arp,
        correta: ac.nome,
        opcoes: embaralhar(unicos(tipos.map(t => Teoria.acorde(raiz, t).nome))),
        oQueSoa: arp.hz,
        notasNomes: arp.nomes,
        dica: 'São as notas do acorde, uma depois da outra. Subiu e voltou.',
        arpejoHz: arp.hz
      });
      const r = rodadas[i];
      if (!r.opcoes.map(String).includes(String(r.correta))) {
        r.opcoes[Math.floor(Math.random() * r.opcoes.length)] = r.correta;
      }
    }
    return { tipo: 'arpejo', rodadas, instrucao: 'Qual acorde esse arpejo está desenhando?' };
  }

  /* --- Fase 24: inversões, quem está no baixo --- */
  function fase24(n = 8) {
    const rodadas = [];
    const tipos = ['maior', 'menor'];
    for (let i = 0; i < n; i++) {
      const tipo = escolher(tipos);
      const raiz = Teoria.naZona(60 + escolher([0, 2, 4, 5, 7, 9]), 'medio');
      const qual = Math.floor(rnd(0, 3));
      const inv = Teoria.inversao(raiz, tipo, qual);
      const funcoes = ['a raiz', 'a terça', 'a quinta'];
      rodadas.push({
        tipo: 'inversao',
        inversao: inv, qual, tipoAcorde: tipo,
        correta: funcoes[qual],
        opcoes: ['a raiz', 'a terça', 'a quinta'],
        oQueSoa: inv.hz,
        nomeBaixo: inv.nomeBaixo,
        cifraBaixo: inv.cifraBaixo,
        dica: inv.desc,
        acorde: inv.nome
      });
    }
    return { tipo: 'inversao', rodadas, instrucao: 'Qual nota do acorde está embaixo?' };
  }

  /* --- Fase 25: os modos são a mesma escala de pontos diferentes --- */
  function fase25(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const tonica = Teoria.naZona(60 + escolher([0, 2, 5, 7]), 'medio');
      const modos = Teoria.modosDa(tonica);
      const idx = Math.floor(rnd(0, 7));
      const m = modos[idx];
      rodadas.push({
        tipo: 'modo_origem',
        modos, modo: m, idx, tonica,
        correta: String(m.grauInicio),
        opcoes: ['1', '2', '3', '4', '5', '6', '7'],
        oQueSoa: m.hz,
        hz: m.hz,
        dica: 'São as mesmas notas de sempre. A única coisa que muda é onde a escala começa.',
        nomeModo: m.nome,
        nomeTonica: m.nomeTonica,
        grauInicio: m.grauInicio,
        receita: m.receita,
        todasNotas: modos[0].notas.map(x => Teoria.hzDoMidi(x))
      });
    }
    return { tipo: 'modo_origem', rodadas, instrucao: 'De qual grau da escala maior essa melodia está partindo?' };
  }

  /* --- Fase 26: o que muda entre um modo e o vizinho --- */
  function fase26(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const tonica = Teoria.naZona(60 + escolher([0, 2, 5, 7]), 'medio');
      const modos = Teoria.modosDa(tonica);

      // Escolhe pares que diferem em POUCOS passos. Dois modos que divergem em
      // 4 lugares não ensinam nada; dois que divergem em 1 mostram o poder de
      // uma nota só — que é exatamente o que a fase quer ensinar.
      const pares = [];
      for (let x = 0; x < 7; x++) {
        for (let y = x + 1; y < 7; y++) {
          const d = Teoria.diferencaDeModos(modos[x], modos[y]);
          if (d.length) pares.push({ x, y, dif: d, peso: d.length });
        }
      }
      pares.sort((p, q) => p.peso - q.peso);
      const menorPeso = pares[0].peso;
      const escolhido = escolher(pares.filter(p => p.peso === menorPeso));

      const a = escolhido.x, b = escolhido.y;
      const mA = modos[a], mB = modos[b];
      const dif = escolhido.dif;
      rodadas.push({
        tipo: 'compara_modo',
        modoA: mA, modoB: mB, idxA: a, idxB: b,
        correta: String(dif[0] || 1),
        opcoes: ['1', '2', '3', '4', '5', '6', '7'],
        oQueSoa: mA.hz.concat([null]).concat(mB.hz).filter(x => x !== null),
        hzA: mA.hz, hzB: mB.hz,
        // A dica aponta ONDE OLHAR, não a resposta. Antes ela listava todos os
        // passos divergentes e entregava o gabarito.
        dica: 'Compare as duas receitas passo a passo, do começo. O primeiro lugar onde elas se separam é a resposta.',
        diferencas: dif,
        quantasDiferencas: dif.length,
        nomesA: mA.nome, nomesB: mB.nome,
        receitaA: mA.receita, receitaB: mB.receita
      });
      const r = rodadas[i];
      if (dif.length) {
        // a resposta é o primeiro passo onde divergem; garante que está nas opções
        if (!r.opcoes.map(String).includes(String(r.correta))) r.correta = String(dif[0]);
      } else {
        // modos idênticos não deveriam acontecer; usa o primeiro passo como fallback
        r.correta = '1';
      }
    }
    return { tipo: 'compara_modo', rodadas, instrucao: 'Em qual passo esses dois modos divergem?' };
  }

  /* ====================================================================
     CAPÍTULO DO RITMO E MÉTRICA (27-33)
     Segue o Bona: quaternário simples primeiro, depois os outros compassos.
     ==================================================================== */

  /* --- Fase 27: compasso 4/4 --- */
  function fase27(n = 8) {
    const rodadas = [];
    // foco no 4/4, mas com a fórmula como conteúdo
    for (let i = 0; i < n; i++) {
      const c = Teoria.compasso('4/4');
      const tipo = escolher(['forte', 'contagem', 'formula']);
      if (tipo === 'forte') {
        rodadas.push({
          tipo: 'tempo_forte',
          compasso: c,
          correta: String(escolher([1, 3])),
          opcoes: ['1', '2', '3', '4'],
          oQueSoa: [Teoria.hzDoMidi(60), Teoria.hzDoMidi(60), Teoria.hzDoMidi(60), Teoria.hzDoMidi(60)],
          dica: c.desc,
          bpm: 76
        });
      } else if (tipo === 'contagem') {
        rodadas.push({
          tipo: 'contagem',
          compasso: c,
          correta: '4',
          opcoes: ['2', '3', '4', '6'],
          oQueSoa: [Teoria.hzDoMidi(60), Teoria.hzDoMidi(60), Teoria.hzDoMidi(60), Teoria.hzDoMidi(60)],
          dica: 'Conte os tempos: UM-dois-três-quatro.',
          bpm: 76
        });
      } else {
        rodadas.push({
          tipo: 'formula',
          compasso: c,
          correta: '4/4',
          opcoes: ['2/4', '3/4', '4/4', '6/8'],
          oQueSoa: [Teoria.hzDoMidi(60), Teoria.hzDoMidi(60), Teoria.hzDoMidi(60), Teoria.hzDoMidi(60)],
          dica: 'Quatro tempos de semínima.',
          bpm: 76
        });
      }
    }
    return { tipo: 'compasso', rodadas, instrucao: 'Ouça o compasso. Quantos tempos?', bpm: 76 };
  }

  /* --- Fase 28: binário (2/4) e ternário (3/4) --- */
  function fase28(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const binario = Math.random() < 0.5;
      const c = Teoria.compasso(binario ? '2/4' : '3/4');
      rodadas.push({
        tipo: 'agrupamento',
        compasso: c,
        correta: binario ? 'marcha' : 'valsa',
        opcoes: ['marcha', 'valsa'],
        tempos: c.tempos,
        oQueSoa: Array(c.tempos).fill(Teoria.hzDoMidi(67)),
        dica: binario ? 'Grupos de dois: UM-dois.' : 'Grupos de três: UM-dois-três.',
        formula: c.formula,
        bpm: 84
      });
    }
    return { tipo: 'agrupamento', rodadas, instrucao: 'É marcha (dois) ou valsa (três)?', bpm: 84 };
  }

  /* --- Fase 29: simples x composto --- */
  function fase29(n = 8) {
    const rodadas = [];
    const pares = [['2/4', '6/8'], ['3/4', '9/8'], ['4/4', '12/8']];
    for (let i = 0; i < n; i++) {
      const [simples, composto] = escolher(pares);
      const ehSimples = Math.random() < 0.5;
      const formula = ehSimples ? simples : composto;
      const c = Teoria.compasso(formula);
      rodadas.push({
        tipo: 'subdivisao',
        compasso: c,
        par: { simples, composto },
        correta: c.subdivisao,
        opcoes: ['simples', 'composto'],
        formula,
        oQueSoa: Array(c.porCompasso).fill(Teoria.hzDoMidi(67)),
        dica: ehSimples
          ? 'Cada tempo se divide em DUAS partes.'
          : 'Cada tempo se divide em TRÊS partes. É o que faz ondular.',
        bpm: 72
      });
    }
    return { tipo: 'subdivisao', rodadas, instrucao: 'O compasso é simples (divide em 2) ou composto (divide em 3)?', bpm: 72 };
  }

  /* --- Fase 30: pausas --- */
  function fase30(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const p = escolher(Teoria.PAUSAS);
      rodadas.push({
        tipo: 'pausa',
        pausa: p,
        correta: p.figura,
        opcoes: embaralhar(unicos(Teoria.PAUSAS.map(x => x.figura))),
        oQueSoa: [Teoria.hzDoMidi(67)],
        aberta: true,
        dica: p.desc,
        silencioValor: p.valor,
        todas: Teoria.PAUSAS
      });
    }
    return { tipo: 'pausa', rodadas, instrucao: 'Quanto tempo de silêncio é isso?', pausas: Teoria.PAUSAS };
  }

  /* --- Fase 31: ponto e ligadura ---
     A pergunta é sobre DURAÇÃO: a nota dura 1, 1,5 ou 2 tempos. Então os
     casos só podem gerar essas três respostas — misturar "mínima" aqui
     criava uma resposta que não existia entre as opções.
     ------------------------------------------------------------------- */
  function fase31(n = 8) {
    const rodadas = [];
    const casos = [
      { nome: 'simples',  total: 1,   desc: 'Semínima normal: um tempo exato.',            cobertura: 1 },
      { nome: 'pontuada', total: 1.5, desc: 'Semínima pontuada: um tempo e meio. O ponto soma metade.', cobertura: 1 },
      { nome: 'ligada',   total: 2,   desc: 'Duas semínimas ligadas: dois tempos sem interrupção.',     cobertura: 2 }
    ];
    for (let i = 0; i < n; i++) {
      const c = escolher(casos);
      rodadas.push({
        tipo: 'duracao',
        caso: c,
        correta: c.nome,
        opcoes: ['simples', 'pontuada', 'ligada'],
        oQueSoa: [Teoria.hzDoMidi(67)],
        duracaoSeg: c.total * 0.6,
        dica: c.desc,
        total: c.total,
        formula: Teoria.pontuado(1)
      });
    }
    return { tipo: 'duracao', rodadas, instrucao: 'Essa nota dura um tempo, um e meio, ou dois?' };
  }

  /* --- Fase 32: síncope, contratempo, anacruse --- */
  function fase32(n = 8) {
    const rodadas = [];
    const padroes = [
      { nome: 'sincope',     padrao: [1.5, 0.5, 1, 1],     desc: 'O som começa no fraco e se prolonga no forte.' },
      { nome: 'sincope',     padrao: [0.5, 1, 0.5, 2],     desc: 'Acentos fora do tempo, atravessando a barra.' },
      { nome: 'contratempo', padrao: [0.5, 0.5, 0.5, 0.5, 0.5, 0.5, 0.5, 0.5], desc: 'O som cai onde deveria haver pausa.' },
      { nome: 'anacruse',    padrao: [0.5, 1, 1, 1, 0.5],  desc: 'Começa antes do compasso: uma entrada incompleta.' },
      { nome: 'regular',     padrao: [1, 1, 1, 1],         desc: 'Tudo no tempo, sem deslocamento.' }
    ];
    for (let i = 0; i < n; i++) {
      const p = escolher(padroes);
      rodadas.push({
        tipo: 'deslocamento',
        padrao: p.padrao,
        nomePadrao: p.nome,
        correta: p.nome,
        opcoes: ['regular', 'sincope', 'contratempo', 'anacruse'],
        oQueSoa: p.padrao.map(() => Teoria.hzDoMidi(67)),
        sequencia: p.padrao.map(d => [Teoria.hzDoMidi(67), d * 0.5]),
        dica: p.desc,
        bpm: 80
      });
    }
    return { tipo: 'deslocamento', rodadas, instrucao: 'O acento está no tempo ou deslocado?', bpm: 80 };
  }

  /* --- Fase 33: tercinas e subdivisões --- */
  function fase33(n = 8) {
    const rodadas = [];
    for (let i = 0; i < n; i++) {
      const s = escolher(Teoria.SUBDIVISOES);
      const cabendo = s.quantas === 3 ? 2 : (s.quantas >= 5 ? 4 : 1);
      rodadas.push({
        tipo: 'subdivisao_qtd',
        subdivisao: s,
        cabendo,
        correta: String(s.quantas),
        opcoes: ['2', '3', '4', '5', '6'],
        oQueSoa: Array(s.quantas).fill(Teoria.hzDoMidi(67)),
        dica: s.desc,
        bpm: 76,
        todas: Teoria.SUBDIVISOES
      });
    }
    return { tipo: 'subdivisao_qtd', rodadas, instrucao: 'Quantas notas cabem no espaço?', bpm: 76 };
  }

  /* --- gerador principal --- */
  const GERADORES = {
    1: fase1, 2: fase2, 3: fase3, 4: fase4, 5: fase5, 6: fase6, 7: fase7,
    8: fase8, 9: fase9, 10: fase10, 11: fase11, 12: fase12, 13: fase13, 14: fase14,
    15: fase15, 16: fase16, 17: fase17, 18: fase18, 19: fase19, 20: fase20, 21: fase27,
    22: fase28, 23: fase29, 24: fase30, 25: fase31, 26: fase32, 27: fase33, 28: fase21,
    29: fase22, 30: fase23, 31: fase24, 32: fase25, 33: fase26
  }

  // A fase 1 tem mecânica própria (ritmo), então `rodadas` é um CONTADOR,
  // não uma lista. Só as fases de pergunta usam a lista de rodadas.
  function gerar(id, modo = 'aprender') {
    const gen = GERADORES[id];
    if (!gen) return null;
    const base = gen();
    const temLista = Array.isArray(base.rodadas);
    if (!temLista) { base.modo = modo; return base; }

    // 'dominar' é mais longo e sem dica visível
    if (modo === 'dominar') {
      base.rodadas = base.rodadas.concat(base.rodadas);
      if (base.rodadas.length > 14) base.rodadas = base.rodadas.slice(0, 14);
      base.semDica = true;
    }
    if (modo === 'aprender') {
      base.rodadas = base.rodadas.slice(0, Math.max(3, Math.ceil(base.rodadas.length / 2)));
      base.comDica = true;
    }
    base.modo = modo;
    return base;
  }

  /** Critério de domínio: acertar o suficiente sem errar demais. */
  function dominou(acertos, totalRodadas) {
    if (!totalRodadas) return false;
    return acertos / totalRodadas >= 0.8;
  }

  return { LISTA, porId, total, gerar, dominou, CADENCIAS, MODOS_FASE };
})();

window.Fases = Fases;
