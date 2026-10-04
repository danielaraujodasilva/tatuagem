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

    // IA local do simulador (?page=simulador)
    // O LM Studio precisa do servidor local ligado (Developer > Start Server).
    'lmstudio_url' => 'http://127.0.0.1:1234/v1',   // API compativel com OpenAI
    'lmstudio_model' => '',                          // vazio = primeiro modelo carregado

    // "Meu cerebro" no simulador: o mesmo modelo que roda o Codex.
    // A chave sai do ambiente (DEEPSEEK_CODEX_KEY) - nao precisa por aqui.
    // 'cerebro_url' => 'https://api.deepseek.com/v1',
    // 'cerebro_model' => 'deepseek-chat',
];
