<?php
// Copie para config.local.php e ajuste. Nao versionar o arquivo real.
return [
    // banco do CRM atual (conversas / leads)
    'crm_host' => 'localhost',
    'crm_database' => 'crm_simples',
    'crm_username' => 'seu_usuario',
    'crm_password' => 'sua_senha',

    // voz
    'tts_engine' => 'kokoro',              // kokoro | piper | edge | windows
    'kokoro_voice' => 'pf_dora',           // pf_dora | pm_alex | pm_santa
    'piper_bin' => '',                     // caminho do piper.exe (vazio = procura no PATH)
    'piper_voice' => '',                   // caminho do .onnx pt-BR
    'edge_voice' => 'pt-BR-FranciscaNeural',
    'windows_voice' => 'Microsoft Maria',
];
