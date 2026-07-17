param(
    [string]$Php = "C:\xampp\php\php.exe",
    [switch]$Visible
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path $Php)) {
    throw "PHP was not found at $Php"
}

# Load local KEY=VALUE configuration before child processes are created.
$envFile = Join-Path $Root '.env'
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
            $name, $value = $line.Split('=', 2)
            [Environment]::SetEnvironmentVariable(
                $name.Trim(),
                $value.Trim().Trim('"').Trim("'"),
                'Process'
            )
        }
    }
}

$env:API_BASE_URL = 'http://127.0.0.1:9000'
$env:IDENTITY_SERVICE_URL = 'http://127.0.0.1:9101'
$env:CATALOG_SERVICE_URL = 'http://127.0.0.1:9102'
$env:LEARNING_SERVICE_URL = 'http://127.0.0.1:9103'
$env:COMMERCE_SERVICE_URL = 'http://127.0.0.1:9104'
$env:PAYMENT_SERVICE_URL = 'http://127.0.0.1:9105'
$env:ENROLLMENT_SERVICE_URL = 'http://127.0.0.1:9106'
$env:MEDIA_SERVICE_URL = 'http://127.0.0.1:9107'
$env:NOTIFICATION_SERVICE_URL = 'http://127.0.0.1:9108'
$env:REVIEW_SERVICE_URL = 'http://127.0.0.1:9109'
$env:REPORTING_SERVICE_URL = 'http://127.0.0.1:9110'

$servers = @(
    @{ Name='gateway';      Port=9000; Root='services/api-gateway/public';          Router='services/api-gateway/public/index.php' },
    @{ Name='identity';     Port=9101; Root='services/identity-service/public';     Router='services/identity-service/public/index.php' },
    @{ Name='catalog';      Port=9102; Root='services/catalog-service/public';      Router='services/catalog-service/public/index.php' },
    @{ Name='learning';     Port=9103; Root='services/learning-service/public';     Router='services/learning-service/public/index.php' },
    @{ Name='commerce';     Port=9104; Root='services/commerce-service/public';     Router='services/commerce-service/public/index.php' },
    @{ Name='payment';      Port=9105; Root='services/payment-service/public';      Router='services/payment-service/public/index.php' },
    @{ Name='enrollment';   Port=9106; Root='services/enrollment-service/public';   Router='services/enrollment-service/public/index.php' },
    @{ Name='media';        Port=9107; Root='services/media-service/public';        Router='services/media-service/public/index.php' },
    @{ Name='notification'; Port=9108; Root='services/notification-service/public'; Router='services/notification-service/public/index.php' },
    @{ Name='review';       Port=9109; Root='services/review-service/public';       Router='services/review-service/public/index.php' },
    @{ Name='reporting';    Port=9110; Root='services/reporting-service/public';    Router='services/reporting-service/public/index.php' },
    @{ Name='web';          Port=9001; Root='apps/web-platform/public';             Router='apps/web-platform/public/router.php' }
)

$runtimeDirectory = Join-Path $Root 'storage\runtime\local-platform'
$logDirectory = Join-Path $Root 'storage\logs\local-platform'
$pidFile = Join-Path $runtimeDirectory 'processes.json'

New-Item -ItemType Directory -Path $runtimeDirectory -Force | Out-Null
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null

# Prevent duplicate launchers from quietly occupying the same ports.
if (Test-Path $pidFile) {
    $existing = @(Get-Content $pidFile -Raw | ConvertFrom-Json)
    $active = @($existing | Where-Object {
        Get-Process -Id ([int]$_.pid) -ErrorAction SilentlyContinue
    })

    if ($active.Count -gt 0) {
        throw "CourseHub is already running. Use tools\stop-local-platform.ps1 first."
    }

    Remove-Item $pidFile -Force
}

$started = @()

try {
    foreach ($server in $servers) {
        $listener = Get-NetTCPConnection -LocalPort $server.Port -State Listen -ErrorAction SilentlyContinue
        if ($listener) {
            throw "Port $($server.Port) is already in use. Stop the existing process before starting CourseHub."
        }

        $documentRoot = Join-Path $Root $server.Root
        $router = Join-Path $Root $server.Router
        $stdout = Join-Path $logDirectory "$($server.Name).out.log"
        $stderr = Join-Path $logDirectory "$($server.Name).error.log"

        # Clear previous run logs so the current run is easy to inspect.
        Set-Content -Path $stdout -Value ''
        Set-Content -Path $stderr -Value ''

        $parameters = @{
            FilePath               = $Php
            WorkingDirectory       = $Root
            ArgumentList           = @('-S', "127.0.0.1:$($server.Port)", '-t', $documentRoot, $router)
            RedirectStandardOutput = $stdout
            RedirectStandardError  = $stderr
            PassThru               = $true
        }

        $parameters.WindowStyle = if ($Visible) { 'Normal' } else { 'Hidden' }
        $process = Start-Process @parameters

        $started += [pscustomobject]@{
            name = $server.Name
            port = $server.Port
            pid  = $process.Id
            stdout = $stdout
            stderr = $stderr
        }

        Write-Host "Started $($server.Name.PadRight(12)) PID $($process.Id) on http://127.0.0.1:$($server.Port)"
    }

    $started | ConvertTo-Json -Depth 3 | Set-Content -Path $pidFile -Encoding UTF8
} catch {
    # Do not leave half the platform running when one service fails to start.
    foreach ($record in $started) {
        Stop-Process -Id ([int]$record.pid) -Force -ErrorAction SilentlyContinue
    }
    Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
    throw
}

Write-Host ''
Write-Host 'CourseHub is running in the background.'
Write-Host 'Web:  http://127.0.0.1:9001'
Write-Host "Logs: $logDirectory"
Write-Host 'Stop: powershell -ExecutionPolicy Bypass -File tools\stop-local-platform.ps1'
