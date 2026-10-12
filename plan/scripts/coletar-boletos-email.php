<?php
declare(strict_types=1);

// Coletor de boletos por e-mail para as contas fixas do Plan Financeiro.
//
// Le a caixa do Gmail (integracao do ProjetoCRM) e atualiza valor e vencimento
// das contas fixas correspondentes. O reconhecimento do pagamento continua
// sendo feito pelos matchers do banco (Pluggy/Santander) que ja existem.
//
// Uso:
//   php coletar-boletos-email.php             -> aplica as atualizacoes
//   php coletar-boletos-email.php --dry-run   -> so mostra o que faria

$bolDryRun = in_array('--dry-run', array_slice($argv ?? [], 1), true);

// Integracao do Gmail (token, refresh e chamadas a API).
require 'C:/xampp/htdocs/site/projetocrm/app/bootstrap.php';

// Fontes conhecidas: cada conta fixa sabe de onde o boleto vem.
const BOL_ESTUDIO = ['id' => 1, 'database_name' => 'projetocrm_cereja'];

// Aparelho/contrato -> descricao da conta fixa no Plan.
const BOL_MAPA_ENEL = [
    '0204225111' => 'Enel',
    '0204225099' => 'Enel',
];
const BOL_MAPA_LIDER = [
    '3335' => 'Internet Estudio',
    '4375' => 'Internet ap',
];
const BOL_MAPA_COMGAS = [
    '68777582' => 'Comgas',
];

function bol_plan_config(): array
{
    $config = [];
    require dirname(__DIR__) . '/config.php';
    return is_array($config) ? $config : [];
}

function bol_plan_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = bol_plan_config();
    $dsn = 'mysql:host=' . ($config['host'] ?? 'localhost')
        . (!empty($config['port']) ? ';port=' . $config['port'] : '')
        . ';dbname=' . ($config['database'] ?? 'plan_financeiro')
        . ';charset=utf8mb4';
    $pdo = new PDO($dsn, (string)($config['username'] ?? 'root'), (string)($config['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function bol_numero(string $valor): float
{
    $valor = str_replace(['R$', ' ', "\xc2\xa0", '.'], '', trim($valor));
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float)$valor : 0.0;
}

function bol_data(string $valor): ?string
{
    $parsed = DateTime::createFromFormat('d/m/Y', trim($valor));
    return $parsed instanceof DateTime ? $parsed->format('Y-m-d') : null;
}

function bol_texto_limpo(string $html): string
{
    return trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
}

function bol_get(string $token, string $url): array
{
    $response = google_calendar_http('GET', $url, ['Authorization: Bearer ' . $token]);
    return is_array($response['data']) ? $response['data'] : [];
}

function bol_corpo_e_anexos(string $token, string $id): array
{
    $msg = bol_get($token, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($id) . '?format=full');
    $texto = '';
    $anexos = [];
    $pilha = [$msg['payload'] ?? []];
    while ($pilha) {
        $parte = array_pop($pilha);
        if (!is_array($parte)) {
            continue;
        }
        foreach ($parte['parts'] ?? [] as $filho) {
            $pilha[] = $filho;
        }
        $nome = trim((string)($parte['filename'] ?? ''));
        $anexoId = (string)($parte['body']['attachmentId'] ?? '');
        if ($nome !== '' && $anexoId !== '') {
            $anexos[] = ['nome' => $nome, 'id' => $anexoId];
        }
        $dados = (string)($parte['body']['data'] ?? '');
        if ($dados !== '' && trim((string)($parte['filename'] ?? '')) === '') {
            $decodificado = base64_decode(strtr($dados, '-_', '+/'));
            if ($decodificado !== false) {
                $texto .= ' ' . $decodificado;
            }
        }
    }
    return ['texto' => bol_texto_limpo($texto), 'anexos' => $anexos];
}

function bol_anexo_salvar(string $token, string $mensagemId, array $anexo, string $pasta): ?string
{
    $dados = bol_get($token, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($mensagemId) . '/attachments/' . rawurlencode($anexo['id']));
    $conteudo = base64_decode(strtr((string)($dados['data'] ?? ''), '-_', '+/'));
    if ($conteudo === false || $conteudo === '') {
        return null;
    }
    $arquivo = rtrim($pasta, '/\\') . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/', '_', $anexo['nome']);
    return file_put_contents($arquivo, $conteudo) !== false ? $arquivo : null;
}

function bol_pdf_texto(string $arquivo): string
{
    $python = 'C:/Users/server_spd/Documents/voz-hellen/venv-xtts/Scripts/python.exe';
    if (!is_file($python)) {
        return '';
    }
    $comando = escapeshellarg($python) . ' ' . escapeshellarg(__DIR__ . '/pdf_texto.py') . ' ' . escapeshellarg($arquivo);
    $saida = (string)shell_exec($comando);
    $json = json_decode($saida, true);
    if (!is_array($json)) {
        // O interpretador pode misturar avisos na saida; pega so o objeto JSON.
        if (preg_match('/\{.*"texto".*\}/s', $saida, $achado)) {
            $json = json_decode($achado[0], true);
        }
    }
    return is_array($json) && !empty($json['ok']) ? (string)$json['texto'] : '';
}

function bol_extrair_enel(string $texto): ?array
{
    if (!preg_match('/INSTALA[^:]{0,30}:\s*(\d{6,})/iu', $texto, $uc)) {
        return null;
    }
    if (!preg_match('/R\$\s*([\d.]+,\d{2})/u', $texto, $valor)) {
        return null;
    }
    if (!preg_match('/vencimento\s*(\d{2}\/\d{2}\/\d{4})/iu', $texto, $venc)) {
        return null;
    }
    return [
        'chave' => $uc[1],
        'valor' => bol_numero($valor[1]),
        'vencimento' => bol_data($venc[1]),
        'barras' => bol_extrair_barras($texto),
    ];
}

function bol_extrair_lider(string $texto): ?array
{
    if (!preg_match('/anexo de numero\s*(\d+)/iu', $texto, $numero)) {
        return null;
    }
    if (!preg_match('/no valor de\s*R\$\s*([\d.]+,\d{2})/iu', $texto, $valor)) {
        return null;
    }
    if (!preg_match('/vencimento em\s*(\d{2}\/\d{2}\/\d{4})/iu', $texto, $venc)) {
        return null;
    }
    $barras = '';
    if (preg_match('/barras de numero:\s*([\d.\s]{20,})/iu', $texto, $b)) {
        $barras = trim(preg_replace('/\s+/', ' ', $b[1]));
    }
    return [
        'numero' => $numero[1],
        'valor' => bol_numero($valor[1]),
        'vencimento' => bol_data($venc[1]),
        'barras' => $barras,
    ];
}

function bol_extrair_comgas(string $texto): ?array
{
    if (!preg_match('/c.digo do usu.rio:\s*(\d+)/iu', $texto, $usuario)) {
        return null;
    }
    if (!preg_match('/valor de\s*R\$\s*([\d.]+,\d{2})/iu', $texto, $valor)) {
        return null;
    }
    if (!preg_match('/vencimento para\s*(\d{2})\.(\d{2})\.(\d{4})/iu', $texto, $venc)) {
        return null;
    }
    return [
        'chave' => $usuario[1],
        'valor' => bol_numero($valor[1]),
        'vencimento' => $venc[3] . '-' . $venc[2] . '-' . $venc[1],
        'barras' => bol_extrair_barras($texto),
    ];
}

function bol_extrair_barras(string $texto): string
{
    // Linha digitavel: de 44 a 48 digitos seguidos (Enel, Comgas, Lider).
    if (preg_match('/\b(\d{44,48})\b/', $texto, $achado)) {
        return $achado[1];
    }
    return '';
}

function bol_boleto_limpar(string $codigo): string
{
    $digitos = preg_replace('/\D+/', '', $codigo);
    return is_string($digitos) && strlen($digitos) >= 20 ? $digitos : '';
}

function bol_boleto_salvar(string $token, string $mensagemId, array $anexos, int $regraId): ?string
{
    $pasta = dirname(__DIR__) . '/storage/boletos';
    if (!is_dir($pasta) && !@mkdir($pasta, 0777, true) && !is_dir($pasta)) {
        return null;
    }
    foreach ($anexos as $anexo) {
        if (!str_ends_with(mb_strtolower((string)$anexo['nome']), '.pdf')) {
            continue;
        }
        $dados = bol_get($token, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode($mensagemId) . '/attachments/' . rawurlencode((string)$anexo['id']));
        $conteudo = base64_decode(strtr((string)($dados['data'] ?? ''), '-_', '+/'));
        if ($conteudo === false || $conteudo === '') {
            return null;
        }
        $arquivo = 'conta-' . $regraId . '.pdf';
        return file_put_contents($pasta . '/' . $arquivo, $conteudo) !== false ? $arquivo : null;
    }
    return null;
}

function bol_boleto_registrar(int $regraId, string $codigo, ?string $arquivo, string $fonte): void
{
    $stmt = bol_plan_db()->prepare('UPDATE recurring_rules
        SET boleto_codigo = ?, boleto_arquivo = COALESCE(?, boleto_arquivo), boleto_fonte = ?, boleto_atualizado_em = NOW()
        WHERE id = ?');
    $stmt->execute([bol_boleto_limpar($codigo), $arquivo, $fonte, $regraId]);
}

function bol_regras_por_descricao(): array
{
    $regras = [];
    foreach (bol_plan_db()->query('SELECT id, description, amount, next_due_date FROM recurring_rules WHERE is_active = 1') as $linha) {
        $regras[mb_strtolower(trim((string)$linha['description']))] = $linha;
    }
    return $regras;
}

function bol_aplicar(array $regra, float $valor, ?string $vencimento, bool $dryRun): ?array
{
    $antes = ['amount' => (float)$regra['amount'], 'next_due_date' => (string)($regra['next_due_date'] ?? '')];
    $depois = ['amount' => $valor, 'next_due_date' => $vencimento ?? $antes['next_due_date']];
    if (abs($antes['amount'] - $depois['amount']) < 0.005 && $antes['next_due_date'] === $depois['next_due_date']) {
        return null;
    }
    if (!$dryRun) {
        $stmt = bol_plan_db()->prepare('UPDATE recurring_rules SET amount = ?, next_due_date = ? WHERE id = ?');
        $stmt->execute([$depois['amount'], $depois['next_due_date'], (int)$regra['id']]);
    }
    return ['antes' => $antes, 'depois' => $depois];
}

$bolDryRun = $bolDryRun;
$bolSala = sys_get_temp_dir() . '/plan-boletos';
if (!is_dir($bolSala)) {
    @mkdir($bolSala, 0777, true);
}

$resultado = ['ok' => true, 'dry_run' => $bolDryRun, 'coletados' => [], 'ignorados' => [], 'erros' => []];

try {
    $token = google_gmail_access_token(BOL_ESTUDIO);
    $regras = bol_regras_por_descricao();
    $jaUsadas = [];

    $consultas = [
        'enel' => 'from:faturaporemailsp@brasil.enel.com subject:"Conta por email"',
        'lider' => 'from:nao-responder@liderfibra.com subject:"Lembrete de Fatura"',
        'comgas' => 'from:suafatura@comgas.com.br subject:"Conta Comgas"',
    ];

    foreach ($consultas as $fonte => $consulta) {
        $mensagens = google_gmail_search(BOL_ESTUDIO, $consulta . ' newer_than:45d', 10);
        if (!$mensagens) {
            continue;
        }
        // A mais recente primeiro.
        usort($mensagens, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));

        foreach ($mensagens as $mensagem) {
            $conteudo = bol_corpo_e_anexos($token, (string)$mensagem['id']);
            $item = ['fonte' => $fonte, 'data_email' => substr((string)$mensagem['date'], 0, 10), 'assunto' => $mensagem['subject']];

            if ($fonte !== 'lider') {
                $dados = $fonte === 'comgas' ? bol_extrair_comgas($conteudo['texto']) : bol_extrair_enel($conteudo['texto']);
                if (!$dados) {
                    $resultado['ignorados'][] = $item + ['motivo' => 'nao consegui ler valor/vencimento'];
                    continue;
                }
                $item += $dados;
                if ((float)$dados['valor'] <= 0) {
                    $resultado['ignorados'][] = $item + ['motivo' => 'conta sem valor a pagar'];
                    continue;
                }
                $mapa = $fonte === 'comgas' ? BOL_MAPA_COMGAS : BOL_MAPA_ENEL;
                $descricao = $mapa[$dados['chave']] ?? null;
                if ($descricao === null) {
                    $resultado['ignorados'][] = $item + ['motivo' => 'codigo/instalacao sem conta fixa mapeada'];
                    continue;
                }
            } else {
                $dados = bol_extrair_lider($conteudo['texto']);
                if (!$dados) {
                    $resultado['ignorados'][] = $item + ['motivo' => 'nao consegui ler valor/vencimento'];
                    continue;
                }
                $item += $dados;
                $contrato = '';
                foreach ($conteudo['anexos'] as $anexo) {
                    if (!str_ends_with(mb_strtolower($anexo['nome']), '.pdf')) {
                        continue;
                    }
                    $arquivo = bol_anexo_salvar($token, (string)$mensagem['id'], $anexo, $bolSala);
                    if ($arquivo === null) {
                        continue;
                    }
                    $textoPdf = bol_pdf_texto($arquivo);
                    @unlink($arquivo);
                    if (preg_match('/contrato \(ID\):\s*(\d+)/u', $textoPdf, $c)) {
                        $contrato = $c[1];
                        break;
                    }
                }
                $item['contrato'] = $contrato;
                $descricao = BOL_MAPA_LIDER[$contrato] ?? null;
                if ($descricao === null) {
                    $resultado['ignorados'][] = $item + ['motivo' => 'contrato sem conta fixa mapeada'];
                    continue;
                }
            }

            $chave = mb_strtolower($descricao);
            if (isset($jaUsadas[$chave])) {
                $resultado['ignorados'][] = $item + ['motivo' => 'boleto mais antigo; o mais recente ja foi usado'];
                continue;
            }
            if (!isset($regras[$chave])) {
                $resultado['ignorados'][] = $item + ['motivo' => 'conta fixa "' . $descricao . '" nao encontrada'];
                continue;
            }
            $regra = $regras[$chave];
            $item['conta_fixa'] = $regra['description'];
            // Guarda o boleto (codigo de barras + PDF) para conferir/pagar na tela.
            if ($bolDryRun) {
                $item['boleto_codigo'] = bol_boleto_limpar((string)($item['barras'] ?? ''));
            } else {
                $item['boleto_codigo'] = bol_boleto_limpar((string)($item['barras'] ?? ''));
                $item['boleto_arquivo'] = bol_boleto_salvar($token, (string)$mensagem['id'], $conteudo['anexos'], (int)$regra['id']);
                bol_boleto_registrar((int)$regra['id'], (string)($item['barras'] ?? ''), $item['boleto_arquivo'], $fonte);
            }
            $mudanca = bol_aplicar($regra, (float)$item['valor'], $item['vencimento'] ?? null, $bolDryRun);
            if ($mudanca === null) {
                $item['acao'] = 'ja estava igual';
            } else {
                $item['acao'] = 'atualizada';
                $item['antes'] = $mudanca['antes'];
                $item['depois'] = $mudanca['depois'];
                $regras[$chave]['amount'] = $mudanca['depois']['amount'];
                $regras[$chave]['next_due_date'] = $mudanca['depois']['next_due_date'];
            }
            $jaUsadas[$chave] = true;
            $resultado['coletados'][] = $item;
        }
    }
} catch (Throwable $e) {
    $resultado['ok'] = false;
    $resultado['erros'][] = $e->getMessage();
}

$linha = json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
@file_put_contents(__DIR__ . '/coletor-boletos.log', date('c') . ' ' . $linha, FILE_APPEND);
fwrite($resultado['ok'] ? STDOUT : STDERR, $linha);
exit($resultado['ok'] ? 0 : 1);