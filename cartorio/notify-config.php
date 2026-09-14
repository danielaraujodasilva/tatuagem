<?php
declare(strict_types=1);
/* Configuração dos avisos automáticos do cartório.
   Os valores podem vir de variáveis de ambiente do servidor (getenv) ou ser
   preenchidos aqui. Troque apenas o lado direito das setas. */

// Remetente. Em hospedagem compartilhada o e-mail precisa pertencer a um
// domínio hospedado no mesmo servidor; "no-reply@danieltatuador.com" serve.
define('CARTORIO_MAIL_FROM', getenv('CARTORIO_MAIL_FROM') ?: 'no-reply@danieltatuador.com');
define('CARTORIO_MAIL_FROM_NAME', getenv('CARTORIO_MAIL_FROM_NAME') ?: 'CNJP - Cartório Digital');

// Destino interno: quem recebe o aviso de cada pedido novo.
// Deixe vazio para desativar o aviso interno (a auto-resposta segue funcionando).
define('CARTORIO_NOTIFY_EMAIL', getenv('CARTORIO_NOTIFY_EMAIL') ?: '');

// Auto-resposta ao cliente: ligada/desligada.
define('CARTORIO_AUTOREPLY_ENABLED', (getenv('CARTORIO_AUTOREPLY_ENABLED') ?: '1') !== '0');

// Canais que aparecem na assinatura da auto-resposta (só entram os preenchidos).
define('CARTORIO_CONTACT_WHATSAPP', getenv('CARTORIO_CONTACT_WHATSAPP') ?: '');
define('CARTORIO_CONTACT_EMAIL', getenv('CARTORIO_CONTACT_EMAIL') ?: '');
define('CARTORIO_CONTACT_PHONE', getenv('CARTORIO_CONTACT_PHONE') ?: '');
define('CARTORIO_CONTACT_HOURS', getenv('CARTORIO_CONTACT_HOURS') ?: '');
define('CARTORIO_SITE_URL', getenv('CARTORIO_SITE_URL') ?: 'https://danieltatuador.com/cartorio/');
