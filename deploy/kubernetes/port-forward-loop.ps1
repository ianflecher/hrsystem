param(
    [Parameter(Mandatory = $true)]
    [string] $Service,
    [Parameter(Mandatory = $true)]
    [int] $LocalPort,
    [int] $RemotePort = 10000
)

$ErrorActionPreference = 'Continue'

function Resolve-Kubectl {
    $command = Get-Command kubectl -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $candidates = @(
        (Join-Path $env:ProgramFiles 'Docker\Docker\resources\bin\kubectl.exe'),
        (Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin\kubectl.exe'),
        (Join-Path $env:ProgramData 'DockerDesktop\version-bin\kubectl.exe')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    throw 'kubectl was not found. Install Docker Desktop or add kubectl to PATH.'
}

$kubectl = Resolve-Kubectl

while ($true) {
    & $kubectl port-forward `
        --namespace imprint-test `
        "service/$Service" `
        "$($LocalPort):$($RemotePort)" `
        --address 127.0.0.1

    Start-Sleep -Seconds 2
}
