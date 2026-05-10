<?php

if (!function_exists('system_settings_path')) {
    function system_settings_path(): string
    {
        return dirname(__DIR__) . '/crm/data/config.json';
    }
}

if (!function_exists('system_settings_defaults')) {
    function system_settings_defaults(): array
    {
        return [
            'mensagem_trigger' => 'oi',
            'valor_pomada_anestesica' => 100.0,
            'openai_enabled' => false,
            'openai_api_key' => '',
            'openai_model' => 'gpt-5-mini',
            'ai_provider' => 'ollama',
            'ollama_url' => 'http://localhost:11434',
            'ollama_model' => 'qwen3:14b',
            'ai_timeout_seconds' => 120,
            'ai_num_predict' => 220,
            'data_ai_model' => 'qwen3:14b',
            'data_ai_timeout_seconds' => 240,
            'data_ai_num_predict' => 2400,
            'openai_max_history' => 20,
            'tattoo_artists' => [
                [
                    'id' => 'daniel',
                    'nome' => 'Daniel Araujo',
                    'cor' => '#ef4444',
                    'ativo' => true,
                ],
            ],
            'attendants' => [
                [
                    'id' => 'daniel',
                    'nome' => 'Daniel Araujo',
                    'email' => 'danielaraujodasilva@gmail.com',
                    'ativo' => true,
                ],
            ],
            'openai_business_prompt' => "Você é o assistente de atendimento de um estúdio de tatuagem. Responda em português do Brasil, de forma natural, curta e acolhedora. Ajude a entender a ideia da tatuagem, peça referência, tamanho aproximado em cm e local do corpo quando faltar informação. Não invente preço fechado, data disponível ou política que não esteja no contexto. Quando fizer sentido, conduza o cliente para orçamento e agendamento com um atendente.",
        ];
    }
}

if (!function_exists('system_settings_load')) {
    function system_settings_load(): array
    {
        $path = system_settings_path();
        $defaults = system_settings_defaults();

        if (!is_file($path)) {
            return $defaults;
        }

        $settings = json_decode((string)file_get_contents($path), true);
        if (!is_array($settings)) {
            return $defaults;
        }

        return array_merge($defaults, $settings);
    }
}

if (!function_exists('system_settings_save')) {
    function system_settings_save(array $settings): void
    {
        $path = system_settings_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $merged = array_merge(system_settings_load(), $settings);
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        copy($tmp, $path);
        unlink($tmp);
    }
}

if (!function_exists('system_setting_float')) {
    function system_setting_float(string $key, float $default = 0.0): float
    {
        $settings = system_settings_load();
        $value = $settings[$key] ?? $default;
        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float)$value : $default;
    }
}

if (!function_exists('system_pomada_unit_price')) {
    function system_pomada_unit_price(): float
    {
        return max(0.0, system_setting_float('valor_pomada_anestesica', 100.0));
    }
}

if (!function_exists('system_apply_pomada_total')) {
    function system_apply_pomada_total(float $baseValue, int $pomadas): float
    {
        return max(0.0, $baseValue) + (max(0, $pomadas) * system_pomada_unit_price());
    }
}
