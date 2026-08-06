# Copy Auto Bridge URL from UMS Admin > Dong bo Sheet and paste it here.
# Example:
# $SyncUrl = 'http://172.30.134.76/UMS/?ums_auto_sync_bridge=1&token=...'
$SyncUrl = 'http://172.30.134.76/UMS/?ums_auto_sync_bridge=1&token=nmdJ1LHDXA0KHEgSkS1kuBAjFA3Rx9dqz3MTlvhQmEkFsw2f'

if ($SyncUrl -eq 'PASTE_UMS_AUTO_BRIDGE_URL_HERE') {
    throw 'Hay copy Auto Bridge URL tu UMS Admin > Dong bo Sheet va dan vao bien $SyncUrl trong file nay.'
}

$ChromeUserDataDir = Join-Path $env:LOCALAPPDATA 'Google\Chrome\User Data'

# Open chrome://version in the Chrome profile that has company SSO, then copy
# the last path segment from "Profile Path" here. Common values: Default, Profile 1, Profile 2.
$ChromeProfileDirectory = 'Default'

$ChromeCandidates = @(
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
)

$ChromePath = $ChromeCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $ChromePath) {
    throw 'Khong tim thay chrome.exe. Hay cai Google Chrome hoac sua $ChromeCandidates trong file nay.'
}

if (-not (Test-Path -LiteralPath (Join-Path $ChromeUserDataDir $ChromeProfileDirectory))) {
    throw "Khong tim thay Chrome profile: $ChromeProfileDirectory trong $ChromeUserDataDir"
}

# Task Scheduler can run this script once per day on an internal PC that can access UMS.
# Use a Chrome profile that is already signed in to company SSO.
Start-Process -FilePath $ChromePath -ArgumentList @(
    '--disable-popup-blocking',
    "--user-data-dir=$ChromeUserDataDir",
    "--profile-directory=$ChromeProfileDirectory",
    '--new-window',
    $SyncUrl
)
