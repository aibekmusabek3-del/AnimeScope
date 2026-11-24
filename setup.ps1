param([switch]$NoServe)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
function Run-Checked([string]$Label, [scriptblock]$Command) {
    Write-Host "`n=== $Label ===" -ForegroundColor Cyan
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "$Label failed (exit $LASTEXITCODE). Read the error above." }
}
foreach ($tool in @('php','composer','node','npm.cmd')) {
    if (!(Get-Command $tool -ErrorAction SilentlyContinue)) { throw "$tool not found. Install PHP 8.2+, Composer and Node.js 20.19+ (22 LTS recommended)." }
}
Run-Checked 'PHP version' { php -v }
Run-Checked 'Node version' { node -v }
Run-Checked 'Required PHP extensions' { php scripts/check-php.php }
foreach ($dir in @('bootstrap/cache','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs')) { New-Item -ItemType Directory -Force $dir | Out-Null }
if (!(Test-Path .env)) { Copy-Item .env.example .env }
Run-Checked 'Composer dependencies' { composer install --no-interaction --prefer-dist }
if (!(Select-String -Path .env -Pattern '^APP_KEY=.+')) { Run-Checked 'Application key' { php artisan key:generate --force } }
Run-Checked 'Clear Laravel configuration cache' { php artisan config:clear }
if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite | Out-Null }
Run-Checked 'Create social database' { php artisan migrate --force }
if (Test-Path 'package-lock.json') { Run-Checked 'React dependencies' { npm.cmd ci --no-audit --no-fund } }
else { Run-Checked 'React dependencies' { npm.cmd install --no-audit --no-fund } }
Run-Checked 'Build React' { npm.cmd run build }
Run-Checked 'Frontend unit tests' { npm.cmd test }
Run-Checked 'Laravel tests' { php vendor/bin/phpunit }
Write-Host "`n=== Live API check ===" -ForegroundColor Cyan
php artisan anime:check
if ($LASTEXITCODE -ne 0) { Write-Warning 'Live API unavailable. Previously cached responses remain available. See README for diagnosis.' }
if (!$NoServe) { Write-Host 'Open http://127.0.0.1:8000' -ForegroundColor Green; php artisan serve --host=127.0.0.1 --port=8000 }
