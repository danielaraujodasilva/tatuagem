<?php

function crmDataDir() {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function crmClientesLegacyPath() {
    return crmDataDir() . '/clientes.json';
}

function crmClientesPath() {
    $runtime = crmDataDir() . '/clientes_runtime.json';
    $legacy = crmClientesLegacyPath();

    if (!file_exists($runtime)) {
        if (is_file($legacy) && filesize($legacy) > 0) {
            copy($legacy, $runtime);
        } else {
            file_put_contents($runtime, "[]");
        }
    }

    return $runtime;
}

function crmCarregarClientes() {
    $runtime = crmClientesPath();
    $clientes = json_decode((string)file_get_contents($runtime), true);

    if (!is_array($clientes)) {
        $clientes = [];
    }

    $legacy = crmClientesLegacyPath();
    if (count($clientes) === 0 && is_file($legacy) && filesize($legacy) > 2) {
        $clientesLegacy = json_decode((string)file_get_contents($legacy), true);
        if (is_array($clientesLegacy) && count($clientesLegacy) > 0) {
            $clientes = $clientesLegacy;
            crmSalvarClientes($clientes);
        }
    }

    return $clientes;
}

function crmSalvarClientes($clientes) {
    $path = crmClientesPath();
    $tmp = $path . '.tmp';
    $json = json_encode(array_values($clientes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (is_file($path) && filesize($path) > 2) {
        copy($path, $path . '.bak');
    }

    file_put_contents($tmp, $json === false ? "[]" : $json);
    copy($tmp, $path);
    unlink($tmp);
}
