param([switch]$RepairCertificate)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
Write-Host '=== Windows HTTPS check ===' -ForegroundColor Cyan
curl.exe -sS --max-time 15 -o NUL -w 'HTTP %{http_code}\n' 'https://shikimori.one/api/animes/21'
Write-Host '=== PHP configuration ===' -ForegroundColor Cyan
php --ini
php artisan anime:check
$code = $LASTEXITCODE
if ($RepairCertificate) {
    if (!(Test-Path .env)) { throw 'Run setup.ps1 first.' }
    Write-Host 'Downloading trusted CA bundle from curl.se (HTTPS)...' -ForegroundColor Cyan
    New-Item -ItemType Directory -Force 'storage/certs' | Out-Null
    curl.exe -fL --max-time 40 'https://curl.se/ca/cacert.pem' -o 'storage/certs/cacert.pem'
    if ($LASTEXITCODE -ne 0) { throw 'CA bundle download failed. TLS verification has not been disabled.' }
    $ca = (Resolve-Path 'storage/certs/cacert.pem').Path.Replace('\','/')
    $envText = [IO.File]::ReadAllText((Resolve-Path '.env').Path)
    $newLine = 'SHIKIMORI_CA_BUNDLE="' + $ca + '"'
    if ($envText -match '(?m)^SHIKIMORI_CA_BUNDLE=.*$') { $envText = [regex]::Replace($envText,'(?m)^SHIKIMORI_CA_BUNDLE=.*$',[System.Text.RegularExpressions.MatchEvaluator]{param($m) $newLine}) }
    else { $envText += "`r`n$newLine`r`n" }
    [IO.File]::WriteAllText((Resolve-Path '.env').Path,$envText,(New-Object System.Text.UTF8Encoding($false)))
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Config clear failed.' }
    php artisan anime:check
    $code = $LASTEXITCODE
}
exit $code
