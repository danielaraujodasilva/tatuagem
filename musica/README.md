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

## Capítulo 3 — O capítulo do ritmo (fases 21–27)

Segue a ordem do **Método Bona**: começa SÓ no compasso quaternário simples e só
depois abre para os outros. É a ordem que o método usa porque funciona.

| Fase | O que ensina |
|---|---|
| 21 | Compasso 4/4 — a fórmula e os tempos fortes |
| 22 | Binário e Ternário — marcha (2) e valsa (3) |
| 23 | Compassos Compostos — 6/8, 9/8, 12/8: subdivisão ternária |
| 24 | Pausas — o silêncio também tem duração |
| 25 | Ponto e Ligadura — o ponto soma metade |
| 26 | Síncope — o acento deslocado |
| 27 | Tercinas — três no lugar de dois |

### O conceito que confunde todo iniciante

Compasso **simples** subdivide em 2; **composto** subdivide em 3. Por isso 2/4 e 6/8
não são irmãos, são **correspondentes**: duram o mesmo, mas subdividem diferente.

| Simples | Composto |
|---|---|
| 2/4 (marcha) | 6/8 (baião) |
| 3/4 (valsa) | 9/8 |
| 4/4 | 12/8 (blues, gospel) |

---

## Capítulo 4 — O capítulo da escrita (fases 16–20)

Aqui o símbolo aparece. Você já sabe ouvir tudo isso; agora aprende a ler e escrever.

| Fase | O que ensina |
|---|---|
| 16 | A Pauta — onde a nota mora escrita |
| 17 | Ler e Ouvir — o símbolo vira som |
| 18 | Figuras de Tempo — semibreve, mínima, semínima, colcheia |
| 19 | Escrever a Melodia — você escreve o que ouviu |
| 20 | Ler e Tocar — leia uma frase nunca ouvida |

## Capítulo 5 — O capítulo da harmonia (fases 28–33)

Escalas, acordes, arpejos e os modos gregos — construídos, não decorados.

| Fase | O que ensina |
|---|---|
| 28 | A Escala — a receita de passos (2-2-1-2-2-2-1) |
| 29 | Empilhar Terças — o acorde nasce da escala |
| 30 | Arpejo — as notas do acorde em fila |
| 31 | Inversões — o mesmo acorde, outro peso |
| 32 | Os Sete Modos — uma escala, sete pontos de partida |
| 33 | Modo e Humor — uma nota muda o caráter inteiro |

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
│   ├── fases.js        # as 33 fases: dados, modos e conteúdo de cada uma
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
- **295 checagens automatizadas** passando no total:
  - 64 — jornada sonora (	estar.js)
  - 71 — capítulo da escrita (	estar-partitura.js)
  - 75 — capítulo do ritmo (	estar-ritmo.js)
  - 68 — capítulo da harmonia (	estar-harmonia.js)
  - 17 — migração de progresso (	estar-migracao.js)
- **Teoria conferida contra a música real, não contra si mesma:** compassos (6/8 tem 2 tempos, não 6),
  correspondentes (2/4 ↔ 6/8 com numerador triplo), tercina (3 notas somam exatamente 2),
  ponto (soma metade), e os 7 modos de Dó um a um.

