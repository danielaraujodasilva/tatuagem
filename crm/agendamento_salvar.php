<?php
require __DIR__ . '/../ficha/config/conexao.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

function agendaPost(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function agendaDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function agendaEmailTecnico(string $telefone): string
{
    $digits = agendaDigits($telefone) ?: uniqid();
    return 'sem-email+' . $digits . '@danieltatuador.local';
}

function agendaSalvarReferencia(): string
{
    if (empty($_FILES['referencia']) || !is_uploaded_file($_FILES['referencia']['tmp_name'])) {
        return '';
    }

    $file = $_FILES['referencia'];
    $maxBytes = 8 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('A arte de referencia precisa ter ate 8 MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];

    $mime = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Envie uma imagem JPG, PNG, WEBP, GIF ou PDF.');
    }

    $dir = __DIR__ . '/../ficha/uploads/referencias';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $name = uniqid('ref_', true) . '.' . $allowed[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Nao consegui salvar a arte de referencia.');
    }

    return 'uploads/referencias/' . $name;
}

function agendaFindClientePorTelefone(mysqli $conn, string $telefone): ?array
{
    $digits = agendaDigits($telefone);
    if ($digits === '') {
        return null;
    }

    $like = '%' . $digits . '%';
    $stmt = $conn->prepare('
        SELECT id, nome, telefone
        FROM clientes
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone, " ", ""), "-", ""), "(", ""), ")", "") LIKE ?
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $cliente ?: null;
}

try {
    $clienteId = (int)agendaPost('cliente_id', '0');
    $nome = agendaPost('nome');
    $telefone = agendaPost('telefone');
    $data = agendaPost('data_tatuagem');
    $horaInicio = agendaPost('hora_inicio');
    $horaFim = agendaPost('hora_fim');
    $descricao = agendaPost('descricao', 'Tatuagem');
    $observacoes = agendaPost('observacoes');
    $valor = (float)str_replace(',', '.', agendaPost('valor', '0'));
    $pomadas = max(0, (int)agendaPost('pomadas_anestesicas', '0'));

    if ($data === '' || $horaInicio === '') {
        throw new RuntimeException('Informe data e horario para agendar.');
    }

    if ($horaFim === '') {
        $horaFim = date('H:i', strtotime($horaInicio . ' +1 hour'));
    }

    if ($clienteId <= 0) {
        $clienteExistente = agendaFindClientePorTelefone($conn, $telefone);
        if ($clienteExistente) {
            $clienteId = (int)$clienteExistente['id'];
            if ($nome === '') {
                $nome = (string)$clienteExistente['nome'];
            }
        }
    }

    $clienteCriado = false;
    if ($clienteId <= 0) {
        if ($nome === '' || $telefone === '') {
            throw new RuntimeException('Para cliente novo, preencha nome e telefone.');
        }

        $email = agendaEmailTecnico($telefone);
        $stmt = $conn->prepare('INSERT INTO clientes (nome, email, telefone) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $nome, $email, $telefone);
        $stmt->execute();
        $clienteId = (int)$stmt->insert_id;
        $stmt->close();
        $clienteCriado = true;
    }

    $referencia = agendaSalvarReferencia();
    $descricaoCompleta = $descricao !== '' ? $descricao : 'Tatuagem';

    $stmt = $conn->prepare('
        INSERT INTO tatuagens
            (cliente_id, descricao, valor, data_tatuagem, hora_inicio, hora_fim, status, observacoes, pomadas_anestesicas, referencia_arte)
        VALUES
            (?, ?, ?, ?, ?, ?, "agendado", ?, ?, ?)
    ');
    $stmt->bind_param(
        'isdssssis',
        $clienteId,
        $descricaoCompleta,
        $valor,
        $data,
        $horaInicio,
        $horaFim,
        $observacoes,
        $pomadas,
        $referencia
    );
    $stmt->execute();
    $agendamentoId = (int)$stmt->insert_id;
    $stmt->close();

    if ($agendamentoId <= 0) {
        throw new RuntimeException('O banco nao confirmou o ID do agendamento.');
    }

    $stmt = $conn->prepare('
        SELECT
            t.id,
            t.descricao,
            t.data_tatuagem,
            t.hora_inicio,
            t.hora_fim,
            t.status,
            c.nome AS cliente_nome
        FROM tatuagens t
        LEFT JOIN clientes c ON c.id = t.cliente_id
        WHERE t.id = ?
        LIMIT 1
    ');
    $stmt->bind_param('i', $agendamentoId);
    $stmt->execute();
    $agendaEvento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$agendaEvento) {
        throw new RuntimeException('O agendamento foi salvo, mas nao apareceu na consulta da agenda.');
    }

    $dbAtual = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';

    echo json_encode([
        'ok' => true,
        'agendamento_id' => $agendamentoId,
        'cliente_id' => $clienteId,
        'cliente_criado' => $clienteCriado,
        'ficha_url' => '../ficha/cadastro_publico.php?cliente_id=' . $clienteId,
        'agenda_url' => '../ficha/agenda/?data=' . urlencode($data) . '&agendamento_id=' . $agendamentoId,
        'agenda_evento' => $agendaEvento,
        'database' => $dbAtual,
        'message' => $clienteCriado
            ? 'Cliente basico criado e agendamento salvo.'
            : 'Agendamento salvo para cliente existente.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
