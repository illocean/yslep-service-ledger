param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$WatchPath = '',
    [int]$DebounceMilliseconds = 1200
)

$ErrorActionPreference = 'Stop'

function Get-DotEnvValue {
    param(
        [string]$EnvPath,
        [string]$Key
    )

    if (-not (Test-Path $EnvPath)) {
        return $null
    }

    $line = Get-Content $EnvPath | Where-Object { $_ -match "^\s*$Key\s*=" } | Select-Object -First 1

    if (-not $line) {
        return $null
    }

    return ($line -split '=', 2)[1].Trim().Trim("'`"")
}

if (-not $WatchPath) {
    $envPath = Join-Path $ProjectRoot '.env'
    $vaultPath = Get-DotEnvValue -EnvPath $envPath -Key 'OBSIDIAN_VAULT_PATH'
    $notesDirectory = Get-DotEnvValue -EnvPath $envPath -Key 'OBSIDIAN_REPORT_NOTES_DIRECTORY'

    if (-not $vaultPath) {
        throw 'Could not resolve OBSIDIAN_VAULT_PATH from .env. Pass -WatchPath explicitly.'
    }

    if (-not $notesDirectory) {
        $notesDirectory = 'REPORTS'
    }

    $WatchPath = Join-Path $vaultPath $notesDirectory
}

if (-not (Test-Path $WatchPath)) {
    New-Item -ItemType Directory -Path $WatchPath -Force | Out-Null
}

$artisan = Join-Path $ProjectRoot 'artisan'

if (-not (Test-Path $artisan)) {
    throw "Could not find artisan at $artisan"
}

$state = [hashtable]::Synchronized(@{
    Pending = $false
    LastEventUtc = [datetime]::MinValue
})

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $WatchPath
$watcher.Filter = '*.md'
$watcher.IncludeSubdirectories = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName, LastWrite, CreationTime, Size'
$watcher.EnableRaisingEvents = $true

$action = {
    $state.Pending = $true
    $state.LastEventUtc = [datetime]::UtcNow
}

$createdEvent = Register-ObjectEvent -InputObject $watcher -EventName Created -Action $action
$changedEvent = Register-ObjectEvent -InputObject $watcher -EventName Changed -Action $action
$deletedEvent = Register-ObjectEvent -InputObject $watcher -EventName Deleted -Action $action
$renamedEvent = Register-ObjectEvent -InputObject $watcher -EventName Renamed -Action $action

Write-Host "Watching Obsidian report notes in: $WatchPath"
Write-Host "Project root: $ProjectRoot"
Write-Host "Debounce: ${DebounceMilliseconds}ms"
Write-Host "Press Ctrl+C to stop."

try {
    while ($true) {
        Start-Sleep -Milliseconds 300

        if (-not $state.Pending) {
            continue
        }

        $elapsed = [datetime]::UtcNow - $state.LastEventUtc

        if ($elapsed.TotalMilliseconds -lt $DebounceMilliseconds) {
            continue
        }

        $state.Pending = $false

        Write-Host "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Syncing saved report notes from Obsidian..."
        & php $artisan obsidian:sync-report-notes

        if ($LASTEXITCODE -ne 0) {
            Write-Warning "obsidian:sync-report-notes exited with code $LASTEXITCODE"
        }
    }
}
finally {
    $watcher.EnableRaisingEvents = $false
    $watcher.Dispose()

    $createdEvent, $changedEvent, $deletedEvent, $renamedEvent | ForEach-Object {
        if ($_ -ne $null) {
            Unregister-Event -SubscriptionId $_.Id -ErrorAction SilentlyContinue
            Remove-Job -Id $_.Id -Force -ErrorAction SilentlyContinue
        }
    }
}
