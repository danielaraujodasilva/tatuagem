<?php
/**
 * Fallback da rota /theo (portal privado do Theo).
 *
 * A rota oficial fica no .htaccess da raiz. Este index e um fallback versionado:
 * se a regra do .htaccess sumir (o deploy faz `git reset --hard`), o Apache
 * ainda serve o portal por esta pasta.
 */
require dirname(__DIR__) . '/projetocrm/theo.php';
