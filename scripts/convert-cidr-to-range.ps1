[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string[]] $Cidr
)

function Convert-NumberToIPv4 {
    param([Parameter(Mandatory = $true)][uint64] $Number)

    return [string]::Join('.', @(
        (($Number -shr 24) -band 255),
        (($Number -shr 16) -band 255),
        (($Number -shr 8) -band 255),
        ($Number -band 255)
    ))
}

$index = 0

$Cidr | ForEach-Object {
    $index++
    $parts = $_.Trim().Split('/')

    if ($parts.Count -ne 2) {
        throw "CIDR inválido: $_"
    }

    $address = [Net.IPAddress]::Parse($parts[0])
    $prefix = [int] $parts[1]

    if ($address.AddressFamily -ne [Net.Sockets.AddressFamily]::InterNetwork) {
        throw "Solo se admiten direcciones IPv4: $_"
    }

    if ($prefix -lt 0 -or $prefix -gt 32) {
        throw "Prefijo inválido: $_"
    }

    $bytes = $address.GetAddressBytes()
    $number = (
        ([uint64] $bytes[0] -shl 24) -bor
        ([uint64] $bytes[1] -shl 16) -bor
        ([uint64] $bytes[2] -shl 8) -bor
        [uint64] $bytes[3]
    )

    $blockSize = [uint64] [Math]::Pow(2, 32 - $prefix)
    $network = $number - ($number % $blockSize)
    $broadcast = $network + $blockSize - 1

    [PSCustomObject]@{
        RuleName = "Render-$index"
        Cidr = $_
        StartIp = Convert-NumberToIPv4 -Number $network
        EndIp = Convert-NumberToIPv4 -Number $broadcast
    }
} | Format-Table -AutoSize
