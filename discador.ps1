# Script PowerShell para Sistema Discador v2.0
# Uso: .\discador.ps1 [-Stop] [-Restart] [-Logs] [-Status] [-Clean] [-TestPersistence] [-CreateEnv] [-Service nome]

param(
    [switch]$Stop,
    [switch]$Restart,
    [switch]$Logs,
    [switch]$Status,
    [switch]$Clean,
    [switch]$TestPersistence,
    [switch]$CreateEnv,
    [string]$Service = ""
)

function Write-Info { param([string]$Message) Write-Host $Message -ForegroundColor Cyan }
function Write-Success { param([string]$Message) Write-Host $Message -ForegroundColor Green }
function Write-Warning { param([string]$Message) Write-Host $Message -ForegroundColor Yellow }
function Write-Error { param([string]$Message) Write-Host $Message -ForegroundColor Red }

function Test-DockerRunning {
    try {
        $result = docker info *>&1
        return $LASTEXITCODE -eq 0
    } catch {
        return $false
    }
}

function Show-Header {
    Write-Info "======================================"
    Write-Info "      Sistema Discador v2.0"
    Write-Info "======================================"
    Write-Host ""
}

function Show-Status {
    Write-Info "Status dos Containers:"
    Write-Host "====================="
    docker-compose ps
    Write-Host ""
    
    Write-Info "Verificando servicos:"
    Write-Host "===================="
      # Testar HTTP (porta 8080)
    Write-Host "   Web Interface: " -NoNewline
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8080" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
        Write-Success "OK"
    } catch {
        Write-Warning "OFFLINE"
    }
    
    # Testar MariaDB (porta 3307)
    Write-Host "   MariaDB:        " -NoNewline
    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $connect = $tcpClient.BeginConnect("localhost", 3307, $null, $null)
        $wait = $connect.AsyncWaitHandle.WaitOne(3000, $false)
        if ($wait -and $tcpClient.Connected) {
            Write-Success "OK"
        } else {
            Write-Warning "OFFLINE"
        }
        $tcpClient.Close()
    } catch {
        Write-Warning "OFFLINE"
    }
    
    # Testar Redis (porta 6380)
    Write-Host "   Redis:          " -NoNewline
    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $connect = $tcpClient.BeginConnect("localhost", 6380, $null, $null)
        $wait = $connect.AsyncWaitHandle.WaitOne(3000, $false)
        if ($wait -and $tcpClient.Connected) {
            Write-Success "OK"
        } else {
            Write-Warning "OFFLINE"
        }
        $tcpClient.Close()
    } catch {
        Write-Warning "OFFLINE"
    }
    
    # Testar Asterisk AMI (porta 5038)
    Write-Host "   Asterisk:       " -NoNewline
    try {
        $tcpClient = New-Object System.Net.Sockets.TcpClient
        $connect = $tcpClient.BeginConnect("localhost", 5038, $null, $null)
        $wait = $connect.AsyncWaitHandle.WaitOne(3000, $false)
        if ($wait -and $tcpClient.Connected) {
            Write-Success "OK"
        } else {
            Write-Warning "OFFLINE"
        }
        $tcpClient.Close()
    } catch {
        Write-Warning "OFFLINE"
    }
}

function Start-Services {
    Show-Header
    
    Write-Info "Verificando Docker..."
    if (-not (Test-DockerRunning)) {
        Write-Error "ERRO: Docker nao esta rodando!"
        Write-Warning "Inicie o Docker Desktop e tente novamente."
        exit 1
    }
    Write-Success "Docker esta rodando"
    
    if (-not (Test-Path ".env")) {
        Write-Warning "Arquivo .env nao encontrado!"
        Write-Info "Criando arquivo .env com configuracoes padrao..."
        Create-EnvFile
    } else {
        Write-Success "Arquivo .env encontrado"
    }
    
    Write-Info "Construindo e iniciando servicos..."
    docker-compose up -d --build
    
    if ($LASTEXITCODE -eq 0) {
        Write-Success "Servicos iniciados com sucesso!"
        Write-Host ""
        Write-Info "Aguardando servicos ficarem prontos..."
        Start-Sleep -Seconds 15
        
        Show-Status
        
        Write-Host ""
        Write-Success "Sistema Discador v2.0 iniciado!"
        Write-Host ""Write-Info "Acessos:"
        Write-Host "   Web Interface: http://localhost:8080"
        Write-Host "   HTTPS:         https://localhost:8443"
        Write-Host "   Portainer:     http://localhost:9000"
        Write-Host "   MariaDB:       localhost:3307"
        Write-Host "   Redis:         localhost:6380"
        Write-Host "   Asterisk AMI:  localhost:5038"
        Write-Host "   WebRTC HTTP:   localhost:8188"
        Write-Host "   WebRTC HTTPS:  localhost:8189"
    } else {
        Write-Error "ERRO ao iniciar servicos!"
        exit 1
    }
}

function Stop-Services {
    Show-Header
    
    Write-Info "Verificando Docker..."
    if (-not (Test-DockerRunning)) {
        Write-Error "ERRO: Docker nao esta rodando!"
        Write-Warning "Inicie o Docker Desktop e tente novamente."
        exit 1
    }
    Write-Success "Docker esta rodando"
    
    Write-Info "Parando servicos..."
    
    if ($Clean) {
        Write-Warning "Removendo volumes e imagens..."
        docker-compose down -v --rmi all
        docker volume prune -f
        docker image prune -f
    } else {
        docker-compose down
    }
    
    Write-Success "Servicos parados"
}

function Show-Logs {
    Show-Header
    
    Write-Info "Verificando Docker..."
    if (-not (Test-DockerRunning)) {
        Write-Error "ERRO: Docker nao esta rodando!"
        Write-Warning "Inicie o Docker Desktop e tente novamente."
        exit 1
    }
    Write-Success "Docker esta rodando"
    
    Write-Host ""
    if ($Service) {
        Write-Info "Logs do servico: $Service"
        docker-compose logs -f --tail=50 $Service
    } else {
        Write-Info "Logs de todos os servicos:"
        docker-compose logs -f --tail=50
    }
}

function Restart-Services {
    Show-Header
    
    Write-Info "Verificando Docker..."
    if (-not (Test-DockerRunning)) {
        Write-Error "ERRO: Docker nao esta rodando!"
        Write-Warning "Inicie o Docker Desktop e tente novamente."
        exit 1
    }
    Write-Success "Docker esta rodando"
    
    if ($Service) {
        Write-Info "Reiniciando servico: $Service"
        docker-compose restart $Service
    } else {
        Write-Info "Reiniciando todos os servicos..."
        docker-compose restart
    }
    Write-Success "Reinicializacao concluida"
}

function Test-DataPersistence {
    Show-Header
    
    Write-Info "Testando Persistencia de Dados..."
    Write-Host "================================="
    Write-Host ""
    
    # Verificar volumes do Docker
    Write-Info "Verificando volumes Docker:"
    $volumes = @(
        "discador_v2_mariadb_data",
        "discador_v2_redis_data",
        "discador_v2_asterisk_sounds",
        "discador_v2_asterisk_spool",
        "discador_v2_asterisk_recordings",
        "discador_v2_asterisk_lib",
        "discador_v2_nginx_ssl",
        "discador_v2_portainer_data"
    )
    
    foreach ($volume in $volumes) {
        Write-Host "   $volume : " -NoNewline
        $result = docker volume inspect $volume 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Success "OK"
        } else {
            Write-Warning "NAO EXISTE"
        }
    }    Write-Host ""
    Write-Info "Testando dados MariaDB:"
    try {
        $result = docker exec discador_mariadb mysql -uroot -p"discador_root_secure_2025" -e "SHOW DATABASES;" 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Success "MariaDB conectado e respondendo"
            Write-Host "Databases:"
            $result | ForEach-Object { Write-Host "   $_" }
            
            # Teste de persistência com dados de exemplo
            docker exec discador_mariadb mysql -uroot -p"discador_root_secure_2025" -e "USE discador; CREATE TABLE IF NOT EXISTS test_persistence (id INT AUTO_INCREMENT PRIMARY KEY, data VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);" 2>$null
            docker exec discador_mariadb mysql -uroot -p"discador_root_secure_2025" -e "USE discador; INSERT INTO test_persistence (data) VALUES ('dados_persistidos_$(Get-Date -Format 'yyyyMMdd_HHmmss')');" 2>$null
            $testData = docker exec discador_mariadb mysql -uroot -p"discador_root_secure_2025" -e "USE discador; SELECT data FROM test_persistence ORDER BY id DESC LIMIT 1;" 2>$null | Select-Object -Last 1
            Write-Host "Teste de escrita/leitura: $testData"
        } else {
            Write-Warning "Erro ao conectar no MariaDB"
        }
    } catch {
        Write-Warning "Container MariaDB nao encontrado"
    }
    
    Write-Host ""
    Write-Info "Testando dados Redis:"
    try {
        $result = docker exec discador_redis redis-cli -a redis123 ping 2>$null
        if ($LASTEXITCODE -eq 0 -and $result -eq "PONG") {
            Write-Success "Redis conectado e respondendo"
            
            # Testar dados de exemplo
            docker exec discador_redis redis-cli -a redis123 set test_persistence "dados_persistidos_$(Get-Date -Format 'yyyyMMdd_HHmmss')" | Out-Null
            $testValue = docker exec discador_redis redis-cli -a redis123 get test_persistence
            Write-Host "Teste de escrita/leitura: $testValue"
        } else {
            Write-Warning "Erro ao conectar no Redis"
        }
    } catch {
        Write-Warning "Container Redis nao encontrado"
    }
    
    Write-Host ""
    Write-Info "Testando Asterisk:"
    try {
        $result = docker exec discador_asterisk asterisk -rx "core show version" 2>$null
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Asterisk conectado e respondendo"
            Write-Host "Versao: $($result | Select-Object -First 1)"
        } else {
            Write-Warning "Erro ao conectar no Asterisk"
        }
    } catch {
        Write-Warning "Container Asterisk nao encontrado"
    }
}

function Create-EnvFile {
    Show-Header
    
    Write-Info "Criando arquivo .env..."
    
    if (Test-Path ".env") {
        Write-Warning "Arquivo .env ja existe!"
        $response = Read-Host "Deseja sobrescrever? (s/n)"
        if ($response -ne "s" -and $response -ne "S") {
            Write-Info "Operacao cancelada"
            return
        }
    }
    
    $envContent = @"
# Configuracoes do Sistema Discador v2.0
# Gerado automaticamente em $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')

# MariaDB
MYSQL_ROOT_PASSWORD=root123
MYSQL_DATABASE=discador
MYSQL_USER=discador
MYSQL_PASSWORD=discador123

# Redis
REDIS_PASSWORD=redis123

# PHP
PHP_MEMORY_LIMIT=512M
PHP_MAX_EXECUTION_TIME=300

# Timezone
TZ=America/Sao_Paulo

# Debug
DEBUG=false
LOG_LEVEL=info
"@
    
    $envContent | Out-File -FilePath ".env" -Encoding UTF8
    Write-Success "Arquivo .env criado com sucesso!"
    Write-Host ""
    Write-Info "Configuracoes padrao aplicadas:"
    Write-Host "   MySQL Root Password: root123"
    Write-Host "   MySQL Database: discador"
    Write-Host "   MySQL User: discador"
    Write-Host "   MySQL Password: discador123"
    Write-Host "   Redis Password: redis123"
    Write-Host "   Timezone: America/Sao_Paulo"
    Write-Host ""
    Write-Warning "IMPORTANTE: Altere as senhas em producao!"
}

# Execucao principal
switch ($true) {
    $Stop { Stop-Services }
    $Restart { Restart-Services }
    $Logs { Show-Logs }
    $Status { 
        Show-Header
        Write-Info "Verificando Docker..."
        if (-not (Test-DockerRunning)) {
            Write-Error "ERRO: Docker nao esta rodando!"
            Write-Warning "Inicie o Docker Desktop e tente novamente."
            exit 1
        }
        Write-Success "Docker esta rodando"
        Show-Status 
    }
    $TestPersistence { Test-DataPersistence }
    $CreateEnv { Create-EnvFile }
    default { Start-Services }
}
