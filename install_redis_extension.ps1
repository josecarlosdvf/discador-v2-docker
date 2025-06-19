# Script para instalar extensão Redis no XAMPP
# Execute como Administrador

Write-Host "=== INSTALAÇÃO EXTENSÃO REDIS PARA XAMPP ===" -ForegroundColor Green

# Verificar se está executando como administrador
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")
if (-not $isAdmin) {
    Write-Host "❌ Execute como Administrador!" -ForegroundColor Red
    Write-Host "Clique com botão direito no PowerShell e 'Executar como Administrador'" -ForegroundColor Yellow
    pause
    exit 1
}

# Detectar versão do PHP
try {
    $phpInfo = php -v 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ PHP não encontrado! Verifique se o XAMPP está instalado." -ForegroundColor Red
        pause
        exit 1
    }
    
    Write-Host "✅ PHP detectado:" -ForegroundColor Green
    Write-Host $phpInfo.Split("`n")[0] -ForegroundColor Cyan
} catch {
    Write-Host "❌ Erro ao detectar PHP: $_" -ForegroundColor Red
    pause
    exit 1
}

# Verificar diretório do XAMPP
$xamppPath = "C:\xampp"
$phpExtPath = "$xamppPath\php\ext"
$phpIniPath = "$xamppPath\php\php.ini"

if (-not (Test-Path $phpExtPath)) {
    Write-Host "❌ Diretório XAMPP não encontrado em $xamppPath" -ForegroundColor Red
    Write-Host "Verifique se o XAMPP está instalado corretamente." -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "✅ XAMPP encontrado: $xamppPath" -ForegroundColor Green

# URLs das extensões Redis por versão
$redisExtensions = @{
    "8.0" = "https://windows.php.net/downloads/pecl/releases/redis/5.3.7/php_redis-5.3.7-8.0-ts-vs16-x64.zip"
    "8.1" = "https://windows.php.net/downloads/pecl/releases/redis/5.3.7/php_redis-5.3.7-8.1-ts-vs16-x64.zip"
    "8.2" = "https://windows.php.net/downloads/pecl/releases/redis/5.3.7/php_redis-5.3.7-8.2-ts-vs16-x64.zip"
}

# Detectar versão do PHP
$phpVersionMatch = $phpInfo -match "PHP (\d+\.\d+)"
if (-not $phpVersionMatch) {
    Write-Host "❌ Não foi possível detectar a versão do PHP" -ForegroundColor Red
    pause
    exit 1
}

$phpVersion = $matches[1]
Write-Host "📋 Versão do PHP detectada: $phpVersion" -ForegroundColor Cyan

if (-not $redisExtensions.ContainsKey($phpVersion)) {
    Write-Host "❌ Versão do PHP $phpVersion não suportada por este script" -ForegroundColor Red
    Write-Host "Versões suportadas: $($redisExtensions.Keys -join ', ')" -ForegroundColor Yellow
    pause
    exit 1
}

$downloadUrl = $redisExtensions[$phpVersion]
Write-Host "📦 URL de download: $downloadUrl" -ForegroundColor Cyan

# Download da extensão
$tempFile = "$env:TEMP\php_redis.zip"
$extractPath = "$env:TEMP\php_redis"

try {
    Write-Host "⬇️ Baixando extensão Redis..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri $downloadUrl -OutFile $tempFile -UseBasicParsing
    Write-Host "✅ Download concluído" -ForegroundColor Green
} catch {
    Write-Host "❌ Erro no download: $_" -ForegroundColor Red
    pause
    exit 1
}

# Extrair arquivo
try {
    Write-Host "📂 Extraindo arquivo..." -ForegroundColor Yellow
    if (Test-Path $extractPath) {
        Remove-Item $extractPath -Recurse -Force
    }
    Expand-Archive -Path $tempFile -DestinationPath $extractPath -Force
    Write-Host "✅ Arquivo extraído" -ForegroundColor Green
} catch {
    Write-Host "❌ Erro na extração: $_" -ForegroundColor Red
    pause
    exit 1
}

# Copiar DLL
$sourceDll = Get-ChildItem -Path $extractPath -Name "php_redis.dll" -Recurse | Select-Object -First 1
if (-not $sourceDll) {
    Write-Host "❌ Arquivo php_redis.dll não encontrado no download" -ForegroundColor Red
    pause
    exit 1
}

$sourcePath = Join-Path $extractPath $sourceDll.FullName
$destPath = Join-Path $phpExtPath "php_redis.dll"

try {
    Write-Host "📋 Copiando php_redis.dll para $phpExtPath..." -ForegroundColor Yellow
    Copy-Item $sourcePath $destPath -Force
    Write-Host "✅ DLL copiada com sucesso" -ForegroundColor Green
} catch {
    Write-Host "❌ Erro ao copiar DLL: $_" -ForegroundColor Red
    pause
    exit 1
}

# Verificar se extensão já está no php.ini
$phpIniContent = Get-Content $phpIniPath -Raw
if ($phpIniContent -match "extension=redis") {
    Write-Host "✅ Extensão Redis já configurada no php.ini" -ForegroundColor Green
} else {
    try {
        Write-Host "📝 Adicionando extensão ao php.ini..." -ForegroundColor Yellow
        Add-Content $phpIniPath "`nextension=redis"
        Write-Host "✅ Extensão adicionada ao php.ini" -ForegroundColor Green
    } catch {
        Write-Host "❌ Erro ao modificar php.ini: $_" -ForegroundColor Red
        Write-Host "Adicione manualmente: extension=redis" -ForegroundColor Yellow
    }
}

# Limpeza
Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
Remove-Item $extractPath -Recurse -Force -ErrorAction SilentlyContinue

Write-Host "" -ForegroundColor White
Write-Host "🎉 INSTALAÇÃO CONCLUÍDA!" -ForegroundColor Green
Write-Host "" -ForegroundColor White
Write-Host "⚠️  IMPORTANTE: Reinicie o Apache no XAMPP Control Panel" -ForegroundColor Yellow
Write-Host "" -ForegroundColor White
Write-Host "Para verificar se funcionou, execute:" -ForegroundColor Cyan
Write-Host "php -m | findstr redis" -ForegroundColor White
Write-Host "" -ForegroundColor White

pause
