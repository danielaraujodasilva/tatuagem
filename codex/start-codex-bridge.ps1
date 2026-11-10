param(
    [string]$NodePath = "node",
    [string]$ScriptPath = (Join-Path $PSScriptRoot "bridge\server.js"),
    [int]$Port = 3051,
    [switch]$Watch,
    [switch]$Register,
    [switch]$Unregister,
    [int]$Intervalo = 30,
    [string]$Modelo = "deepseek-flash",
    [string]$Provedor = "deepseek"
)

$ErrorActionPreference = "Stop"

$dataDir = Join-Path $PSScriptRoot "bridge\data"
New-Item -ItemType Directory -Force -Path $dataDir | Out-Null
$pidFile = Join-Path $dataDir "bridge.pid"
$logFile = Join-Path $dataDir "watchdog.log"
$taskName = "Codex Web Bridge"

function Write-Log([string]$mensagem) {
    $linha = "[" + (Get-Date -Format "s") + "] " + $mensagem
    Write-Host $linha
    try { Add-Content -LiteralPath $logFile -Value $linha -Encoding UTF8 } catch {}
}

function Test-Porta {
    try {
        $cliente = New-Object System.Net.Sockets.TcpClient
        $cliente.Connect('127.0.0.1', $Port)
        $cliente.Close()
        return $true
    } catch {
        return $false
    }
}

function Test-ProcessoVivo {
    if (-not (Test-Path -LiteralPath $pidFile)) { return $false }
    try {
        $existente = [int](Get-Content -LiteralPath $pidFile -Raw).Trim()
        # Nao basta o PID existir: o Windows reutiliza PIDs. Confirma que ainda
        # e o node rodando esta ponte, senao um PID reaproveitado (ex.: cloudflared)
        # faz o watchdog achar que a ponte esta viva para sempre.
        $processo = Get-CimInstance Win32_Process -Filter "ProcessId=$existente" -ErrorAction SilentlyContinue
        if (-not $processo) { return $false }
        if ($processo.Name -notmatch '^node(\.exe)?$') { return $false }
        return [bool]($processo.CommandLine -and $processo.CommandLine -match 'server\.js')
    } catch {
        return $false
    }
}

function Start-Bridge {
    $portaAnterior = $env:CODEX_WEB_PORT
    $env:CODEX_WEB_PORT = [string]$Port
    # Modelo/provedor do site (nao mexe no config.toml do app Codex do desktop).
    $env:CODEX_WEB_MODEL = $Modelo
    $env:CODEX_WEB_PROVIDER = $Provedor
    if (-not $env:DEEPSEEK_CODEX_KEY) {
        $chave = [Environment]::GetEnvironmentVariable('DEEPSEEK_CODEX_KEY', 'User')
        if ($chave) { $env:DEEPSEEK_CODEX_KEY = $chave }
    }
    # A ponte tambem roda turnos do DSH (`dsh headless`), que le DEEPSEEK_API_KEY.
    if (-not $env:DEEPSEEK_API_KEY -and $env:DEEPSEEK_CODEX_KEY) {
        $env:DEEPSEEK_API_KEY = $env:DEEPSEEK_CODEX_KEY
    }
    try {
        $processo = Start-Process -FilePath $NodePath -ArgumentList @($ScriptPath) -WindowStyle Hidden -PassThru
    } finally {
        if ($null -eq $portaAnterior) {
            Remove-Item Env:CODEX_WEB_PORT -ErrorAction SilentlyContinue
        } else {
            $env:CODEX_WEB_PORT = $portaAnterior
        }
    }
    Set-Content -LiteralPath $pidFile -Value $processo.Id
    Write-Log "Ponte Codex iniciada no PID $($processo.Id) (http://127.0.0.1:$Port)."
}

if ($Unregister) {
    if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
        Write-Log "Tarefa agendada '$taskName' removida."
    } else {
        Write-Log "Nenhuma tarefa '$taskName' encontrada."
    }
    return
}

if ($Register) {
    $acao = New-ScheduledTaskAction -Execute "powershell.exe" `
        -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$PSCommandPath`" -Watch"
    $config = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
        -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit (New-TimeSpan -Seconds 0)
    $gatilhos = @(New-ScheduledTaskTrigger -AtLogOn)
    try {
        $gatilhos += New-ScheduledTaskTrigger -AtStartup
        Register-ScheduledTask -TaskName $taskName -Action $acao -Trigger $gatilhos -Settings $config `
            -Description "Mantem a ponte local do Codex (codex.danieltatuador.com) de pe." -Force | Out-Null
        Write-Log "Tarefa '$taskName' criada (logon + boot)."
    } catch {
        Register-ScheduledTask -TaskName $taskName -Action $acao -Trigger $gatilhos[0] -Settings $config `
            -Description "Mantem a ponte local do Codex (codex.danieltatuador.com) de pe." -Force | Out-Null
        Write-Log "Tarefa '$taskName' criada apenas no logon (boot exige admin)."
    }
    return
}

if ($Watch) {
    Write-Log "Watchdog da ponte Codex ativo (porta $Port, intervalo ${Intervalo}s)."
    while ($true) {
        if (-not (Test-Porta) -and -not (Test-ProcessoVivo)) {
            try { Start-Bridge } catch { Write-Log "Falha ao subir a ponte: $($_.Exception.Message)" }
        }
        Start-Sleep -Seconds $Intervalo
    }
}

if (Test-Porta) {
    Write-Host "Ponte Codex ja esta respondendo na porta $Port."
    exit 0
}

if (Test-ProcessoVivo) {
    Write-Host "Ponte Codex ja esta em execucao (PID no $pidFile)."
    exit 0
}

Start-Bridge
