<?php

if (!function_exists('crm_transcription_log')) {
    function crm_transcription_log(array $dados): void
    {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $dir . '/transcricao_debug.log',
            '[' . date('Y-m-d H:i:s') . '] ' . json_encode($dados, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );
    }
}

if (!function_exists('crm_transcription_command_available')) {
    function crm_transcription_command_available(string $funcao): bool
    {
        if (!function_exists($funcao)) {
            return false;
        }

        $desabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array($funcao, $desabilitadas, true);
    }
}

if (!function_exists('crm_transcription_exec')) {
    function crm_transcription_exec(string $command): array
    {
        if (crm_transcription_command_available('exec')) {
            $output = [];
            $exitCode = null;
            exec($command, $output, $exitCode);
            return [
                'exitCode' => $exitCode,
                'output' => implode(PHP_EOL, $output),
                'runner' => 'exec',
            ];
        }

        if (crm_transcription_command_available('shell_exec')) {
            $output = shell_exec($command);
            return [
                'exitCode' => $output === null ? 1 : 0,
                'output' => (string)$output,
                'runner' => 'shell_exec',
            ];
        }

        return [
            'exitCode' => 1,
            'output' => 'exec e shell_exec estao desabilitados no PHP',
            'runner' => 'none',
        ];
    }
}

if (!function_exists('crm_transcription_exec_timeout')) {
    function crm_transcription_exec_timeout(string $command, int $timeoutSeconds): array
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') === false || !crm_transcription_command_available('proc_open')) {
            return crm_transcription_exec($command);
        }

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return [
                'exitCode' => 1,
                'output' => 'Nao consegui iniciar o processo',
                'runner' => 'proc_open',
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $error = '';
        $startedAt = time();

        while (true) {
            $status = proc_get_status($process);
            $output .= stream_get_contents($pipes[1]);
            $error .= stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }

            if ((time() - $startedAt) >= $timeoutSeconds) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
                return [
                    'exitCode' => 124,
                    'output' => trim($output . PHP_EOL . $error . PHP_EOL . 'Timeout apos ' . $timeoutSeconds . 's'),
                    'runner' => 'proc_open',
                ];
            }

            usleep(200000);
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'output' => trim($output . PHP_EOL . $error),
            'runner' => 'proc_open',
        ];
    }
}

if (!function_exists('crm_transcription_json_from_output')) {
    function crm_transcription_json_from_output(string $output): ?array
    {
        $trimmed = trim($output);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{(?:.|\s)*\}\s*$/', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

if (!function_exists('crm_transcription_preview')) {
    function crm_transcription_preview(string $texto, int $limite): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $limite);
        }

        return substr($texto, 0, $limite);
    }
}

if (!function_exists('crm_transcription_run')) {
    function crm_transcription_run(string $audioPath, string $model = 'small'): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $audioPathReal = realpath($audioPath);
        $mediaRoot = realpath(__DIR__ . '/data/media');

        if (!$audioPathReal || !$mediaRoot || strpos($audioPathReal, $mediaRoot) !== 0 || !is_file($audioPathReal)) {
            crm_transcription_log(['error' => 'Arquivo de audio invalido', 'audioPath' => $audioPath]);
            return ['ok' => false, 'error' => 'Arquivo de audio invalido'];
        }

        $script = __DIR__ . '/scripts/transcribe_audio.py';
        if (!is_file($script)) {
            return ['ok' => false, 'error' => 'Script de transcricao nao encontrado'];
        }

        $audioForWhisper = $audioPathReal;
        $convertedAudio = null;
        $ffmpegOut = tempnam(sys_get_temp_dir(), 'ffmpeg_out_');
        $ffmpegErr = tempnam(sys_get_temp_dir(), 'ffmpeg_err_');
        $convertedCandidate = tempnam(sys_get_temp_dir(), 'whisper_wav_');

        if ($convertedCandidate !== false) {
            if (is_file($convertedCandidate)) {
                unlink($convertedCandidate);
            }
            $convertedCandidate .= '.wav';
            $ffmpegCommand = 'ffmpeg -y -i ' . escapeshellarg($audioPathReal)
                . ' -af ' . escapeshellarg('apad=pad_dur=1')
                . ' -ar 16000 -ac 1 -vn ' . escapeshellarg($convertedCandidate)
                . ' > ' . escapeshellarg($ffmpegOut)
                . ' 2> ' . escapeshellarg($ffmpegErr);

            crm_transcription_log(['ffmpegCommand' => $ffmpegCommand]);
            $ffmpegRun = crm_transcription_exec($ffmpegCommand);
            $ffmpegExitCode = $ffmpegRun['exitCode'];
            $ffmpegStdout = is_file($ffmpegOut) ? file_get_contents($ffmpegOut) : '';
            $ffmpegStderr = is_file($ffmpegErr) ? file_get_contents($ffmpegErr) : '';
            crm_transcription_log([
                'ffmpegExitCode' => $ffmpegExitCode,
                'ffmpegStdout' => crm_transcription_preview($ffmpegStdout, 1200),
                'ffmpegStderr' => crm_transcription_preview($ffmpegStderr, 2000),
                'runner' => $ffmpegRun['runner'] ?? '',
                'runnerOutput' => crm_transcription_preview($ffmpegRun['output'] ?? '', 1200),
                'convertedAudio' => is_file($convertedCandidate) ? $convertedCandidate : '',
                'convertedSize' => is_file($convertedCandidate) ? filesize($convertedCandidate) : 0,
            ]);

            if ($ffmpegExitCode === 0 && is_file($convertedCandidate) && filesize($convertedCandidate) > 0) {
                $audioForWhisper = $convertedCandidate;
                $convertedAudio = $convertedCandidate;
            } elseif (is_file($convertedCandidate)) {
                unlink($convertedCandidate);
            }
        }

        if (is_file($ffmpegOut)) {
            unlink($ffmpegOut);
        }
        if (is_file($ffmpegErr)) {
            unlink($ffmpegErr);
        }

        $commands = ['py -3', 'python', 'python3'];
        $engines = ['openai', 'faster'];
        $result = null;
        $lastOutput = '';
        $lastError = '';
        $lastExitCode = null;
        $lastRunner = '';

        foreach ($engines as $engine) {
            foreach ($commands as $cmd) {
                $stdoutFile = tempnam(sys_get_temp_dir(), 'whisper_out_');
                $stderrFile = tempnam(sys_get_temp_dir(), 'whisper_err_');
                $fullCommand = $cmd . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($audioForWhisper) . ' ' . escapeshellarg($model) . ' ' . escapeshellarg($engine)
                    . ' > ' . escapeshellarg($stdoutFile)
                    . ' 2> ' . escapeshellarg($stderrFile);
                crm_transcription_log(['command' => $fullCommand, 'engine' => $engine]);
                $run = crm_transcription_exec_timeout($fullCommand, $engine === 'faster' ? 900 : 180);
                $exitCode = $run['exitCode'];
                $lastExitCode = $exitCode;
                $lastRunner = $run['runner'] ?? '';
                $lastOutput = is_file($stdoutFile) ? file_get_contents($stdoutFile) : '';
                $lastError = is_file($stderrFile) ? file_get_contents($stderrFile) : '';
                if (is_file($stdoutFile)) {
                    unlink($stdoutFile);
                }
                if (is_file($stderrFile)) {
                    unlink($stderrFile);
                }
                crm_transcription_log([
                    'exitCode' => $exitCode,
                    'runner' => $lastRunner,
                    'stdout' => crm_transcription_preview($lastOutput, 4000),
                    'stderr' => crm_transcription_preview($lastError, 4000),
                    'runnerOutput' => crm_transcription_preview($run['output'] ?? '', 4000),
                ]);
                $decoded = crm_transcription_json_from_output($lastOutput);

                if (is_array($decoded)) {
                    $result = $decoded;
                    if (!empty($decoded['ok'])) {
                        break 2;
                    }
                }
            }
        }

        if ($convertedAudio && is_file($convertedAudio)) {
            unlink($convertedAudio);
        }

        if (empty($result['ok'])) {
            $erro = $result['error'] ?? trim($lastError) ?: trim($lastOutput) ?: ('Falha ao transcrever audio. Runner: ' . $lastRunner . '. Exit code: ' . (string)$lastExitCode);
            crm_transcription_log(['error' => $erro]);
            return ['ok' => false, 'error' => $erro];
        }

        $text = trim((string)($result['text'] ?? ''));
        return [
            'ok' => true,
            'text' => $text,
            'engine' => $result['engine'] ?? '',
        ];
    }
}

if (!function_exists('crm_transcription_is_audio')) {
    function crm_transcription_is_audio(string $tipoMensagem, string $mediaMime, ?string $mediaUrl): bool
    {
        return $mediaUrl !== null
            && $mediaUrl !== ''
            && ($tipoMensagem === 'audio' || strpos(strtolower($mediaMime), 'audio/') === 0);
    }
}

if (!function_exists('crm_transcription_media_path')) {
    function crm_transcription_media_path(string $mediaUrl): string
    {
        return __DIR__ . '/' . ltrim($mediaUrl, '/');
    }
}
