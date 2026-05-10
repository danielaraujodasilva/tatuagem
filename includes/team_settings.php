<?php

require_once __DIR__ . '/system_settings.php';

if (!function_exists('team_default_tattoo_artists')) {
    function team_default_tattoo_artists(): array
    {
        return [[
            'id' => 'daniel',
            'nome' => 'Daniel Araujo',
            'cor' => '#ef4444',
            'ativo' => true,
        ]];
    }
}

if (!function_exists('team_default_attendants')) {
    function team_default_attendants(): array
    {
        return [[
            'id' => 'user-1',
            'usuario_id' => 1,
            'nome' => 'Daniel Araujo',
            'email' => 'danielaraujodasilva@gmail.com',
            'ativo' => true,
        ]];
    }
}

if (!function_exists('team_bool')) {
    function team_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'sim', 'yes', 'on', 'ativo'], true);
    }
}

if (!function_exists('team_person_id')) {
    function team_person_id(string $prefix, string $name, string $extra = ''): string
    {
        $base = strtolower(trim($name . ' ' . $extra));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?: '';
        $base = trim($base, '-');

        if ($base === '') {
            $base = substr(sha1($name . $extra . microtime(true)), 0, 8);
        }

        return $prefix . '-' . substr($base, 0, 42);
    }
}

if (!function_exists('team_color')) {
    function team_color($value, int $index = 0): string
    {
        $value = trim((string)$value);
        if (preg_match('/^#[0-9a-f]{6}$/i', $value)) {
            return strtolower($value);
        }

        $palette = ['#ef4444', '#22c55e', '#38bdf8', '#f59e0b', '#a855f7', '#14b8a6'];
        return $palette[$index % count($palette)];
    }
}

if (!function_exists('team_normalize_people')) {
    function team_normalize_people(array $people, string $kind): array
    {
        $normalized = [];

        foreach (array_values($people) as $index => $person) {
            if (!is_array($person)) {
                continue;
            }

            $name = trim((string)($person['nome'] ?? $person['name'] ?? $person['label'] ?? ''));
            if ($name === '') {
                continue;
            }

            $email = trim((string)($person['email'] ?? ''));
            $userId = (int)($person['usuario_id'] ?? $person['user_id'] ?? 0);
            $id = trim((string)($person['id'] ?? ''));
            if ($id === '') {
                $id = $kind === 'attendant' && $userId > 0
                    ? 'user-' . $userId
                    : team_person_id($kind === 'tattoo_artist' ? 'tattoo' : 'attendant', $name, $email);
            }

            $item = [
                'id' => preg_replace('/[^a-zA-Z0-9_-]/', '-', $id),
                'nome' => $name,
                'ativo' => array_key_exists('ativo', $person) ? team_bool($person['ativo']) : true,
            ];

            if ($kind === 'tattoo_artist') {
                $item['cor'] = team_color($person['cor'] ?? '', $index);
            } else {
                $item['usuario_id'] = $userId;
                $item['email'] = $email;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }
}

if (!function_exists('team_payload_people')) {
    function team_payload_people($payload, string $kind): array
    {
        $decoded = is_string($payload) ? json_decode($payload, true) : $payload;
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return team_normalize_people($decoded, $kind);
    }
}

if (!function_exists('team_tattoo_artists')) {
    function team_tattoo_artists(): array
    {
        $settings = system_settings_load();
        $artists = team_normalize_people($settings['tattoo_artists'] ?? [], 'tattoo_artist');

        return $artists ?: team_default_tattoo_artists();
    }
}

if (!function_exists('team_active_tattoo_artists')) {
    function team_active_tattoo_artists(): array
    {
        $artists = array_values(array_filter(team_tattoo_artists(), static fn(array $artist): bool => !empty($artist['ativo'])));

        return $artists ?: team_default_tattoo_artists();
    }
}

if (!function_exists('team_attendants')) {
    function team_attendants(): array
    {
        $settings = system_settings_load();
        $attendants = team_normalize_people($settings['attendants'] ?? [], 'attendant');

        return $attendants ?: team_default_attendants();
    }
}

if (!function_exists('team_active_attendants')) {
    function team_active_attendants(): array
    {
        return array_values(array_filter(team_attendants(), static fn(array $attendant): bool => !empty($attendant['ativo'])));
    }
}

if (!function_exists('team_find_tattoo_artist')) {
    function team_find_tattoo_artist(string $id): ?array
    {
        foreach (team_tattoo_artists() as $artist) {
            if ((string)$artist['id'] === $id) {
                return $artist;
            }
        }

        return null;
    }
}

if (!function_exists('team_default_tattoo_artist')) {
    function team_default_tattoo_artist(): array
    {
        return team_active_tattoo_artists()[0] ?? team_default_tattoo_artists()[0];
    }
}

if (!function_exists('team_resolve_tattoo_artist')) {
    function team_resolve_tattoo_artist(string $id = '', string $fallbackName = ''): array
    {
        $id = trim($id);
        if ($id !== '') {
            $found = team_find_tattoo_artist($id);
            if ($found) {
                return $found;
            }
        }

        if (trim($fallbackName) !== '') {
            return [
                'id' => $id !== '' ? $id : team_person_id('tattoo', $fallbackName),
                'nome' => trim($fallbackName),
                'cor' => team_color(''),
                'ativo' => false,
            ];
        }

        return team_default_tattoo_artist();
    }
}

if (!function_exists('team_current_attendant')) {
    function team_current_attendant(?array $user = null): array
    {
        $user = $user ?: (function_exists('current_user') ? (current_user() ?: []) : []);
        $userId = (int)($user['id'] ?? 0);
        $email = strtolower(trim((string)($user['email'] ?? '')));

        if ($userId > 0) {
            foreach (team_active_attendants() as $attendant) {
                if ((int)($attendant['usuario_id'] ?? 0) === $userId || (string)($attendant['id'] ?? '') === 'user-' . $userId) {
                    return $attendant;
                }
            }
        }

        if ($email !== '') {
            foreach (team_active_attendants() as $attendant) {
                if (strtolower(trim((string)($attendant['email'] ?? ''))) === $email) {
                    return $attendant;
                }
            }
        }

        $name = trim((string)($user['nome'] ?? $user['username'] ?? 'Atendente'));

        return [
            'id' => $userId > 0 ? 'user-' . $userId : team_person_id('attendant', $name, $email),
            'usuario_id' => $userId,
            'nome' => $name !== '' ? $name : 'Atendente',
            'email' => $email,
            'ativo' => true,
        ];
    }
}

if (!function_exists('team_conversation_is_human')) {
    function team_conversation_is_human(array $cliente): bool
    {
        $mode = strtolower(trim((string)($cliente['modo_atendimento'] ?? '')));
        $attendantName = strtolower(trim((string)($cliente['atendente'] ?? '')));

        return $mode === 'humano' || ($mode === '' && $attendantName !== '' && $attendantName !== 'bot');
    }
}

if (!function_exists('team_conversation_owner')) {
    function team_conversation_owner(array $cliente): array
    {
        if (!team_conversation_is_human($cliente)) {
            return ['id' => '', 'nome' => ''];
        }

        return [
            'id' => trim((string)($cliente['atendente_id'] ?? '')),
            'nome' => trim((string)($cliente['atendente'] ?? '')),
        ];
    }
}

if (!function_exists('team_conversation_owned_by')) {
    function team_conversation_owned_by(array $cliente, array $attendant): bool
    {
        $owner = team_conversation_owner($cliente);
        if ($owner['id'] === '' && $owner['nome'] === '') {
            return false;
        }

        $attendantId = trim((string)($attendant['id'] ?? ''));
        $attendantName = strtolower(trim((string)($attendant['nome'] ?? '')));

        if ($owner['id'] !== '' && $attendantId !== '' && hash_equals($owner['id'], $attendantId)) {
            return true;
        }

        return $owner['id'] === '' && $owner['nome'] !== '' && strtolower($owner['nome']) === $attendantName;
    }
}

if (!function_exists('team_conversation_assigned_to_other')) {
    function team_conversation_assigned_to_other(array $cliente, array $attendant): bool
    {
        $owner = team_conversation_owner($cliente);
        if ($owner['id'] === '' && $owner['nome'] === '') {
            return false;
        }

        return !team_conversation_owned_by($cliente, $attendant);
    }
}

if (!function_exists('team_mysqli_column_exists')) {
    function team_mysqli_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->bind_param('s', $column);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('team_mysqli_index_exists')) {
    function team_mysqli_index_exists(mysqli $conn, string $table, string $index): bool
    {
        $stmt = $conn->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
        $stmt->bind_param('s', $index);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('team_ensure_tatuagens_team_schema')) {
    function team_ensure_tatuagens_team_schema(mysqli $conn): void
    {
        try {
            if (!team_mysqli_column_exists($conn, 'tatuagens', 'tatuador_id')) {
                $conn->query("ALTER TABLE tatuagens ADD COLUMN tatuador_id VARCHAR(80) NULL AFTER cliente_id");
            }

            if (!team_mysqli_column_exists($conn, 'tatuagens', 'tatuador_nome')) {
                $conn->query("ALTER TABLE tatuagens ADD COLUMN tatuador_nome VARCHAR(150) NULL AFTER tatuador_id");
            }

            if (!team_mysqli_index_exists($conn, 'tatuagens', 'idx_tatuagens_tatuador_data')) {
                $conn->query("CREATE INDEX idx_tatuagens_tatuador_data ON tatuagens (tatuador_id, data_tatuagem, hora_inicio)");
            }
        } catch (Throwable $e) {
            error_log('Team tattoo schema check failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('team_validate_tattoo_schedule')) {
    function team_validate_tattoo_schedule(mysqli $conn, ?int $ignoreId, string $artistId, string $date, string $start, string $end): ?string
    {
        if ($artistId === '' || $date === '' || $start === '' || $end === '') {
            return null;
        }

        $defaultArtist = team_default_tattoo_artist();
        $defaultArtistId = (string)($defaultArtist['id'] ?? '');
        $sql = "
            SELECT id
            FROM tatuagens
            WHERE data_tatuagem = ?
              AND status <> 'cancelado'
              AND COALESCE(NULLIF(tatuador_id, ''), ?) = ?
              AND (? <= 0 OR id <> ?)
              AND hora_inicio < ?
              AND COALESCE(hora_fim, ADDTIME(hora_inicio, '01:00:00')) > ?
            LIMIT 1
        ";

        $ignore = $ignoreId ?: 0;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssiiss', $date, $defaultArtistId, $artistId, $ignore, $ignore, $end, $start);
        $stmt->execute();
        $conflict = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($conflict) {
            $artist = team_resolve_tattoo_artist($artistId);
            return 'Este tatuador ja tem um agendamento neste horario: ' . ($artist['nome'] ?? 'tatuador') . '.';
        }

        return null;
    }
}
