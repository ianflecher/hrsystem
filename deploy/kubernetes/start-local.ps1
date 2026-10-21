param(
    [string] $ProductionPath = (Join-Path $PSScriptRoot '..\..\..\production\application'),
    [string] $MarketingPath = (Join-Path $PSScriptRoot '..\..\..\marketing\temp_app'),
    [string] $CentralAdminPath = (Join-Path $PSScriptRoot '..\..\..\imprint-admin'),
    [switch] $SeedDefaultAccounts,
    [switch] $SeedDemoData
)

$ErrorActionPreference = 'Stop'

function Require-Command([string] $Name) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "'$Name' is required. Install Docker Desktop with Kubernetes enabled."
    }
}

function Ensure-LocalRegistry {
    $registryName = 'imprint-registry'
    $existing = & docker ps -a --filter "name=^/$registryName$" --format '{{.Names}}'

    if (-not $existing) {
        Write-Host 'Creating the local image registry...'
        & docker run -d --name $registryName -p 5000:5000 --restart unless-stopped registry:2
        if ($LASTEXITCODE -ne 0) {
            throw 'The local Docker registry could not be created.'
        }
    } else {
        $running = & docker ps --filter "name=^/$registryName$" --format '{{.Names}}'
        if (-not $running) {
            Write-Host 'Starting the existing local image registry...'
            & docker start $registryName
        }
    }
}

function Publish-LocalImage([string] $LocalTag, [string] $RegistryTag) {
    & docker tag $LocalTag $RegistryTag
    if ($LASTEXITCODE -ne 0) {
        throw "Could not tag $LocalTag for the local registry."
    }

    & docker push $RegistryTag
    if ($LASTEXITCODE -ne 0) {
        throw "Could not publish $RegistryTag to the local registry."
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

    $loopScript = Join-Path $PSScriptRoot 'port-forward-loop.ps1'
    $running = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -like "*port-forward-loop.ps1*" -and $_.CommandLine -like "*-Service $Service*" }
    if ($running) {
        Write-Host "Port-forward supervisor for $Service is already running."
        return
    }

    $stdout = Join-Path $env:TEMP "imprint-$Service-port-forward.out.log"
    $stderr = Join-Path $env:TEMP "imprint-$Service-port-forward.err.log"
    Start-Process -WindowStyle Hidden -FilePath 'powershell.exe' -ArgumentList @(
        '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $loopScript,
        '-Service', $Service, '-LocalPort', $LocalPort
    ) -RedirectStandardOutput $stdout -RedirectStandardError $stderr | Out-Null
}

Require-Command 'docker'
Require-Command 'kubectl'

Ensure-LocalRegistry

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$productionPath = (Resolve-Path $ProductionPath).Path
$marketingPath = (Resolve-Path $MarketingPath).Path
$centralAdminPath = (Resolve-Path $CentralAdminPath).Path

if (-not (Test-Path (Join-Path $productionPath 'composer.json'))) {
    throw "Production repository was not found at $productionPath. Clone https://github.com/ianflecher/production beside hris or pass -ProductionPath."
}
if (-not (Test-Path (Join-Path $marketingPath 'composer.json'))) {
    throw "Marketing repository was not found at $marketingPath. Clone https://github.com/ianflecher/marketing beside hris or pass -MarketingPath."
}
if (-not (Test-Path (Join-Path $centralAdminPath 'package.json'))) {
    throw "Central Admin repository was not found at $centralAdminPath. Clone https://github.com/imprintcustomsecommerce-cell/imprint-admin beside hris or pass -CentralAdminPath."
}

Write-Host 'Building HRIS...'
& docker build -t imprint/hris:central-admin-v1 $repoRoot
Write-Host 'Building Central Admin...'
& docker build -t imprint/central-admin:central-admin-v2 $centralAdminPath
Write-Host 'Building Production...'
& docker build -f (Join-Path $PSScriptRoot 'production.Dockerfile') -t imprint/production:central-admin-v2 $productionPath
Write-Host 'Building Marketing...'
& docker build -f (Join-Path $PSScriptRoot 'marketing.Dockerfile') -t imprint/marketing:central-admin-v2 $marketingPath

Write-Host 'Publishing images to the local Kubernetes registry...'
Publish-LocalImage 'imprint/hris:central-admin-v1' 'localhost:5000/imprint/hris:central-admin-v1'
Publish-LocalImage 'imprint/production:central-admin-v2' 'localhost:5000/imprint/production:central-admin-v2'
Publish-LocalImage 'imprint/marketing:central-admin-v2' 'localhost:5000/imprint/marketing:central-admin-v2'
Publish-LocalImage 'imprint/central-admin:central-admin-v2' 'localhost:5000/imprint/central-admin:central-admin-v2'

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
Ensure-Secret 'central-admin-internal' @{
    token = 'imprint-central-admin-local-token'
}

Write-Host 'Applying Kubernetes resources...'
& kubectl apply -f (Join-Path $PSScriptRoot 'local-test.yaml')
& kubectl rollout status deployment/mysql -n imprint-test --timeout=180s
& kubectl rollout status deployment/hris -n imprint-test --timeout=1800s
if ($SeedDefaultAccounts) {
    Write-Host 'Seeding the default local administrator accounts by request...'
    & kubectl exec deployment/hris -n imprint-test -- php artisan db:seed --force
}
& kubectl rollout status deployment/production -n imprint-test --timeout=180s
& kubectl rollout status deployment/marketing -n imprint-test --timeout=180s
if ($SeedDemoData) {
    Write-Host 'Loading disposable demo data into Production and Marketing...'
    & kubectl exec deployment/production -n imprint-test -- php artisan db:seed --class=DemoDataSeeder --force --no-interaction
    & kubectl exec deployment/marketing -n imprint-test -- php artisan db:seed --class=DemoSeeder --force --no-interaction
}
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
