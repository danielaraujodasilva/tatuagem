<?php
// Catálogo inicial de serviços compatíveis com fluxos do e-Notariado.
// O sistema organiza a solicitação; o ato oficial depende do tabelião competente.
$eNotariadoServices = [
  ['certidao-nascimento','Certidão de nascimento','Segunda via e localização do registro.','civil',89,'child_care'],
  ['certidao-casamento','Certidão de casamento','Segunda via e pesquisa do cartório.','civil',99,'favorite'],
  ['assinaturas','Reconhecimento de firma','Conferência e encaminhamento.','notas',49,'draw'],
  ['autenticacao','Autenticação de cópias','Organização e encaminhamento.','notas',49,'verified'],
  ['procuracoes','Procuração pública','Checklist e acompanhamento.','notas',199,'contract_edit'],
  ['imoveis','Matrícula atualizada','Localização e solicitação.','imoveis',149,'home_work'],
  ['pesquisa-matricula','Pesquisa de matrícula','Pesquisa pelos dados disponíveis.','imoveis',149,'travel_explore'],
  ['protestos','Certidão de protesto','Pesquisa e obtenção de certidões.','cobranca',99,'gavel'],
  ['notificacao-cobranca','Notificação de cobrança','Comunicação formal de dívida.','cobranca',199,'mark_email_unread'],
  ['mediacao-geral','Mediação extrajudicial','Organização do diálogo entre as partes.','mediacao',390,'handshake'],
  ['escritura-compra-venda','Escritura pública de compra e venda','Organização de documentos e encaminhamento para escritura eletrônica, quando cabível.','imoveis',0,'home_work'],
  ['escritura-doacao','Escritura pública de doação','Checklist e encaminhamento para doação de bens por escritura pública.','imoveis',0,'volunteer_activism'],
  ['inventario-partilha','Inventário e partilha extrajudicial','Organização documental e encaminhamento para inventário e partilha em cartório.','familia',0,'family_restroom'],
  ['divorcio-extrajudicial','Divórcio extrajudicial','Triagem documental e encaminhamento para divórcio consensual em cartório, quando cabível.','familia',0,'family_restroom'],
  ['uniao-estavel','Escritura de união estável','Organização dos dados e documentos para formalização de união estável.','familia',0,'favorite'],
  ['dissolucao-uniao-estavel','Dissolução de união estável','Checklist e encaminhamento para dissolução consensual, quando cabível.','familia',0,'heart_broken'],
  ['procuracao-publica','Procuração pública','Preparação de informações e documentos para procuração pública.','notas',199,'contract_edit'],
  ['testamento-publico','Testamento público','Orientação operacional e checklist para atendimento notarial de testamento.','familia',0,'description'],
  ['ata-notarial','Ata notarial','Organização do pedido e dos elementos para constatação por ata notarial.','notas',0,'fact_check'],
  ['apostilamento-haia','Apostilamento de Haia','Conferência e encaminhamento de documento para apostilamento.','documentos',0,'verified'],
  ['autenticacao-digital-cenad','Autenticação digital (CENAD)','Encaminhamento para autenticação digital de documento, conforme disponibilidade do serviço.','documentos',0,'verified_user'],
  ['reconhecimento-assinatura-enot','Reconhecimento de assinatura eletrônica (e-Not Assina)','Organização do documento e encaminhamento pelo módulo e-Not Assina.','notas',0,'draw'],
  ['certificado-digital-notarizado','Certificado digital notarizado','Orientação e encaminhamento para emissão ou revogação do certificado digital notarizado.','digital',0,'badge'],
  ['aev','Autorização Eletrônica de Viagem (AEV)','Apoio no preenchimento e encaminhamento da autorização eletrônica de viagem.','familia',0,'flight_takeoff'],
  ['aedo','Autorização Eletrônica de Doação de Órgãos (AEDO)','Apoio no preenchimento e encaminhamento da autorização eletrônica de doação.','familia',0,'volunteer_activism'],
];
$insert = $db->prepare('INSERT OR IGNORE INTO services(slug,title,description,price,active,category,icon,kind,channel,docs_json,steps_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
foreach ($eNotariadoServices as $service) {
  $insert->execute([$service[0],$service[1],$service[2],$service[4],1,$service[3],$service[5],'encaminhamento','e-Notariado','[]','[]']);
}
unset($eNotariadoServices, $insert);
