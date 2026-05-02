<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Mapa Interativo de Orçamento Tattoo</title>
<link rel="icon" href="data:," />

<style>
:root {
  --bg: #080808;
  --panel: #111;
  --panel-2: #171717;
  --line: rgba(255,255,255,.12);
  --text: #fff;
  --muted: #b9b9b9;
  --red: #d7192a;
  --red-2: #ff334b;
  --green: #25d366;
  --amber: #f2b84b;
  --blue: #56a3ff;
}

* { box-sizing: border-box; }

body {
  margin: 0;
  min-height: 100vh;
  font-family: Arial, Helvetica, sans-serif;
  color: var(--text);
  background:
    linear-gradient(135deg, rgba(215,25,42,.16), transparent 28%),
    linear-gradient(315deg, rgba(86,163,255,.12), transparent 28%),
    var(--bg);
  padding: 24px 14px 96px;
}

button, input, select, textarea { font: inherit; }
button { cursor: pointer; }

.container {
  max-width: 1320px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: minmax(0, 1fr) 430px;
  gap: 18px;
  align-items: start;
}

.header {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 16px;
  align-items: end;
  padding: 8px 4px 4px;
}

.header h1 {
  margin: 0;
  font-size: clamp(28px, 4vw, 52px);
  text-transform: uppercase;
  line-height: .98;
  letter-spacing: 0;
}

.header p {
  max-width: 760px;
  margin: 10px 0 0;
  color: var(--muted);
  line-height: 1.5;
}

.card {
  background: linear-gradient(180deg, rgba(24,24,24,.98), rgba(10,10,10,.98));
  border: 1px solid var(--line);
  border-radius: 8px;
  box-shadow: 0 18px 54px rgba(0,0,0,.45);
}

.map-card { padding: 16px; }

.toolbar {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.segmented {
  display: inline-flex;
  gap: 4px;
  background: #0b0b0b;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 4px;
}

.segmented button, .ghost-btn, .admin-btn {
  border: 1px solid transparent;
  background: transparent;
  color: #f5f5f5;
  border-radius: 6px;
  padding: 10px 13px;
  font-weight: 800;
  min-height: 40px;
}

.segmented button.active {
  background: var(--red);
  border-color: rgba(255,255,255,.12);
}

.ghost-btn, .admin-btn {
  background: #121212;
  border-color: var(--line);
}

.ghost-btn:hover, .admin-btn:hover { border-color: rgba(255,255,255,.35); }

.map-wrap {
  position: relative;
  display: grid;
  place-items: center;
  min-height: 760px;
  overflow: hidden;
}

.body-map {
  width: min(100%, 560px);
  height: auto;
  transition: transform .32s ease, filter .32s ease;
}

.profile-feminino .body-map { transform: scaleX(.92); }
.profile-masculino .body-map { transform: scaleX(1.04); }
.profile-neutro .body-map { transform: scaleX(.98); }

.hidden { display: none; }

.base {
  fill: #141414;
  stroke: #696969;
  stroke-width: 2;
}

.body-contour {
  fill: #161616;
  stroke: #777;
  stroke-width: 2.2;
}

.joint-line, .muscle-line {
  fill: none;
  stroke: rgba(255,255,255,.14);
  stroke-width: 1.2;
}

.body-part {
  fill: rgba(215,25,42,.14);
  stroke: rgba(255,255,255,.52);
  stroke-width: 1.25;
  cursor: pointer;
  transition: fill .2s ease, stroke .2s ease, filter .2s ease, transform .2s ease;
  transform-box: fill-box;
  transform-origin: center;
}

.body-part:hover, .body-part.focused {
  fill: rgba(215,25,42,.55);
  stroke: #fff;
  filter: drop-shadow(0 0 8px rgba(255,51,75,.9));
}

.body-part.selected {
  fill: rgba(255,51,75,.88);
  stroke: #fff;
  filter: drop-shadow(0 0 13px rgba(255,51,75,.95));
  animation: selectPulse .34s ease;
}

.body-part.disabled {
  opacity: .25;
  pointer-events: none;
}

@keyframes selectPulse {
  0% { transform: scale(.98); }
  55% { transform: scale(1.04); }
  100% { transform: scale(1); }
}

.tooltip {
  position: fixed;
  z-index: 20;
  pointer-events: none;
  transform: translate(12px, 12px);
  background: #050505;
  color: #fff;
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 6px;
  padding: 8px 10px;
  font-weight: 800;
  box-shadow: 0 10px 28px rgba(0,0,0,.45);
  opacity: 0;
  transition: opacity .12s ease;
}

.tooltip.show { opacity: 1; }

.legend {
  display: flex;
  justify-content: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 12px;
  color: #cfcfcf;
  font-size: 13px;
}

.legend span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
}

.dot {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  display: inline-block;
}

.dot.low { background: #5ad66f; }
.dot.mid { background: var(--amber); }
.dot.high { background: var(--red-2); }
.dot.premium { background: var(--blue); }

.info {
  padding: 18px;
  position: sticky;
  top: 14px;
}

.badge-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.badge {
  display: inline-flex;
  color: #ffc8ce;
  border: 1px solid rgba(255,51,75,.6);
  background: rgba(215,25,42,.14);
  padding: 7px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.selection-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0 0 12px;
  min-height: 32px;
}

.pill {
  border: 1px solid rgba(255,255,255,.16);
  background: rgba(255,255,255,.06);
  color: #fff;
  border-radius: 6px;
  padding: 7px 9px;
  font-size: 13px;
  font-weight: 800;
}

.info h2 {
  margin: 0 0 8px;
  font-size: 28px;
  line-height: 1.08;
}

.price {
  font-size: 34px;
  color: var(--red-2);
  font-weight: 900;
  margin: 12px 0 4px;
}

.desc {
  color: #ddd;
  line-height: 1.5;
  margin: 10px 0 16px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin: 14px 0;
}

.field {
  display: grid;
  gap: 6px;
}

.field.full { grid-column: 1 / -1; }

.field label {
  color: #dcdcdc;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}

.field input, .field select, .field textarea {
  width: 100%;
  border: 1px solid rgba(255,255,255,.13);
  background: #0d0d0d;
  color: #fff;
  border-radius: 6px;
  padding: 11px 10px;
  outline: none;
}

.field textarea {
  resize: vertical;
  min-height: 72px;
}

.range-row {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
}

input[type="range"] { accent-color: var(--red); }

.details {
  display: grid;
  gap: 8px;
  margin: 14px 0;
}

.detail {
  background: rgba(255,255,255,.045);
  border: 1px solid rgba(255,255,255,.08);
  padding: 11px 12px;
  border-radius: 6px;
  color: #d1d1d1;
  line-height: 1.38;
}

.detail strong { color: #fff; }

.warnings {
  display: grid;
  gap: 7px;
  margin: 12px 0;
}

.warning {
  border-left: 3px solid var(--amber);
  background: rgba(242,184,75,.1);
  padding: 9px 10px;
  color: #f4e4c2;
  line-height: 1.35;
  border-radius: 4px;
  font-size: 13px;
}

.actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 14px;
}

.whatsapp, .secondary-action {
  display: grid;
  place-items: center;
  text-align: center;
  text-decoration: none;
  border-radius: 6px;
  min-height: 48px;
  padding: 12px;
  font-weight: 900;
}

.whatsapp {
  background: var(--green);
  color: #06170b;
}

.secondary-action {
  background: #f6f6f6;
  color: #111;
}

.whatsapp:hover, .secondary-action:hover { filter: brightness(1.07); }

.note {
  color: #aaa;
  font-size: 13px;
  line-height: 1.45;
  margin: 12px 0 0;
}

.portfolio {
  grid-column: 1 / -1;
  padding: 18px;
}

.section-title {
  display: flex;
  align-items: end;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 12px;
}

.section-title h2 {
  margin: 0;
  font-size: 24px;
}

.section-title p {
  margin: 4px 0 0;
  color: var(--muted);
}

.gallery {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 10px;
}

.gallery-card {
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 8px;
  overflow: hidden;
  min-height: 170px;
  background: #0d0d0d;
}

.gallery-card img {
  width: 100%;
  height: 138px;
  object-fit: cover;
  display: block;
}

.gallery-card div {
  padding: 8px;
  color: #ddd;
  font-size: 12px;
  font-weight: 800;
}

.admin-panel {
  grid-column: 1 / -1;
  padding: 18px;
  display: none;
}

.admin-panel.open { display: block; }

.admin-table-wrap {
  overflow: auto;
  border: 1px solid var(--line);
  border-radius: 8px;
}

.admin-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 780px;
}

.admin-table th, .admin-table td {
  border-bottom: 1px solid rgba(255,255,255,.08);
  padding: 9px;
  text-align: left;
  vertical-align: middle;
}

.admin-table th {
  color: #ddd;
  font-size: 12px;
  text-transform: uppercase;
  background: #101010;
}

.admin-table input {
  width: 100%;
  border: 1px solid rgba(255,255,255,.12);
  background: #0a0a0a;
  color: #fff;
  border-radius: 5px;
  padding: 8px;
}

.mobile-cta {
  position: fixed;
  left: 12px;
  right: 12px;
  bottom: 12px;
  z-index: 12;
  display: none;
  background: var(--green);
  color: #05140a;
  text-decoration: none;
  text-align: center;
  padding: 14px 16px;
  border-radius: 8px;
  font-weight: 900;
  box-shadow: 0 12px 36px rgba(0,0,0,.42);
}

@media (max-width: 1080px) {
  .container { grid-template-columns: 1fr; }
  .info { position: static; }
  .map-wrap { min-height: 660px; }
  .gallery { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 720px) {
  body { padding: 14px 10px 88px; }
  .header { grid-template-columns: 1fr; }
  .header h1 { font-size: 32px; }
  .toolbar { display: grid; }
  .segmented { width: 100%; display: grid; grid-auto-flow: column; }
  .segmented button { padding: 10px 8px; }
  .map-card { padding: 10px; }
  .map-wrap { min-height: 560px; align-items: start; }
  .body-map { width: min(118vw, 560px); }
  .form-grid, .actions { grid-template-columns: 1fr; }
  .gallery { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .mobile-cta { display: block; }
}
</style>
</head>

<body>
<main class="container">
  <header class="header">
    <div>
      <h1>Mapa de Orçamento Tattoo</h1>
      <p>Escolha uma ou mais regiões, ajuste tamanho, estilo e detalhe, e gere uma prévia de orçamento pronta para mandar no WhatsApp.</p>
    </div>
    <button class="admin-btn" id="adminToggle" type="button">Admin</button>
  </header>

  <section class="card map-card">
    <div class="toolbar">
      <div class="segmented" aria-label="Vista do corpo">
        <button class="active" type="button" data-view="frente">Frente</button>
        <button type="button" data-view="costas">Costas</button>
      </div>
      <div class="segmented" aria-label="Biotipo">
        <button class="active" type="button" data-profile="masculino">Masculino</button>
        <button type="button" data-profile="feminino">Feminino</button>
        <button type="button" data-profile="neutro">Neutro</button>
      </div>
      <button class="ghost-btn" id="resetZoom" type="button">Ver corpo todo</button>
    </div>

    <div class="map-wrap profile-masculino" id="mapStage">
      <svg id="frente" class="body-map" viewBox="0 0 420 820" aria-label="Mapa corporal frente">
        <ellipse class="base" cx="210" cy="72" rx="38" ry="46"/>
        <path class="body-contour" d="M175 120 Q210 145 245 120 L258 170 Q286 190 305 245 L288 330 Q282 420 270 505 L262 720 Q238 755 218 722 L210 520 L202 722 Q182 755 158 720 L150 505 Q138 420 132 330 L115 245 Q134 190 162 170 Z"/>
        <path class="muscle-line" d="M178 185 Q210 205 242 185 M210 170 L210 455 M170 300 Q210 320 250 300 M164 458 Q210 492 256 458"/>

        <path class="body-part" data-area="cabeca" d="M172 65 Q176 25 210 24 Q244 25 248 65 Q250 105 210 118 Q170 105 172 65Z"/>
        <path class="muscle-line" d="M190 70 Q210 78 230 70 M196 92 Q210 100 224 92"/>
        <path class="body-part" data-area="pescoco" d="M185 112 L235 112 L243 150 Q210 166 177 150 Z"/>
        <path class="body-part" data-area="ombros" d="M140 155 Q210 122 280 155 L258 190 Q210 170 162 190 Z"/>
        <path class="body-part" data-area="peito" d="M160 190 Q210 168 260 190 L252 278 Q210 302 168 278 Z"/>
        <path class="body-part" data-area="abdomen" d="M168 282 Q210 302 252 282 L242 382 Q210 410 178 382 Z"/>
        <path class="body-part" data-area="costela" d="M132 245 Q145 210 164 194 L168 375 Q148 360 138 320 Z"/>
        <path class="body-part" data-area="costela" d="M288 245 Q275 210 256 194 L252 375 Q272 360 282 320 Z"/>
        <path class="body-part" data-area="quadril" d="M176 388 Q210 410 244 388 L260 455 Q210 480 160 455 Z"/>
        <path class="body-part" data-area="braco_externo" d="M138 165 Q100 190 84 255 L102 268 Q126 210 160 188 Z"/>
        <path class="body-part" data-area="braco_externo" d="M282 165 Q320 190 336 255 L318 268 Q294 210 260 188 Z"/>
        <path class="body-part" data-area="antebraco_interno" d="M101 270 Q85 335 93 410 L118 408 Q124 335 103 270 Z"/>
        <path class="body-part" data-area="antebraco_interno" d="M319 270 Q335 335 327 410 L302 408 Q296 335 317 270 Z"/>
        <path class="body-part" data-area="pulso" d="M93 410 L118 408 L121 432 L92 434 Z"/>
        <path class="body-part" data-area="pulso" d="M327 410 L302 408 L299 432 L328 434 Z"/>
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
        <path class="body-part" data-area="coxa_frontal" d="M160 456 Q186 478 205 470 L201 585 Q188 628 158 612 L150 505 Z"/>
        <path class="body-part" data-area="coxa_frontal" d="M260 456 Q234 478 215 470 L219 585 Q232 628 262 612 L270 505 Z"/>
        <ellipse class="body-part" data-area="joelho" cx="181" cy="612" rx="25" ry="28"/>
        <ellipse class="body-part" data-area="joelho" cx="239" cy="612" rx="25" ry="28"/>
        <path class="body-part" data-area="canela" d="M158 638 Q182 648 201 638 L196 720 Q178 742 158 720 Z"/>
        <path class="body-part" data-area="canela" d="M262 638 Q238 648 219 638 L224 720 Q242 742 262 720 Z"/>
        <path class="body-part" data-area="tornozelo" d="M158 720 L196 720 L198 745 Q176 758 154 742 Z"/>
        <path class="body-part" data-area="tornozelo" d="M262 720 L224 720 L222 745 Q244 758 266 742 Z"/>
        <path class="body-part" data-area="pe" d="M154 744 Q178 734 202 752 Q210 772 188 782 L146 776 Q136 760 154 744 Z"/>
        <path class="body-part" data-area="pe" d="M266 744 Q242 734 218 752 Q210 772 232 782 L274 776 Q284 760 266 744 Z"/>
        <circle class="body-part" data-area="dedos_pe" cx="147" cy="777" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="158" cy="780" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="170" cy="782" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="272" cy="777" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="261" cy="780" r="5"/>
        <circle class="body-part" data-area="dedos_pe" cx="249" cy="782" r="5"/>
      </svg>

      <svg id="costas" class="body-map hidden" viewBox="0 0 420 820" aria-label="Mapa corporal costas">
        <ellipse class="base" cx="210" cy="72" rx="38" ry="46"/>
        <path class="body-contour" d="M175 120 Q210 145 245 120 L258 170 Q286 190 305 245 L288 330 Q282 420 270 505 L262 720 Q238 755 218 722 L210 520 L202 722 Q182 755 158 720 L150 505 Q138 420 132 330 L115 245 Q134 190 162 170 Z"/>
        <path class="muscle-line" d="M171 190 Q210 165 249 190 M210 176 L210 452 M162 295 Q210 330 258 295 M170 398 Q210 424 250 398"/>
        <path class="body-part" data-area="cabeca" d="M172 65 Q176 25 210 24 Q244 25 248 65 Q250 105 210 118 Q170 105 172 65Z"/>
        <path class="body-part" data-area="nuca" d="M185 112 L235 112 L243 150 Q210 166 177 150 Z"/>
        <path class="body-part" data-area="ombros" d="M140 155 Q210 122 280 155 L258 190 Q210 170 162 190 Z"/>
        <path class="body-part" data-area="costas" d="M160 190 Q210 165 260 190 L256 292 Q210 318 164 292 Z"/>
        <path class="body-part" data-area="costas" d="M164 296 Q210 318 256 296 L244 390 Q210 418 176 390 Z"/>
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
      <span><i class="dot low"></i>Até R$ 1.500</span>
      <span><i class="dot mid"></i>R$ 1.500 a R$ 3.500</span>
      <span><i class="dot high"></i>R$ 3.500 a R$ 7.000</span>
      <span><i class="dot premium"></i>Fechamento</span>
    </div>
  </section>

  <aside class="card info">
    <div class="badge-row">
      <span class="badge">Pré-orçamento</span>
      <button class="ghost-btn" id="clearSelection" type="button">Limpar</button>
    </div>

    <h2 id="titulo">Selecione uma área</h2>
    <div class="selection-list" id="selectionList"></div>
    <div class="price" id="preco">---</div>
    <p class="desc" id="descricao">Clique em uma ou mais partes do corpo para montar uma estimativa inicial.</p>

    <div class="form-grid">
      <div class="field full">
        <label for="cliente">Nome do cliente</label>
        <input id="cliente" type="text" placeholder="Ex.: Ana Souza" />
      </div>
      <div class="field full">
        <label for="tamanho">Tamanho</label>
        <div class="range-row">
          <input id="tamanho" type="range" min="0" max="3" step="1" value="1" />
          <strong id="tamanhoLabel">Médio</strong>
        </div>
      </div>
      <div class="field">
        <label for="estilo">Estilo</label>
        <select id="estilo">
          <option value="fineline">Fine line</option>
          <option value="blackwork">Blackwork</option>
          <option value="realismo">Realismo</option>
          <option value="anime">Anime / colorida</option>
          <option value="lettering">Lettering</option>
          <option value="ornamental">Ornamental</option>
        </select>
      </div>
      <div class="field">
        <label for="detalhe">Detalhe</label>
        <select id="detalhe">
          <option value="simples">Simples</option>
          <option value="medio" selected>Médio</option>
          <option value="insano">Insano</option>
        </select>
      </div>
      <div class="field">
        <label for="cor">Cor</label>
        <select id="cor">
          <option value="pb">Preto e cinza</option>
          <option value="colorido">Colorido</option>
        </select>
      </div>
      <div class="field">
        <label for="tipoProjeto">Projeto</label>
        <select id="tipoProjeto">
          <option value="primeira">Primeira tattoo</option>
          <option value="continuidade">Continuidade</option>
          <option value="cobertura">Cobertura / reforma</option>
          <option value="fechamento">Fechamento</option>
        </select>
      </div>
      <div class="field">
        <label for="sessoesInput">Sessões</label>
        <select id="sessoesInput">
          <option value="1">1 sessão</option>
          <option value="2">2 sessões</option>
          <option value="3">3 sessões</option>
          <option value="5">4 a 5 sessões</option>
          <option value="8">6+ sessões</option>
        </select>
      </div>
      <div class="field">
        <label for="utm">Campanha</label>
        <input id="utm" type="text" value="orcamento_mapa" />
      </div>
      <div class="field full">
        <label for="referencia">Referência</label>
        <textarea id="referencia" placeholder="Cole link, descreva a ideia ou diga que vai enviar referência no WhatsApp."></textarea>
      </div>
    </div>

    <div class="details">
      <div class="detail"><strong>Dor:</strong> <span id="dor">---</span></div>
      <div class="detail"><strong>Funciona melhor:</strong> <span id="indicacao">---</span></div>
      <div class="detail"><strong>Orientação:</strong> <span id="orientacao">---</span></div>
      <div class="detail"><strong>Tamanho mínimo:</strong> <span id="minimo">---</span></div>
    </div>

    <div class="warnings" id="warnings"></div>

    <div class="actions">
      <a id="zap" class="whatsapp" href="#" target="_blank" rel="noopener">Quero orçamento agora</a>
      <a id="zapRef" class="secondary-action" href="#" target="_blank" rel="noopener">Enviar referência</a>
    </div>

    <p class="note">Valor final depende de avaliação presencial ou por referência. A estimativa considera região, tamanho, estilo, detalhe, cor, cobertura e sessões.</p>
  </aside>

  <section class="card portfolio">
    <div class="section-title">
      <div>
        <h2>Exemplos e recomendações</h2>
        <p id="portfolioIntro">Ao selecionar uma região, a galeria e os textos mudam para guiar o cliente.</p>
      </div>
    </div>
    <div class="gallery" id="gallery"></div>
  </section>

  <section class="card admin-panel" id="adminPanel">
    <div class="section-title">
      <div>
        <h2>Painel admin local</h2>
        <p>Edite preços, ative/desative áreas e salve no navegador. Depois dá para ligar isso em JSON, MySQL ou MiniCRM.</p>
      </div>
      <button class="ghost-btn" id="saveAdmin" type="button">Salvar preços</button>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Área</th>
            <th>Mínimo</th>
            <th>Máximo</th>
            <th>Ativa</th>
          </tr>
        </thead>
        <tbody id="adminRows"></tbody>
      </table>
    </div>
  </section>
</main>

<div class="tooltip" id="tooltip"></div>
<a id="mobileCta" class="mobile-cta" href="#" target="_blank" rel="noopener">Quero orçamento agora</a>

<script>
const whatsapp = "5511947573311";
const baseViewBox = "0 0 420 820";
const smallAreaViewBoxes = {
  mao: "55 390 310 170",
  dedos_mao: "70 440 280 130",
  pe: "120 690 180 115",
  dedos_pe: "128 730 164 72",
  pescoco: "150 70 120 120",
  nuca: "150 70 120 120",
  pulso: "70 360 280 120",
  tornozelo: "128 680 164 100"
};

const galleryImages = [
  "../fotos/0f20fb8e-5fb1-499f-aead-4f6b93210dc9.jpg",
  "../fotos/18213344-55a0-4de9-88f6-9693601f6984.jpg",
  "../fotos/1ddbc90c-0eb4-4976-8406-e19c4fd273b9.jpg",
  "../fotos/248ef59a-0795-4cf4-be02-b2643415556e.jpg",
  "../fotos/2a5282e1-ad90-45f8-82d9-8f3b457bc102.jpg",
  "../fotos/35c9c706-59e5-455b-aa11-b26370b37eb4.jpg",
  "../fotos/3dbb1060-bdd3-405f-a669-dc9b4da5a1de.jpg",
  "../fotos/4e0ebe81-bced-4d86-94e7-5cb2f9973c22.jpg",
  "../fotos/6ba0f9db-a193-4cbd-8419-7280f62caa1d.jpg",
  "../fotos/7272d676-a963-4ca6-b685-54fa87b17020.jpg",
  "../fotos/aa0da8f3-be61-450f-a391-500f6734625c.jpg",
  "../fotos/bb343c20-14ed-4f11-9755-fee46f613a0a.jpg"
];

const dadosBase = {
  cabeca: area("Cabeça", 2500, 6500, "Área extrema e muito visível. Precisa de leitura forte, contraste e desenho que envelheça bem.", "Alta", "Blackwork, ornamental e projetos ousados", "Encaixe anatômico", "Médio para cima", ["Área muito visível. Avaliação é obrigatória antes de fechar."]),
  pescoco: area("Pescoço", 1500, 3500, "Região de alto impacto visual, boa para composições verticais e fechamentos.", "Alta", "Lettering, blackwork, ornamental", "Vertical ou anatômica", "Médio", ["Região com movimento e exposição solar. Contraste ajuda muito."]),
  nuca: area("Nuca", 900, 2200, "Boa para peças menores, símbolos, ornamentos e complemento de costas ou pescoço.", "Média", "Projetos pequenos e médios", "Vertical ou central", "Pequeno forte", []),
  ombros: area("Ombros", 1800, 4200, "Excelente para encaixe anatômico, fechamentos de braço e projetos com presença.", "Média", "Realismo, mandalas, blackwork", "Circular ou anatômica", "Médio", ["Selo: muito procurado para começar fechamento de braço."]),
  peito: area("Peito", 2500, 7000, "Área grande e visual, ideal para projetos simétricos ou peça central de impacto.", "Alta", "Realismo, blackwork, ornamental", "Horizontal ou central", "Grande", []),
  abdomen: area("Abdômen", 2000, 5600, "Área ampla, sensível e com variação de elasticidade. Pede planejamento limpo.", "Alta", "Projetos verticais, centrais e fechamentos", "Vertical ou central", "Médio para grande", ["Distorce mais com movimento e variação corporal."]),
  costela: area("Costela", 1800, 5600, "Ótima para composições laterais, florais, lettering e projetos verticais.", "Alta", "Florais, lettering, realismo", "Vertical", "Médio", ["Região sensível. Linhas muito finas precisam de respiro."]),
  quadril: area("Quadril / Virilha", 1500, 4200, "Boa para peças ornamentais, sensuais ou complemento de perna e abdômen.", "Média/Alta", "Ornamental, fineline e composições laterais", "Anatômica", "Médio", []),
  costas: area("Costas", 3200, 12000, "Área nobre para projetos grandes, painéis, simetrias e fechamentos de alto impacto.", "Média", "Realismo, blackwork, oriental e painéis", "Vertical, central ou painel", "Grande", ["Costas agora é uma área única: sem divisão alta/baixa."]),
  lombar: area("Lombar", 1500, 4200, "Boa para peças centrais, ornamentais e complementos de fechamento.", "Média/Alta", "Ornamentos, mandalas e simetria", "Horizontal ou central", "Médio", []),
  braco_externo: area("Braço Externo", 1800, 4800, "Uma das melhores áreas: boa leitura, boa resistência e encaixe anatômico.", "Média", "Realismo, anime, blackwork", "Vertical ou anatômica", "Médio", ["Selo: uma das áreas mais procuradas."]),
  antebraco_interno: area("Antebraço Interno", 1200, 3400, "Área muito procurada, boa para projetos detalhados e leitura frontal.", "Média/Alta", "Fineline, realismo, lettering", "Vertical", "Médio", ["Selo: campeã de orçamento."]),
  antebraco_externo: area("Antebraço Externo", 1200, 3200, "Área resistente e excelente para contraste, com boa leitura no dia a dia.", "Média", "Projetos visíveis e fechamentos", "Vertical", "Médio", []),
  pulso: area("Pulso", 600, 1600, "Área menor e delicada para símbolos, pulseiras, detalhes e complementos.", "Média/Alta", "Lettering, símbolos e ornamentos", "Circular ou horizontal", "Pequeno simples", ["Linhas muito pequenas podem abrir com o tempo."]),
  mao: area("Mão", 900, 2800, "Área muito visível e com maior desgaste. Precisa de desenho forte e manutenção consciente.", "Alta", "Blackwork, símbolos e lettering", "Encaixe anatômico", "Pequeno forte", ["Mão desgasta mais. Contraste e simplicidade vencem."]),
  dedos_mao: area("Dedos da mão", 400, 1300, "Área pequena, visível e com alto desgaste. Melhor para símbolos simples e letras.", "Alta", "Símbolos simples, letras e ornamentos", "Linear", "Micro, mas sem excesso", ["Dedos precisam de desenho simples para não virar borrão com o tempo."]),
  coxa_frontal: area("Coxa Frontal", 2500, 7200, "Área ampla para peças grandes, fechamentos e projetos com bastante detalhe.", "Média", "Realismo, anime, blackwork", "Vertical", "Grande", ["Selo: ótima para projeto grande com impacto."]),
  coxa_posterior: area("Coxa Posterior", 2300, 6600, "Excelente para continuidade de fechamento de perna e composição vertical.", "Média/Alta", "Fechamento de perna e projetos verticais", "Vertical", "Grande", []),
  gluteo: area("Glúteo", 1800, 4600, "Boa para composições grandes e complementos de perna ou quadril.", "Média", "Ornamental, blackwork e fechamentos", "Anatômica", "Médio", []),
  joelho: area("Joelho", 1200, 3800, "Área complexa, dobra bastante e exige desenho inteligente.", "Alta", "Ornamentos, mandalas e blackwork", "Circular", "Médio", ["Região que distorce com movimento. Desenho precisa respirar."]),
  joelho_posterior: area("Parte de trás do joelho", 900, 2700, "Área sensível e delicada, geralmente usada para complementar fechamento de perna.", "Alta", "Complementos e ornamentos", "Horizontal ou anatômica", "Pequeno forte", ["Dobra bastante. Evitar microdetalhe."]),
  canela: area("Canela", 1800, 4800, "Área de impacto visual, ótima para projetos verticais e contraste forte.", "Alta", "Realismo, anime e blackwork", "Vertical", "Médio", []),
  panturrilha: area("Panturrilha", 1800, 4800, "Boa curvatura para peças médias, grandes e fechamento de perna.", "Média", "Peças médias, grandes e fechamento", "Vertical ou anatômica", "Médio", []),
  tornozelo: area("Tornozelo", 600, 1900, "Área menor, boa para detalhes, tornozeleiras e complementos.", "Média/Alta", "Ornamentos, símbolos e fineline", "Circular", "Pequeno forte", []),
  pe: area("Pé", 900, 2800, "Área visível, sensível e com desgaste. Precisa de projeto simples e bem planejado.", "Alta", "Símbolos, ornamentos e projetos pequenos", "Horizontal ou anatômica", "Pequeno forte", ["Pé desgasta bastante por atrito. Evitar detalhe minúsculo."]),
  dedos_pe: area("Dedos do pé", 400, 1100, "Área pequena, sensível e com alto desgaste. Melhor para marcas simples.", "Alta", "Símbolos pequenos e letras", "Linear", "Micro simples", ["Dedos do pé têm alto desgaste e pedem manutenção."])
};

function area(titulo, min, max, descricao, dor, indicacao, orientacao, minimo, avisos) {
  return { titulo, min, max, descricao, dor, indicacao, orientacao, minimo, avisos, ativa: true };
}

const multiplicadores = {
  tamanho: [
    { label: "Pequeno", mult: .68 },
    { label: "Médio", mult: 1 },
    { label: "Grande", mult: 1.55 },
    { label: "Fechamento", mult: 2.45 }
  ],
  estilo: { fineline: .9, blackwork: 1, realismo: 1.35, anime: 1.25, lettering: .85, ornamental: 1.08 },
  detalhe: { simples: .82, medio: 1, insano: 1.45 },
  cor: { pb: 1, colorido: 1.22 },
  tipoProjeto: { primeira: 1, continuidade: 1.08, cobertura: 1.38, fechamento: 1.6 }
};

let dados = carregarDados();
let selecionadas = new Set();
let vistaAtual = "frente";

const $ = (id) => document.getElementById(id);
const tooltip = $("tooltip");
const controls = ["cliente", "tamanho", "estilo", "detalhe", "cor", "tipoProjeto", "sessoesInput", "utm", "referencia"];

function carregarDados() {
  try {
    const saved = JSON.parse(localStorage.getItem("orcamentoTattooAreas"));
    return saved ? { ...dadosBase, ...saved } : { ...dadosBase };
  } catch (e) {
    return { ...dadosBase };
  }
}

function moeda(valor) {
  return valor.toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 });
}

function getOpcoes() {
  const tamanho = multiplicadores.tamanho[Number($("tamanho").value)];
  return {
    tamanho,
    estilo: $("estilo").value,
    detalhe: $("detalhe").value,
    cor: $("cor").value,
    tipoProjeto: $("tipoProjeto").value,
    sessoes: Number($("sessoesInput").value)
  };
}

function calcular() {
  const areas = [...selecionadas].map(id => dados[id]).filter(Boolean);
  if (!areas.length) return null;
  const op = getOpcoes();
  const baseMin = areas.reduce((total, item) => total + Number(item.min || 0), 0);
  const baseMax = areas.reduce((total, item) => total + Number(item.max || 0), 0);
  const multi = op.tamanho.mult * multiplicadores.estilo[op.estilo] * multiplicadores.detalhe[op.detalhe] * multiplicadores.cor[op.cor] * multiplicadores.tipoProjeto[op.tipoProjeto];
  const sessaoExtra = Math.max(0, op.sessoes - 1) * 250;
  return {
    min: Math.round((baseMin * multi + sessaoExtra) / 50) * 50,
    max: Math.round((baseMax * multi + sessaoExtra * 1.6) / 50) * 50
  };
}

function atualizar() {
  $("tamanhoLabel").innerText = multiplicadores.tamanho[Number($("tamanho").value)].label;
  document.querySelectorAll(".body-part").forEach(part => {
    const ativa = dados[part.dataset.area]?.ativa !== false;
    part.classList.toggle("disabled", !ativa);
    part.classList.toggle("selected", selecionadas.has(part.dataset.area));
  });

  const ids = [...selecionadas];
  const areas = ids.map(id => dados[id]).filter(Boolean);
  const total = calcular();

  $("selectionList").innerHTML = areas.length
    ? areas.map(item => `<button class="pill" type="button" data-remove="${item.titulo}">${item.titulo} ×</button>`).join("")
    : "";

  if (!areas.length || !total) {
    $("titulo").innerText = "Selecione uma área";
    $("preco").innerText = "---";
    $("descricao").innerText = "Clique em uma ou mais partes do corpo para montar uma estimativa inicial.";
    $("dor").innerText = "---";
    $("indicacao").innerText = "---";
    $("orientacao").innerText = "---";
    $("minimo").innerText = "---";
    $("warnings").innerHTML = "";
    renderGallery();
    atualizarLinks();
    return;
  }

  const principal = areas[areas.length - 1];
  $("titulo").innerText = areas.length === 1 ? principal.titulo : `${areas.length} áreas selecionadas`;
  $("preco").innerText = `${moeda(total.min)} a ${moeda(total.max)}`;
  $("descricao").innerText = principal.descricao;
  $("dor").innerText = combinarUnico(areas.map(item => item.dor));
  $("indicacao").innerText = principal.indicacao;
  $("orientacao").innerText = principal.orientacao;
  $("minimo").innerText = principal.minimo;

  const avisos = [
    ...new Set(areas.flatMap(item => item.avisos || [])),
    "Valor final depende de avaliação da pele, referência e encaixe do desenho."
  ];
  $("warnings").innerHTML = avisos.map(aviso => `<div class="warning">${aviso}</div>`).join("");
  renderGallery(principal);
  atualizarLinks();
}

function combinarUnico(valores) {
  return [...new Set(valores.filter(Boolean))].join(" / ");
}

function atualizarLinks() {
  const total = calcular();
  const areas = [...selecionadas].map(id => dados[id]?.titulo).filter(Boolean);
  const op = getOpcoes();
  const nome = $("cliente").value.trim() || "Cliente ainda não informou";
  const referencia = $("referencia").value.trim() || "Vou enviar referência pelo WhatsApp";
  const valor = total ? `${moeda(total.min)} a ${moeda(total.max)}` : "Aguardando seleção de área";
  const utm = $("utm").value.trim() || "orcamento_mapa";
  const msg = [
    "Olá! Quero um orçamento de tatuagem.",
    `Nome: ${nome}`,
    `Área escolhida: ${areas.length ? areas.join(", ") : "a definir"}`,
    `Tamanho: ${op.tamanho.label}`,
    `Estilo: ${textoSelect("estilo")}`,
    `Detalhe: ${textoSelect("detalhe")}`,
    `Cor: ${textoSelect("cor")}`,
    `Projeto: ${textoSelect("tipoProjeto")}`,
    `Sessões estimadas: ${textoSelect("sessoesInput")}`,
    `Referência: ${referencia}`,
    `Pré-orçamento: ${valor}`,
    `Origem: ${utm}`,
    "Sei que o valor final depende de avaliação."
  ].join("\n");
  const utmParam = encodeURIComponent(utm);
  const href = `https://wa.me/${whatsapp}?text=${encodeURIComponent(msg)}&utm_source=${utmParam}&utm_medium=site&utm_campaign=${utmParam}`;
  $("zap").href = href;
  $("zapRef").href = href;
  $("mobileCta").href = href;
}

function textoSelect(id) {
  const el = $(id);
  return el.options[el.selectedIndex]?.text || el.value;
}

function renderGallery(areaAtual) {
  const titulo = areaAtual?.titulo || "Portfólio";
  $("portfolioIntro").innerText = areaAtual
    ? `Exemplos e ideias recomendadas para ${titulo}. Depois podemos trocar por fotos marcadas por região no admin.`
    : "Selecione uma região para filtrar exemplos, antes/depois e projetos recomendados.";

  $("gallery").innerHTML = galleryImages.slice(0, 6).map((src, index) => `
    <article class="gallery-card">
      <img src="${src}" alt="Exemplo de tattoo ${index + 1}" loading="lazy">
      <div>${areaAtual ? titulo : "Exemplo real"} · ${index % 3 === 0 ? "projeto recomendado" : index % 3 === 1 ? "referência de estilo" : "ideia de encaixe"}</div>
    </article>
  `).join("");
}

function selecionarArea(area) {
  if (!dados[area] || dados[area].ativa === false) return;
  if (selecionadas.has(area)) selecionadas.delete(area);
  else selecionadas.add(area);
  aplicarZoom(area);
  registrarEvento("area_click", { area });
  atualizar();
}

function aplicarZoom(area) {
  const svg = $(vistaAtual);
  svg.setAttribute("viewBox", smallAreaViewBoxes[area] || baseViewBox);
}

function resetZoom() {
  $("frente").setAttribute("viewBox", baseViewBox);
  $("costas").setAttribute("viewBox", baseViewBox);
}

function registrarEvento(nome, payload) {
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ event: nome, ...payload });
  if (window.fbq) window.fbq("trackCustom", nome, payload);
}

document.querySelectorAll(".body-part").forEach(part => {
  part.addEventListener("click", () => selecionarArea(part.dataset.area));
  part.addEventListener("mousemove", (event) => {
    const item = dados[part.dataset.area];
    if (!item) return;
    tooltip.textContent = item.titulo;
    tooltip.style.left = `${event.clientX}px`;
    tooltip.style.top = `${event.clientY}px`;
    tooltip.classList.add("show");
    document.querySelectorAll(`[data-area="${part.dataset.area}"]`).forEach(el => el.classList.add("focused"));
  });
  part.addEventListener("mouseleave", () => {
    tooltip.classList.remove("show");
    document.querySelectorAll(".body-part.focused").forEach(el => el.classList.remove("focused"));
  });
});

document.querySelectorAll("[data-view]").forEach(button => {
  button.addEventListener("click", () => {
    vistaAtual = button.dataset.view;
    document.querySelectorAll("[data-view]").forEach(btn => btn.classList.toggle("active", btn === button));
    $("frente").classList.toggle("hidden", vistaAtual !== "frente");
    $("costas").classList.toggle("hidden", vistaAtual !== "costas");
    resetZoom();
  });
});

document.querySelectorAll("[data-profile]").forEach(button => {
  button.addEventListener("click", () => {
    const profile = button.dataset.profile;
    document.querySelectorAll("[data-profile]").forEach(btn => btn.classList.toggle("active", btn === button));
    $("mapStage").className = `map-wrap profile-${profile}`;
  });
});

controls.forEach(id => $(id).addEventListener("input", atualizar));
$("resetZoom").addEventListener("click", resetZoom);
$("clearSelection").addEventListener("click", () => {
  selecionadas.clear();
  resetZoom();
  atualizar();
});

$("selectionList").addEventListener("click", (event) => {
  const button = event.target.closest("[data-remove]");
  if (!button) return;
  const area = Object.entries(dados).find(([, item]) => item.titulo === button.dataset.remove)?.[0];
  if (area) selecionadas.delete(area);
  atualizar();
});

$("zap").addEventListener("click", () => registrarEvento("whatsapp_click", { areas: [...selecionadas].join(",") }));
$("zapRef").addEventListener("click", () => registrarEvento("whatsapp_referencia_click", { areas: [...selecionadas].join(",") }));
$("mobileCta").addEventListener("click", () => registrarEvento("whatsapp_mobile_click", { areas: [...selecionadas].join(",") }));

$("adminToggle").addEventListener("click", () => {
  $("adminPanel").classList.toggle("open");
  renderAdmin();
});

$("saveAdmin").addEventListener("click", () => {
  document.querySelectorAll("#adminRows tr").forEach(row => {
    const id = row.dataset.area;
    dados[id].min = Number(row.querySelector("[data-min]").value || dados[id].min);
    dados[id].max = Number(row.querySelector("[data-max]").value || dados[id].max);
    dados[id].ativa = row.querySelector("[data-active]").checked;
  });
  localStorage.setItem("orcamentoTattooAreas", JSON.stringify(dados));
  atualizar();
});

function renderAdmin() {
  $("adminRows").innerHTML = Object.entries(dados).map(([id, item]) => `
    <tr data-area="${id}">
      <td>${item.titulo}</td>
      <td><input data-min type="number" min="0" step="50" value="${item.min}"></td>
      <td><input data-max type="number" min="0" step="50" value="${item.max}"></td>
      <td><input data-active type="checkbox" ${item.ativa !== false ? "checked" : ""}></td>
    </tr>
  `).join("");
}

renderGallery();
renderAdmin();
atualizar();
</script>
</body>
</html>
