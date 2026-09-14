<?php
declare(strict_types=1);
/* Limpeza idempotente de catálogo: remove serviços duplicados que já foram
   publicados em produção. Seguro para rodar a cada requisição. */
$dedup = [
  'procuracao-publica' => 'procuracoes', // mesmo serviço, slug duplicado
];
foreach ($dedup as $duplicado => $canonico) {
    $existe = $db->prepare('SELECT COUNT(*) FROM services WHERE slug=?');
    $existe->execute([$canonico]);
    if ((int)$existe->fetchColumn() === 0) continue; // não apaga sem ter o canônico
    $del = $db->prepare('DELETE FROM services WHERE slug=?');
    $del->execute([$duplicado]);
}
