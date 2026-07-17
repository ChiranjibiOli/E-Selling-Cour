param(
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$pidFile = Join-Path $Root 'storage\runtime\local-platform\processes.json'

if (-not (Test-Path $pidFile)) {
    Write-Host 'No CourseHub PID file was found. The tracked local platform is not running.'
    exit 0
}

$records = @(Get-Content $pidFile -Raw | ConvertFrom-Json)
$stopped = 0

foreach ($record in $records) {
    $process = Get-Process -Id ([int]$record.pid) -ErrorAction SilentlyContinue
    if (-not $process) {
        Write-Host "Already stopped: $($record.name)"
        continue
    }

    try {
        Stop-Process -Id $process.Id -Force:$Force -ErrorAction Stop
        Wait-Process -Id $process.Id -Timeout 5 -ErrorAction SilentlyContinue

        if (Get-Process -Id $process.Id -ErrorAction SilentlyContinue) {
            Stop-Process -Id $process.Id -Force -ErrorAction Stop
        }

        $stopped++
        Write-Host "Stopped $($record.name) (PID $($record.pid))"
    } catch {
        Write-Warning "Could not stop $($record.name) (PID $($record.pid)): $($_.Exception.Message)"
    }
}

Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
Write-Host "Stopped $stopped CourseHub process(es)."
