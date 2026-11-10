@echo off
chcp 65001 >nul
setlocal EnableExtensions
title Coleta de Hardware - %COMPUTERNAME%

net session >nul 2>&1
if errorlevel 1 (
  echo Pedindo permissao de Administrador...
  powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)

set "PSFILE=%TEMP%\coleta_hardware_%RANDOM%%RANDOM%.ps1"
powershell -NoProfile -Command "$c=Get-Content -LiteralPath '%~f0' -Raw; $i=$c.IndexOf([char]10+'#:PSSTART'); $f=$c.IndexOf([char]10+'#:PSEND'); if($i -lt 0 -or $f -lt 0){exit 2}; Set-Content -LiteralPath '%PSFILE%' -Value $c.Substring($i+10, $f-$i-10) -Encoding UTF8"
if errorlevel 1 (
  echo Falha ao extrair o script interno.
  pause
  exit /b 1
)

echo Coletando informacoes do hardware. Aguarde...
powershell -NoProfile -ExecutionPolicy Bypass -File "%PSFILE%"
set "RC=%ERRORLEVEL%"
del "%PSFILE%" >nul 2>&1

echo.
echo Finalizado.
pause
exit /b %RC%

#:PSSTART
$ErrorActionPreference = 'SilentlyContinue'
$ProgressPreference    = 'SilentlyContinue'

$L = New-Object System.Collections.Generic.List[string]
function Sec([string]$t) {
    $L.Add('')
    $L.Add(('=' * 78))
    $L.Add('  ' + $t.ToUpper())
    $L.Add(('=' * 78))
}
function Line([string]$s) { $L.Add($s) }
function Item($o) {
    if ($null -eq $o) { $L.Add('(sem dados)'); return }
    $s = ($o | Out-String).TrimEnd()
    if ([string]::IsNullOrWhiteSpace($s)) { $L.Add('(sem dados)') } else { $L.Add($s) }
}

$stamp = Get-Date -Format 'yyyy-MM-dd_HH-mm'
$out   = Join-Path ([Environment]::GetFolderPath('Desktop')) ('hardware_' + $env:COMPUTERNAME + '_' + $stamp + '.txt')
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

Sec 'Cabecalho'
Line ('Data          : ' + (Get-Date -Format 'dd/MM/yyyy HH:mm:ss'))
Line ('Computador    : ' + $env:COMPUTERNAME)
Line ('Usuario       : ' + $env:USERNAME)
Line ('Administrador : ' + $isAdmin)
Line ('Arquivo       : ' + $out)

Sec 'Sistema - resumo'
Item (Get-CimInstance Win32_ComputerSystem | Select-Object Manufacturer,Model,SystemType,TotalPhysicalMemory,Domain,Workgroup | Format-List)
Item (Get-CimInstance Win32_ComputerSystemProduct | Select-Object Name,Version,Vendor,IdentifyingNumber | Format-List)
$os = Get-CimInstance Win32_OperatingSystem
Item ($os | Select-Object Caption,Version,BuildNumber,OSArchitecture,InstallDate,LastBootUpTime,SystemDrive,WindowsDirectory | Format-List)
if ($os.LastBootUpTime) { Line ('Uptime        : ' + ((Get-Date) - $os.LastBootUpTime).ToString()) }

Sec 'CPU'
Item (Get-CimInstance Win32_Processor | Select-Object Name,Manufacturer,NumberOfCores,NumberOfLogicalProcessors,MaxClockSpeed,CurrentClockSpeed,ExtClock,L2CacheSize,L3CacheSize,SocketDesignation,VirtualizationFirmwareEnabled | Format-List)
Item (Get-CimInstance Win32_CacheMemory | Select-Object Level,Purpose,InstalledSize,MaxCacheSize | Format-Table -AutoSize)

Sec 'Memoria RAM'
$arr  = Get-CimInstance Win32_PhysicalMemoryArray
$mods = Get-CimInstance Win32_PhysicalMemory
if ($mods) {
    $tot = [math]::Round((($mods | Measure-Object -Property Capacity -Sum).Sum / 1GB), 2)
    Line ('Total instalado : ' + $tot + ' GB')
}
if ($arr) { Line ('Slots           : ' + $arr.MemoryDevices + ' (livres: ' + ($arr.MemoryDevices - @($mods).Count) + ')') }
Item ($mods | Select-Object BankLabel,DeviceLocator,Manufacturer,PartNumber,SerialNumber,@{n='GB';e={[math]::Round($_.Capacity/1GB,0)}},Speed,ConfiguredClockSpeed,FormFactor | Format-Table -AutoSize)

Sec 'Placa-mae e BIOS'
Item (Get-CimInstance Win32_BaseBoard | Select-Object Manufacturer,Product,Version,SerialNumber | Format-List)
Item (Get-CimInstance Win32_BIOS | Select-Object Manufacturer,Name,Version,SMBIOSBIOSVersion,ReleaseDate | Format-List)

Sec 'GPU / Video'
Item (Get-CimInstance Win32_VideoController | Select-Object Name,VideoProcessor,DriverVersion,DriverDate,AdapterRAM,CurrentHorizontalResolution,CurrentVerticalResolution,CurrentRefreshRate,Status | Format-List)
Line '--- VRAM real (via registro) ---'
$cls = 'HKLM:\SYSTEM\CurrentControlSet\Control\Class\{4d36e968-e325-11ce-bfc1-08002be10318}'
foreach ($k in (Get-ChildItem $cls -ErrorAction SilentlyContinue | Where-Object { $_.PSChildName -match '^[0-9]{4}$' })) {
    $p = Get-ItemProperty -Path $k.PSPath -ErrorAction SilentlyContinue
    $vram = $p.'HardwareInformation.qwMemorySize'
    if ($vram) { Line ('  ' + $p.DriverDesc + ' -> ' + [math]::Round($vram/1GB,1) + ' GB VRAM') }
}

Sec 'Discos'
Item (Get-PhysicalDisk | Select-Object DeviceId,FriendlyName,MediaType,BusType,@{n='GB';e={[math]::Round($_.Size/1GB,1)}},HealthStatus,OperationalStatus,FirmwareVersion,SpindleSpeed | Format-Table -AutoSize)
Item (Get-Disk | Select-Object Number,FriendlyName,PartitionStyle,@{n='GB';e={[math]::Round($_.Size/1GB,1)}},HealthStatus,IsBoot,IsSystem | Format-Table -AutoSize)
Item (Get-Volume | Select-Object DriveLetter,FileSystemLabel,FileSystem,@{n='GB_Livre';e={[math]::Round($_.SizeRemaining/1GB,1)}},@{n='GB_Total';e={[math]::Round($_.Size/1GB,1)}},HealthStatus | Format-Table -AutoSize)
Line '--- SMART / desgaste ---'
Item (Get-PhysicalDisk | Get-StorageReliabilityCounter | Select-Object DeviceId,Temperature,TemperatureMax,Wear,PowerOnHours,StartStopCycleCount,ReadErrorsTotal,WriteErrorsTotal | Format-Table -AutoSize)

Sec 'Rede'
Item (Get-NetAdapter | Select-Object Name,InterfaceDescription,MacAddress,LinkSpeed,Status,MediaType,DriverVersion,DriverDate | Format-Table -AutoSize)
Item (Get-NetAdapterHardwareInfo | Select-Object Name,BusNumber,DeviceNumber,FunctionNumber,Slot | Format-Table -AutoSize)
Item (Get-NetIPConfiguration | Out-String)
Line '--- Wi-Fi ---'
Item (netsh wlan show interfaces)

Sec 'Monitores'
$mons = Get-CimInstance -Namespace root\wmi -ClassName WmiMonitorID
if ($mons) {
    foreach ($m in $mons) {
        $fab = -join ($m.ManufacturerName | Where-Object { $_ -gt 0 } | ForEach-Object { [char]$_ })
        $mod = -join ($m.UserFriendlyName  | Where-Object { $_ -gt 0 } | ForEach-Object { [char]$_ })
        $ser = -join ($m.SerialNumberID    | Where-Object { $_ -gt 0 } | ForEach-Object { [char]$_ })
        Line ('  ' + $fab + ' | ' + $mod + ' | serie ' + $ser + ' | ano ' + $m.YearOfManufacture)
    }
} else { Line '(sem dados)' }

Sec 'Audio'
Item (Get-CimInstance Win32_SoundDevice | Select-Object Name,Manufacturer,Status | Format-Table -AutoSize)

Sec 'Impressoras'
Item (Get-Printer | Select-Object Name,DriverName,PortName,Type,Shared | Format-Table -AutoSize)

Sec 'Bateria'
$bat = Get-CimInstance Win32_Battery
if ($bat) { Item ($bat | Select-Object Name,EstimatedChargeRemaining,BatteryStatus,DesignVoltage | Format-List) } else { Line '(desktop - sem bateria)' }

Sec 'Temperatura (tentativa - pode vir vazio em desktop)'
Item (Get-CimInstance -Namespace root/wmi -ClassName MSAcpi_ThermalZoneTemperature | Select-Object InstanceName,@{n='Celsius';e={[math]::Round($_.CurrentTemperature/10-273.15,1)}} | Format-Table -AutoSize)

Sec 'Atualizacoes recentes do Windows'
Item (Get-HotFix | Sort-Object InstalledOn -Descending | Select-Object -First 15 HotFixID,Description,InstalledOn | Format-Table -AutoSize)

Sec 'Top 15 processos por memoria'
Item (Get-Process | Sort-Object WorkingSet64 -Descending | Select-Object -First 15 Name,Id,@{n='RAM_MB';e={[math]::Round($_.WorkingSet64/1MB,0)}} | Format-Table -AutoSize)

Sec 'Fim'
Line 'Nenhum dado foi enviado para a internet - tudo gerado localmente.'
Line ('Gerado em ' + (Get-Date -Format 'dd/MM/yyyy HH:mm:ss'))

$L -join "`r`n" | Set-Content -LiteralPath $out -Encoding UTF8

Write-Host ''
Write-Host 'Relatorio salvo em:' -ForegroundColor Green
Write-Host $out -ForegroundColor Green
Write-Host ''
Start-Process notepad.exe -ArgumentList ('"' + $out + '"')

#:PSEND