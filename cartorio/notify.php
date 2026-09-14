<?php
declare(strict_types=1);
/* Avisos automáticos do cartório:
   - e-mail interno para a equipe a cada pedido novo;
   - auto-resposta ao cliente com o código do pedido.
   Nunca lança exceção: falha de e-mail não pode derrubar a criação do pedido. */

function cartorio_mail_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = __DIR__ . '/notify-config.php';
    if (is_file($file)) require_once $file;
    $g = static fn(string $k, string $d = ''): string => (defined($k) ? (string)constant($k) : (string)(getenv($k) ?: $d));
    $cfg = [
        'from'       => $g('CARTORIO_MAIL_FROM', 'no-reply@danieltatuador.com'),
        'from_name'  => $g('CARTORIO_MAIL_FROM_NAME', 'CNJP - Cartório Digital'),
        'notify'     => $g('CARTORIO_NOTIFY_EMAIL'),
        'autoreply'  => $g('CARTORIO_AUTOREPLY_ENABLED', '1') !== '0',
        'whatsapp'   => $g('CARTORIO_CONTACT_WHATSAPP'),
        'email'      => $g('CARTORIO_CONTACT_EMAIL'),
        'phone'      => $g('CARTORIO_CONTACT_PHONE'),
        'hours'      => $g('CARTORIO_CONTACT_HOURS'),
        'site'       => $g('CARTORIO_SITE_URL', 'https://danieltatuador.com/cartorio/'),
    ];
    return $cfg;
}

function cartorio_mail_headers(array $cfg, string $to): string {
    $fromName = str_replace(["\r", "\n"], '', $cfg['from_name']);
    $h  = 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8', 'B') . ' <' . $cfg['from'] . ">\r\n";
    $h .= 'Reply-To: ' . ($cfg['email'] !== '' ? $cfg['email'] : $cfg['from']) . "\r\n";
    $h .= 'To: ' . $to . "\r\n";
    $h .= "MIME-Version: 1.0\r\n";
    $h .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $h .= "Content-Transfer-Encoding: 8bit\r\n";
    $h .= "X-Mailer: CNJP-Cartorio\r\n";
    return $h;
}

function cartorio_send_mail(string $to, string $subject, string $body, array $cfg, bool $isAutoReply = false): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    $headers = cartorio_mail_headers($cfg, $to);
    if ($isAutoReply) {
        $headers = "Auto-Submitted: auto-replied\r\n" . $headers;
        $headers = "Auto-Response-Suppress: All\r\n" . $headers;
    }
    $ok = false;
    try { $ok = @mail($to, $subject, $body, $headers); } catch (Throwable $e) { $ok = false; }
    return (bool)$ok;
}

/* Assinatura de contato: só inclui os campos que existem. */
function cartorio_signature(array $cfg): string {
    $lines = [];
    if ($cfg['whatsapp'] !== '') $lines[] = 'WhatsApp: ' . $cfg['whatsapp'];
    if ($cfg['phone'] !== '' && $cfg['phone'] !== $cfg['whatsapp']) $lines[] = 'Telefone: ' . $cfg['phone'];
    if ($cfg['email'] !== '')    $lines[] = 'E-mail: ' . $cfg['email'];
    if ($cfg['hours'] !== '')    $lines[] = 'Horário de atendimento: ' . $cfg['hours'];
    return $lines ? ("Canais de atendimento:\r\n" . implode("\r\n", $lines) . "\r\n") : "Canais de atendimento informados no site:\r\n" . $cfg['site'] . "\r\n";
}

function cartorio_notify_new_request(PDO $db, array $r): void {
    try {
        $cfg = cartorio_mail_config();
        $code  = (string)($r['code'] ?? '');
        $name  = (string)($r['name'] ?? '');
        $phone = (string)($r['phone'] ?? '');
        $email = trim((string)($r['email'] ?? ''));
        $svc   = (string)($r['service'] ?? '');
        $desc  = trim((string)($r['description'] ?? ''));
        $chan  = (string)($r['channel'] ?? '');
        $created = (string)($r['created_at'] ?? date('Y-m-d H:i:s'));

        if ($cfg['notify'] !== '') {
            $subject = sprintf('Novo pedido %s - %s', $code, $svc);
            $body = "Entrou um pedido novo no site.\r\n\r\n"
                  . "Código: $code\r\n"
                  . "Cliente: $name\r\n"
                  . "Telefone: $phone\r\n"
                  . ($email !== '' ? "E-mail: $email\r\n" : '')
                  . "Serviço: $svc\r\n"
                  . "Canal: $chan\r\n"
                  . "Recebido em: $created\r\n\r\n"
                  . "Relato do cliente:\r\n" . ($desc !== '' ? $desc : '(sem descrição)') . "\r\n\r\n"
                  . "Abra o painel para assumir o atendimento.\r\n";
            $sent = cartorio_send_mail($cfg['notify'], $subject, $body, $cfg);
            cartorio_log_event($db, $code, $sent ? 'Aviso interno enviado' : 'Falha no aviso interno',
                $sent ? 'E-mail enviado para ' . $cfg['notify'] : 'Não foi possível enviar o e-mail para ' . $cfg['notify'], 'sistema');
        }

        if ($cfg['autoreply'] && $email !== '') {
            $subject = 'Recebemos seu pedido ' . $code . ' - CNJP';
            $body = "Olá, $name.\r\n\r\n"
                  . "Recebemos seu pedido e ele já está na fila de atendimento.\r\n\r\n"
                  . "Código do pedido: $code\r\n"
                  . "Serviço: $svc\r\n"
                  . "Recebido em: $created\r\n\r\n"
                  . "Guarde este código: é com ele (e com o telefone informado) que você acompanha o andamento no portal: "
                  . $cfg['site'] . "\r\n\r\n"
                  . "Prazo de resposta: até 1 dia útil. Se o seu caso for urgente, chame nos canais abaixo.\r\n\r\n"
                  . cartorio_signature($cfg);
            $sent = cartorio_send_mail($email, $subject, $body, $cfg, true);
            cartorio_log_event($db, $code, $sent ? 'Auto-resposta enviada' : 'Falha na auto-resposta',
                $sent ? 'Confirmação enviada para ' . $email : 'Não foi possível enviar para ' . $email, 'sistema');
        }
    } catch (Throwable $e) {
        // silencioso de propósito: e-mail nunca bloqueia o pedido
    }
}

function cartorio_log_event(PDO $db, string $code, string $event, string $detail, string $username = 'sistema'): void {
    try {
        $s = $db->prepare('INSERT INTO events(code,event,detail,username) VALUES(?,?,?,?)');
        $s->execute([$code, $event, $detail, $username]);
    } catch (Throwable $e) {
        // ignora
    }
}
