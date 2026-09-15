# Primeira Voz

Jogo de teoria musical — do zero ao avançado. HTML + JS puro, sem build, sem dependência.

Método: **ouvir → nomear → ler → escrever.** O símbolo vem por último, nunca primeiro.

## Como jogar

Abra `index.html` (ou sirva a pasta) e comece pela **Fase 1**. O progresso fica em `localStorage`.

## Estrutura

```
musica/
├── index.html          # casca: canvas + camada de UI
├── css/style.css       # identidade visual (tinta, papel, vermelho, ouro)
├── js/
│   ├── audio.js        # motor de áudio Web Audio (síntese, zero sample)
│   ├── teoria.js       # matemática musical (Hz, intervalos, escalas, acordes, graus)
│   ├── engine.js       # laço, canvas, física vetorial, utilidades de desenho
│   ├── fases.js        # as 15 fases: dados, modos e conteúdo de cada uma
│   ├── voz.js          # trilha visual "A Primeira Voz" + avatar do Daniel
│   └── main.js         # telas, menus, progresso, cola tudo
└── README.md
```

## Decisões de design

- **Errar nunca é alarme.** A resposta errada é o som vizinho de meio-tom, não silêncio nem ruído.
- **Nada de áudio de terceiros.** Todo som é sintetizado no navegador em tempo real.
- **Progresso não trava o menu.** Todas as fases estão liberadas; o que se ganha é selo e a evolução da voz.

## Validado em bancada

Afinação por FFT sobre tom sintetizado: Dó central medido em **261,626 Hz (0,00 cent de erro)** — o modo "toque junto com o jogo" é confiável.
