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

## Capítulo 3 — O capítulo da harmonia (fases 21–26)

Escalas, acordes, arpejos e os modos gregos — construídos, não decorados.

| Fase | O que ensina |
|---|---|
| 21 | A Escala — a receita de passos (2-2-1-2-2-2-1) |
| 22 | Empilhar Terças — o acorde nasce da escala |
| 23 | Arpejo — as notas do acorde em fila |
| 24 | Inversões — o mesmo acorde, outro peso |
| 25 | Os Sete Modos — uma escala, sete pontos de partida |
| 26 | Modo e Humor — uma nota muda o caráter inteiro |

### O conceito central dos modos

Os modos gregos **não são sete escalas**. São a mesma escala maior começando de
sete pontos diferentes. Em Dó maior:

| Modo | Começa em | Receita | Humor |
|---|---|---|---|
| Jônio | Dó | 2-2-1-2-2-2-1 | alegre, resolve |
| Dórico | Ré | 2-1-2-2-2-1-2 | menor com esperança |
| Frígio | Mi | 1-2-2-2-1-2-2 | espanhol, sombrio |
| Lídio | Fá | 2-2-2-1-2-2-1 | etéreo, flutuante |
| Mixolídio | Sol | 2-2-1-2-2-1-2 | maior com blues |
| Eólio | Lá | 2-1-2-2-1-2-2 | triste sem drama |
| Lócrio | Si | 1-2-2-1-2-2-2 | instável |

Nenhuma nota foi adicionada ou removida em nenhum deles. Muda só onde a escala para.

---

## Estrutura

```
musica/
├── index.html          # casca: canvas + camada de UI
├── css/style.css       # identidade visual (tinta, papel, vermelho, ouro)
├── js/
│   ├── audio.js        # motor de áudio Web Audio (síntese, zero sample)
│   ├── teoria.js       # matemática musical (Hz, escalas, acordes, modos, arpejos, inversões)
│   ├── engine.js       # laço, canvas, física vetorial, utilidades de desenho
│   ├── partitura.js    # pauta real: clave, notas, hastes, figuras de tempo
│   ├── fases.js        # as 26 fases: dados, modos e conteúdo de cada uma
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
- **A grafia segue o que o músico escreve:** Dó menor com sétima sai "Dó Mib Sol Sib",
  nunca "Dó Ré# Sol Lá#". A tecla é a mesma, mas ensinar a grafia errada cria confusão depois.

## Validado em bancada

- Afinação por FFT sobre tom sintetizado: Dó central medido em **261,626 Hz (0,00 cent de erro)**.
- Geometria da pauta: ida e volta grau ↔ MIDI consistente em toda a faixa legível.
- 135 checagens automatizadas passando (64 da jornada sonora + 71 do capítulo da escrita).
- **202 checagens automatizadas** passando no total:
  - 64 — jornada sonora (`testar.js`)
  - 71 — capítulo da escrita (`testar-partitura.js`)
  - 67 — capítulo da harmonia (`testar-harmonia.js`)
- **Teoria conferida contra a música real, não contra si mesma:** os sete modos de Dó foram
  checados um a um (partem de Dó, Ré, Mi, Fá, Sol, Lá, Si; receitas conferem com a rotação da
  escala maior). A pentatônica maior de Dó foi comparada com a menor de Lá: mesma coleção.
  As inversões de Dó maior foram checadas para conterem sempre as mesmas notas.

