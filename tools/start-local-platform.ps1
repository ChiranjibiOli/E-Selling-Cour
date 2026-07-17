param(
    [string]$Php = "C:\xampp\php\php.exe"
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path $Php)) { throw "PHP was not found at $Php" }

# Load simple KEY=VALUE entries so every child service receives the same local configuration.
$envFile = Join-Path $Root '.env'
if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith('#') -and $line.Contains('=')) {
            $name, $value = $line.Split('=', 2)
            [Environment]::SetEnvironmentVariable($name.Trim(), $value.Trim().Trim('"').Trim("'"), 'Process')
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
    @{ Name='gateway'; Port=9000; Root='services/api-gateway/public'; Router='services/api-gateway/public/index.php' },
    @{ Name='identity'; Port=9101; Root='services/identity-service/public'; Router='services/identity-service/public/index.php' },
    @{ Name='catalog'; Port=9102; Root='services/catalog-service/public'; Router='services/catalog-service/public/index.php' },
    @{ Name='learning'; Port=9103; Root='services/learning-service/public'; Router='services/learning-service/public/index.php' },
    @{ Name='commerce'; Port=9104; Root='services/commerce-service/public'; Router='services/commerce-service/public/index.php' },
    @{ Name='payment'; Port=9105; Root='services/payment-service/public'; Router='services/payment-service/public/index.php' },
    @{ Name='enrollment'; Port=9106; Root='services/enrollment-service/public'; Router='services/enrollment-service/public/index.php' },
    @{ Name='media'; Port=9107; Root='services/media-service/public'; Router='services/media-service/public/index.php' },
    @{ Name='notification'; Port=9108; Root='services/notification-service/public'; Router='services/notification-service/public/index.php' },
    @{ Name='review'; Port=9109; Root='services/review-service/public'; Router='services/review-service/public/index.php' },
    @{ Name='reporting'; Port=9110; Root='services/reporting-service/public'; Router='services/reporting-service/public/index.php' },
    @{ Name='web'; Port=9001; Root='apps/web-platform/public'; Router='apps/web-platform/public/router.php' }
)

foreach ($server in $servers) {
    $documentRoot = Join-Path $Root $server.Root
    $router = Join-Path $Root $server.Router
    Start-Process -FilePath $Php -WorkingDirectory $Root -ArgumentList @('-S', "127.0.0.1:$($server.Port)", '-t', $documentRoot, $router) -WindowStyle Minimized
    Write-Host "Started $($server.Name) on http://127.0.0.1:$($server.Port)"
}

Write-Host 'CourseHub local platform started. Open http://127.0.0.1:9001'
