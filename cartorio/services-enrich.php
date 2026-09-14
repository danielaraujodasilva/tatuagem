<?php
declare(strict_types=1);
/* Preenche docs_json/steps_json dos serviços a partir do catálogo curado em data.js.
   Roda uma vez por serviço; não sobrescreve o que o admin já editou. */

$fonte = dirname(__DIR__) . '/data.js';
if (!is_file($fonte)) return;

$js = (string)file_get_contents($fonte);
// extrai as chamadas S('slug','cat','icon','Titulo','Desc',preco,'canal',['docs'],['steps'])
preg_match_all("/S\('([^']+)','([^']*)','([^']*)','([^']*)','([^']*)',(\d+),'([^']*)',\[([^\]]*)\],\[([^\]]*)\]/", $js, $m, PREG_SET_ORDER);

$upd = $db->prepare('UPDATE services SET docs_json=?, steps_json=? WHERE slug=? AND (docs_json IS NULL OR docs_json=? OR docs_json=\'\')');
foreach ($m as $r) {
    $slug = $r[1];
    $docs = array_values(array_filter(array_map(fn($x) => trim(trim($x), "'\""), explode(',', $r[8]))));
    $steps = array_values(array_filter(array_map(fn($x) => trim(trim($x), "'\""), explode(',', $r[9]))));
    if (!$docs && !$steps) continue;
    $upd->execute([json_encode($docs, JSON_UNESCAPED_UNICODE), json_encode($steps, JSON_UNESCAPED_UNICODE), $slug, '[]']);
}
