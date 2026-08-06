# Copy Auto Bridge URL from UMS Admin > Dong bo Sheet and paste it here.
# Example:
# $SyncUrl = 'http://172.30.134.76/UMS/?ums_auto_sync_bridge=1&token=...'
$SyncUrl = 'http://172.30.134.76/UMS/?ums_auto_sync_bridge=1&token=nmdJ1LHDXA0KHEgSkS1kuBAjFA3Rx9dqz3MTlvhQmEkFsw2f'

if ($SyncUrl -eq 'PASTE_UMS_AUTO_BRIDGE_URL_HERE') {
    throw 'Hay copy Auto Bridge URL tu UMS Admin > Dong bo Sheet va dan vao bien $SyncUrl trong file nay.'
}

$ChromeCandidates = @(
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe"
)

$ChromePath = $ChromeCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $ChromePath) {
    throw 'Khong tim thay chrome.exe. Hay cai Google Chrome hoac sua $ChromeCandidates trong file nay.'
}

# Task Scheduler can run this script once per day on an internal PC that can access UMS.
# Use a Chrome profile that is already signed in to company SSO.
Start-Process -FilePath $ChromePath -ArgumentList @(
    '--disable-popup-blocking',
    '--new-window',
    $SyncUrl
)
