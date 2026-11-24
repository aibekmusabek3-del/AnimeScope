param([string]$BindIP,[int]$Port=8000)
$ErrorActionPreference='Stop'
Set-Location $PSScriptRoot
if (!$BindIP) { $BindIP=Read-Host 'Enter your private VPN IPv4 address (or LAN IPv4)' }
$parsed=$null
if (![System.Net.IPAddress]::TryParse($BindIP,[ref]$parsed) -or $parsed.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork) { throw 'Enter a valid IPv4 address.' }
$b=$parsed.GetAddressBytes()
$private=($b[0] -eq 10) -or ($b[0] -eq 172 -and $b[1] -ge 16 -and $b[1] -le 31) -or ($b[0] -eq 192 -and $b[1] -eq 168) -or ($b[0] -eq 100 -and $b[1] -ge 64 -and $b[1] -le 127)
if (!$private) { throw 'Only a private LAN or VPN address is allowed.' }
if (!(Get-NetIPAddress -AddressFamily IPv4 -IPAddress $BindIP -ErrorAction SilentlyContinue)) { throw 'Address does not belong to this computer.' }
if (!(Test-Path vendor/autoload.php) -or !(Test-Path public/build/manifest.json) -or !(Test-Path database/database.sqlite)) { throw 'Run setup.ps1 or update.ps1 first.' }
Write-Host "Both players open http://${BindIP}:${Port}/friends" -ForegroundColor Green
Write-Host 'Use a trusted private VPN. Do not expose the PHP development server to the public Internet.'
php artisan serve --host=$BindIP --port=$Port
