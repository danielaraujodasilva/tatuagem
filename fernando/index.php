<?php
/**
 * Fallback da rota /fernando (portal privado do Fernando).
 *
 * Mesmo motivo do /theo/index.php: a regra oficial fica no .htaccess da raiz,
 * mas se ela sumir num `git reset --hard`, o Apache ainda serve o portal por
 * esta pasta.
 */
require dirname(__DIR__) . '/projetocrm/fernando.php';
