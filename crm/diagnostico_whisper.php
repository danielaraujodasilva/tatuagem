<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);

function runCheck($label, $cmd) {
    echo "== {$label} ==\n";
    echo "CMD: {$cmd}\n";
    $output = shell_exec($cmd . ' 2>&1');
    echo trim($output ?: '(sem saída)') . "\n\n";
}

echo "PHP: " . PHP_VERSION . "\n";
echo "shell_exec: " . (function_exists('shell_exec') ? 'habilitado' : 'desabilitado') . "\n";
echo "PATH: " . getenv('PATH') . "\n\n";

runCheck('Python launcher', 'py -3 --version');
runCheck('Python', 'python --version');
runCheck('FFmpeg', 'ffmpeg -version');
runCheck('faster-whisper', 'py -3 -c "import faster_whisper; print(\'faster_whisper ok\')"');
runCheck('openai-whisper', 'py -3 -c "import whisper; print(\'openai_whisper ok\')"');
