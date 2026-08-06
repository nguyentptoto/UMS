$SyncUrl = 'http://172.30.134.76/UMS/wp-admin/admin.php?page=tvn-ums-sheet-sync&ums_auto_sync=1'

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
# Use a Chrome profile that is already signed in to both WordPress Admin and company SSO.
Start-Process -FilePath $ChromePath -ArgumentList @(
    '--disable-popup-blocking',
    '--new-window',
    $SyncUrl
)
