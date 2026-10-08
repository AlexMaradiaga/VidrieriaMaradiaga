[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $Server,

    [Parameter(Mandatory = $true)]
    [string] $Username,

    [string] $Database = 'DB_VidrieriaMaradiaga'
)

$ErrorActionPreference = 'Stop'

function Invoke-Artisan {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments)

    & php artisan @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "Falló: php artisan $($Arguments -join ' ')"
    }
}

$securePassword = Read-Host 'Contraseña del administrador de Azure SQL' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
$sqlPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)

$variables = @{
    DB_CONNECTION = 'sqlsrv'
    DB_HOST = $Server
    DB_PORT = '1433'
    DB_DATABASE = $Database
    DB_USERNAME = $Username
    DB_PASSWORD = $sqlPassword
    DB_ENCRYPT = 'yes'
    DB_TRUST_SERVER_CERTIFICATE = 'no'
}

$previousValues = @{}

try {
    foreach ($name in $variables.Keys) {
        $previousValues[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $variables[$name], 'Process')
    }

    Write-Host ''
    Write-Host '1/6 Limpiando configuración local...' -ForegroundColor Cyan
    Invoke-Artisan optimize:clear

    Write-Host '2/6 Creando la estructura de Azure SQL...' -ForegroundColor Cyan
    Invoke-Artisan migrate --force

    Write-Host '3/6 Instalando catálogos, roles y cuentas contables...' -ForegroundColor Cyan
    Invoke-Artisan db:seed --force

    Write-Host '4/6 Importando los 301 productos del cliente...' -ForegroundColor Cyan
    Invoke-Artisan db:seed --class=ClientProductCatalogSeeder --force

    Write-Host '5/6 Creando el usuario inicial...' -ForegroundColor Cyan
    Invoke-Artisan access:create-user

    Write-Host '6/6 Asignando el rol Administrador...' -ForegroundColor Cyan
    Write-Host 'Selecciona Administrador cuando aparezca la lista de roles.' -ForegroundColor Yellow
    Invoke-Artisan access:assign-role

    Write-Host ''
    Write-Host 'Azure SQL quedó inicializado correctamente.' -ForegroundColor Green
    Write-Host 'Ejecuta database/sql/verify_azure_deployment.sql en SSMS para comprobarlo.'
}
finally {
    foreach ($name in $variables.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousValues[$name], 'Process')
    }

    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }

    $sqlPassword = $null
    $securePassword = $null
}
