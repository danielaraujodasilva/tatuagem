<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Mapa Interativo de Orçamento Tattoo</title>

<style>
:root {
  --bg: #070707;
  --card: #121212;
  --card2: #181818;
  --red: #d7192a;
  --red2: #ff3048;
  --text: #ffffff;
  --muted: #b8b8b8;
  --line: #686868;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  min-height: 100vh;
  font-family: Arial, Helvetica, sans-serif;
  background:
    radial-gradient(circle at top left, rgba(170, 0, 20, 0.3), transparent 35%),
    radial-gradient(circle at bottom right, rgba(120, 0, 10, 0.22), transparent 35%),
    var(--bg);
  color: var(--text);
  padding: 28px 16px;
}

.container {
  max-width: 1240px;
  margin: auto;
  display: grid;
  grid-template-columns: 1fr 390px;
  gap: 24px;
  align-items: start;
}

.header {
  grid-column: 1 / -1;
  text-align: center;
  margin-bottom: 8px;
}

.header h1 {
  margin: 0;
  font-size: clamp(30px, 5vw, 58px);
  text-transform: uppercase;
  letter-spacing: 2px;
}

.header p {
  max-width: 720px;
  margin: 12px auto 0;
  color: var(--muted);
  line-height: 1.55;
}

.card {
  background: linear-gradient(180deg, rgba(24,24,24,.96), rgba(10,10,10,.96));
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 26px;
  box-shadow: 0 24px 70px rgba(0,0,0,.5);
}

.map-card {
  padding: 20px;
}

.tabs {
  display: flex;
  justify-content: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.tabs button {
  border: 1px solid rgba(255,255,255,.14);
  background: #101010;
  color: white;
  border-radius: 999px;
  padding: 11px 24px;
  cursor: pointer;
  font-weight: 800;
  letter-spacing: .4px;
  transition: .2s;
}

.tabs button:hover,
.tabs button.active {
  background: var(--red);
  border-color: var(--red);
  box-shadow: 0 0 22px rgba(215,25,42,.35);
}

.map-wrap {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 760px;
  overflow: hidden;
}

svg {
  width: 100%;
  max-width: 520px;
  height: auto;
}

.base {
  fill: #151515;
  stroke: #525252;
  stroke-width: 2;
}

.joint-line {
  fill: none;
  stroke: rgba(255,255,255,.12);
  stroke-width: 1.4;
}

.body-part {
  fill: rgba(215,25,42,.13);
  stroke: rgba(255,255,255,.45);
  stroke-width: 1.25;
  cursor: pointer;
  transition: .18s;
}

.body-part:hover {
  fill: rgba(215,25,42,.58);
  stroke: white;
  filter: drop-shadow(0 0 8px rgba(255,48,72,.8));
}

.body-part.selected {
  fill: rgba(255,48,72,.82);
  stroke: white;
  filter: drop-shadow(0 0 12px rgba(255,48,72,.9));
}

.face-line {
  fill: none;
  stroke: rgba(255,255,255,.22);
  stroke-width: 1.2;
}

.hidden {
  display: none;
}

.info {
  padding: 26px;
  position: sticky;
  top: 20px;
}

.badge {
  display: inline-block;
  color: #ffc3c9;
  border: 1px solid rgba(255,48,72,.65);
  background: rgba(215,25,42,.14);
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: bold;
  margin-bottom: 16px;
}

.info h2 {
  margin: 0 0 10px;
  font-size: 34px;
  line-height: 1.1;
}

.price {
  font-size: 34px;
  color: var(--red2);
  font-weight: 900;
  margin: 14px 0;
}

.desc {
  color: #d7d7d7;
  line-height: 1.6;
  margin-bottom: 20px;
}

.details {
  display: grid;
  gap: 10px;
  margin-bottom: 22px;
}

.detail {
  background: rgba(255,255,255,.04);
  border: 1px solid rgba(255,255,255,.08);
  padding: 13px 14px;
  border-radius: 16px;
  color: #cfcfcf;
}

.detail strong {
  color: white;
}

.whatsapp {
  display: block;
  text-align: center;
  text-decoration: none;
  background: #25d366;
  color: #06170b;
  padding: 15px 18px;
  border-radius: 16px;
  font-weight: 900;
  transition: .2s;
}

.whatsapp:hover {
  transform: translateY(-2px);
  filter: brightness(1.08);
}

.note {
  color: #969696;
  font-size: 13px;
  line-height: 1.45;
  margin-top: 16px;
}

.legend {
  margin-top: 14px;
  color: #aaa;
  font-size: 13px;
  text-align: center;
}

@media (max-width: 920px) {
  .container {
    grid-template-columns: 1fr;
  }

  .map-wrap {
    min-height: auto;
  }

  .info {
    position: static;
  }
}
</style>
</head>

<body>

<main class="container">

  <header class="header">
    <h1>Mapa de Orçamento Tattoo</h1>
    <p>
      Clique em uma região do corpo para ver uma estimativa de valor, sessão e indicação.
      Um jeito civilizado de evitar aquele clássico “quanto fica uma tattoo?” sem tamanho, sem local e sem noção.
    </p>
  </header>

  <section class="card map-card">
    <div class="tabs">
      <button class="active" onclick="trocarVista('frente', this)">Frente</button>
      <button onclick="trocarVista('costas', this)">Costas</button>
    </div>

    <div class="map-wrap">

      <!-- FRENTE -->
      <svg id="frente" viewBox="0 0 420 820" aria-label="Mapa corporal frente">

        <!-- base cabeça e corpo -->
        <ellipse class="base" cx="210" cy="72" rx="38" ry="46"/>
        <path class="base" d="M175 120 Q210 145 245 120 L258 170 Q286 190 305 245 L288 330 Q282 420 270 505 L262 720 Q238 755 218 722 L210 520 L202 722 Q182 755 158 720 L150 505 Q138 420 132 330 L115 245 Q134 190 162 170 Z"/>

        <!-- cabeça -->
        <path class="body-part" data-area="cabeca" d="M172 65 Q176 25 210 24 Q244 25 248 65 Q250 105 210 118 Q170 105 172 65Z"/>
        <path class="face-line" d="M190 70 Q210 78 230 70"/>
        <path class="face-line" d="M196 92 Q210 100 224 92"/>

        <!-- pescoço -->
        <path class="body-part" data-area="pescoco" d="M185 112 L235 112 L243 150 Q210 166 177 150 Z"/>

        <!-- ombros -->
        <path class="body-part" data-area="ombros" d="M140 155 Q210 122 280 155 L258 190 Q210 170 162 190 Z"/>

        <!-- peito -->
        <path class="body-part" data-area="peito" d="M160 190 Q210 168 260 190 L252 278 Q210 302 168 278 Z"/>

        <!-- abdomen -->
        <path class="body-part" data-area="abdomen" d="M168 282 Q210 302 252 282 L242 382 Q210 410 178 382 Z"/>

        <!-- costelas frente esquerda/direita -->
        <path class="body-part" data-area="costela" d="M132 245 Q145 210 164 194 L168 375 Q148 360 138 320 Z"/>
        <path class="body-part" data-area="costela" d="M288 245 Q275 210 256 194 L252 375 Q272 360 282 320 Z"/>

        <!-- quadril -->
        <path class="body-part" data-area="quadril" d="M176 388 Q210 410 244 388 L260 455 Q210 480 160 455 Z"/>

        <!-- braços -->
        <path class="body-part" data-area="braco_externo" d="M138 165 Q100 190 84 255 L102 268 Q126 210 160 188 Z"/>
        <path class="body-part" data-area="braco_externo" d="M282 165 Q320 190 336 255 L318 268 Q294 210 260 188 Z"/>

        <path class="body-part" data-area="antebraco_interno" d="M101 270 Q85 335 93 410 L118 408 Q124 335 103 270 Z"/>
        <path class="body-part" data-area="antebraco_interno" d="M319 270 Q335 335 327 410 L302 408 Q296 335 317 270 Z"/>

        <!-- pulsos -->
        <path class="body-part" data-area="pulso" d="M93 410 L118 408 L121 432 L92 434 Z"/>
        <path class="body-part" data-area="pulso" d="M327 410 L302 408 L299 432 L328 434 Z"/>

        <!-- mãos frente -->
        <path class="body-part" data-area="mao" d="M88 434 Q106 422 124 436 L126 484 Q106 502 86 484 Z"/>
        <path class="body-part" data-area="mao" d="M332 434 Q314 422 296 436 L294 484 Q314 502 334 484 Z"/>

        <!-- dedos mão esquerda -->
        <path class="body-part" data-area="dedos_mao" d="M88 484 L95 484 L94 530 Q86 532 84 524 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M98 491 L105 491 L104 538 Q96 540 95 531 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M108 491 L115 491 L116 535 Q108 539 106 529 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M118 484 L126 484 L130 520 Q124 529 119 520 Z"/>

        <!-- dedos mão direita -->
        <path class="body-part" data-area="dedos_mao" d="M332 484 L325 484 L326 530 Q334 532 336 524 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M322 491 L315 491 L316 538 Q324 540 325 531 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M312 491 L305 491 L304 535 Q312 539 314 529 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M302 484 L294 484 L290 520 Q296 529 301 520 Z"/>

        <!-- coxas -->
        <path class="body-part" data-area="coxa_frontal" d="M160 456 Q186 478 205 470 L201 585 Q188 628 158 612 L150 505 Z"/>
        <path class="body-part" data-area="coxa_frontal" d="M260 456 Q234 478 215 470 L219 585 Q232 628 262 612 L270 505 Z"/>

        <!-- joelhos -->
        <ellipse class="body-part" data-area="joelho" cx="181" cy="612" rx="25" ry="28"/>
        <ellipse class="body-part" data-area="joelho" cx="239" cy="612" rx="25" ry="28"/>

        <!-- canelas -->
        <path class="body-part" data-area="canela" d="M158 638 Q182 648 201 638 L196 720 Q178 742 158 720 Z"/>
        <path class="body-part" data-area="canela" d="M262 638 Q238 648 219 638 L224 720 Q242 742 262 720 Z"/>

        <!-- tornozelos -->
        <path class="body-part" data-area="tornozelo" d="M158 720 L196 720 L198 745 Q176 758 154 742 Z"/>
        <path class="body-part" data-area="tornozelo" d="M262 720 L224 720 L222 745 Q244 758 266 742 Z"/>

        <!-- pés -->
        <path class="body-part" data-area="pe" d="M154 744 Q178 734 202 752 Q210 772 188 782 L146 776 Q136 760 154 744 Z"/>
        <path class="body-part" data-area="pe" d="M266 744 Q242 734 218 752 Q210 772 232 782 L274 776 Q284 760 266 744 Z"/>

        <!-- dedos dos pés -->
        <circle class="body-part" data-area="dedos_pe" cx="147" cy="777" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="158" cy="780" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="170" cy="782" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="272" cy="777" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="261" cy="780" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="249" cy="782" r="5"/>

      </svg>

      <!-- COSTAS -->
      <svg id="costas" class="hidden" viewBox="0 0 420 820" aria-label="Mapa corporal costas">

        <ellipse class="base" cx="210" cy="72" rx="38" ry="46"/>
        <path class="base" d="M175 120 Q210 145 245 120 L258 170 Q286 190 305 245 L288 330 Q282 420 270 505 L262 720 Q238 755 218 722 L210 520 L202 722 Q182 755 158 720 L150 505 Q138 420 132 330 L115 245 Q134 190 162 170 Z"/>

        <path class="body-part" data-area="cabeca" d="M172 65 Q176 25 210 24 Q244 25 248 65 Q250 105 210 118 Q170 105 172 65Z"/>
        <path class="body-part" data-area="nuca" d="M185 112 L235 112 L243 150 Q210 166 177 150 Z"/>

        <path class="body-part" data-area="ombros" d="M140 155 Q210 122 280 155 L258 190 Q210 170 162 190 Z"/>

        <path class="body-part" data-area="costas_alta" d="M160 190 Q210 165 260 190 L256 292 Q210 318 164 292 Z"/>
        <path class="body-part" data-area="costas_baixa" d="M164 296 Q210 318 256 296 L244 390 Q210 418 176 390 Z"/>

        <path class="body-part" data-area="lombar" d="M176 392 Q210 418 244 392 L260 455 Q210 480 160 455 Z"/>

        <path class="body-part" data-area="braco_externo" d="M138 165 Q100 190 84 255 L102 268 Q126 210 160 188 Z"/>
        <path class="body-part" data-area="braco_externo" d="M282 165 Q320 190 336 255 L318 268 Q294 210 260 188 Z"/>

        <path class="body-part" data-area="antebraco_externo" d="M101 270 Q85 335 93 410 L118 408 Q124 335 103 270 Z"/>
        <path class="body-part" data-area="antebraco_externo" d="M319 270 Q335 335 327 410 L302 408 Q296 335 317 270 Z"/>

        <path class="body-part" data-area="mao" d="M88 434 Q106 422 124 436 L126 484 Q106 502 86 484 Z"/>
        <path class="body-part" data-area="mao" d="M332 434 Q314 422 296 436 L294 484 Q314 502 334 484 Z"/>

        <path class="body-part" data-area="dedos_mao" d="M88 484 L95 484 L94 530 Q86 532 84 524 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M98 491 L105 491 L104 538 Q96 540 95 531 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M108 491 L115 491 L116 535 Q108 539 106 529 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M118 484 L126 484 L130 520 Q124 529 119 520 Z"/>

        <path class="body-part" data-area="dedos_mao" d="M332 484 L325 484 L326 530 Q334 532 336 524 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M322 491 L315 491 L316 538 Q324 540 325 531 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M312 491 L305 491 L304 535 Q312 539 314 529 Z"/>
        <path class="body-part" data-area="dedos_mao" d="M302 484 L294 484 L290 520 Q296 529 301 520 Z"/>

        <path class="body-part" data-area="gluteo" d="M160 456 Q185 474 207 464 L207 535 Q180 560 150 520 Z"/>
        <path class="body-part" data-area="gluteo" d="M260 456 Q235 474 213 464 L213 535 Q240 560 270 520 Z"/>

        <path class="body-part" data-area="coxa_posterior" d="M150 524 Q180 560 207 536 L201 612 Q180 632 158 612 Z"/>
        <path class="body-part" data-area="coxa_posterior" d="M270 524 Q240 560 213 536 L219 612 Q240 632 262 612 Z"/>

        <ellipse class="body-part" data-area="joelho_posterior" cx="181" cy="612" rx="25" ry="22"/>
        <ellipse class="body-part" data-area="joelho_posterior" cx="239" cy="612" rx="25" ry="22"/>

        <path class="body-part" data-area="panturrilha" d="M158 638 Q182 650 201 638 L196 720 Q178 742 158 720 Z"/>
        <path class="body-part" data-area="panturrilha" d="M262 638 Q238 650 219 638 L224 720 Q242 742 262 720 Z"/>

        <path class="body-part" data-area="tornozelo" d="M158 720 L196 720 L198 745 Q176 758 154 742 Z"/>
        <path class="body-part" data-area="tornozelo" d="M262 720 L224 720 L222 745 Q244 758 266 742 Z"/>

        <path class="body-part" data-area="pe" d="M154 744 Q178 734 202 752 Q210 772 188 782 L146 776 Q136 760 154 744 Z"/>
        <path class="body-part" data-area="pe" d="M266 744 Q242 734 218 752 Q210 772 232 782 L274 776 Q284 760 266 744 Z"/>

      </svg>

    </div>

    <div class="legend">
      Passe o mouse ou toque nas áreas. Sim, até dedo tem preço. Capitalismo anatômico.
    </div>
  </section>

  <aside class="card info">
    <span class="badge">Área selecionada</span>
    <h2 id="titulo">Selecione uma área</h2>
    <div class="price" id="preco">---</div>

    <p class="desc" id="descricao">
      Clique em uma parte do corpo para visualizar uma estimativa inicial de orçamento.
    </p>

    <div class="details">
      <div class="detail"><strong>Sessões:</strong> <span id="sessoes">---</span></div>
      <div class="detail"><strong>Dor estimada:</strong> <span id="dor">---</span></div>
      <div class="detail"><strong>Indicação:</strong> <span id="indicacao">---</span></div>
    </div>

    <a id="zap" class="whatsapp" href="#" target="_blank">
      Quero orçamento dessa área
    </a>

    <p class="note">
      *Valores demonstrativos. O preço final depende de tamanho, complexidade, pele, detalhes, cobertura e do quanto o cliente quer transformar um antebraço numa Capela Sistina.
    </p>
  </aside>

</main>

<script>
const whatsapp = "5511947573311";

const dados = {
  cabeca: {
    titulo: "Cabeça",
    preco: "a partir de R$ 2.500",
    descricao: "Área extrema e muito visível. Exige projeto limpo, leitura forte e coragem, porque depois não dá pra fingir que foi fase.",
    sessoes: "1 a 3 sessões",
    dor: "Alta",
    indicacao: "Projetos ousados, ornamentais ou blackwork"
  },
  pescoco: {
    titulo: "Pescoço",
    preco: "R$ 1.500 a R$ 3.500",
    descricao: "Região de alto impacto visual, boa para composições verticais, lettering e fechamentos.",
    sessoes: "1 a 2 sessões",
    dor: "Alta",
    indicacao: "Projetos visíveis e marcantes"
  },
  nuca: {
    titulo: "Nuca",
    preco: "R$ 900 a R$ 2.000",
    descricao: "Boa região para peças menores, símbolos, ornamentos ou complemento de costas/pescoço.",
    sessoes: "1 sessão",
    dor: "Média",
    indicacao: "Projetos pequenos e médios"
  },
  ombros: {
    titulo: "Ombros",
    preco: "R$ 1.800 a R$ 4.000",
    descricao: "Excelente área para encaixe anatômico, fechamentos de braço e projetos com bastante presença.",
    sessoes: "1 a 3 sessões",
    dor: "Média",
    indicacao: "Fechamento de braço, mandalas, realismo e blackwork"
  },
  peito: {
    titulo: "Peito",
    preco: "R$ 2.500 a R$ 6.000",
    descricao: "Área grande, forte e muito visual. Ideal para projetos simétricos ou peças centrais de impacto.",
    sessoes: "2 a 5 sessões",
    dor: "Alta",
    indicacao: "Fechamentos grandes e composições centrais"
  },
  abdomen: {
    titulo: "Abdômen",
    preco: "R$ 2.000 a R$ 5.000",
    descricao: "Região ampla, mas com pele mais sensível e variação de elasticidade. Exige planejamento decente, olha que milagre.",
    sessoes: "2 a 4 sessões",
    dor: "Alta",
    indicacao: "Projetos verticais, centrais e fechamentos"
  },
  costela: {
    titulo: "Costela",
    preco: "R$ 1.800 a R$ 5.500",
    descricao: "Área excelente para composições laterais. Dor mais intensa, porque aparentemente o corpo humano foi desenhado por alguém sem atendimento ao cliente.",
    sessoes: "1 a 4 sessões",
    dor: "Alta",
    indicacao: "Florais, lettering, realismo e projetos verticais"
  },
  quadril: {
    titulo: "Quadril / Virilha",
    preco: "R$ 1.500 a R$ 4.000",
    descricao: "Boa área para peças sensuais, ornamentais ou complementos de fechamento de perna e abdômen.",
    sessoes: "1 a 3 sessões",
    dor: "Média/Alta",
    indicacao: "Projetos ornamentais, fineline e composições laterais"
  },
  costas_alta: {
    titulo: "Costas Alta",
    preco: "R$ 2.800 a R$ 7.000",
    descricao: "Região nobre para peças grandes, simétricas e com leitura forte.",
    sessoes: "2 a 6 sessões",
    dor: "Média",
    indicacao: "Painéis grandes, realismo e fechamento de costas"
  },
  costas_baixa: {
    titulo: "Costas Baixa",
    preco: "R$ 2.000 a R$ 5.000",
    descricao: "Boa área para complementar projetos de costas ou construir composições horizontais e ornamentais.",
    sessoes: "1 a 4 sessões",
    dor: "Média/Alta",
    indicacao: "Complementos, blackwork e peças grandes"
  },
  lombar: {
    titulo: "Lombar",
    preco: "R$ 1.500 a R$ 4.000",
    descricao: "Área boa para peças centrais, ornamentais e complementos de fechamento.",
    sessoes: "1 a 3 sessões",
    dor: "Média/Alta",
    indicacao: "Ornamentos, mandalas e composições simétricas"
  },
  braco_externo: {
    titulo: "Braço Externo",
    preco: "R$ 1.800 a R$ 4.500",
    descricao: "Uma das melhores áreas para tatuagem: boa leitura, boa resistência e encaixe anatômico ótimo.",
    sessoes: "1 a 3 sessões",
    dor: "Média",
    indicacao: "Realismo, blackwork, anime, fechamento de braço"
  },
  antebraco_interno: {
    titulo: "Antebraço Interno",
    preco: "R$ 1.200 a R$ 3.200",
    descricao: "Área muito procurada, boa para projetos detalhados e com leitura frontal.",
    sessoes: "1 a 2 sessões",
    dor: "Média/Alta",
    indicacao: "Projetos médios, fineline, realismo e lettering"
  },
  antebraco_externo: {
    titulo: "Antebraço Externo",
    preco: "R$ 1.200 a R$ 3.000",
    descricao: "Área resistente e excelente para contraste. Boa para quem quer ver a tattoo sem precisar virar um contorcionista.",
    sessoes: "1 a 2 sessões",
    dor: "Média",
    indicacao: "Projetos visíveis e fechamentos"
  },
  pulso: {
    titulo: "Pulso",
    preco: "R$ 600 a R$ 1.500",
    descricao: "Área menor e delicada. Boa para símbolos, pulseiras, detalhes e complementos.",
    sessoes: "1 sessão",
    dor: "Média/Alta",
    indicacao: "Pequenas peças, lettering e ornamentos"
  },
  mao: {
    titulo: "Mão",
    preco: "R$ 900 a R$ 2.500",
    descricao: "Área muito visível e com maior desgaste. Precisa de desenho forte e manutenção mais consciente.",
    sessoes: "1 a 2 sessões",
    dor: "Alta",
    indicacao: "Blackwork, símbolos, lettering e ornamentos"
  },
  dedos_mao: {
    titulo: "Dedos da mão",
    preco: "R$ 400 a R$ 1.200",
    descricao: "Área pequena, muito visível e com alto desgaste. Não é lugar para microdetalhe milagroso, infelizmente a pele não leu seu briefing.",
    sessoes: "1 sessão",
    dor: "Alta",
    indicacao: "Símbolos simples, letras e ornamentos pequenos"
  },
  coxa_frontal: {
    titulo: "Coxa Frontal",
    preco: "R$ 2.500 a R$ 6.500",
    descricao: "Área ampla, ótima para peças grandes e fechamentos. Boa leitura e bastante espaço para composição.",
    sessoes: "2 a 5 sessões",
    dor: "Média",
    indicacao: "Fechamentos, realismo, anime, blackwork e grandes peças"
  },
  coxa_posterior: {
    titulo: "Coxa Posterior",
    preco: "R$ 2.300 a R$ 6.000",
    descricao: "Área excelente para continuidade de fechamento de perna, com boa composição vertical.",
    sessoes: "2 a 5 sessões",
    dor: "Média/Alta",
    indicacao: "Fechamentos de perna e projetos verticais"
  },
  gluteo: {
    titulo: "Glúteo",
    preco: "R$ 1.800 a R$ 4.500",
    descricao: "Área boa para composições grandes e complementos de perna/quadril.",
    sessoes: "1 a 3 sessões",
    dor: "Média",
    indicacao: "Projetos ornamentais, blackwork e fechamentos"
  },
  joelho: {
    titulo: "Joelho",
    preco: "R$ 1.200 a R$ 3.500",
    descricao: "Área complexa, dobra bastante e exige desenho inteligente. O joelho não facilita, porque claro que não.",
    sessoes: "1 a 3 sessões",
    dor: "Alta",
    indicacao: "Ornamentos, mandalas e blackwork"
  },
  joelho_posterior: {
    titulo: "Parte de trás do joelho",
    preco: "R$ 900 a R$ 2.500",
    descricao: "Área sensível e delicada, geralmente usada para complementar fechamento de perna.",
    sessoes: "1 a 2 sessões",
    dor: "Alta",
    indicacao: "Complementos, ornamentos e detalhes"
  },
  canela: {
    titulo: "Canela",
    preco: "R$ 1.800 a R$ 4.500",
    descricao: "Área de muito impacto visual. Dor mais chatinha por causa do osso, esse detalhe inútil que insiste em existir.",
    sessoes: "1 a 3 sessões",
    dor: "Alta",
    indicacao: "Projetos verticais, realismo, anime e blackwork"
  },
  panturrilha: {
    titulo: "Panturrilha",
    preco: "R$ 1.800 a R$ 4.500",
    descricao: "Boa curvatura, ótima para peças médias e grandes. Funciona muito bem em fechamento de perna.",
    sessoes: "1 a 3 sessões",
    dor: "Média",
    indicacao: "Peças médias, grandes e fechamento de perna"
  },
  tornozelo: {
    titulo: "Tornozelo",
    preco: "R$ 600 a R$ 1.800",
    descricao: "Área menor, boa para detalhes, tornozeleiras e complementos.",
    sessoes: "1 sessão",
    dor: "Média/Alta",
    indicacao: "Ornamentos, símbolos e fineline"
  },
  pe: {
    titulo: "Pé",
    preco: "R$ 900 a R$ 2.500",
    descricao: "Área visível, sensível e com desgaste. Precisa de projeto simples, forte e bem planejado.",
    sessoes: "1 a 2 sessões",
    dor: "Alta",
    indicacao: "Símbolos, ornamentos e projetos pequenos/médios"
  },
  dedos_pe: {
    titulo: "Dedos do pé",
    preco: "R$ 400 a R$ 1.000",
    descricao: "Área pequena, sensível e com alto desgaste. Melhor para marcas simples e sem firula microscópica.",
    sessoes: "1 sessão",
    dor: "Alta",
    indicacao: "Pequenos símbolos e letras"
  }
};

function selecionarParte(el) {
  document.querySelectorAll(".body-part").forEach(p => p.classList.remove("selected"));
  el.classList.add("selected");

  const area = el.dataset.area;
  const item = dados[area];

  if (!item) return;

  document.getElementById("titulo").innerText = item.titulo;
  document.getElementById("preco").innerText = item.preco;
  document.getElementById("descricao").innerText = item.descricao;
  document.getElementById("sessoes").innerText = item.sessoes;
  document.getElementById("dor").innerText = item.dor;
  document.getElementById("indicacao").innerText = item.indicacao;

  const msg = encodeURIComponent(
    `Olá! Tenho interesse em fazer uma tatuagem na região: ${item.titulo}. Gostaria de um orçamento.`
  );

  document.getElementById("zap").href = `https://wa.me/${whatsapp}?text=${msg}`;
}

document.querySelectorAll(".body-part").forEach(part => {
  part.addEventListener("click", () => selecionarParte(part));
});

function trocarVista(vista, botao) {
  document.getElementById("frente").classList.add("hidden");
  document.getElementById("costas").classList.add("hidden");
  document.getElementById(vista).classList.remove("hidden");

  document.querySelectorAll(".tabs button").forEach(btn => btn.classList.remove("active"));
  botao.classList.add("active");

  document.querySelectorAll(".body-part").forEach(p => p.classList.remove("selected"));
}
</script>

</body>
</html>