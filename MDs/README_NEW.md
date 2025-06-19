# Discador v2.0 - Sistema de Discagem Automática Modernizado

## Visão Geral

O Discador v2.0 é uma versão modernizada e robusta do sistema de discagem automática, utilizando uma arquitetura Master-Worker distribuída com Redis como sistema de fila e cache. O sistema oferece tanto ambiente dockerizado quanto instalação nativa Windows, com interface web moderna para gerenciamento completo.

## 🌟 Características Principais

- **Arquitetura Master-Worker**: Processamento distribuído e escalável
- **Sistema de Filas Redis**: Gerenciamento robusto de tarefas
- **Interface Web Moderna**: Dashboard completo com controles em tempo real
- **Compatibilidade Windows**: Sem dependências POSIX
- **Ambiente Dockerizado**: Deploy rápido com containers
- **Monitoramento em Tempo Real**: Dashboard de monitoramento dedicado
- **Scripts de Deploy**: Instalação automatizada
- **Sistema de Backup**: Backup e manutenção automatizados
- **Diagnóstico Avançado**: Ferramentas de troubleshooting

## 🏗️ Arquitetura do Sistema

### Ambiente Dockerizado
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     Nginx       │    │   PHP-FPM       │    │    MariaDB      │
│   (Port 80/443) │───▶│   (Port 9000)   │───▶│   (Port 3306)   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌─────────────────┐    ┌─────────────────┐
                       │     Redis       │    │    Asterisk     │
                       │   (Port 6379)   │    │   (Port 5060)   │
                       └─────────────────┘    └─────────────────┘
```

### Estrutura de Código
```
discador_v2/
├── src/                          # Código-fonte principal
│   ├── config/                   # Configurações
│   ├── services/                 # Serviços core
│   │   ├── managers/            # Master processes
│   │   ├── workers/             # Worker processes
│   │   └── *.php               # Serviços base
│   ├── api/                     # APIs REST
│   ├── classes/                 # Classes auxiliares
│   ├── templates/               # Templates web
│   └── index.php               # Dashboard principal
├── scripts/                     # Scripts de controle
│   ├── deploy.bat              # Deploy automatizado (Windows)
│   ├── install_redis.bat       # Instalação Redis (Windows)
│   ├── discador_control.php    # Controle CLI
│   ├── discador_monitor.php    # Monitor CLI
│   ├── discador_maintenance.php # Manutenção
│   ├── discador_diagnostic.php # Diagnóstico
│   └── redis_config.php        # Configuração Redis
├── docker/                     # Configurações Docker
├── logs/                       # Logs do sistema
├── backup/                     # Backups
└── data/                       # Dados persistentes
```

## 📋 Requisitos do Sistema

### Para Ambiente Dockerizado
- **Windows 10/11** com WSL2 ativado
- **Docker Desktop** para Windows
- **4GB RAM** mínimo (8GB recomendado)
- **10GB** espaço em disco

### Para Instalação Nativa Windows
- **Windows 10/11** ou **Windows Server 2016+**
- **PHP 8.0+** com extensões:
  - `pdo_mysql`
  - `redis` (recomendado)
  - `curl`, `json`, `mbstring`
- **MySQL/MariaDB 5.7+**
- **Redis 5.0+** (instalação automática disponível)
- **Servidor Web** (Apache/Nginx ou PHP built-in server)

### Integração com Asterisk
- **Asterisk 16+** com AMI habilitado
- Conectividade de rede para AMI (porta 5038)

## 🚀 Instalação

### Opção 1: Ambiente Dockerizado (Recomendado)

#### 1. Configuração Inicial
```bash
# Clone ou acesse o diretório do projeto
cd discador_v2

# Copie e configure as variáveis de ambiente
cp .env.example .env
# Edite o .env conforme necessário
```

#### 2. Iniciando os Serviços
```bash
# Linux/WSL2
chmod +x scripts/*.sh
./scripts/start.sh

# Ou manualmente
docker-compose up -d --build
```

#### 3. Acesso ao Sistema
- **Web Interface**: http://localhost
- **phpMyAdmin**: http://localhost:8080
- **Asterisk CLI**: `docker exec -it asterisk asterisk -r`

### Opção 2: Instalação Nativa Windows

#### 1. Deploy Automatizado
```batch
# Execute como Administrador
scripts\deploy.bat
```

#### 2. Instale o Redis (se necessário)
```batch
# Instala e configura Redis automaticamente
scripts\install_redis.bat
```

#### 3. Configure o Banco de Dados
Edite `src/config/config.php` com suas credenciais do banco de dados.

#### 4. Inicie o Sistema
```batch
# Use os scripts criados pelo deploy
start_discador.bat
```

## 💻 Interface Web - Gerenciamento

### Dashboard Principal (`index.php`)
O dashboard principal inclui uma seção de **Gerenciamento do Discador v2.0** (disponível para administradores):

#### 📊 Status do Sistema
- Status do Master Process
- Workers ativos/total
- Tarefas na fila
- Status do Redis

#### 🎛️ Controles do Sistema
- **Iniciar/Parar/Reiniciar**: Controle completo do sistema
- **Status Detalhado**: Informações completas de todos os componentes
- **Gerenciamento de Workers**: Visualizar e controlar workers
- **Monitoramento de Fila**: Status das tarefas pendentes

#### 📈 Monitoramento
- **Dashboard Completo**: Monitor dedicado em nova janela
- **Logs em Tempo Real**: Console de logs do sistema
- **Métricas de Performance**: Gráficos e estatísticas

#### 🔧 Manutenção
- **Backup**: Backup completo do sistema
- **Limpeza**: Limpeza de logs e dados temporários
- **Otimização**: Otimização do banco e cache
- **Diagnóstico**: Teste completo de todos os componentes

### Monitor Dashboard (`monitor-dashboard.php`)
Dashboard dedicado com:

- **Métricas em Tempo Real**: CPU, memória, workers, filas
- **Gráficos Interativos**: Performance e estatísticas históricas
- **Status dos Workers**: Detalhes de cada worker ativo
- **Logs do Sistema**: Console de logs em tempo real
- **Auto-refresh**: Atualização automática a cada 30 segundos

## 🛠️ Comandos de Controle

### Scripts Windows (.bat)
```batch
# Controle básico
start_discador.bat     # Inicia o sistema
stop_discador.bat      # Para o sistema
status_discador.bat    # Verifica status
monitor_discador.bat   # Dashboard CLI

# Redis
start_redis.bat        # Inicia Redis
stop_redis.bat         # Para Redis
```

### CLI PHP (Avançado)
```batch
# Controle do sistema
php scripts\discador_control.php start
php scripts\discador_control.php stop
php scripts\discador_control.php restart
php scripts\discador_control.php status

# Gerenciamento de workers
php scripts\discador_control.php workers
php scripts\discador_control.php queue
php scripts\discador_control.php logs

# Manutenção
php scripts\discador_maintenance.php backup
php scripts\discador_maintenance.php cleanup
php scripts\discador_maintenance.php optimize

# Diagnóstico
php scripts\discador_diagnostic.php
```

### Scripts Docker
```bash
# Controle dos containers
./scripts/start.sh     # Inicia todos os serviços
./scripts/stop.sh      # Para todos os serviços
./scripts/restart.sh   # Reinicia serviços
./scripts/logs.sh      # Visualiza logs
./scripts/backup.sh    # Backup completo
```

### API REST
```bash
# Status do sistema
GET /api/discador-status.php

# Controle via POST
POST /api/discador-control.php
{
  "action": "control",
  "command": "start"
}
```

## ⚙️ Configuração

### Configuração Principal (`src/config/config.php`)
```php
<?php
// Banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'discador');
define('DB_USER', 'user');
define('DB_PASS', 'password');

// Redis
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_DB', 0);

// Asterisk
define('ASTERISK_HOST', 'localhost');
define('ASTERISK_AMI_PORT', 5038);
define('ASTERISK_AMI_USER', 'admin');
define('ASTERISK_AMI_PASS', 'secret');
```

### Variáveis de Ambiente Docker (`.env`)
```env
# MySQL
MYSQL_ROOT_PASSWORD=root_password_2024!
MYSQL_DATABASE=discador
MYSQL_USER=discador_user
MYSQL_PASSWORD=discador_pass_2024!

# Redis
REDIS_PASSWORD=redis_pass_2024!

# Asterisk
ASTERISK_AMI_USER=admin
ASTERISK_AMI_PASSWORD=asterisk_ami_2024!

# Timezone
TZ=America/Sao_Paulo
```

## 🔍 Monitoramento e Logs

### Logs do Sistema
```bash
# Docker
docker-compose logs -f discador

# Windows nativo
tail -f logs\discador.log
tail -f logs\workers.log
tail -f logs\errors.log
```

### Métricas Redis
```bash
# Conectar ao Redis
redis-cli
> INFO memory
> KEYS discador:*
> MONITOR
```

### Status dos Workers
```bash
# Via CLI
php scripts\discador_control.php workers

# Via API
curl http://localhost/api/discador-status.php
```

## 🚨 Troubleshooting

### Problemas Comuns

#### Redis não conecta
```bash
# Verificar se Redis está rodando
redis-cli ping

# Windows: verificar serviço
services.msc → Redis

# Docker: verificar container
docker-compose ps redis
```

#### Workers não iniciam
```bash
# Verificar logs
php scripts\discador_diagnostic.php

# Verificar configuração
php -f src\config\config.php

# Verificar permissões (Windows)
icacls logs\ /grant Everyone:(OI)(CI)F
```

#### Asterisk não conecta
```bash
# Testar conectividade
telnet asterisk_host 5038

# Verificar credenciais AMI
asterisk -rx "manager show users"
```

### Scripts de Diagnóstico
```bash
# Diagnóstico completo
php scripts\discador_diagnostic.php

# Teste de componentes
php scripts\redis_config.php test
php scripts\discador_control.php status
```

## 📁 Estrutura de Arquivos

### Arquivos Principais
- `src/index.php` - Dashboard principal com GUI de gerenciamento
- `src/monitor-dashboard.php` - Dashboard de monitoramento dedicado
- `src/api/discador-*.php` - APIs REST para controle
- `scripts/deploy.bat` - Script de deploy automatizado
- `scripts/install_redis.bat` - Instalação Redis para Windows
- `docker-compose.yml` - Configuração Docker

### Diretórios de Dados
- `logs/` - Logs do sistema
- `backup/` - Backups automáticos
- `data/` - Dados persistentes
- `tmp/` - Arquivos temporários

## 🔐 Segurança

### Autenticação Web
- Sistema de login integrado
- Controle de permissões por usuário
- Sessões seguras com timeout

### API Security
- Validação de sessão para todas as APIs
- Sanitização de comandos CLI
- Rate limiting (configurável)

### Redis Security
- Autenticação por senha
- Bind apenas para localhost
- TTL para chaves temporárias

## 📈 Performance

### Otimizações Redis
- Configuração automática de políticas de memória
- Persistência otimizada (AOF + RDB)
- TTL automático para dados temporários

### Otimizações MySQL
- Indexes otimizados para consultas de discagem
- Conexões pooled
- Queries preparadas

### Monitoramento de Performance
- Métricas de workers em tempo real
- Estatísticas de fila e processamento
- Alertas automáticos para problemas

## 🆕 Novidades da v2.0

### Interface Web
- ✅ Dashboard moderno com Bootstrap 5
- ✅ Controles em tempo real via GUI
- ✅ Monitor dashboard dedicado
- ✅ Console de logs integrado
- ✅ APIs REST para automação

### Arquitetura
- ✅ Master-Worker distribuído
- ✅ Sistema de filas Redis
- ✅ Distributed locking
- ✅ Auto-recovery de falhas
- ✅ Windows compatibility (sem POSIX)

### Deploy e Manutenção
- ✅ Scripts de deploy automatizado
- ✅ Instalação Redis automatizada
- ✅ Sistema de backup integrado
- ✅ Diagnóstico avançado
- ✅ Suporte Docker completo

## 📞 Suporte

Para suporte técnico, consulte:
- Logs do sistema em `logs/`
- Scripts de diagnóstico em `scripts/`
- Monitor dashboard para métricas em tempo real
- APIs de status para integração externa

## 📄 Licença

Este projeto é proprietário. Todos os direitos reservados.

---

**Discador v2.0** - Sistema de Discagem Automática Modernizado  
*Versão 2.0.0 - 2024*
