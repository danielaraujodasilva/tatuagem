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
            'agenda_dias_disponiveis' => '1,2,3,4,5',
            'agenda_horarios_disponiveis' => '10:00,15:00',
            'agenda_tempo_atendimento_minutos' => 300,
            'tattoo_artists' => [
                [
                    'id' => 'user-1',
                    'usuario_id' => 1,
                    'nome' => 'Daniel Araujo',
                    'email' => 'danielaraujodasilva@gmail.com',
                    'cor' => '#ef4444',
                    'ativo' => true,
                ],
            ],
            'attendants' => [
                [
                    'id' => 'user-1',
                    'usuario_id' => 1,
                    'nome' => 'Daniel Araujo',
                    'email' => 'danielaraujodasilva@gmail.com',
                    'ativo' => true,
                ],
            ],
            'studio_plans' => [
                [
                    'id' => 'essencial',
                    'nome' => 'Essencial',
                    'descricao' => 'Para estúdios pequenos que precisam do CRM, atendimento e agenda organizados.',
                    'valor' => 97.00,
                    'periodicidade' => 'mensal',
                    'recursos' => [
                        'Leads e funil',
                        'WhatsApp integrado',
                        'Agenda e ficha de clientes',
                    ],
                    'ativo' => true,
                ],
                [
                    'id' => 'profissional',
                    'nome' => 'Profissional',
                    'descricao' => 'Para equipes em crescimento que precisam de automações e mais controle do atendimento.',
                    'valor' => 197.00,
                    'periodicidade' => 'mensal',
                    'recursos' => [
                        'Tudo do plano Essencial',
                        'Automações e respostas rápidas',
                        'Múltiplos atendentes',
                        'Relatórios e IA local',
                    ],
                    'ativo' => true,
                ],
                [
                    'id' => 'premium',
                    'nome' => 'Premium',
                    'descricao' => 'Para estúdios com operação maior, vários artistas e necessidade de suporte prioritário.',
                    'valor' => 297.00,
                    'periodicidade' => 'mensal',
                    'recursos' => [
                        'Tudo do plano Profissional',
                        'Multiunidades ou múltiplos perfis',
                        'Configuração personalizada',
                        'Suporte prioritário',
                    ],
                    'ativo' => true,
                ],
            ],
            'openai_business_prompt' => "Você é o assistente de atendimento de um estúdio de tatuagem. Responda em português do Brasil, de forma natural, curta e acolhedora.\n\nObjetivo: ajudar o cliente a avançar sem enrolar.\n- Se a pergunta for sobre agenda, responda com no máximo 2 frases curtas.\n- Se não houver vaga no dia pedido, diga apenas que o dia está lotado e informe a próxima vaga real com data e hora.\n- Se houver vaga, informe só a próxima opção real com data e hora.\n- Não liste nomes de clientes, horários ocupados, bastidores, raciocínio interno ou explicações longas.\n- Quando faltar informação sobre a tatuagem, peça referencia, tamanho aproximado em cm e local do corpo.\n- Não invente preço fechado, política ou data disponível que não esteja no contexto.\n\nExemplos de resposta:\n- 'Tem vaga sim. O próximo horário livre real é 23/05 às 10:00.'\n- 'Não tem vaga nesse dia. A próxima vaga real é 26/05 às 15:00.'\n\nQuando fizer sentido, conduza o cliente para orçamento e agendamento com um atendente.",
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
