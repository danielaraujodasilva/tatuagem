<?php
declare(strict_types=1);
/* Código de pedido à prova de corrida.

   O código deriva do id real do registro: CD-<id + 1090>. Para isso, o
   registro é inserido com um código provisório único (baseado em microtempo),
   e em seguida o código definitivo é gravado. Vantagens:
   - pedidos apagados não reaproveitam número;
   - dois pedidos simultâneos nunca colidem, pois cada id é único;
   - em caso de falha entre insert e update, o código provisório continua
     único e o registro não fica sem identificação. */

function cartorio_criar_pedido(PDO $db, array $campos): array {
    $provisorio = 'TMP-' . bin2hex(random_bytes(6));
    $campos['code'] = $provisorio;

    $colunas = array_keys($campos);
    $place = implode(',', array_fill(0, count($colunas), '?'));
    $st = $db->prepare('INSERT INTO requests(' . implode(',', $colunas) . ") VALUES($place)");
    $st->execute(array_values($campos));

    $id = (int)$db->lastInsertId();
    $code = 'CD-' . ($id + 1090);
    $up = $db->prepare('UPDATE requests SET code=? WHERE id=?');
    $up->execute([$code, $id]);

    return ['code' => $code, 'id' => $id];
}
