# Primeira Voz

Jogo de teoria musical — do zero ao avançado. HTML + JS puro, sem build, sem dependência.

Método: **ouvir → nomear → ler → escrever.** O símbolo vem por último, nunca primeiro.

## Como jogar

Abra `index.html` (ou sirva a pasta) e comece pela **Fase 1**. O progresso fica em `localStorage`.

---

## Capítulo 1 — A jornada sonora (fases 1–15)

Nada de nome, nada de símbolo. Você treina o ouvido primeiro.

| Fase | O que ensina |
|---|---|
| 1 | A Pulsação — o tempo antes das notas |
| 2 | Grave e Agudo — direção e salto |
| 3 | Altura Aproximada — contorno melódico |
| 4 | Maior e Menor — o humor da escala |
| 5 | Dó Maior no teclado — onde a escala mora |
| 6 | Tom e Semitom — os dois tamanhos de degrau |
| 7 | Intervalos — 2ª até 8ª |
| 8 | Solfejo — dar nome às notas |
| 9 | Armaduras — sustenidos e bemóis |
| 10 | Acordes I–IV–V |
| 11 | Círculo das Quintas |
| 12 | Função — tônica, subdominante, dominante |
| 13 | Cadências |
| 14 | Modos |
| 15 | Harmonia — análise real |

## Capítulo 2 — O capítulo da escrita (fases 16–20)

Aqui o símbolo aparece. Você já sabe ouvir tudo isso; agora aprende a ler e escrever.

| Fase | O que ensina |
|---|---|
| 16 | A Pauta — onde a nota mora escrita |
| 17 | Ler e Ouvir — o símbolo vira som |
| 18 | Figuras de Tempo — semibreve, mínima, semínima, colcheia |
| 19 | Escrever a Melodia — você escreve o que ouviu |
| 20 | Ler e Tocar — leia uma frase nunca ouvida |

---

## Estrutura

```
musica/
├── index.html          # casca: canvas + camada de UI
├── css/style.css       # identidade visual (tinta, papel, vermelho, ouro)
├── js/
│   ├── audio.js        # motor de áudio Web Audio (síntese, zero sample)
│   ├── teoria.js       # matemática musical (Hz, intervalos, escalas, acordes, graus)
│   ├── engine.js       # laço, canvas, física vetorial, utilidades de desenho
│   ├── partitura.js    # pauta real: clave, notas, hastes, figuras de tempo
│   ├── fases.js        # as 20 fases: dados, modos e conteúdo de cada uma
│   ├── voz.js          # trilha visual "A Primeira Voz" + avatar do Daniel
│   └── jogo.js         # telas, menus, progresso, cola tudo
└── README.md
```

## Decisões de design

- **Errar nunca é alarme.** A resposta errada é o som vizinho de meio-tom, não silêncio nem ruído.
- **Nada de áudio de terceiros.** Todo som é sintetizado no navegador em tempo real.
- **Progresso não trava o menu.** Todas as fases estão liberadas; o que se ganha é selo e a evolução da voz.
- **A pauta é desenhada à mão em canvas** — clave, hastes, linhas suplementares. A geometria foi
  conferida contra a teoria real (Mi4 na linha de baixo, Dó4 na suplementar abaixo, etc.).

## Validado em bancada

- Afinação por FFT sobre tom sintetizado: Dó central medido em **261,626 Hz (0,00 cent de erro)**.
- Geometria da pauta: ida e volta grau ↔ MIDI consistente em toda a faixa legível.
- 135 checagens automatizadas passando (64 da jornada sonora + 71 do capítulo da escrita).

