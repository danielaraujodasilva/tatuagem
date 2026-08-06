<?php

declare(strict_types=1);

$htmlFile = __DIR__ . DIRECTORY_SEPARATOR . 'index.html';
$html = file_get_contents($htmlFile);

if ($html === false) {
    http_response_code(500);
    exit('Não foi possível carregar o checklist.');
}

$replacements = [
    '<span class="badge">Progresso salvo no navegador</span>'
        => '<span class="badge">Progresso sincronizado em JSON</span>',

    '<footer>Checklist local. As marcações ficam salvas neste navegador via localStorage. Tecnologia de ponta finalmente aplicada à necessidade humana de não perder o fio da meada.</footer>'
        => '<footer>Checklist sincronizado pelo arquivo progress.json. Agora até trocar de aparelho deixou de ser uma aventura paralela.</footer>',

    "    const storageKey = 'witcherDubChecklistV1';\n    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');"
        => <<<'JS'
    const saved = {};
    let saveTimer = null;

    async function loadProgress() {
      try {
        const response = await fetch(`progress.php?t=${Date.now()}`, { cache: 'no-store' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        Object.assign(saved, data.steps || {});
      } catch (error) {
        console.error('Falha ao carregar o progresso sincronizado:', error);
      }
    }

    function saveProgress() {
      clearTimeout(saveTimer);
      saveTimer = setTimeout(async () => {
        try {
          const response = await fetch('progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ steps: saved })
          });
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
        } catch (error) {
          console.error('Falha ao salvar o progresso sincronizado:', error);
          alert('Não consegui salvar o progresso no servidor. Verifique se progress.json tem permissão de escrita.');
        }
      }, 250);
    }
JS,

    "          localStorage.setItem(storageKey, JSON.stringify(saved));"
        => "          saveProgress();",

    "    document.getElementById('resetProgress').addEventListener('click', () => {"
        => "    document.getElementById('resetProgress').addEventListener('click', async () => {",

    "      localStorage.removeItem(storageKey);\n      Object.keys(saved).forEach(key => delete saved[key]);\n      render();"
        => "      Object.keys(saved).forEach(key => delete saved[key]);\n      saveProgress();\n      render();",

    "    render();\n  </script>"
        => "    loadProgress().then(render);\n  </script>",
];

$html = str_replace(array_keys($replacements), array_values($replacements), $html);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $html;
