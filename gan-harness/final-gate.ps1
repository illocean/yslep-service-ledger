# Final-gate verification for the YSLEP gan-harness design loop.
# (spec.md, section "Verification every iteration", step 5)
#
# Runs the canonical end-of-iteration gate in order:
#   1. npm run build            (spec.md hard constraint 6 / step 1)
#   2. php artisan test compact  (serial per .ai/rules/tests.md; rubric blocker)
#   3. vendor/bin/pint dirty format agent
#   4. detect.mjs (impeccable anti-slop detector -- optional, external path)
#
# Stops at the FIRST failing step (fail-fast) and exits non-zero.
# Enforces serial test execution: if a php or php artisan test process is
# already running, it aborts to avoid the flaky shared-DB failures documented
# in .ai/rules/tests.md.
#
# .PARAMETER SkipDetect
#   Skip the external detect.mjs step entirely (useful when the personal config
#   path is not present on this machine).
#
# .EXAMPLE
#   .\gan-harness\final-gate.ps1
#   .\gan-harness\final-gate.ps1 -SkipDetect
#
# .NOTES
#   Project: YSLEP Service Ledger
#   Environment: Windows / PowerShell 5.1 (per spec.md hard constraint 7).
#   detect.mjs default path mirrors spec.md; override via IMPPECCABLE_DETECT.

[CmdletBinding()]
param(
    [switch]$SkipDetect
)

$ErrorActionPreference = 'Stop'

function Invoke-Step {
    param(
        [string]$Name,
        [string[]]$Command,
        [string]$WorkingDir = (Get-Location)
    )
    Write-Host "`n=== $Name ===" -ForegroundColor Cyan
    Write-Host "  $ $($Command -join ' ')" -ForegroundColor DarkGray
    Push-Location $WorkingDir
    try {
        $exe = $Command[0]
        $argsStr = ''
        if ($Command.Length -gt 1) {
            $argList = $Command[1..($Command.Length - 1)]
            $argsStr = $argList -join ' '
        }
        $p = Start-Process -FilePath $exe -ArgumentList $argsStr -NoNewWindow -Wait -PassThru -WorkingDirectory $WorkingDir
        if ($p.ExitCode -ne 0) {
            Write-Host "!! FAIL: '$Name' exited with code $($p.ExitCode)" -ForegroundColor Red
            throw "Final gate failed at: $Name"
        }
    } finally {
        Pop-Location
    }
    Write-Host "  -> OK" -ForegroundColor Green
}

function Assert-NoConcurrentTests {
    # Prevent serial-test flakiness (.ai/rules/tests.md): two concurrent
    # phpunit/php artisan test processes share one test DB.
    $procs = Get-Process -Name 'php' -ErrorAction SilentlyContinue
    if ($procs) {
        Write-Warning "A PHP process is already running (possible concurrent test/build). Aborting to protect the shared test DB."
        throw "Concurrent PHP process detected -- see .ai/rules/tests.md."
    }
}

# Step 1: build assets (spec.md hard constraint 6: verify build after changes)
Invoke-Step "npm run build" @('npm', 'run', 'build')

# Step 2: tests, serial only (rubric blocker: php artisan test must be green)
Assert-NoConcurrentTests
Invoke-Step "php artisan test compact" @('php', 'artisan', 'test', '--compact')

# Step 3: pint formatting
$pintBin = Join-Path (Get-Location) 'vendor/bin/pint'
if (Test-Path $pintBin) {
    Invoke-Step "pint dirty format agent" @('php', '-d', 'memory_limit=512M', 'vendor/bin/pint', '--dirty', '--format', 'agent')
} else {
    Write-Host "=== pint (skipped: vendor/bin/pint not present) ===" -ForegroundColor Yellow
}

# Step 4: anti-slop detector (external personal config path -- optional)
$detectPath = $env:IMPPECCABLE_DETECT
if (-not $detectPath) {
    $detectPath = 'D:\Softwares\aiOpencode\config\skills\impeccable\scripts\detect.mjs'
}

if ($SkipDetect -or -not (Test-Path $detectPath)) {
    if ($SkipDetect) { $msg = '-SkipDetect was specified' } else { $msg = "detect.mjs not found at $detectPath" }
    Write-Host "=== detect.mjs (skipped: $msg) ===" -ForegroundColor Yellow
} else {
    # Capture changed frontend files via git (cmd /c avoids PowerShell 5.1 '--' parsing).
    $staged = & cmd /c 'git diff --name-only --diff-filter=ADM 2^>nul'
    $untracked = & cmd /c 'git ls-files --others --exclude-standard 2^>nul'
    $all = @()
    if ($staged) { $all += $staged }
    if ($untracked) { $all += $untracked }
    $changed = $all | Where-Object { $_ -match '\.(blade\.php|css|js|vue)$' } | Where-Object { $_ }
    if ($changed) {
        $args = @('node', $detectPath, '--json')
        $args += $changed
        Invoke-Step "detect.mjs (anti-slop)" $args
    } else {
        Write-Host "=== detect.mjs (skipped: no changed views/css/js in git) ===" -ForegroundColor Yellow
    }
}

Write-Host "`n=== FINAL GATE PASSED ===" -ForegroundColor Green
exit 0
