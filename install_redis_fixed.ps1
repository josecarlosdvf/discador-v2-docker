# Script para instalar extensao Redis no XAMPP
# Execute como Administrador

Write-Host "=== INSTALACAO EXTENSAO REDIS PARA XAMPP ===" -ForegroundColor Green

# Verificar se esta executando como administrador
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
if (-not $isAdmin) {
    Write-Host "Erro: Execute como Administrador!" -ForegroundColor Red
    Write-Host "Clique com botao direito no PowerShell e 'Executar como Administrador'" -ForegroundColor Yellow
    pause
    exit 1
}

# Detectar versao do PHP
try {
    $phpInfo = php -v 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Erro: PHP nao encontrado! Verifique se o XAMPP esta instalado." -ForegroundColor Red
        pause
        exit 1
    }
    
    Write-Host "PHP detectado:" -ForegroundColor Green
    Write-Host $phpInfo.Split("`n")[0] -ForegroundColor Cyan
} catch {
    Write-Host "Erro ao detectar PHP: $_" -ForegroundColor Red
    pause
    exit 1
}

# Verificar diretorio do XAMPP
$xamppPath = "C:\xampp"
$phpExtPath = "$xamppPath\php\ext"
$phpIniPath = "$xamppPath\php\php.ini"

if (-not (Test-Path $phpExtPath)) {
    Write-Host "Erro: Diretorio XAMPP nao encontrado em $xamppPath" -ForegroundColor Red
    Write-Host "Verifique se o XAMPP esta instalado corretamente." -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "XAMPP encontrado: $xamppPath" -ForegroundColor Green

# URL da extensao Redis para PHP 8.0
$downloadUrl = "https://windows.php.net/downloads/pecl/releases/redis/5.3.7/php_redis-5.3.7-8.0-ts-vs16-x64.zip"
Write-Host "URL de download: $downloadUrl" -ForegroundColor Cyan

# Download da extensao
$tempFile = "$env:TEMP\php_redis.zip"
$extractPath = "$env:TEMP\php_redis"

try {
    Write-Host "Baixando extensao Redis..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri $downloadUrl -OutFile $tempFile -UseBasicParsing
    Write-Host "Download concluido" -ForegroundColor Green
} catch {
    Write-Host "Erro no download: $_" -ForegroundColor Red
    pause
    exit 1
}

# Extrair arquivo
try {
    Write-Host "Extraindo arquivo..." -ForegroundColor Yellow
    if (Test-Path $extractPath) {
        Remove-Item $extractPath -Recurse -Force
    }
    Expand-Archive -Path $tempFile -DestinationPath $extractPath -Force
    Write-Host "Arquivo extraido" -ForegroundColor Green
} catch {
    Write-Host "Erro na extracao: $_" -ForegroundColor Red
    pause
    exit 1
}

# Encontrar e copiar DLL
$dllFiles = Get-ChildItem -Path $extractPath -Name "php_redis.dll" -Recurse
if ($dllFiles.Count -eq 0) {
    Write-Host "Erro: Arquivo php_redis.dll nao encontrado no download" -ForegroundColor Red
    pause
    exit 1
}

$sourcePath = $dllFiles[0].FullName
$destPath = Join-Path $phpExtPath "php_redis.dll"

try {
    Write-Host "Copiando php_redis.dll para $phpExtPath..." -ForegroundColor Yellow
    Copy-Item $sourcePath $destPath -Force
    Write-Host "DLL copiada com sucesso" -ForegroundColor Green
} catch {
    Write-Host "Erro ao copiar DLL: $_" -ForegroundColor Red
    pause
    exit 1
}

# Verificar se extensao ja esta no php.ini
$phpIniContent = Get-Content $phpIniPath -Raw
if ($phpIniContent -match "extension=redis") {
    Write-Host "Extensao Redis ja configurada no php.ini" -ForegroundColor Green
} else {
    try {
        Write-Host "Adicionando extensao ao php.ini..." -ForegroundColor Yellow
        Add-Content $phpIniPath "`nextension=redis"
        Write-Host "Extensao adicionada ao php.ini" -ForegroundColor Green
    } catch {
        Write-Host "Erro ao modificar php.ini: $_" -ForegroundColor Red
        Write-Host "Adicione manualmente: extension=redis" -ForegroundColor Yellow
    }
}

# Limpeza
Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
Remove-Item $extractPath -Recurse -Force -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "INSTALACAO CONCLUIDA!" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANTE: Reinicie o Apache no XAMPP Control Panel" -ForegroundColor Yellow
Write-Host ""
Write-Host "Para verificar se funcionou, execute:" -ForegroundColor Cyan
Write-Host "php -m | findstr redis" -ForegroundColor White
Write-Host ""

pause
