$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
$php = (Get-Command php -ErrorAction Stop).Source
$securePassword = Read-Host 'Contraseña de TiDB (no se mostrará)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $env:RSU_TIDB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    & $php (Join-Path $PSScriptRoot 'migrate-tidb.php')
    if ($LASTEXITCODE -ne 0) { throw 'Migración incompleta. Revisa el mensaje anterior; no vuelvas a ejecutarla sin revisar el destino.' }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    Remove-Item Env:RSU_TIDB_PASSWORD -ErrorAction SilentlyContinue
}
