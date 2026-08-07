param(
    [Parameter(Mandatory = $true)]
    [string]$BlockId,

    [string]$ProjectRoot = "C:\witcher-dub-br",
    [string]$WebRoot = $PSScriptRoot,
    [int]$Limit = 100,
    [string]$Python = "python"
)

$ErrorActionPreference = "Stop"

$RuntimeDir = Join-Path $WebRoot "runtime"
$JobFile = Join-Path $RuntimeDir "job.json"
$ControlFile = Join-Path $RuntimeDir "control.json"
$ProgressFile = Join-Path $WebRoot "progress.json"
$LogDir = Join-Path $ProjectRoot "logs\web-dashboard"
$LogFile = Join-Path $LogDir ("dashboard-" + $BlockId + "-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".log")

New-Item -ItemType Directory -Force -Path $RuntimeDir, $LogDir | Out-Null

function ConvertTo-PlainHashtable {
    param([object]$Value)
    if ($null -eq $Value) {
        return $null
    }
    if ($Value -is [System.Collections.IDictionary]) {
        $hash = @{}
        foreach ($key in $Value.Keys) {
            $hash[$key] = ConvertTo-PlainHashtable $Value[$key]
        }
        return $hash
    }
    if ($Value.GetType().Name -eq "PSCustomObject") {
        $hash = @{}
        foreach ($property in $Value.PSObject.Properties) {
            $hash[$property.Name] = ConvertTo-PlainHashtable $property.Value
        }
        return $hash
    }
    if ($Value -is [System.Collections.IEnumerable] -and $Value -isnot [string]) {
        $items = @()
        foreach ($item in $Value) {
            $items += ,(ConvertTo-PlainHashtable $item)
        }
        return $items
    }
    return $Value
}

function Read-JsonHashtable {
    param([string]$Path, [hashtable]$Default)
    if (-not (Test-Path -LiteralPath $Path)) {
        return $Default
    }
    $raw = Get-Content -LiteralPath $Path -Raw -Encoding UTF8
    if ([string]::IsNullOrWhiteSpace($raw)) {
        return $Default
    }
    return ConvertTo-PlainHashtable ($raw | ConvertFrom-Json)
}

function Write-JsonFile {
    param([string]$Path, [object]$Data)
    $json = $Data | ConvertTo-Json -Depth 12
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $json, $utf8NoBom)
}

function New-DefaultProgress {
    return @{
        version = 2
        mode = "idle"
        active_block = $null
        started_at = $null
        updated_at = $null
        operator_note = ""
        blocks = @{}
        events = @()
    }
}

function Ensure-ProgressBlock {
    param([hashtable]$Progress, [string]$Id)
    if (-not $Progress.ContainsKey("blocks") -or $null -eq $Progress.blocks -or $Progress.blocks -isnot [System.Collections.IDictionary]) {
        $Progress.blocks = @{}
    }
    if (-not $Progress.blocks.ContainsKey($Id)) {
        $Progress.blocks[$Id] = @{
            status = "pending"
            completed_lines = 0
            failed_lines = 0
            progress_percent = 0
            stage_index = 0
            stage_count = 0
            stage_label = ""
            current_action = ""
            error = ""
            note = ""
            started_at = $null
            updated_at = $null
        }
    }
    return $Progress.blocks[$Id]
}

function Add-ProgressEvent {
    param([hashtable]$Progress, [string]$Type, [string]$Message)
    if (-not $Progress.ContainsKey("events") -or $null -eq $Progress.events -or $Progress.events -is [string]) {
        $Progress.events = @()
    } elseif ($Progress.events -is [System.Collections.IDictionary]) {
        $Progress.events = @($Progress.events)
    }
    $Progress.events += @{
        at = (Get-Date).ToUniversalTime().ToString("o")
        block = $BlockId
        type = $Type
        message = $Message
    }
    if ($Progress.events.Count -gt 80) {
        $Progress.events = @($Progress.events | Select-Object -Last 80)
    }
}

function Update-Progress {
    param(
        [string]$Status,
        [string]$Message,
        [int]$Completed = -1,
        [int]$Failed = -1,
        [int]$ProgressPercent = -1,
        [int]$StageIndex = -1,
        [int]$StageCount = -1,
        [string]$StageLabel = "",
        [string]$ErrorText = ""
    )
    $progress = Read-JsonHashtable -Path $ProgressFile -Default (New-DefaultProgress)
    $block = Ensure-ProgressBlock -Progress $progress -Id $BlockId
    $block.status = $Status
    $block.current_action = $Message
    $block.updated_at = (Get-Date).ToUniversalTime().ToString("o")
    if ($null -eq $block.started_at -and $Status -eq "running") {
        $block.started_at = (Get-Date).ToUniversalTime().ToString("o")
    }
    if ($Completed -ge 0) {
        $block.completed_lines = $Completed
    }
    if ($Failed -ge 0) {
        $block.failed_lines = $Failed
    }
    if ($ProgressPercent -ge 0) {
        $block.progress_percent = [Math]::Min(100, [Math]::Max(0, $ProgressPercent))
    }
    if ($StageIndex -ge 0) {
        $block.stage_index = $StageIndex
    }
    if ($StageCount -ge 0) {
        $block.stage_count = $StageCount
    }
    if ($StageLabel -ne "") {
        $block.stage_label = $StageLabel
    }
    if ($ErrorText -ne "") {
        $block.error = $ErrorText
    }
    $progress.mode = if ($Status -eq "running") { "running" } elseif ($Status -eq "error") { "needs_attention" } else { $Status }
    $progress.active_block = $BlockId
    if ($null -eq $progress.started_at) {
        $progress.started_at = (Get-Date).ToUniversalTime().ToString("o")
    }
    $progress.updated_at = (Get-Date).ToUniversalTime().ToString("o")
    Add-ProgressEvent -Progress $progress -Type $Status -Message $Message
    Write-JsonFile -Path $ProgressFile -Data $progress
}

function Update-Job {
    param([string]$Status, [string]$Stage, [string]$Message)
    Write-JsonFile -Path $JobFile -Data @{
        pid = $PID
        block = $BlockId
        status = $Status
        stage = $Stage
        started_at = $script:StartedAt
        updated_at = (Get-Date).ToUniversalTime().ToString("o")
        log = $LogFile
        message = $Message
    }
}

function Test-PauseRequested {
    if (-not (Test-Path -LiteralPath $ControlFile)) {
        return $false
    }
    $control = Read-JsonHashtable -Path $ControlFile -Default @{}
    return (($control.pause_requested -eq $true) -and (($control.block -eq $BlockId) -or [string]::IsNullOrWhiteSpace([string]$control.block)))
}

function Invoke-Stage {
    param([string]$Name, [string[]]$Command, [int]$StageIndex, [int]$StageCount)
    $message = "Executando etapa: $Name"
    Update-Job -Status "running" -Stage $Name -Message $message
    $progressPercent = if ($StageCount -gt 0) { [Math]::Floor(($StageIndex / $StageCount) * 100) } else { 0 }
    Update-Progress -Status "running" -Message $message -ProgressPercent $progressPercent -StageIndex $StageIndex -StageCount $StageCount -StageLabel $Name
    Add-Content -LiteralPath $LogFile -Encoding UTF8 -Value ("`n[" + (Get-Date -Format "o") + "] " + ($Command -join " "))
    $stageOutput = & $Command[0] @($Command[1..($Command.Count - 1)]) 2>&1
    $exitCode = if ($null -ne $LASTEXITCODE) { $LASTEXITCODE } else { 0 }
    foreach ($line in @($stageOutput)) {
        $text = [string]$line
        Add-Content -LiteralPath $LogFile -Encoding UTF8 -Value $text
        Write-Output $text
    }
    if ($exitCode -ne 0) {
        throw "Etapa $Name falhou com codigo $exitCode"
    }
    if (Test-PauseRequested) {
        Update-Job -Status "paused" -Stage $Name -Message "Pausado apos concluir $Name."
        Update-Progress -Status "paused" -Message "Pausado apos concluir $Name." -ProgressPercent ([Math]::Floor(($StageIndex / $StageCount) * 100)) -StageIndex $StageIndex -StageCount $StageCount -StageLabel $Name
        exit 0
    }
}

function Get-FileSha256 {
    param([string]$Path)
    return (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash.ToLowerInvariant()
}

function Assert-PathInside {
    param([string]$Root, [string]$Path)
    $rootFull = [System.IO.Path]::GetFullPath($Root.TrimEnd("\") + "\")
    $pathFull = [System.IO.Path]::GetFullPath($Path)
    if (-not $pathFull.StartsWith($rootFull, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Caminho fora do pacote recusado: $Path"
    }
}

function New-KnownOverridePackage {
    param([string]$PackageName, [string[]]$LineIds)
    $packageRoot = Join-Path $ProjectRoot "packages\$PackageName"
    $overrideDir = Join-Path $packageRoot "Data\Override"
    New-Item -ItemType Directory -Force -Path $overrideDir | Out-Null
    $files = @()
    foreach ($lineId in $LineIds) {
        $source = Join-Path $ProjectRoot "converted\$lineId.ogg"
        if (-not (Test-Path -LiteralPath $source)) {
            throw "Audio convertido ausente para empacotar: $source"
        }
        $relativePath = "Data\Override\$lineId.ogg"
        $destination = Join-Path $packageRoot $relativePath
        Assert-PathInside -Root $packageRoot -Path $destination
        Copy-Item -LiteralPath $source -Destination $destination -Force
        $item = Get-Item -LiteralPath $destination
        $files += @{
            relative_path = $relativePath
            sha256 = Get-FileSha256 -Path $destination
            size_bytes = $item.Length
            line_id = $lineId
        }
    }
    Write-JsonFile -Path (Join-Path $packageRoot "package-manifest.json") -Data @{
        package = $PackageName
        method = "Data\Override"
        files = $files
    }
}

function Assert-PackageComplete {
    param([string]$PackageName, [int]$ExpectedCount)
    $packageRoot = Join-Path $ProjectRoot "packages\$PackageName"
    $manifestPath = Join-Path $packageRoot "package-manifest.json"
    if (-not (Test-Path -LiteralPath $manifestPath)) {
        throw "Manifest do pacote nao encontrado: $manifestPath"
    }
    $manifest = Read-JsonHashtable -Path $manifestPath -Default @{}
    $files = @($manifest.files)
    if ($files.Count -ne $ExpectedCount) {
        throw "Pacote $PackageName invalido: manifest tem $($files.Count) arquivo(s), esperado $ExpectedCount."
    }
    foreach ($file in $files) {
        $relativePath = if ($file -is [System.Collections.IDictionary]) { [string]$file["relative_path"] } else { [string]$file.relative_path }
        $expectedHash = if ($file -is [System.Collections.IDictionary]) { [string]$file["sha256"] } else { [string]$file.sha256 }
        if ([string]::IsNullOrWhiteSpace($relativePath) -or [string]::IsNullOrWhiteSpace($expectedHash)) {
            throw "Manifest do pacote $PackageName contem entrada incompleta."
        }
        $target = Join-Path $packageRoot $relativePath
        Assert-PathInside -Root $packageRoot -Path $target
        if (-not (Test-Path -LiteralPath $target)) {
            throw "Arquivo do pacote ausente: $target"
        }
        $actualHash = Get-FileSha256 -Path $target
        if ($actualHash -ne $expectedHash.ToLowerInvariant()) {
            throw "Hash divergente no pacote $PackageName para $relativePath."
        }
    }
}

function Assert-ProjectReady {
    if (-not (Test-Path -LiteralPath $ProjectRoot)) {
        throw "Projeto local nao encontrado: $ProjectRoot"
    }
    if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot "scripts"))) {
        throw "Pasta scripts nao encontrada dentro do projeto local."
    }
    $pythonCommand = Get-Command $Python -ErrorAction SilentlyContinue
    if ($null -eq $pythonCommand) {
        throw "Python nao encontrado no PATH do servidor."
    }
}

$script:StartedAt = (Get-Date).ToUniversalTime().ToString("o")

try {
    Assert-ProjectReady
    Update-Job -Status "running" -Stage "starting" -Message "Worker iniciado em background no servidor."
    Update-Progress -Status "running" -Message "Worker iniciado em background no servidor."

    $scriptsDir = Join-Path $ProjectRoot "scripts"
    $stages = @()
    $expectedCompleted = 0
    $packageName = $null
    $prologueLineIds = @()

    switch ($BlockId) {
        "prologue" {
            $expectedCompleted = 5
            $packageName = "dashboard-prologue"
            $prologueLineIds = @(
                "leoo_1252_1319",
                "grlt_1252_811",
                "eskl_1263_2099",
                "leoo_1263_5887",
                "grlt_1263_781"
            )
            $stages = @(
                @{ name = "discover"; command = @($Python, (Join-Path $scriptsDir "discover_lines.py"), "--prologue-kaer-morhen", "--limit", "$Limit") },
                @{ name = "extract"; command = @($Python, (Join-Path $scriptsDir "extract_lines.py"), "--limit", "$Limit") },
                @{ name = "translate"; command = @($Python, (Join-Path $scriptsDir "translate_lines.py"), "--limit", "$Limit") },
                @{ name = "references"; command = @($Python, (Join-Path $scriptsDir "create_references.py"), "--limit", "$Limit") },
                @{ name = "generate"; command = @($Python, (Join-Path $scriptsDir "generate_voice.py"), "--limit", "$Limit") },
                @{ name = "convert"; command = @($Python, (Join-Path $scriptsDir "convert_voice.py"), "--limit", "$Limit") }
            )
        }
        "first_phase" {
            $expectedCompleted = 12
            $packageName = "first-phase-dialogues"
            $stages = @(
                @{ name = "build-first-phase"; command = @($Python, (Join-Path $scriptsDir "build_first_phase_dialogues.py")) }
            )
        }
        default {
            throw "Bloco ainda sem comando seguro: $BlockId"
        }
    }

    $stageCount = $stages.Count + 1
    if ($BlockId -eq "prologue") {
        $stageCount += 1
    }
    $stageIndex = 0

    foreach ($stage in $stages) {
        $stageIndex += 1
        Invoke-Stage -Name $stage.name -Command $stage.command -StageIndex $stageIndex -StageCount $stageCount
    }

    if ($BlockId -eq "prologue") {
        $stageIndex += 1
        Update-Job -Status "running" -Stage "package" -Message "Criando pacote de override validavel para o prologo."
        Update-Progress -Status "running" -Message "Criando pacote de override validavel para o prologo." -ProgressPercent ([Math]::Floor(($stageIndex / $stageCount) * 100)) -StageIndex $stageIndex -StageCount $stageCount -StageLabel "package"
        New-KnownOverridePackage -PackageName $packageName -LineIds $prologueLineIds
    }

    $stageIndex += 1
    Update-Job -Status "running" -Stage "validate-package" -Message "Validando manifest, arquivos e hashes do pacote."
    Update-Progress -Status "running" -Message "Validando manifest, arquivos e hashes do pacote." -ProgressPercent ([Math]::Floor(($stageIndex / $stageCount) * 100)) -StageIndex $stageIndex -StageCount $stageCount -StageLabel "validate-package"
    Assert-PackageComplete -PackageName $packageName -ExpectedCount $expectedCompleted

    $doneMessage = "Bloco finalizado com pacote validado ($expectedCompleted arquivos). Nada foi instalado no jogo."
    Update-Job -Status "done" -Stage "complete" -Message $doneMessage
    Update-Progress -Status "done" -Message $doneMessage -Completed $expectedCompleted -Failed 0 -ProgressPercent 100 -StageIndex $stageCount -StageCount $stageCount -StageLabel "complete"
    exit 0
}
catch {
    $message = $_.Exception.Message
    Add-Content -LiteralPath $LogFile -Encoding UTF8 -Value ("`n[" + (Get-Date -Format "o") + "] ERRO: " + $message)
    Update-Job -Status "error" -Stage "error" -Message $message
    Update-Progress -Status "error" -Message "Erro no worker. Copie o relatorio para o Codex." -ErrorText $message
    exit 1
}
