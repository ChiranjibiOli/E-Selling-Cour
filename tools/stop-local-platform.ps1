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

$decoded = Get-Content $pidFile -Raw | ConvertFrom-Json
$records = @($decoded)
$stopped = 0

foreach ($record in $records) {
    # Some PowerShell versions deserialize a numeric property as an array.
    # Normalize every value so both scalar and array PID records are supported.
    $pidValues = @($record.pid)
    $nameValues = @($record.name)

    for ($index = 0; $index -lt $pidValues.Count; $index++) {
        $rawPid = $pidValues[$index]
        $serviceName = if ($index -lt $nameValues.Count -and $nameValues[$index]) {
            [string] $nameValues[$index]
        } elseif ($record.name) {
            [string] $record.name
        } else {
            'unknown-service'
        }

        $parsedPid = 0
        if (-not [int]::TryParse([string] $rawPid, [ref] $parsedPid)) {
            Write-Warning "Skipping invalid PID '$rawPid' for $serviceName."
            continue
        }

        $process = Get-Process -Id $parsedPid -ErrorAction SilentlyContinue
        if (-not $process) {
            Write-Host "Already stopped: $serviceName (PID $parsedPid)"
            continue
        }

        try {
            Stop-Process -Id $parsedPid -Force:$Force -ErrorAction Stop
            Wait-Process -Id $parsedPid -Timeout 5 -ErrorAction SilentlyContinue

            if (Get-Process -Id $parsedPid -ErrorAction SilentlyContinue) {
                Stop-Process -Id $parsedPid -Force -ErrorAction Stop
            }

            $stopped++
            Write-Host "Stopped $serviceName (PID $parsedPid)"
        } catch {
            Write-Warning "Could not stop $serviceName (PID $parsedPid): $($_.Exception.Message)"
        }
    }
}

Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
Write-Host "Stopped $stopped CourseHub process(es)."