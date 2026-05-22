param(
    [string]$NodePath = "node",
    [string]$ScriptPath = (Join-Path $PSScriptRoot "baileys.js")
)

$ErrorActionPreference = "Stop"

$pidFile = Join-Path (Join-Path $PSScriptRoot "..\data") "whatsapp_service.pid"
if (Test-Path $pidFile) {
    try {
        $existingPid = [int](Get-Content -LiteralPath $pidFile -Raw).Trim()
        $alive = Get-Process -Id $existingPid -ErrorAction SilentlyContinue
        if ($alive) {
            Write-Host "WhatsApp ja esta em execucao no PID $existingPid."
            exit 0
        }
    } catch {}
}

Start-Process -FilePath $NodePath -ArgumentList @($ScriptPath) -WindowStyle Hidden
