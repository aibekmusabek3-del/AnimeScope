param([string]$HostUrl)
if (!$HostUrl) { $HostUrl=Read-Host 'Enter the AnimeScope address sent by your friend (http://VPN-IP:8000)' }
$uri=$null
if (![Uri]::TryCreate($HostUrl,[UriKind]::Absolute,[ref]$uri) -or $uri.Scheme -notin @('http','https') -or $uri.UserInfo) { throw 'Invalid server URL.' }
Start-Process ($uri.GetLeftPart([UriPartial]::Authority)+'/friends')
