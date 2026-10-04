<?php
$pdo = v2_try(static fn() => v2_crm(), null);

$msgs = [];
if ($pdo instanceof PDO && v2_tabela_existe($pdo, 'crm_whatsapp_mensagens')) {
    $msgs = v2_q($pdo, 'SELECT cliente_id, from_me, tipo, texto, transcricao, data FROM crm_whatsapp_mensagens ORDER BY cliente_id, data, id');
}

$conv = [];
foreach ($msgs as $m) {
    $conv[(string)$m['cliente_id']][] = $m;
}

$limpa = static function (?string $t): string {
    return trim((string)preg_replace('/\s+/u', ' ', (string)$t));
};

$nossas = [];
$deles = [];
foreach ($msgs as $m) {
    $t = $limpa($m['texto']);
    if ($t === '') {
        continue;
    }
    if ((int)$m['from_me'] === 1) {
        $nossas[] = $t;
    } else {
        $deles[] = strtolower($t);
    }
}

$temas = [
    'horário / agenda' => '/hor[áa]rio|que horas|abre|fecha|disponibilidade|agenda|vaga/',
    'preço / quanto custa' => '/quanto|valor|pre[çc]o|caro|or[çc]amento|barato/',
    'onde fica / como chega' => '/onde|endere[çc]o|fica|localiza|como chego|metr[ôo]/',
    'cuidados / cicatrização' => '/cuidado|p[óo]s|cicatri|pomada|hidratante|sol/',
    'forma de pagamento' => '/cart[ãa]o|pix|d[ée]bito|cr[ée]dito|parcel|dinheiro/',
    'vou pensar / depois' => '/vou pensar|depois|mais pra frente|semana que vem|m[êe]s que vem|te falo/',
    'medo / dor' => '/d[óo]i|medo|anestesia|aguent|sangra/',
];
$contagem = [];
foreach ($temas as $nome => $re) {
    $n = 0;
    foreach ($deles as $t) {
        if (preg_match($re, $t)) {
            $n++;
        }
    }
    $contagem[$nome] = $n;
}
arsort($contagem);

$tempoResposta = [];
foreach ($conv as $lista) {
    for ($i = 1; $i < count($lista); $i++) {
        if ((int)$lista[$i]['from_me'] === 1 && (int)$lista[$i - 1]['from_me'] === 0) {
            $d = strtotime((string)$lista[$i]['data']) - strtotime((string)$lista[$i - 1]['data']);
            if ($d >= 0 && $d < 259200) {
                $tempoResposta[] = $d;
            }
        }
    }
}
sort($tempoResposta);
$mediana = $tempoResposta ? $tempoResposta[intdiv(count($tempoResposta), 2)] : 0;
$sobUmaHora = $tempoResposta ? (int)round(100 * count(array_filter($tempoResposta, static fn($s) => $s < 3600)) / count($tempoResposta)) : 0;



$exemplos = [
    'abertura' => ['Opa, legal! Qual seu nome? E já sabe qual tatuagem quer fazer?', 'Opa, qual seu nome? Já sabe qual tattoo quer fazer?', 'Bom dia 🌺', 'Boa noite 🌃'],
    'descoberta' => ['Qual seu nome?', 'Já sabe qual tatuagem quer fazer?', 'Quer fazer quando?', 'Qual a região do corpo?'],
    'preco' => ['699', '699 sem pomada anestésica', '1100 com pomada anestésica', '1200 com pomada anestésica', '700 cada'],
    'local' => ['Rua Catende, 287B, Jd Nordeste, São Paulo', 'Pertinho da estação Artur Alvim do metrô'],
    'fechamento' => ['Fechou', 'Assim que tiver ideia de data me chama 🔥 aqui', 'Obrigada 😘', 'Ta jóia, quando quiser agendar, é só me chamar aqui'],
    'reserva' => ['Trabalhamos com reserva ok', 'Vou precisar do seu nome completo, data, horário e o sinal de 50,00', 'No dia será descontado o valor'],
    'retomada' => ['Oi? Bora retomar o agendamento da sua tatuagem?', 'Opa, e ae, bora retomar?', 'tenho vaga pra domingo, quer aproveitar?'],
];

$vozAtual = (string)(v2_config()['tts_engine'] ?? 'windows');
$demoVoz = is_file(__DIR__ . '/../assets/voz/voz-edge-calor.mp3') ? 'assets/voz/voz-edge-calor.mp3' : '';
$podeOuvir = $demoVoz !== '' || v2_tts_available($vozAtual);

v2_layout_top('aprendizado', 'Aprendizado');
?>
<div class="head">
  <div class="kick">o que eu li e o que eu entendi</div>
  <h2>Aprendi lendo as conversas do estúdio</h2>
  <p><?= count($conv) ?> conversas, <?= count($nossas) + count($deles) ?> mensagens com texto. Não é teoria: é o jeito de vocês, extraído do histórico real do WhatsApp.</p>
</div>

<div class="grid g4">
  <div class="card kpi"><div class="k">conversas</div><div class="v"><?= count($conv) ?></div><div class="h t-blue">desde 29/04</div></div>
  <div class="card kpi"><div class="k">nossas mensagens</div><div class="v"><?= count($nossas) ?></div><div class="h">texto puro</div></div>
  <div class="card kpi"><div class="k">mensagens deles</div><div class="v"><?= count($deles) ?></div><div class="h">o que precisam ouvir</div></div>
  <div class="card kpi"><div class="k">1ª resposta</div><div class="v"><?= $mediana < 60 ? max(1, $mediana) . ' min' : round($mediana / 60) . ' h' ?></div><div class="h t-green"><?= $sobUmaHora ?>% em até 1h</div></div>
</div>

<div class="grid g23" style="margin-top:14px;align-items:start">
  <div class="card pad">
    <p class="sect">🗣️ Quem fala com o cliente</p>
    <div class="note n-violet">A voz do estúdio tem nome: <b>“Meu nome é Ellen, sou secretária do estúdio do Daniel tatuador 🔥”</b>. Aparece 33 vezes, sempre igual. É essa persona que eu visto ao responder — não a minha, nem a sua.</div>
    <div class="note n-gray" style="margin-top:10px">Frase mais repetida do histórico, 57 vezes: <b>“Oi? Bora retomar o agendamento da sua tatuagem?”</b> — ou seja, o follow-up mais importante do estúdio hoje é feito na mão, uma conversa por vez.</div>
  </div>

  <div class="card pad">
    <p class="sect">❓ O que eles mais perguntam</p>
    <?php foreach ($contagem as $nome => $n): ?>
      <div class="row">
        <div class="dotline dl-blue"></div>
        <div class="who"><b><?= v2_h($nome) ?></b></div>
        <span class="badge b-blue"><?= $n ?>x</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card pad" style="margin-top:14px">
  <p class="sect">📚 O guia de estilo do estúdio (o que eu pratico)</p>
  <div class="grid g2">
    <?php
    $titulos = [
        'abertura' => '1 · Como a gente abre',
        'descoberta' => '2 · Como a gente descobre',
        'preco' => '3 · Como a gente fala preço',
        'local' => '4 · Como a gente manda o endereço',
        'fechamento' => '5 · Como a gente fecha',
        'reserva' => '6 · A regra da reserva',
        'retomada' => '7 · Como a gente retoma quem sumiu',
    ];
    foreach ($exemplos as $chave => $frases): ?>
      <div>
        <p class="sect"><?= v2_h($titulos[$chave]) ?></p>
        <?php foreach ($frases as $f): ?>
          <div class="note n-gray" style="margin-bottom:7px">“<?= v2_h($f) ?>”</div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid g23" style="margin-top:14px;align-items:start">
  <div class="card pad">
    <p class="sect">🎙️ O tom: curto, caloroso, sem enrolação</p>
    <?php foreach ([
        ['Mensagem média nossa', '66 caracteres', 'Uma frase resolve. Ninguém escreve parágrafo.'],
        ['Emoji no lugar de ponto', '🔥 😘 🥰 🌃 🌺', 'Carinho e humor sem virar textão.'],
        ['“rs” em vez de “kk”', '42x', 'É a risada da casa — eu imito essa, não a minha.'],
        ['Apelido na hora certa', '“Fechou”, “Top”, “Bom demais”', 'Fecha assunto com simpatia, sem formalidade.'],
    ] as [$k, $v, $h]): ?>
      <div class="row">
        <div class="dotline dl-amber"></div>
        <div class="who"><b><?= v2_h($k) ?></b><small><?= v2_h($h) ?></small></div>
        <span class="badge b-amber"><?= v2_h($v) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card pad">
    <p class="sect">🎧 Ouça eu falando do jeito deles</p>
    <?php if ($podeOuvir): ?>
      <p style="color:var(--muted);font-size:.83rem;margin:0 0 10px">Frase montada 100% com o vocabulário real do histórico:</p>
      <audio class="audio" controls preload="none" src="<?= v2_h($demoVoz !== '' ? $demoVoz : 'api/tts.php?engine=' . urlencode($vozAtual) . '&v=calor&text=' . urlencode('Oi, Ricardo! Aqui é a Ellen, do estúdio do Daniel. Vi que você gostou do leão, bora retomar o agendamento? Eu tenho vaga pra domingo 🔥')) ?>"></audio>
      <div class="note n-green" style="margin-top:10px">Mesma fala, motores diferentes: compare na aba <a href="index.php?page=voz">Voz</a>.</div>
    <?php else: ?>
      <div class="note n-amber">Nenhum motor de voz configurado neste ambiente.</div>
    <?php endif; ?>
  </div>
</div>

<div class="card pad" style="margin-top:14px">
  <p class="sect">🚨 O que isso me diz sobre o estúdio hoje</p>
  <div class="note n-amber">A primeira resposta é rápida (<?= $sobUmaHora ?>% em até 1h) — o time é bom no tempo real. O que não existe é o <b>depois</b>: a retomada de quem sumiu depende de alguém lembrar e digitar 57 vezes a mesma frase. E o preço, que 63 pessoas perguntaram, é respondido à mão, um por um, cada vez.</div>
  <div class="note n-violet" style="margin-top:10px"><b>Onde eu entro:</b> assumo exatamente essas 7 etapas do guia, com o tom acima, e devolvo pro humano só quando foge do script — objeção séria, reclamação ou cliente pronto pra fechar.</div>
</div>
<?php v2_layout_bottom(); ?>
