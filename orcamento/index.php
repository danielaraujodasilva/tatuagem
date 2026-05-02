<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Orçamento de Tattoo</title>
<link rel="icon" href="data:," />
<style>
:root {
  --bg: #080808;
  --line: rgba(255,255,255,.12);
  --text: #fff;
  --muted: #b9b9b9;
  --red: #d7192a;
  --red-2: #ff344b;
  --green: #25d366;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  min-height: 100vh;
  font-family: Arial, Helvetica, sans-serif;
  color: var(--text);
  background:
    linear-gradient(140deg, rgba(215,25,42,.17), transparent 30%),
    linear-gradient(320deg, rgba(255,255,255,.06), transparent 26%),
    var(--bg);
  padding: 22px 14px 92px;
}

button, input, textarea { font: inherit; }
button { cursor: pointer; }

.container {
  width: min(1260px, 100%);
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 410px;
  gap: 18px;
  align-items: start;
}

.header {
  grid-column: 1 / -1;
  padding: 4px 2px;
}

.header h1 {
  margin: 0;
  font-size: clamp(30px, 4.4vw, 58px);
  line-height: .95;
  text-transform: uppercase;
  letter-spacing: 0;
}

.header p {
  max-width: 780px;
  margin: 10px 0 0;
  color: var(--muted);
  line-height: 1.48;
}

.card {
  background: linear-gradient(180deg, rgba(27,27,27,.98), rgba(9,9,9,.98));
  border: 1px solid var(--line);
  border-radius: 8px;
  box-shadow: 0 18px 54px rgba(0,0,0,.45);
}

.map-card { padding: 16px; }

.topbar {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
}

.segmented {
  display: inline-flex;
  gap: 4px;
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 8px;
  background: #0c0c0c;
}

.segmented button, .ghost-btn {
  min-height: 40px;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 10px 13px;
  background: transparent;
  color: #fff;
  font-weight: 900;
}

.segmented button.active {
  background: var(--red);
  border-color: rgba(255,255,255,.14);
}

.ghost-btn {
  background: #121212;
  border-color: var(--line);
}

.map-wrap {
  position: relative;
  display: grid;
  place-items: center;
  min-height: 760px;
  overflow: hidden;
}

.body-map {
  width: min(100%, 570px);
  height: auto;
}

.hidden { display: none; }

.body-base {
  fill: #171717;
  stroke: rgba(255,255,255,.46);
  stroke-width: 2.4;
  filter: drop-shadow(0 24px 32px rgba(0,0,0,.45));
}

.body-shade { fill: rgba(255,255,255,.045); }

.body-line {
  fill: none;
  stroke: rgba(255,255,255,.13);
  stroke-width: 1.3;
}

.body-part {
  fill: transparent;
  stroke: transparent;
  stroke-width: 1.25;
  cursor: pointer;
  transition: fill .18s ease, stroke .18s ease, filter .18s ease, opacity .18s ease;
}

.body-part:hover, .body-part.focused {
  fill: rgba(215,25,42,.34);
  stroke: rgba(255,255,255,.75);
  filter: drop-shadow(0 0 9px rgba(255,52,75,.85));
}

.body-part.selected {
  fill: rgba(255,52,75,.68);
  stroke: #fff;
  filter: drop-shadow(0 0 14px rgba(255,52,75,.95));
}

.body-part.disabled {
  pointer-events: none;
}

.tooltip {
  position: fixed;
  z-index: 10;
  pointer-events: none;
  opacity: 0;
  transform: translate(12px, 12px);
  padding: 8px 10px;
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 6px;
  background: #050505;
  color: #fff;
  font-weight: 900;
  box-shadow: 0 10px 28px rgba(0,0,0,.45);
  transition: opacity .12s ease;
}

.tooltip.show { opacity: 1; }

.map-hint {
  max-width: 560px;
  margin: 12px auto 0;
  color: var(--muted);
  font-size: 13px;
  text-align: center;
  line-height: 1.4;
}

.info {
  position: sticky;
  top: 14px;
  padding: 18px;
}

.badge-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.badge {
  display: inline-flex;
  padding: 7px 10px;
  border: 1px solid rgba(255,52,75,.58);
  border-radius: 6px;
  background: rgba(215,25,42,.14);
  color: #ffcbd0;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.info h2 {
  margin: 0 0 8px;
  font-size: 28px;
  line-height: 1.08;
}

.selection-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  min-height: 32px;
  margin-bottom: 10px;
}

.pill {
  border: 1px solid rgba(255,255,255,.16);
  border-radius: 6px;
  padding: 7px 9px;
  background: rgba(255,255,255,.06);
  color: #fff;
  font-size: 13px;
  font-weight: 900;
}

.price {
  margin: 10px 0;
  color: var(--red-2);
  font-size: 34px;
  font-weight: 900;
}

.desc {
  color: #dfdfdf;
  line-height: 1.5;
  margin: 10px 0 16px;
}

.form-grid {
  display: grid;
  gap: 10px;
}

.field {
  display: grid;
  gap: 6px;
}

.field label {
  color: #ddd;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.field input, .field textarea {
  width: 100%;
  border: 1px solid rgba(255,255,255,.13);
  border-radius: 6px;
  background: #0d0d0d;
  color: #fff;
  padding: 11px 10px;
  outline: none;
}

.field textarea {
  min-height: 92px;
  resize: vertical;
}

.upload-preview {
  display: none;
  grid-template-columns: 72px 1fr;
  gap: 10px;
  align-items: center;
  margin-top: 4px;
  padding: 8px;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 6px;
  background: rgba(255,255,255,.045);
}

.upload-preview.show { display: grid; }
.upload-preview img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 6px;
}
.upload-preview strong { display: block; }
.upload-preview span { color: var(--muted); font-size: 13px; }

.warning {
  margin: 12px 0 0;
  border-left: 3px solid rgba(255,255,255,.28);
  border-radius: 4px;
  padding: 10px 11px;
  background: rgba(255,255,255,.055);
  color: #d8d8d8;
  font-size: 13px;
  line-height: 1.38;
}

.whatsapp {
  display: grid;
  place-items: center;
  min-height: 50px;
  margin-top: 14px;
  padding: 12px;
  border-radius: 6px;
  background: var(--green);
  color: #06170b;
  text-align: center;
  text-decoration: none;
  font-weight: 900;
}

.note {
  margin: 12px 0 0;
  color: #aaa;
  font-size: 13px;
  line-height: 1.45;
}

.mobile-cta {
  position: fixed;
  left: 12px;
  right: 12px;
  bottom: 12px;
  z-index: 9;
  display: none;
  padding: 14px 16px;
  border-radius: 8px;
  background: var(--green);
  color: #06170b;
  text-align: center;
  text-decoration: none;
  font-weight: 900;
  box-shadow: 0 12px 36px rgba(0,0,0,.42);
}

@media (max-width: 1080px) {
  .container { grid-template-columns: 1fr; }
  .info { position: static; }
  .map-wrap { min-height: 670px; }
}

@media (max-width: 720px) {
  body { padding: 14px 10px 88px; }
  .header h1 { font-size: 33px; }
  .segmented { width: 100%; display: grid; grid-auto-flow: column; }
  .map-card { padding: 10px; }
  .map-wrap { min-height: 570px; align-items: start; }
  .body-map { width: min(118vw, 580px); }
  .mobile-cta { display: block; }
}
</style>
</head>
<body>
<main class="container">
  <header class="header">
    <h1>Mapa de Orçamento Tattoo</h1>
    <p id="introText">Selecione a região, envie uma referência e receba uma prévia de orçamento direto no WhatsApp.</p>
  </header>

  <section class="card map-card">
    <div class="topbar">
      <div class="segmented" aria-label="Vista do corpo">
        <button class="active" type="button" data-view="frente">Frente</button>
        <button type="button" data-view="costas">Costas</button>
      </div>
    </div>

    <div class="map-wrap">
      <svg id="frente" class="body-map" viewBox="0 0 420 820" aria-label="Mapa corporal frente">
        <ellipse class="body-base" cx="210" cy="68" rx="36" ry="44"/>
        <path class="body-base" d="M174 116 Q210 142 246 116 L258 158 Q286 177 305 240 Q318 288 300 334 Q290 382 280 457 L270 520 L264 704 Q244 746 220 716 L210 528 L200 716 Q176 746 156 704 L150 520 L140 457 Q130 382 120 334 Q102 288 115 240 Q134 177 162 158 Z"/>
        <path class="body-shade" d="M178 176 Q210 155 242 176 L251 285 Q210 314 169 285 Z"/>
        <path class="body-shade" d="M174 396 Q210 426 246 396 L258 462 Q210 488 162 462 Z"/>
        <path class="body-line" d="M188 70 Q210 78 232 70 M196 94 Q210 102 224 94 M181 184 Q210 202 239 184 M210 166 L210 462 M170 296 Q210 318 250 296 M166 458 Q210 488 254 458"/>

        <path class="body-part" data-id="cabeca" data-region="cabeca" d="M174 61 Q178 25 210 23 Q242 25 246 61 Q249 101 210 116 Q171 101 174 61Z"/>
        <path class="body-part" data-id="pescoco_frente" data-region="pescoco" d="M185 112 L235 112 L244 151 Q210 166 176 151 Z"/>
        <path class="body-part" data-id="ombro_esq_frente" data-region="ombros" d="M139 154 Q174 132 204 142 L162 190 Q136 183 139 154 Z"/>
        <path class="body-part" data-id="ombro_dir_frente" data-region="ombros" d="M281 154 Q246 132 216 142 L258 190 Q284 183 281 154 Z"/>
        <path class="body-part" data-id="peito_esq" data-region="peito" d="M160 190 Q188 169 209 174 L209 286 Q181 295 168 276 Z"/>
        <path class="body-part" data-id="peito_dir" data-region="peito" d="M260 190 Q232 169 211 174 L211 286 Q239 295 252 276 Z"/>
        <path class="body-part" data-id="abdomen" data-region="abdomen" d="M168 282 Q210 306 252 282 L242 382 Q210 410 178 382 Z"/>
        <path class="body-part" data-id="costela_esq" data-region="costela" d="M132 242 Q145 210 164 194 L168 375 Q148 360 138 320 Z"/>
        <path class="body-part" data-id="costela_dir" data-region="costela" d="M288 242 Q275 210 256 194 L252 375 Q272 360 282 320 Z"/>
        <path class="body-part" data-id="quadril" data-region="quadril" d="M176 388 Q210 410 244 388 L260 455 Q210 480 160 455 Z"/>
        <path class="body-part" data-id="braco_esq_frente" data-region="braco" d="M138 165 Q102 190 84 255 L102 269 Q126 210 160 188 Z"/>
        <path class="body-part" data-id="braco_dir_frente" data-region="braco" d="M282 165 Q318 190 336 255 L318 269 Q294 210 260 188 Z"/>
        <path class="body-part" data-id="antebraco_esq_interno" data-region="antebraco_interno" d="M101 270 Q85 335 93 410 L118 408 Q124 335 103 270 Z"/>
        <path class="body-part" data-id="antebraco_dir_interno" data-region="antebraco_interno" d="M319 270 Q335 335 327 410 L302 408 Q296 335 317 270 Z"/>
        <path class="body-part" data-id="pulso_esq" data-region="pulso" d="M93 410 L118 408 L121 432 L92 434 Z"/>
        <path class="body-part" data-id="pulso_dir" data-region="pulso" d="M327 410 L302 408 L299 432 L328 434 Z"/>
        <path class="body-part" data-id="mao_esq" data-region="mao" d="M88 434 Q106 422 124 436 L126 484 Q106 502 86 484 Z"/>
        <path class="body-part" data-id="mao_dir" data-region="mao" d="M332 434 Q314 422 296 436 L294 484 Q314 502 334 484 Z"/>
        <path class="body-part" data-id="dedos_mao_esq" data-region="dedos_mao" d="M88 484 L96 484 L95 532 Q86 534 84 524 Z M99 491 L106 491 L105 538 Q96 540 95 531 Z M109 491 L116 491 L117 535 Q108 539 106 529 Z M119 484 L126 484 L130 520 Q124 529 119 520 Z"/>
        <path class="body-part" data-id="dedos_mao_dir" data-region="dedos_mao" d="M332 484 L324 484 L325 532 Q334 534 336 524 Z M321 491 L314 491 L315 538 Q324 540 325 531 Z M311 491 L304 491 L303 535 Q312 539 314 529 Z M301 484 L294 484 L290 520 Q296 529 301 520 Z"/>
        <path class="body-part" data-id="coxa_esq_frontal" data-region="coxa_frontal" d="M160 456 Q186 478 205 470 L201 585 Q188 628 158 612 L150 505 Z"/>
        <path class="body-part" data-id="coxa_dir_frontal" data-region="coxa_frontal" d="M260 456 Q234 478 215 470 L219 585 Q232 628 262 612 L270 505 Z"/>
        <ellipse class="body-part" data-id="joelho_esq" data-region="joelho" cx="181" cy="612" rx="25" ry="28"/>
        <ellipse class="body-part" data-id="joelho_dir" data-region="joelho" cx="239" cy="612" rx="25" ry="28"/>
        <path class="body-part" data-id="canela_esq" data-region="canela" d="M158 638 Q182 648 201 638 L196 720 Q178 742 158 720 Z"/>
        <path class="body-part" data-id="canela_dir" data-region="canela" d="M262 638 Q238 648 219 638 L224 720 Q242 742 262 720 Z"/>
        <path class="body-part" data-id="tornozelo_esq" data-region="tornozelo" d="M158 720 L196 720 L198 745 Q176 758 154 742 Z"/>
        <path class="body-part" data-id="tornozelo_dir" data-region="tornozelo" d="M262 720 L224 720 L222 745 Q244 758 266 742 Z"/>
        <path class="body-part" data-id="pe_esq" data-region="pe" d="M154 744 Q178 734 202 752 Q210 772 188 782 L146 776 Q136 760 154 744 Z"/>
        <path class="body-part" data-id="pe_dir" data-region="pe" d="M266 744 Q242 734 218 752 Q210 772 232 782 L274 776 Q284 760 266 744 Z"/>
        <path class="body-part" data-id="dedos_pe_esq" data-region="dedos_pe" d="M142 775 Q148 767 153 776 Q153 786 145 783 Z M154 779 Q160 769 166 779 Q165 789 156 786 Z M167 780 Q173 771 178 781 Q177 790 169 787 Z"/>
        <path class="body-part" data-id="dedos_pe_dir" data-region="dedos_pe" d="M278 775 Q272 767 267 776 Q267 786 275 783 Z M266 779 Q260 769 254 779 Q255 789 264 786 Z M253 780 Q247 771 242 781 Q243 790 251 787 Z"/>
      </svg>

      <svg id="costas" class="body-map hidden" viewBox="0 0 420 820" aria-label="Mapa corporal costas">
        <ellipse class="body-base" cx="210" cy="68" rx="36" ry="44"/>
        <path class="body-base" d="M174 116 Q210 142 246 116 L258 158 Q286 177 305 240 Q318 288 300 334 Q290 382 280 457 L270 520 L264 704 Q244 746 220 716 L210 528 L200 716 Q176 746 156 704 L150 520 L140 457 Q130 382 120 334 Q102 288 115 240 Q134 177 162 158 Z"/>
        <path class="body-line" d="M182 184 Q210 160 238 184 M210 158 L210 455 M164 292 Q210 326 256 292 M171 394 Q210 420 249 394"/>

        <path class="body-part" data-id="cabeca_costas" data-region="cabeca" d="M174 61 Q178 25 210 23 Q242 25 246 61 Q249 101 210 116 Q171 101 174 61Z"/>
        <path class="body-part" data-id="nuca" data-region="nuca" d="M185 112 L235 112 L244 151 Q210 166 176 151 Z"/>
        <path class="body-part" data-id="ombro_esq_costas" data-region="ombros" d="M139 154 Q174 132 204 142 L162 190 Q136 183 139 154 Z"/>
        <path class="body-part" data-id="ombro_dir_costas" data-region="ombros" d="M281 154 Q246 132 216 142 L258 190 Q284 183 281 154 Z"/>
        <path class="body-part" data-id="costas_esq_alta" data-region="costas" d="M160 190 Q188 168 210 173 L210 312 Q180 312 164 292 Z"/>
        <path class="body-part" data-id="costas_dir_alta" data-region="costas" d="M260 190 Q232 168 210 173 L210 312 Q240 312 256 292 Z"/>
        <path class="body-part" data-id="costas_esq_baixa" data-region="costas" d="M164 296 Q188 318 210 312 L210 418 Q188 414 176 390 Z"/>
        <path class="body-part" data-id="costas_dir_baixa" data-region="costas" d="M256 296 Q232 318 210 312 L210 418 Q232 414 244 390 Z"/>
        <path class="body-part" data-id="lombar" data-region="lombar" d="M176 392 Q210 418 244 392 L260 455 Q210 480 160 455 Z"/>
        <path class="body-part" data-id="braco_esq_costas" data-region="braco" d="M138 165 Q102 190 84 255 L102 269 Q126 210 160 188 Z"/>
        <path class="body-part" data-id="braco_dir_costas" data-region="braco" d="M282 165 Q318 190 336 255 L318 269 Q294 210 260 188 Z"/>
        <path class="body-part" data-id="antebraco_esq_externo" data-region="antebraco_externo" d="M101 270 Q85 335 93 410 L118 408 Q124 335 103 270 Z"/>
        <path class="body-part" data-id="antebraco_dir_externo" data-region="antebraco_externo" d="M319 270 Q335 335 327 410 L302 408 Q296 335 317 270 Z"/>
        <path class="body-part" data-id="mao_esq_costas" data-region="mao" d="M88 434 Q106 422 124 436 L126 484 Q106 502 86 484 Z"/>
        <path class="body-part" data-id="mao_dir_costas" data-region="mao" d="M332 434 Q314 422 296 436 L294 484 Q314 502 334 484 Z"/>
        <path class="body-part" data-id="gluteo_esq" data-region="gluteo" d="M160 456 Q185 474 207 464 L207 535 Q180 560 150 520 Z"/>
        <path class="body-part" data-id="gluteo_dir" data-region="gluteo" d="M260 456 Q235 474 213 464 L213 535 Q240 560 270 520 Z"/>
        <path class="body-part" data-id="coxa_esq_posterior" data-region="coxa_posterior" d="M150 524 Q180 560 207 536 L201 612 Q180 632 158 612 Z"/>
        <path class="body-part" data-id="coxa_dir_posterior" data-region="coxa_posterior" d="M270 524 Q240 560 213 536 L219 612 Q240 632 262 612 Z"/>
        <ellipse class="body-part" data-id="joelho_esq_posterior" data-region="joelho_posterior" cx="181" cy="612" rx="25" ry="22"/>
        <ellipse class="body-part" data-id="joelho_dir_posterior" data-region="joelho_posterior" cx="239" cy="612" rx="25" ry="22"/>
        <path class="body-part" data-id="panturrilha_esq" data-region="panturrilha" d="M158 638 Q182 650 201 638 L196 720 Q178 742 158 720 Z"/>
        <path class="body-part" data-id="panturrilha_dir" data-region="panturrilha" d="M262 638 Q238 650 219 638 L224 720 Q242 742 262 720 Z"/>
        <path class="body-part" data-id="tornozelo_esq_costas" data-region="tornozelo" d="M158 720 L196 720 L198 745 Q176 758 154 742 Z"/>
        <path class="body-part" data-id="tornozelo_dir_costas" data-region="tornozelo" d="M262 720 L224 720 L222 745 Q244 758 266 742 Z"/>
        <path class="body-part" data-id="pe_esq_costas" data-region="pe" d="M154 744 Q178 734 202 752 Q210 772 188 782 L146 776 Q136 760 154 744 Z"/>
        <path class="body-part" data-id="pe_dir_costas" data-region="pe" d="M266 744 Q242 734 218 752 Q210 772 232 782 L274 776 Q284 760 266 744 Z"/>
      </svg>
    </div>
    <p class="map-hint">Toque ou passe o mouse na região desejada. As áreas ficam discretas e só acendem quando você interage.</p>
  </section>

  <aside class="card info">
    <div class="badge-row">
      <span class="badge">Pré-orçamento</span>
      <button class="ghost-btn" id="clearSelection" type="button">Limpar</button>
    </div>

    <h2 id="titulo">Selecione uma área</h2>
    <div class="selection-list" id="selectionList"></div>
    <div class="price" id="preco">---</div>
    <p class="desc" id="descricao">Clique em uma parte do corpo para montar a estimativa inicial.</p>

    <div class="form-grid">
      <div class="field">
        <label for="cliente">Nome do cliente</label>
        <input id="cliente" type="text" placeholder="Ex.: Ana Souza" />
      </div>
      <div class="field">
        <label for="referenciaTexto">Referência / ideia</label>
        <textarea id="referenciaTexto" placeholder="Descreva a ideia ou cole um link de referência."></textarea>
      </div>
      <div class="field">
        <label for="referenciaFoto">Foto de referência</label>
        <input id="referenciaFoto" type="file" accept="image/*" />
        <div class="upload-preview" id="uploadPreview">
          <img id="uploadImage" alt="Prévia da referência enviada" />
          <div>
            <strong id="uploadName"></strong>
            <span>A imagem não vai anexada pelo link. O cliente envia a foto dentro da conversa.</span>
          </div>
        </div>
      </div>
    </div>

    <div class="warning">Essa estimativa é inicial. Valor final, detalhes técnicos e viabilidade do desenho são alinhados no WhatsApp com base na referência.</div>
    <a id="zap" class="whatsapp" href="#" target="_blank" rel="noopener">Quero orçamento agora</a>
    <p class="note">Quanto melhor a referência, mais rápido fica para avaliar e responder.</p>
  </aside>
</main>

<div class="tooltip" id="tooltip"></div>
<a id="mobileCta" class="mobile-cta" href="#" target="_blank" rel="noopener">Quero orçamento agora</a>

<script>
const DEFAULT_CONFIG = {
  whatsapp: "5511947573311",
  cta: "Quero orçamento agora",
  intro: "Selecione a região, envie uma referência e receba uma prévia de orçamento direto no WhatsApp."
};

const DEFAULT_AREAS = {
  cabeca: area("Cabeça", 2500, 6500, "Área extrema e muito visível. Precisa de leitura forte, contraste e desenho que envelheça bem."),
  pescoco: area("Pescoço", 1500, 3500, "Região de alto impacto visual, boa para composições verticais e fechamentos."),
  nuca: area("Nuca", 900, 2200, "Boa para peças menores, símbolos, ornamentos e complemento de costas ou pescoço."),
  ombros: area("Ombros", 1800, 4200, "Excelente para encaixe anatômico, fechamentos de braço e projetos com presença."),
  peito: area("Peito", 2500, 7000, "Área grande e visual, ideal para projetos simétricos ou peça central de impacto."),
  abdomen: area("Abdômen", 2000, 5600, "Área ampla, sensível e com variação de elasticidade. Pede planejamento limpo."),
  costela: area("Costela", 1800, 5600, "Ótima para composições laterais, florais, lettering e projetos verticais."),
  quadril: area("Quadril / Virilha", 1500, 4200, "Boa para peças ornamentais, sensuais ou complemento de perna e abdômen."),
  costas: area("Costas", 3200, 12000, "Área nobre para projetos grandes, painéis, simetrias e fechamentos de alto impacto."),
  lombar: area("Lombar", 1500, 4200, "Boa para peças centrais, ornamentais e complementos de fechamento."),
  braco: area("Braço", 1800, 4800, "Uma das melhores áreas: boa leitura, boa resistência e encaixe anatômico."),
  antebraco_interno: area("Antebraço interno", 1200, 3400, "Área muito procurada, boa para projetos detalhados e leitura frontal."),
  antebraco_externo: area("Antebraço externo", 1200, 3200, "Área resistente e excelente para contraste, com boa leitura no dia a dia."),
  pulso: area("Pulso", 600, 1600, "Área menor e delicada para símbolos, pulseiras, detalhes e complementos."),
  mao: area("Mão", 900, 2800, "Área muito visível e com maior desgaste. Precisa de desenho forte e manutenção consciente."),
  dedos_mao: area("Dedos da mão", 400, 1300, "Área pequena, visível e com alto desgaste. Melhor para símbolos simples e letras."),
  coxa_frontal: area("Coxa frontal", 2500, 7200, "Área ampla para peças grandes, fechamentos e projetos com bastante detalhe."),
  coxa_posterior: area("Coxa posterior", 2300, 6600, "Excelente para continuidade de fechamento de perna e composição vertical."),
  gluteo: area("Glúteo", 1800, 4600, "Boa para composições grandes e complementos de perna ou quadril."),
  joelho: area("Joelho", 1200, 3800, "Área complexa, dobra bastante e exige desenho inteligente."),
  joelho_posterior: area("Parte de trás do joelho", 900, 2700, "Área sensível e delicada, geralmente usada para complementar fechamento de perna."),
  canela: area("Canela", 1800, 4800, "Área de impacto visual, ótima para projetos verticais e contraste forte."),
  panturrilha: area("Panturrilha", 1800, 4800, "Boa curvatura para peças médias, grandes e fechamento de perna."),
  tornozelo: area("Tornozelo", 600, 1900, "Área menor, boa para detalhes, tornozeleiras e complementos."),
  pe: area("Pé", 900, 2800, "Área visível, sensível e com desgaste. Precisa de projeto simples e bem planejado."),
  dedos_pe: area("Dedos do pé", 400, 1100, "Área pequena, sensível e com alto desgaste. Melhor para marcas simples.")
};

function area(titulo, min, max, descricao) {
  return { titulo, min, max, descricao, ativa: true };
}

const sideLabels = { esq: "esquerdo", dir: "direito" };
const $ = (id) => document.getElementById(id);
const config = load("orcamentoTattooConfig", DEFAULT_CONFIG);
const areas = load("orcamentoTattooAreas", DEFAULT_AREAS);
const selected = new Map();
let currentView = "frente";
let referenceFile = "";

function load(key, fallback) {
  try {
    return { ...fallback, ...JSON.parse(localStorage.getItem(key) || "{}") };
  } catch (e) {
    return { ...fallback };
  }
}

function money(value) {
  return value.toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 });
}

function partLabel(part) {
  const base = areas[part.region]?.titulo || part.region;
  const side = part.id.includes("_esq") ? sideLabels.esq : part.id.includes("_dir") ? sideLabels.dir : "";
  return side ? `${base} ${side}` : base;
}

function calculate() {
  if (!selected.size) return null;
  const total = [...selected.values()].reduce((acc, item) => {
    const data = areas[item.region];
    return { min: acc.min + Number(data?.min || 0), max: acc.max + Number(data?.max || 0) };
  }, { min: 0, max: 0 });
  return {
    min: Math.round(total.min / 50) * 50,
    max: Math.round(total.max / 50) * 50
  };
}

function update() {
  document.querySelectorAll(".body-part").forEach(part => {
    const active = areas[part.dataset.region]?.ativa !== false;
    part.classList.toggle("disabled", !active);
    part.classList.toggle("selected", selected.has(part.dataset.id));
  });

  const items = [...selected.values()];
  const estimate = calculate();
  $("selectionList").innerHTML = items.map(item => `<button class="pill" type="button" data-remove="${item.id}">${item.label} ×</button>`).join("");

  if (!items.length || !estimate) {
    $("titulo").innerText = "Selecione uma área";
    $("preco").innerText = "---";
    $("descricao").innerText = "Clique em uma parte do corpo para montar a estimativa inicial.";
    updateLinks();
    return;
  }

  const last = items[items.length - 1];
  const data = areas[last.region];
  $("titulo").innerText = items.length === 1 ? last.label : `${items.length} áreas selecionadas`;
  $("preco").innerText = `${money(estimate.min)} a ${money(estimate.max)}`;
  $("descricao").innerText = data.descricao || "Região selecionada para orçamento.";
  updateLinks();
}

function updateLinks() {
  const estimate = calculate();
  const client = $("cliente").value.trim() || "Cliente ainda não informou";
  const textRef = $("referenciaTexto").value.trim() || "Não descreveu ainda";
  const areaText = [...selected.values()].map(item => item.label).join(", ") || "a definir";
  const price = estimate ? `${money(estimate.min)} a ${money(estimate.max)}` : "Aguardando seleção de área";
  const fileLine = referenceFile ? `Foto de referência: ${referenceFile} (vou enviar a imagem aqui na conversa)` : "Foto de referência: ainda não anexei";
  const msg = [
    "Olá! Quero um orçamento de tatuagem.",
    `Nome: ${client}`,
    `Área escolhida: ${areaText}`,
    `Referência/ideia: ${textRef}`,
    fileLine,
    `Pré-orçamento: ${price}`,
    "Sei que o valor final depende de avaliação."
  ].join("\n");
  const href = `https://wa.me/${config.whatsapp || DEFAULT_CONFIG.whatsapp}?text=${encodeURIComponent(msg)}&utm_source=orcamento_mapa&utm_medium=site&utm_campaign=orcamento`;
  $("zap").href = href;
  $("mobileCta").href = href;
  $("zap").innerText = config.cta || DEFAULT_CONFIG.cta;
  $("mobileCta").innerText = config.cta || DEFAULT_CONFIG.cta;
}

function selectPart(el) {
  const item = {
    id: el.dataset.id,
    region: el.dataset.region,
    label: partLabel(el.dataset)
  };
  if (areas[item.region]?.ativa === false) return;
  if (selected.has(item.id)) selected.delete(item.id);
  else selected.set(item.id, item);
  track("area_click", { area: item.label, region: item.region });
  update();
}

function track(event, payload) {
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ event, ...payload });
  if (window.fbq) window.fbq("trackCustom", event, payload);
}

document.querySelectorAll(".body-part").forEach(part => {
  part.addEventListener("click", () => selectPart(part));
  part.addEventListener("mousemove", event => {
    $("tooltip").textContent = partLabel(part.dataset);
    $("tooltip").style.left = `${event.clientX}px`;
    $("tooltip").style.top = `${event.clientY}px`;
    $("tooltip").classList.add("show");
    part.classList.add("focused");
  });
  part.addEventListener("mouseleave", () => {
    $("tooltip").classList.remove("show");
    part.classList.remove("focused");
  });
});

document.querySelectorAll("[data-view]").forEach(button => {
  button.addEventListener("click", () => {
    currentView = button.dataset.view;
    document.querySelectorAll("[data-view]").forEach(btn => btn.classList.toggle("active", btn === button));
    $("frente").classList.toggle("hidden", currentView !== "frente");
    $("costas").classList.toggle("hidden", currentView !== "costas");
  });
});

$("selectionList").addEventListener("click", event => {
  const button = event.target.closest("[data-remove]");
  if (!button) return;
  selected.delete(button.dataset.remove);
  update();
});

$("clearSelection").addEventListener("click", () => {
  selected.clear();
  update();
});

["cliente", "referenciaTexto"].forEach(id => $(id).addEventListener("input", update));

$("referenciaFoto").addEventListener("change", event => {
  const file = event.target.files?.[0];
  referenceFile = file ? file.name : "";
  if (!file) {
    $("uploadPreview").classList.remove("show");
    update();
    return;
  }
  $("uploadName").innerText = file.name;
  $("uploadImage").src = URL.createObjectURL(file);
  $("uploadPreview").classList.add("show");
  update();
});

$("zap").addEventListener("click", () => track("whatsapp_click", { areas: [...selected.values()].map(item => item.label).join(", ") }));
$("mobileCta").addEventListener("click", () => track("whatsapp_mobile_click", { areas: [...selected.values()].map(item => item.label).join(", ") }));

$("introText").innerText = config.intro || DEFAULT_CONFIG.intro;
update();
</script>
</body>
</html>
