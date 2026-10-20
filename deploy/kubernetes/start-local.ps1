param(
    [string] $ProductionPath = (Join-Path $PSScriptRoot '..\..\..\production'),
    [string] $MarketingPath = (Join-Path $PSScriptRoot '..\..\..\marketing')
)

$ErrorActionPreference = 'Stop'

function Require-Command([string] $Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "'$Name' is required. Install Docker Desktop with Kubernetes enabled."
    }
}

function New-AppKey {
    $bytes = New-Object byte[] 32
    $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $random.GetBytes($bytes)
    } finally {
        $random.Dispose()
    }

    return 'base64:' + [Convert]::ToBase64String($bytes)
}

function Ensure-Secret([string] $Name, [hashtable] $Values) {
    & kubectl -n imprint-test get secret $Name *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Keeping existing secret $Name."
        return
    }

    $arguments = @('-n', 'imprint-test', 'create', 'secret', 'generic', $Name)
    foreach ($entry in $Values.GetEnumerator()) {
        $arguments += "--from-literal=$($entry.Key)=$($entry.Value)"
    }
    $arguments += @('--dry-run=client', '-o', 'yaml')
    & kubectl @arguments | & kubectl apply -f -
}

function Start-PortForward([string] $Service, [int] $LocalPort) {
    if (Get-NetTCPConnection -LocalPort $LocalPort -State Listen -ErrorAction SilentlyContinue) {
        Write-Host "Port $LocalPort is already in use; leaving it alone."
        return
    }

    $stdout = Join-Path $env:TEMP "imprint-$Service-port-forward.out.log"
    $stderr = Join-Path $env:TEMP "imprint-$Service-port-forward.err.log"
    Start-Process -WindowStyle Hidden -FilePath 'kubectl' -ArgumentList @(
        'port-forward', '-n', 'imprint-test', "service/$Service", "${LocalPort}:10000"
    ) -RedirectStandardOutput $stdout -RedirectStandardError $stderr | Out-Null
}

Require-Command 'docker'
Require-Command 'kubectl'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$productionPath = (Resolve-Path $ProductionPath).Path
$marketingPath = (Resolve-Path $MarketingPath).Path

if (-not (Test-Path (Join-Path $productionPath 'composer.json'))) {
    throw "Production repository was not found at $productionPath. Clone https://github.com/ianflecher/production beside hris or pass -ProductionPath."
}
if (-not (Test-Path (Join-Path $marketingPath 'composer.json'))) {
    throw "Marketing repository was not found at $marketingPath. Clone https://github.com/ianflecher/marketing beside hris or pass -MarketingPath."
}

Write-Host 'Building HRIS...'
& docker build -t imprint/hris:central-admin-v1 $repoRoot
Write-Host 'Building Central Admin...'
& docker build -t imprint/central-admin:test (Join-Path $repoRoot 'central-admin')
Write-Host 'Building Production...'
& docker build -f (Join-Path $PSScriptRoot 'production.Dockerfile') -t imprint/production:test $productionPath
Write-Host 'Building Marketing...'
& docker build -f (Join-Path $PSScriptRoot 'marketing.Dockerfile') -t imprint/marketing:test $marketingPath

& kubectl get namespace imprint-test *> $null
if ($LASTEXITCODE -ne 0) {
    & kubectl create namespace imprint-test
}

Ensure-Secret 'mysql-credentials' @{
    password = 'imprint-local-db'
    'root-password' = 'imprint-local-root'
}
Ensure-Secret 'app-keys' @{
    hris = New-AppKey
    production = New-AppKey
    marketing = New-AppKey
}
Ensure-Secret 'central-admin-credentials' @{
    username = 'admin'
    password = 'imprint123'
}

Write-Host 'Applying Kubernetes resources...'
& kubectl apply -f (Join-Path $PSScriptRoot 'local-test.yaml')
& kubectl rollout status deployment/mysql -n imprint-test --timeout=180s
& kubectl rollout status deployment/hris -n imprint-test --timeout=180s
Write-Host 'Seeding the default local administrator accounts...'
& kubectl exec deployment/hris -n imprint-test -- php artisan db:seed --force
& kubectl rollout status deployment/production -n imprint-test --timeout=180s
& kubectl rollout status deployment/marketing -n imprint-test --timeout=180s
& kubectl rollout status deployment/central-admin -n imprint-test --timeout=180s

Start-PortForward 'hris' 30080
Start-PortForward 'production' 30081
Start-PortForward 'marketing' 30082
Start-PortForward 'central-admin' 30083

Write-Host ''
Write-Host 'Local stack is ready:'
Write-Host '  Central Admin:       http://127.0.0.1:30083'
Write-Host '  Production:          http://127.0.0.1:30081'
Write-Host '  Marketing:           http://127.0.0.1:30082'
Write-Host ''
Write-Host '  Central Admin login: admin / imprint123'
Write-Host ''
Write-Host 'The scanner is intentionally not included in this local stack.'
