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
html, body {
  max-width: 100%;
  overflow-x: hidden;
}

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
  min-width: 0;
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
  min-width: 0;
  max-width: 100%;
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
  display: none;
}

.body-shade { display: none; }

.body-line {
  display: none;
}

.body-part {
  fill: rgba(215,25,42,.13);
  stroke: rgba(255,255,255,.54);
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

.promo-panel {
  grid-column: 1 / -1;
  padding: 20px;
  border-color: rgba(255,52,75,.42);
  background:
    linear-gradient(135deg, rgba(215,25,42,.24), rgba(9,9,9,.98) 46%),
    linear-gradient(180deg, rgba(27,27,27,.98), rgba(9,9,9,.98));
  box-shadow: 0 22px 62px rgba(215,25,42,.16), 0 18px 54px rgba(0,0,0,.45);
}

.section-title {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 14px;
  margin-bottom: 12px;
}

.section-title h2 {
  margin: 0;
  font-size: clamp(24px, 3vw, 34px);
}

.section-title p {
  margin: 4px 0 0;
  color: var(--muted);
}

.promo-picker {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  min-width: 0;
}

.promo-picker select {
  min-width: 0;
  border: 1px solid rgba(255,52,75,.35);
  border-radius: 8px;
  padding: 12px;
  background: #101010;
  color: #fff;
  font-weight: 900;
  min-height: 48px;
}

.promo-picker button {
  min-height: 48px;
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 8px;
  padding: 12px 16px;
  background: rgba(255,52,75,.25);
  color: #fff;
  font-weight: 900;
}

.promo-current {
  margin-top: 10px;
  color: #ffcbd0;
  font-size: 13px;
  line-height: 1.4;
  font-weight: 800;
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
  min-width: 0;
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
  .promo-picker { grid-template-columns: 1fr; }
}

@media (max-width: 720px) {
  body { padding: 14px 10px 88px; }
  .header h1 { font-size: 33px; }
  .segmented { width: 100%; display: grid; grid-auto-flow: column; }
  .map-card { padding: 10px; }
  .map-wrap { min-height: 570px; align-items: start; }
  .body-map { width: 100%; max-width: 560px; }
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

  <section class="card promo-panel">
    <div class="section-title">
      <div>
        <h2>Promoções de fechamento</h2>
        <p>Escolha uma promoção para selecionar o pacote completo.</p>
      </div>
    </div>
    <div class="promo-picker">
      <select id="promoSelect"></select>
      <button id="applyPromo" type="button">Aplicar promoção</button>
    </div>
    <div class="promo-current" id="promoCurrent"></div>
  </section>

  <section class="card map-card">
    <div class="topbar">
      <div class="segmented" aria-label="Vista do corpo">
        <button class="active" type="button" data-view="frente">Frente</button>
        <button type="button" data-view="costas">Costas</button>
      </div>
    </div>

    <div class="map-wrap">
      <svg id="frente" class="body-map" viewBox="0 0 420 820" aria-label="Mapa corporal frente">
        <path class="body-base" d="M210 24 C187 24 170 43 170 69 C170 84 174 100 181 111 C179 121 181 137 184 146 C168 153 154 158 143 164 C124 174 111 190 104 213 C99 230 98 248 101 264 C103 278 97 293 92 306 C84 329 83 354 87 378 C90 398 86 414 78 433 C71 452 66 471 59 489 C54 504 44 516 32 528 C25 535 22 548 19 561 C17 571 10 582 1 584 C-6 586 -8 579 -2 573 C5 566 10 555 13 546 C16 536 21 526 27 520 C22 542 18 565 15 590 C14 602 7 606 4 595 C3 574 7 551 12 531 C15 520 22 509 29 502 C27 527 26 556 27 589 C27 604 19 609 15 596 C11 569 13 540 18 516 C20 505 28 495 36 489 C36 513 38 548 42 588 C43 601 36 607 31 595 C25 562 24 530 26 505 C28 491 37 482 48 476 C51 458 55 440 62 420 C69 398 75 377 75 357 C75 330 80 306 88 286 C94 270 91 250 90 235 C88 205 100 179 123 163 C134 156 149 151 164 147 L184 136 C188 164 192 177 210 177 C228 177 232 164 236 136 L256 147 C271 151 286 156 297 163 C320 179 332 205 330 235 C329 250 326 270 332 286 C340 306 345 330 345 357 C345 377 351 398 358 420 C365 440 369 458 372 476 C383 482 392 491 394 505 C396 530 395 562 389 595 C384 607 377 601 378 588 C382 548 384 513 384 489 C392 495 400 505 402 516 C407 540 409 569 405 596 C401 609 393 604 393 589 C394 556 393 527 391 502 C398 509 405 520 408 531 C413 551 417 574 416 595 C413 606 406 602 405 590 C402 565 398 542 393 520 C399 526 404 536 407 546 C410 555 415 566 422 573 C428 579 426 586 419 584 C410 582 403 571 401 561 C398 548 395 535 388 528 C376 516 366 504 361 489 C354 471 349 452 342 433 C334 414 330 398 333 378 C337 354 336 329 328 306 C323 293 317 278 319 264 C322 248 321 230 316 213 C309 190 296 174 277 164 C266 158 252 153 236 146 C239 175 250 190 257 222 C264 254 262 296 258 337 C255 373 258 411 266 449 C276 497 282 554 284 612 C286 668 287 714 291 733 C293 745 310 757 324 771 C338 785 352 799 362 808 C370 815 366 822 356 819 C347 822 334 816 324 807 C314 799 302 789 289 779 C279 771 273 762 271 750 C268 725 268 684 267 640 C265 594 255 554 243 516 C231 480 222 451 210 451 C198 451 189 480 177 516 C165 554 155 594 153 640 C152 684 152 725 149 750 C147 762 141 771 131 779 C118 789 106 799 96 807 C86 816 73 822 64 819 C54 822 50 815 58 808 C68 799 82 785 96 771 C110 757 127 745 129 733 C133 714 134 668 136 612 C138 554 144 497 154 449 C162 411 165 373 162 337 C158 296 156 254 163 222 C170 190 181 175 184 146 C181 137 179 121 181 111 C174 100 170 84 170 69 C170 43 187 24 210 24 Z"/>
        <path class="body-shade" d="M164 182 Q210 154 256 182 Q263 247 256 337 Q250 390 210 420 Q170 390 164 337 Q157 247 164 182 Z"/>
        <path class="body-line" d="M188 70 Q210 78 232 70 M196 94 Q210 102 224 94 M181 184 Q210 202 239 184 M210 166 L210 451 M170 296 Q210 318 250 296 M166 458 Q210 488 254 458 M181 617 Q181 672 177 722 M239 617 Q239 672 243 722"/>

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
        <path class="body-part" data-id="dedos_mao_esq" data-region="dedos_mao" d="M84 480 L91 482 L88 518 Q81 521 78 513 Z M92 484 L99 484 L96 532 Q87 535 85 524 Z M101 491 L108 491 L107 539 Q98 542 96 531 Z M111 491 L118 491 L119 536 Q110 540 108 529 Z M121 484 L129 486 L131 520 Q125 529 119 519 Z"/>
        <path class="body-part" data-id="dedos_mao_dir" data-region="dedos_mao" d="M336 480 L329 482 L332 518 Q339 521 342 513 Z M328 484 L321 484 L324 532 Q333 535 335 524 Z M319 491 L312 491 L313 539 Q322 542 324 531 Z M309 491 L302 491 L301 536 Q310 540 312 529 Z M299 484 L291 486 L289 520 Q295 529 301 519 Z"/>
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
        <path class="body-part" data-id="dedos_pe_esq" data-region="dedos_pe" d="M139 775 Q144 767 150 776 Q150 785 142 783 Z M151 779 Q156 769 162 779 Q161 788 153 786 Z M163 781 Q168 771 174 781 Q173 790 165 788 Z M175 781 Q180 773 185 782 Q184 790 177 788 Z M187 779 Q192 773 197 781 Q196 788 189 786 Z"/>
        <path class="body-part" data-id="dedos_pe_dir" data-region="dedos_pe" d="M281 775 Q276 767 270 776 Q270 785 278 783 Z M269 779 Q264 769 258 779 Q259 788 267 786 Z M257 781 Q252 771 246 781 Q247 790 255 788 Z M245 781 Q240 773 235 782 Q236 790 243 788 Z M233 779 Q228 773 223 781 Q224 788 231 786 Z"/>
      </svg>

      <svg id="costas" class="body-map hidden" viewBox="0 0 420 820" aria-label="Mapa corporal costas">
        <path class="body-base" d="M210 24 C187 24 170 43 170 69 C170 84 174 100 181 111 C179 121 181 137 184 146 C168 153 154 158 143 164 C124 174 111 190 104 213 C99 230 98 248 101 264 C103 278 97 293 92 306 C84 329 83 354 87 378 C90 398 86 414 78 433 C71 452 66 471 59 489 C54 504 44 516 32 528 C25 535 22 548 19 561 C17 571 10 582 1 584 C-6 586 -8 579 -2 573 C5 566 10 555 13 546 C16 536 21 526 27 520 C22 542 18 565 15 590 C14 602 7 606 4 595 C3 574 7 551 12 531 C15 520 22 509 29 502 C27 527 26 556 27 589 C27 604 19 609 15 596 C11 569 13 540 18 516 C20 505 28 495 36 489 C36 513 38 548 42 588 C43 601 36 607 31 595 C25 562 24 530 26 505 C28 491 37 482 48 476 C51 458 55 440 62 420 C69 398 75 377 75 357 C75 330 80 306 88 286 C94 270 91 250 90 235 C88 205 100 179 123 163 C134 156 149 151 164 147 L184 136 C188 164 192 177 210 177 C228 177 232 164 236 136 L256 147 C271 151 286 156 297 163 C320 179 332 205 330 235 C329 250 326 270 332 286 C340 306 345 330 345 357 C345 377 351 398 358 420 C365 440 369 458 372 476 C383 482 392 491 394 505 C396 530 395 562 389 595 C384 607 377 601 378 588 C382 548 384 513 384 489 C392 495 400 505 402 516 C407 540 409 569 405 596 C401 609 393 604 393 589 C394 556 393 527 391 502 C398 509 405 520 408 531 C413 551 417 574 416 595 C413 606 406 602 405 590 C402 565 398 542 393 520 C399 526 404 536 407 546 C410 555 415 566 422 573 C428 579 426 586 419 584 C410 582 403 571 401 561 C398 548 395 535 388 528 C376 516 366 504 361 489 C354 471 349 452 342 433 C334 414 330 398 333 378 C337 354 336 329 328 306 C323 293 317 278 319 264 C322 248 321 230 316 213 C309 190 296 174 277 164 C266 158 252 153 236 146 C239 175 250 190 257 222 C264 254 262 296 258 337 C255 373 258 411 266 449 C276 497 282 554 284 612 C286 668 287 714 291 733 C293 745 310 757 324 771 C338 785 352 799 362 808 C370 815 366 822 356 819 C347 822 334 816 324 807 C314 799 302 789 289 779 C279 771 273 762 271 750 C268 725 268 684 267 640 C265 594 255 554 243 516 C231 480 222 451 210 451 C198 451 189 480 177 516 C165 554 155 594 153 640 C152 684 152 725 149 750 C147 762 141 771 131 779 C118 789 106 799 96 807 C86 816 73 822 64 819 C54 822 50 815 58 808 C68 799 82 785 96 771 C110 757 127 745 129 733 C133 714 134 668 136 612 C138 554 144 497 154 449 C162 411 165 373 162 337 C158 296 156 254 163 222 C170 190 181 175 184 146 C181 137 179 121 181 111 C174 100 170 84 170 69 C170 43 187 24 210 24 Z"/>
        <path class="body-line" d="M182 184 Q210 160 238 184 M210 158 L210 455 M164 292 Q210 326 256 292 M171 394 Q210 420 249 394 M181 617 Q181 672 177 722 M239 617 Q239 672 243 722"/>

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
        <path class="body-part" data-id="dedos_mao_esq_costas" data-region="dedos_mao" d="M84 480 L91 482 L88 518 Q81 521 78 513 Z M92 484 L99 484 L96 532 Q87 535 85 524 Z M101 491 L108 491 L107 539 Q98 542 96 531 Z M111 491 L118 491 L119 536 Q110 540 108 529 Z M121 484 L129 486 L131 520 Q125 529 119 519 Z"/>
        <path class="body-part" data-id="dedos_mao_dir_costas" data-region="dedos_mao" d="M336 480 L329 482 L332 518 Q339 521 342 513 Z M328 484 L321 484 L324 532 Q333 535 335 524 Z M319 491 L312 491 L313 539 Q322 542 324 531 Z M309 491 L302 491 L301 536 Q310 540 312 529 Z M299 484 L291 486 L289 520 Q295 529 301 519 Z"/>
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
        <path class="body-part" data-id="dedos_pe_esq_costas" data-region="dedos_pe" d="M139 775 Q144 767 150 776 Q150 785 142 783 Z M151 779 Q156 769 162 779 Q161 788 153 786 Z M163 781 Q168 771 174 781 Q173 790 165 788 Z M175 781 Q180 773 185 782 Q184 790 177 788 Z M187 779 Q192 773 197 781 Q196 788 189 786 Z"/>
        <path class="body-part" data-id="dedos_pe_dir_costas" data-region="dedos_pe" d="M281 775 Q276 767 270 776 Q270 785 278 783 Z M269 779 Q264 769 258 779 Q259 788 267 786 Z M257 781 Q252 771 246 781 Q247 790 255 788 Z M245 781 Q240 773 235 782 Q236 790 243 788 Z M233 779 Q228 773 223 781 Q224 788 231 786 Z"/>
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
        <textarea id="referenciaTexto" placeholder="Descreva a ideia, cole um link ou avise que vai enviar a imagem no WhatsApp."></textarea>
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

const DEFAULT_PROMOS = [
  promo("Fechamento de braço externo esquerdo", "Antebraço externo + braço externo + ombro", ["antebraco_esq_externo", "braco_esq_costas", "ombro_esq_costas"], .85, "costas"),
  promo("Fechamento de braço externo direito", "Antebraço externo + braço externo + ombro", ["antebraco_dir_externo", "braco_dir_costas", "ombro_dir_costas"], .85, "costas"),
  promo("Fechamento de braço interno esquerdo", "Antebraço interno + braço interno", ["antebraco_esq_interno", "braco_esq_frente"], .9, "frente"),
  promo("Fechamento de braço interno direito", "Antebraço interno + braço interno", ["antebraco_dir_interno", "braco_dir_frente"], .9, "frente"),
  promo("Fechamento de perna frontal esquerda", "Coxa + joelho + canela + tornozelo", ["coxa_esq_frontal", "joelho_esq", "canela_esq", "tornozelo_esq"], .85, "frente"),
  promo("Fechamento de perna frontal direita", "Coxa + joelho + canela + tornozelo", ["coxa_dir_frontal", "joelho_dir", "canela_dir", "tornozelo_dir"], .85, "frente"),
  promo("Fechamento de perna posterior esquerda", "Coxa posterior + joelho posterior + panturrilha + tornozelo", ["coxa_esq_posterior", "joelho_esq_posterior", "panturrilha_esq", "tornozelo_esq_costas"], .85, "costas"),
  promo("Fechamento de perna posterior direita", "Coxa posterior + joelho posterior + panturrilha + tornozelo", ["coxa_dir_posterior", "joelho_dir_posterior", "panturrilha_dir", "tornozelo_dir_costas"], .85, "costas"),
  promo("Fechamento de costas", "Costas completa", ["costas_esq_alta", "costas_dir_alta", "costas_esq_baixa", "costas_dir_baixa", "lombar"], .82, "costas"),
  promo("Fechamento de peitoral", "Peito esquerdo + peito direito", ["peito_esq", "peito_dir"], .9, "frente"),
  promo("Fechamento frontal", "Peitoral completo + abdômen", ["peito_esq", "peito_dir", "abdomen"], .85, "frente")
];

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

function promo(titulo, descricao, ids, desconto, view) {
  return { titulo, descricao, ids, desconto, view, ativa: true };
}

const sideLabels = { esq: "esquerdo", dir: "direito" };
const $ = (id) => document.getElementById(id);
const config = load("orcamentoTattooConfig", DEFAULT_CONFIG);
const areas = load("orcamentoTattooAreas", DEFAULT_AREAS);
const promotions = loadPromos();
const selected = new Map();
let currentView = "frente";
let activePromoTitle = "";

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

function loadPromos() {
  try {
    const saved = JSON.parse(localStorage.getItem("orcamentoTattooPromos") || "[]");
    return Array.isArray(saved) && saved.length ? saved : DEFAULT_PROMOS;
  } catch (e) {
    return DEFAULT_PROMOS;
  }
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
  const applied = getAppliedPromo();
  const discount = applied?.desconto || 1;
  return {
    min: Math.round((total.min * discount) / 50) * 50,
    max: Math.round((total.max * discount) / 50) * 50,
    promo: applied
  };
}

function getAppliedPromo() {
  return promotions
    .filter(item => item.ativa !== false && Array.isArray(item.ids) && item.ids.every(id => selected.has(id)))
    .sort((a, b) => Number(a.desconto || 1) - Number(b.desconto || 1))[0] || null;
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
    renderPromos();
    updateLinks();
    return;
  }

  const last = items[items.length - 1];
  const data = areas[last.region];
  $("titulo").innerText = items.length === 1 ? last.label : `${items.length} áreas selecionadas`;
  $("preco").innerText = `${money(estimate.min)} a ${money(estimate.max)}`;
  $("descricao").innerText = estimate.promo
    ? `${estimate.promo.titulo}: ${estimate.promo.descricao}`
    : data.descricao || "Região selecionada para orçamento.";
  renderPromos();
  updateLinks();
}

function updateLinks() {
  const estimate = calculate();
  const client = $("cliente").value.trim() || "Cliente ainda não informou";
  const textRef = $("referenciaTexto").value.trim() || "Não descreveu ainda";
  const areaText = [...selected.values()].map(item => item.label).join(", ") || "a definir";
  const price = estimate ? `${money(estimate.min)} a ${money(estimate.max)}` : "Aguardando seleção de área";
  const promoLine = estimate?.promo ? `Promoção: ${estimate.promo.titulo}` : "Promoção: não selecionada";
  const msg = [
    "Olá! Quero um orçamento de tatuagem.",
    `Nome: ${client}`,
    `Área escolhida: ${areaText}`,
    promoLine,
    `Referência/ideia: ${textRef}`,
    "Vou enviar a imagem de referência aqui na conversa, se tiver.",
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
  activePromoTitle = "";
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

function selectPromo(item) {
  selected.clear();
  activePromoTitle = item.titulo;
  setView(item.view || inferPromoView(item));
  item.ids.forEach(id => {
    const el = document.querySelector(`[data-id="${id}"]`);
    if (!el || areas[el.dataset.region]?.ativa === false) return;
    selected.set(id, { id, region: el.dataset.region, label: partLabel(el.dataset) });
  });
  track("promo_click", { promo: item.titulo });
  update();
}

function renderPromos() {
  const activePromos = promotions.filter(item => item.ativa !== false);
  $("promoSelect").innerHTML = `<option value="">Selecionar promoção</option>` + activePromos
    .map((item, index) => {
      const discount = Math.round((1 - Number(item.desconto || 1)) * 100);
      return `<option value="${index}">${item.titulo}${discount > 0 ? ` · ${discount}% OFF` : ""}</option>`;
    }).join("");
  const selectedPromo = activePromos.find(item => item.titulo === activePromoTitle) || getAppliedPromo();
  if (selectedPromo) {
    $("promoSelect").value = String(activePromos.indexOf(selectedPromo));
    $("promoCurrent").innerText = `${selectedPromo.titulo}: ${selectedPromo.descricao}`;
  } else {
    $("promoCurrent").innerText = "";
  }
}

function inferPromoView(item) {
  const ids = Array.isArray(item.ids) ? item.ids.join(" ") : "";
  return /costas|posterior|panturrilha|nuca|lombar|gluteo|externo/.test(ids) ? "costas" : "frente";
}

function setView(view) {
  currentView = view === "costas" ? "costas" : "frente";
  document.querySelectorAll("[data-view]").forEach(btn => btn.classList.toggle("active", btn.dataset.view === currentView));
  $("frente").classList.toggle("hidden", currentView !== "frente");
  $("costas").classList.toggle("hidden", currentView !== "costas");
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
    setView(button.dataset.view);
  });
});

$("selectionList").addEventListener("click", event => {
  const button = event.target.closest("[data-remove]");
  if (!button) return;
  activePromoTitle = "";
  selected.delete(button.dataset.remove);
  update();
});

$("applyPromo").addEventListener("click", () => {
  const activePromos = promotions.filter(item => item.ativa !== false);
  const item = activePromos[Number($("promoSelect").value)];
  if (item) selectPromo(item);
});

$("clearSelection").addEventListener("click", () => {
  selected.clear();
  activePromoTitle = "";
  update();
});

["cliente", "referenciaTexto"].forEach(id => $(id).addEventListener("input", update));

$("zap").addEventListener("click", () => track("whatsapp_click", { areas: [...selected.values()].map(item => item.label).join(", ") }));
$("mobileCta").addEventListener("click", () => track("whatsapp_mobile_click", { areas: [...selected.values()].map(item => item.label).join(", ") }));

$("introText").innerText = config.intro || DEFAULT_CONFIG.intro;
renderPromos();
update();
</script>
</body>
</html>
