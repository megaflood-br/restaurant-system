# Agente local de impressao ESC/POS — Windows PowerShell (sem PHP)
# Uso:  powershell -ExecutionPolicy Bypass -File print-agent.ps1
#       ou duplo clique em iniciar.bat

param(
    [string]$Url,
    [string]$Token,
    [string]$Printer,
    [int]$Port = 0,
    [int]$Interval = 0,
    [switch]$Once,
    [switch]$Help
)

$ErrorActionPreference = "Stop"
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

function Show-Help {
    @"
Agente local de impressao Bella Bistro (PowerShell — sem PHP)

1) Copie config.example.ini para config.ini e preencha url/token/printer
2) Duplo clique em iniciar.bat

Opcional (sobrescreve config.ini):
  -Url https://app.bellabistro.com.br
  -Token SEU_TOKEN
  -Printer 192.168.1.100
  -Port 9100
  -Interval 2
  -Once
"@ | Write-Host
}

function Read-Ini([string]$Path) {
    $map = @{}
    if (-not (Test-Path $Path)) { return $map }
    foreach ($line in Get-Content -Path $Path -Encoding UTF8) {
        $trim = $line.Trim()
        if ($trim -eq "" -or $trim.StartsWith(";") -or $trim.StartsWith("#") -or $trim.StartsWith("[")) {
            continue
        }
        $parts = $trim -split "=", 2
        if ($parts.Count -lt 2) { continue }
        $key = $parts[0].Trim().ToLowerInvariant()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        $map[$key] = $value
    }
    return $map
}

function Invoke-Api {
    param(
        [string]$Method,
        [string]$Uri,
        [string]$Token,
        [hashtable]$Body = $null
    )

    $headers = @{
        Authorization = "Bearer $Token"
        Accept        = "application/json"
    }

    $params = @{
        Method  = $Method
        Uri     = $Uri
        Headers = $headers
        TimeoutSec = 20
    }

    if ($null -ne $Body) {
        $params.ContentType = "application/json; charset=utf-8"
        $params.Body = ($Body | ConvertTo-Json -Compress)
    }

    try {
        return Invoke-RestMethod @params
    } catch {
        $msg = $_.Exception.Message
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) {
            $msg = $_.ErrorDetails.Message
        }
        throw "Falha API ($Method $Uri): $msg"
    }
}

function Send-EscPos {
    param(
        [string]$HostName,
        [int]$PortNumber,
        [string]$Text
    )

    $bytes = New-Object System.Collections.Generic.List[byte]
    # ESC @ init
    $bytes.Add(0x1B) | Out-Null
    $bytes.Add(0x40) | Out-Null
    # ESC t 16 code page
    $bytes.Add(0x1B) | Out-Null
    $bytes.Add(0x74) | Out-Null
    $bytes.Add(0x10) | Out-Null
    # ESC a 0 left
    $bytes.Add(0x1B) | Out-Null
    $bytes.Add(0x61) | Out-Null
    $bytes.Add(0x00) | Out-Null

    $normalized = ($Text -replace "`r`n", "`n" -replace "`r", "`n")
    if (-not $normalized.EndsWith("`n")) { $normalized += "`n" }
    $normalized += "`n`n`n"

    $textBytes = [System.Text.Encoding]::ASCII.GetBytes($normalized)
    foreach ($b in $textBytes) { $bytes.Add($b) | Out-Null }

    # GS V A 3 partial cut with feed
    $bytes.Add(0x1D) | Out-Null
    $bytes.Add(0x56) | Out-Null
    $bytes.Add(0x41) | Out-Null
    $bytes.Add(0x03) | Out-Null

    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $iar = $client.BeginConnect($HostName, $PortNumber, $null, $null)
        if (-not $iar.AsyncWaitHandle.WaitOne(5000, $false)) {
            throw "Timeout ao conectar em ${HostName}:${PortNumber}"
        }
        $client.EndConnect($iar)
        $stream = $client.GetStream()
        $stream.WriteTimeout = 5000
        $payload = $bytes.ToArray()
        $stream.Write($payload, 0, $payload.Length)
        $stream.Flush()
    } finally {
        if ($client) { $client.Close() }
    }
}

if ($Help) {
    Show-Help
    exit 0
}

$baseDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $baseDir "config.ini"
$config = Read-Ini $configPath

if (-not $Url) { $Url = [string]$config["url"] }
if (-not $Token) { $Token = [string]$config["token"] }
if (-not $Printer) { $Printer = [string]$config["printer"] }
if ($Port -le 0) {
    if ($config.ContainsKey("port") -and $config["port"]) { $Port = [int]$config["port"] } else { $Port = 9100 }
}
if ($Interval -le 0) {
    if ($config.ContainsKey("interval") -and $config["interval"]) { $Interval = [int]$config["interval"] } else { $Interval = 2 }
}
if (-not $Once -and $config.ContainsKey("once") -and $config["once"]) {
    $Once = $true
}

$Url = $Url.TrimEnd("/")
$Token = $Token.Trim()
$Printer = $Printer.Trim()

if (-not $Url -or -not $Token -or -not $Printer) {
    Write-Host "Falta configuracao." -ForegroundColor Red
    Write-Host "Crie config.ini (veja config.example.ini) ou passe -Url -Token -Printer."
    exit 1
}

Write-Host ("[{0}] Agente iniciado → {1}:{2}" -f (Get-Date -Format "HH:mm:ss"), $Printer, $Port)
Write-Host ("[{0}] Painel: {1}" -f (Get-Date -Format "HH:mm:ss"), $Url)
Write-Host ("[{0}] PowerShell (sem PHP). Deixe esta janela aberta. Ctrl+C para parar." -f (Get-Date -Format "HH:mm:ss"))

do {
    try {
        $response = Invoke-Api -Method "POST" -Uri ($Url + "/api/v1/print-jobs/claim") -Token $Token
        $job = $response.data

        if ($null -eq $job) {
            if (-not $Once) { Start-Sleep -Seconds $Interval }
            continue
        }

        $id = [int]$job.id
        $type = [string]$job.type
        Write-Host ("[{0}] Job #{1} ({2}) imprimindo..." -f (Get-Date -Format "HH:mm:ss"), $id, $type)

        try {
            Send-EscPos -HostName $Printer -PortNumber $Port -Text ([string]$job.payload)
            Invoke-Api -Method "POST" -Uri ($Url + "/api/v1/print-jobs/$id/complete") -Token $Token | Out-Null
            Write-Host ("[{0}] Job #{1} OK" -f (Get-Date -Format "HH:mm:ss"), $id) -ForegroundColor Green
        } catch {
            $err = $_.Exception.Message
            try {
                Invoke-Api -Method "POST" -Uri ($Url + "/api/v1/print-jobs/$id/fail") -Token $Token -Body @{ error = $err } | Out-Null
            } catch { }
            Write-Host ("[{0}] Job #{1} FALHOU: {2}" -f (Get-Date -Format "HH:mm:ss"), $id, $err) -ForegroundColor Yellow
            Start-Sleep -Seconds 2
        }
    } catch {
        Write-Host ("[{0}] API: {1}" -f (Get-Date -Format "HH:mm:ss"), $_.Exception.Message) -ForegroundColor Yellow
        Start-Sleep -Seconds $Interval
    }
} while (-not $Once)

exit 0
