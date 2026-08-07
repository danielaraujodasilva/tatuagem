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
    if ($Value -is [System.Collections.IEnumerable] -and $Value -isnot [string]) {
        $items = @()
        foreach ($item in $Value) {
            $items += ,(ConvertTo-PlainHashtable $item)
        }
        return $items
    }
    if ($Value.PSObject.Properties.Count -gt 0 -and $Value.GetType().Name -eq "PSCustomObject") {
        $hash = @{}
        foreach ($property in $Value.PSObject.Properties) {
            $hash[$property.Name] = ConvertTo-PlainHashtable $property.Value
        }
        return $hash
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
    if (-not $Progress.ContainsKey("blocks") -or $null -eq $Progress.blocks) {
        $Progress.blocks = @{}
    }
    if (-not $Progress.blocks.ContainsKey($Id)) {
        $Progress.blocks[$Id] = @{
            status = "pending"
            completed_lines = 0
            failed_lines = 0
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
    if (-not $Progress.ContainsKey("events") -or $null -eq $Progress.events) {
        $Progress.events = @()
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
    param([string]$Name, [string[]]$Command)
    $message = "Executando etapa: $Name"
    Update-Job -Status "running" -Stage $Name -Message $message
    Update-Progress -Status "running" -Message $message
    Add-Content -LiteralPath $LogFile -Encoding UTF8 -Value ("`n[" + (Get-Date -Format "o") + "] " + ($Command -join " "))
    & $Command[0] @($Command[1..($Command.Count - 1)]) 2>&1 | Tee-Object -FilePath $LogFile -Append
    $exitCode = if ($null -ne $LASTEXITCODE) { $LASTEXITCODE } else { 0 }
    if ($exitCode -ne 0) {
        throw "Etapa $Name falhou com codigo $exitCode"
    }
    if (Test-PauseRequested) {
        Update-Job -Status "paused" -Stage $Name -Message "Pausado apos concluir $Name."
        Update-Progress -Status "paused" -Message "Pausado apos concluir $Name."
        exit 0
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

    switch ($BlockId) {
        "prologue" {
            $expectedCompleted = 5
            $packageName = "dashboard-prologue"
            $stages = @(
                @{ name = "discover"; command = @($Python, (Join-Path $scriptsDir "discover_lines.py"), "--prologue-kaer-morhen", "--limit", "$Limit") },
                @{ name = "extract"; command = @($Python, (Join-Path $scriptsDir "extract_lines.py"), "--limit", "$Limit") },
                @{ name = "translate"; command = @($Python, (Join-Path $scriptsDir "translate_lines.py"), "--limit", "$Limit") },
                @{ name = "references"; command = @($Python, (Join-Path $scriptsDir "create_references.py"), "--limit", "$Limit") },
                @{ name = "generate"; command = @($Python, (Join-Path $scriptsDir "generate_voice.py"), "--limit", "$Limit") },
                @{ name = "convert"; command = @($Python, (Join-Path $scriptsDir "convert_voice.py"), "--limit", "$Limit") },
                @{ name = "package"; command = @($Python, (Join-Path $scriptsDir "package_lines.py"), "--package-name", $packageName, "--limit", "$Limit") }
            )
        }
        "first_phase" {
            $expectedCompleted = 12
            $stages = @(
                @{ name = "build-first-phase"; command = @($Python, (Join-Path $scriptsDir "build_first_phase_dialogues.py")) }
            )
        }
        default {
            throw "Bloco ainda sem comando seguro: $BlockId"
        }
    }

    foreach ($stage in $stages) {
        Invoke-Stage -Name $stage.name -Command $stage.command
    }

    Update-Job -Status "done" -Stage "complete" -Message "Bloco finalizado e pacote preparado. Nada foi instalado no jogo."
    Update-Progress -Status "done" -Message "Bloco finalizado e pacote preparado. Nada foi instalado no jogo." -Completed $expectedCompleted -Failed 0
    exit 0
}
catch {
    $message = $_.Exception.Message
    Add-Content -LiteralPath $LogFile -Encoding UTF8 -Value ("`n[" + (Get-Date -Format "o") + "] ERRO: " + $message)
    Update-Job -Status "error" -Stage "error" -Message $message
    Update-Progress -Status "error" -Message "Erro no worker. Copie o relatorio para o Codex." -ErrorText $message
    exit 1
}
