# Creates the "College of Law Library" shortcut on the Desktop (with the College of Law logo).
$here    = Split-Path -Parent $MyInvocation.MyCommand.Path
$target  = Join-Path $here 'start_library.bat'
$icon    = Join-Path $here 'assets\images\col_logo.ico'
$desktop = [Environment]::GetFolderPath('Desktop')
$link    = Join-Path $desktop 'College of Law Library.lnk'
$sh = New-Object -ComObject WScript.Shell
$sc = $sh.CreateShortcut($link)
$sc.TargetPath       = $target
$sc.WorkingDirectory = $here
$sc.IconLocation     = $icon
$sc.WindowStyle      = 7          # minimized, so the black window stays out of the way
$sc.Description      = 'College of Law Library Management System'
$sc.Save()
Write-Host ''
Write-Host "  Done. The shortcut was placed on the Desktop:"
Write-Host "  $link"
