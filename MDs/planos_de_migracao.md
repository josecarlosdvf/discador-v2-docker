# Planos de Migração do Sistema PBX/Discador Legado

## 📋 Análise do Sistema Atual

### **Estado Atual (Legacy Stack)**
```
┌─────────────────────────────────────────────────────────────┐
│                    SISTEMA LEGADO ATUAL                    │
├─────────────────────────────────────────────────────────────┤
│  🐧 SO: Debian 8.5 (Jessie) - EOL desde 2020             │
│  🐘 PHP: 5.3 - EOL desde 2014 (CRÍTICO!)                 │
│  ☎️  Asterisk: 1.8 - EOL desde 2017                       │
│  🗄️  MariaDB: 10.x (única parte relativamente atual)      │
│  🔧 Dependências: MySQL extension (deprecated)            │
├─────────────────────────────────────────────────────────────┤
│  ⚠️  RISCOS IDENTIFICADOS:                                 │
│  ├── Vulnerabilidades de segurança múltiplas              │
│  ├── Ausência de suporte oficial                          │
│  ├── Incompatibilidade com ferramentas modernas           │
│  ├── Dificuldade de manutenção e evolução                 │
│  └── Performance limitada                                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Estratégias de Migração Recomendadas

### **OPÇÃO 1: Migração PHP Moderna + Docker (RECOMENDADA)**

**Viabilidade: ⭐⭐⭐⭐⭐ (Alta)**
- ✅ Menor risco de quebra
- ✅ Aproveitamento máximo do código existente
- ✅ Migração incremental possível
- ✅ Time-to-market reduzido

**Stack Objetivo:**
```dockerfile
┌─────────────────────────────────────────────────────────────┐
│                    STACK MODERNIZADA PHP                   │
├─────────────────────────────────────────────────────────────┤
│  🐳 Docker: Multi-container orchestration                  │
│  🐘 PHP: 8.2/8.3 + FPM + Composer                         │
│  ☎️  Asterisk: 20 LTS (mais recente LTS)                   │
│  🗄️  MariaDB: 11.x (latest stable)                        │
│  🔄 Redis: Cache e filas de eventos                        │
│  📊 Grafana + Prometheus: Monitoring                       │
│  🌐 Nginx: Reverse proxy e load balancer                   │
└─────────────────────────────────────────────────────────────┘
```

### **OPÇÃO 2: Migração Completa Python/Django**

**Viabilidade: ⭐⭐⭐⭐ (Boa)**
- ✅ Stack mais moderna e robusta
- ✅ Melhor performance e escalabilidade
- ✅ Ecossistema mais rico
- ⚠️ Reescrita completa necessária
- ⚠️ Maior tempo de desenvolvimento

**Stack Objetivo:**
```dockerfile
┌─────────────────────────────────────────────────────────────┐
│                    STACK PYTHON/DJANGO                     │
├─────────────────────────────────────────────────────────────┤
│  🐳 Docker: Kubernetes-ready                               │
│  🐍 Python: 3.11+ + Django 4.2 LTS                        │
│  ☎️  Asterisk: 20 LTS + REST API                           │
│  🗄️  PostgreSQL: 15+ (recomendado sobre MariaDB)          │
│  🔄 Redis: Cache, sessions, Celery queues                  │
│  📊 Grafana + Prometheus: Advanced monitoring              │
│  🌐 Nginx + Gunicorn: Production deployment                │
│  🔍 Elasticsearch: Logs e analytics avançados             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🛠️ PLANO DETALHADO - OPÇÃO 1: Migração PHP Moderna

### **FASE 1: Containerização do Sistema Atual (2-3 semanas)**

#### **1.1 Estrutura Docker**
```yaml
# docker-compose.yml
version: '3.8'
services:
  # Aplicação PHP
  app:
    build: ./docker/php
    volumes:
      - ./src:/var/www/html
      - ./logs:/var/log/asterisk
    environment:
      - PHP_VERSION=8.2
      - DB_HOST=mariadb
      - DB_USER=voipadmin
      - DB_PASS=voipadmin_secure
      - ASTERISK_HOST=asterisk
    networks:
      - pbx_network

  # Asterisk PBX
  asterisk:
    build: ./docker/asterisk
    ports:
      - "5038:5038"  # AMI
      - "5060:5060"  # SIP
      - "10000-10099:10000-10099/udp"  # RTP
    volumes:
      - ./asterisk/config:/etc/asterisk
      - ./asterisk/sounds:/var/lib/asterisk/sounds
      - ./logs:/var/log/asterisk
    networks:
      - pbx_network

  # MariaDB
  mariadb:
    image: mariadb:11
    environment:
      - MYSQL_ROOT_PASSWORD=root_secure
      - MYSQL_DATABASE=voipadmin
      - MYSQL_USER=voipadmin
      - MYSQL_PASSWORD=voipadmin_secure
    volumes:
      - mariadb_data:/var/lib/mysql
      - ./database/init:/docker-entrypoint-initdb.d
    networks:
      - pbx_network

  # Redis para cache e filas
  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
    networks:
      - pbx_network

  # Nginx reverse proxy
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf:/etc/nginx/conf.d
      - ./src:/var/www/html
    depends_on:
      - app
    networks:
      - pbx_network

volumes:
  mariadb_data:
  redis_data:

networks:
  pbx_network:
    driver: bridge
```

#### **1.2 Dockerfile para PHP 8.2**
```dockerfile
# docker/php/Dockerfile
FROM php:8.2-fpm

# Instalar extensões necessárias
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    libredis-dev \
    unixodbc-dev \
    && docker-php-ext-install \
    mysqli \
    pdo_mysql \
    sockets \
    pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuração PHP
COPY php.ini /usr/local/etc/php/

# Scripts de monitoramento
COPY start-monitors.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start-monitors.sh

WORKDIR /var/www/html

CMD ["php-fpm"]
```

#### **1.3 Dockerfile para Asterisk 20**
```dockerfile
# docker/asterisk/Dockerfile
FROM debian:bullseye

# Instalar dependências
RUN apt-get update && apt-get install -y \
    asterisk \
    asterisk-config \
    asterisk-modules \
    asterisk-dev \
    && rm -rf /var/lib/apt/lists/*

# Configurações customizadas
COPY asterisk.conf /etc/asterisk/
COPY manager.conf /etc/asterisk/
COPY extensions.conf /etc/asterisk/
COPY sip.conf /etc/asterisk/

# Script de inicialização
COPY start-asterisk.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start-asterisk.sh

EXPOSE 5038 5060 10000-10099/udp

CMD ["/usr/local/bin/start-asterisk.sh"]
```

### **FASE 2: Modernização do Código PHP (3-4 semanas)**

#### **2.1 Estrutura Modernizada**
```
src/
├── config/
│   ├── database.php
│   ├── asterisk.php
│   └── app.php
├── src/
│   ├── Core/
│   │   ├── Database/
│   │   │   ├── Connection.php
│   │   │   └── QueryBuilder.php
│   │   ├── Asterisk/
│   │   │   ├── AMIConnection.php
│   │   │   └── EventHandler.php
│   │   └── Monitor/
│   │       ├── BaseMonitor.php
│   │       ├── DiscadorMonitor.php
│   │       ├── RamaisMonitor.php
│   │       └── CallsMonitor.php
│   ├── Models/
│   │   ├── Campaign.php
│   │   ├── Contact.php
│   │   ├── Extension.php
│   │   └── Call.php
│   ├── Services/
│   │   ├── DiscadorService.php
│   │   ├── MonitorService.php
│   │   └── MetricsService.php
│   └── Web/
│       ├── Controllers/
│       ├── Views/
│       └── Public/
├── tests/
├── docker/
├── scripts/
└── composer.json
```

#### **2.2 Código Base Modernizado**

**Core/Database/Connection.php**
```php
<?php
namespace PBX\Core\Database;

use PDO;
use PDOException;

class Connection {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $config = require __DIR__ . '/../../config/database.php';
        
        try {
            $this->pdo = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => true
                ]
            );
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPDO(): PDO {
        return $this->pdo;
    }
    
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }
    
    public function commit(): bool {
        return $this->pdo->commit();
    }
    
    public function rollback(): bool {
        return $this->pdo->rollback();
    }
}
```

**Core/Asterisk/AMIConnection.php**
```php
<?php
namespace PBX\Core\Asterisk;

class AMIConnection {
    private $socket;
    private $config;
    private $connected = false;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../../config/asterisk.php';
    }
    
    public function connect(): bool {
        $this->socket = fsockopen(
            $this->config['ami']['host'],
            $this->config['ami']['port'],
            $errno,
            $errstr,
            $this->config['ami']['timeout']
        );
        
        if (!$this->socket) {
            throw new \Exception("AMI Connection failed: {$errstr} ({$errno})");
        }
        
        // Login
        $this->sendAction([
            'Action' => 'Login',
            'Username' => $this->config['ami']['username'],
            'Secret' => $this->config['ami']['secret']
        ]);
        
        $response = $this->waitResponse();
        $this->connected = ($response['Response'] === 'Success');
        
        return $this->connected;
    }
    
    public function sendAction(array $action): bool {
        if (!$this->connected) {
            throw new \Exception("AMI not connected");
        }
        
        $message = '';
        foreach ($action as $key => $value) {
            $message .= "{$key}: {$value}\r\n";
        }
        $message .= "\r\n";
        
        return fwrite($this->socket, $message) !== false;
    }
    
    public function waitResponse(): array {
        $response = [];
        
        while (true) {
            $line = trim(fgets($this->socket, 4096));
            
            if ($line === '') {
                break;
            }
            
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $response[trim($key)] = trim($value);
            }
        }
        
        return $response;
    }
    
    public function originate(string $channel, string $extension, string $context, array $variables = []): array {
        $action = [
            'Action' => 'Originate',
            'Channel' => $channel,
            'Exten' => $extension,
            'Context' => $context,
            'Priority' => 1,
            'Timeout' => 45000,
            'Async' => 'true'
        ];
        
        foreach ($variables as $key => $value) {
            $action["Variable"] = "{$key}={$value}";
        }
        
        $this->sendAction($action);
        return $this->waitResponse();
    }
    
    public function __destruct() {
        if ($this->socket && $this->connected) {
            $this->sendAction(['Action' => 'Logoff']);
            fclose($this->socket);
        }
    }
}
```

**Core/Monitor/BaseMonitor.php**
```php
<?php
namespace PBX\Core\Monitor;

use PBX\Core\Asterisk\AMIConnection;
use PBX\Core\Database\Connection;
use Psr\Log\LoggerInterface;

abstract class BaseMonitor {
    protected $ami;
    protected $db;
    protected $logger;
    protected $running = false;
    
    public function __construct(LoggerInterface $logger) {
        $this->ami = new AMIConnection();
        $this->db = Connection::getInstance();
        $this->logger = $logger;
    }
    
    public function start(): void {
        $this->running = true;
        $this->ami->connect();
        
        $this->logger->info(get_class($this) . " started");
        
        while ($this->running) {
            try {
                $this->processEvents();
                usleep(500000); // 0.5 seconds
            } catch (\Exception $e) {
                $this->logger->error("Monitor error: " . $e->getMessage());
                sleep(5); // Wait before retry
            }
        }
    }
    
    public function stop(): void {
        $this->running = false;
        $this->logger->info(get_class($this) . " stopped");
    }
    
    abstract protected function processEvents(): void;
    
    protected function handleEvent(array $event): void {
        // Override in subclasses
    }
}
```

### **FASE 3: Sistema de Monitoramento Moderno (2-3 semanas)**

#### **3.1 Monitor de Discador Modernizado**
```php
<?php
namespace PBX\Core\Monitor;

use PBX\Models\Campaign;
use PBX\Services\DiscadorService;

class DiscadorMonitor extends BaseMonitor {
    private $discadorService;
    
    public function __construct(\Psr\Log\LoggerInterface $logger) {
        parent::__construct($logger);
        $this->discadorService = new DiscadorService($this->db, $this->ami);
    }
    
    protected function processEvents(): void {
        $campaigns = Campaign::getActive();
        
        foreach ($campaigns as $campaign) {
            if (!$campaign->isInWorkingHours()) {
                continue;
            }
            
            $availableAgents = $this->getAvailableAgents($campaign->getQueueName());
            $callsNeeded = $this->discadorService->calculateCallsNeeded(
                $campaign,
                $availableAgents
            );
            
            if ($callsNeeded > 0) {
                $this->discadorService->makeCall($campaign, $callsNeeded);
            }
            
            // Update metrics
            $this->discadorService->updateMetrics($campaign);
        }
        
        // Send ping to keep AMI alive
        $this->ami->sendAction(['Action' => 'Ping']);
    }
    
    private function getAvailableAgents(string $queue): int {
        $this->ami->sendAction([
            'Action' => 'QueueStatus',
            'Queue' => $queue
        ]);
        
        // Process response and count available agents
        // Implementation details...
        
        return $availableAgents;
    }
}
```

#### **3.2 Supervisor com Health Checks**
```php
<?php
namespace PBX\Core\Monitor;

class MonitorSupervisor {
    private $monitors = [];
    private $logger;
    private $running = false;
    
    public function __construct(\Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    public function addMonitor(string $name, BaseMonitor $monitor): void {
        $this->monitors[$name] = [
            'instance' => $monitor,
            'process' => null,
            'last_heartbeat' => time()
        ];
    }
    
    public function start(): void {
        $this->running = true;
        
        // Start all monitors in separate processes
        foreach ($this->monitors as $name => &$monitor) {
            $pid = pcntl_fork();
            
            if ($pid == 0) {
                // Child process
                $monitor['instance']->start();
                exit(0);
            } else {
                // Parent process
                $monitor['process'] = $pid;
                $this->logger->info("Started monitor: {$name} (PID: {$pid})");
            }
        }
        
        // Supervision loop
        while ($this->running) {
            $this->checkHealth();
            sleep(30); // Check every 30 seconds
        }
    }
    
    private function checkHealth(): void {
        foreach ($this->monitors as $name => &$monitor) {
            $status = pcntl_waitpid($monitor['process'], $status, WNOHANG);
            
            if ($status != 0) {
                $this->logger->warning("Monitor {$name} died, restarting...");
                $this->restartMonitor($name, $monitor);
            }
        }
    }
    
    private function restartMonitor(string $name, array &$monitor): void {
        $pid = pcntl_fork();
        
        if ($pid == 0) {
            $monitor['instance']->start();
            exit(0);
        } else {
            $monitor['process'] = $pid;
            $this->logger->info("Restarted monitor: {$name} (PID: {$pid})");
        }
    }
}
```

#### **3.3 Script de Inicialização**
```php
#!/usr/bin/env php
<?php
// scripts/start-monitors.php

require_once __DIR__ . '/../vendor/autoload.php';

use PBX\Core\Monitor\MonitorSupervisor;
use PBX\Core\Monitor\DiscadorMonitor;
use PBX\Core\Monitor\RamaisMonitor;
use PBX\Core\Monitor\CallsMonitor;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

// Setup logging
$logger = new Logger('pbx_monitor');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
$logger->pushHandler(new RotatingFileHandler('/var/log/asterisk/monitors.log', 0, Logger::DEBUG));

// Create supervisor
$supervisor = new MonitorSupervisor($logger);

// Add monitors
$supervisor->addMonitor('discador', new DiscadorMonitor($logger));
$supervisor->addMonitor('ramais', new RamaisMonitor($logger));
$supervisor->addMonitor('calls', new CallsMonitor($logger));

// Start supervision
$logger->info("Starting PBX Monitor System");
$supervisor->start();
```

### **FASE 4: Interface Web Moderna (2-3 semanas)**

#### **4.1 API REST**
```php
<?php
namespace PBX\Web\Controllers;

class CampaignController {
    private $campaignService;
    
    public function __construct() {
        $this->campaignService = new \PBX\Services\CampaignService();
    }
    
    public function index(): array {
        return [
            'campaigns' => $this->campaignService->getAll(),
            'total' => $this->campaignService->getCount()
        ];
    }
    
    public function show(int $id): array {
        $campaign = $this->campaignService->findById($id);
        
        if (!$campaign) {
            http_response_code(404);
            return ['error' => 'Campaign not found'];
        }
        
        return [
            'campaign' => $campaign,
            'metrics' => $this->campaignService->getMetrics($campaign),
            'contacts' => $this->campaignService->getContacts($campaign)
        ];
    }
    
    public function store(): array {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $campaign = $this->campaignService->create($data);
            http_response_code(201);
            return ['campaign' => $campaign];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
    
    public function update(int $id): array {
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $campaign = $this->campaignService->update($id, $data);
            return ['campaign' => $campaign];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
    
    public function destroy(int $id): array {
        try {
            $this->campaignService->delete($id);
            http_response_code(204);
            return [];
        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
}
```

#### **4.2 Frontend Moderno (Vue.js/React)**
```javascript
// src/Web/Public/js/dashboard.js
class PBXDashboard {
    constructor() {
        this.socket = null;
        this.metrics = {};
        this.init();
    }
    
    init() {
        this.setupWebSocket();
        this.loadInitialData();
        this.setupEventListeners();
    }
    
    setupWebSocket() {
        this.socket = new WebSocket('ws://localhost:8080/ws');
        
        this.socket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleRealtimeUpdate(data);
        };
        
        this.socket.onclose = () => {
            setTimeout(() => this.setupWebSocket(), 5000);
        };
    }
    
    async loadInitialData() {
        try {
            const [campaigns, extensions, metrics] = await Promise.all([
                fetch('/api/campaigns').then(r => r.json()),
                fetch('/api/extensions').then(r => r.json()),
                fetch('/api/metrics').then(r => r.json())
            ]);
            
            this.updateCampaignsTable(campaigns);
            this.updateExtensionsPanel(extensions);
            this.updateMetricsCharts(metrics);
        } catch (error) {
            console.error('Failed to load initial data:', error);
        }
    }
    
    handleRealtimeUpdate(data) {
        switch (data.type) {
            case 'extension_status':
                this.updateExtensionStatus(data.extension, data.status);
                break;
            case 'call_event':
                this.updateCallsCounter(data.event);
                break;
            case 'campaign_metrics':
                this.updateCampaignMetrics(data.campaign_id, data.metrics);
                break;
        }
    }
    
    updateExtensionStatus(extension, status) {
        const element = document.querySelector(`[data-extension="${extension}"]`);
        if (element) {
            element.className = `extension-status ${status}`;
            element.textContent = status.toUpperCase();
        }
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    new PBXDashboard();
});
```

---

## 🐍 PLANO DETALHADO - OPÇÃO 2: Migração Python/Django

### **FASE 1: Setup Inicial Django (1-2 semanas)**

#### **1.1 Estrutura do Projeto**
```
pbx_system/
├── docker-compose.yml
├── Dockerfile
├── requirements.txt
├── manage.py
├── pbx_system/
│   ├── __init__.py
│   ├── settings/
│   │   ├── __init__.py
│   │   ├── base.py
│   │   ├── development.py
│   │   └── production.py
│   ├── urls.py
│   └── wsgi.py
├── apps/
│   ├── campaigns/
│   ├── contacts/
│   ├── extensions/
│   ├── calls/
│   ├── monitoring/
│   └── api/
├── core/
│   ├── asterisk/
│   ├── monitoring/
│   └── utils/
├── templates/
├── static/
└── tests/
```

#### **1.2 Docker Setup para Django**
```dockerfile
# Dockerfile
FROM python:3.11-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gcc \
    default-libmysqlclient-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application
COPY . .

# Collect static files
RUN python manage.py collectstatic --noinput

EXPOSE 8000

CMD ["gunicorn", "--bind", "0.0.0.0:8000", "pbx_system.wsgi:application"]
```

```yaml
# docker-compose.yml for Django
version: '3.8'
services:
  web:
    build: .
    ports:
      - "8000:8000"
    environment:
      - DEBUG=False
      - DATABASE_URL=mysql://user:pass@db:3306/pbx
      - REDIS_URL=redis://redis:6379/0
      - ASTERISK_AMI_HOST=asterisk
    depends_on:
      - db
      - redis
      - asterisk
    volumes:
      - ./logs:/app/logs

  celery:
    build: .
    command: celery -A pbx_system worker -l info
    environment:
      - DATABASE_URL=mysql://user:pass@db:3306/pbx
      - REDIS_URL=redis://redis:6379/0
    depends_on:
      - db
      - redis

  celery-beat:
    build: .
    command: celery -A pbx_system beat -l info
    environment:
      - DATABASE_URL=mysql://user:pass@db:3306/pbx
      - REDIS_URL=redis://redis:6379/0
    depends_on:
      - db
      - redis

  asterisk:
    image: asterisk:20
    ports:
      - "5038:5038"
      - "5060:5060"
    volumes:
      - ./asterisk:/etc/asterisk

  db:
    image: postgres:15
    environment:
      - POSTGRES_DB=pbx
      - POSTGRES_USER=pbx_user
      - POSTGRES_PASSWORD=secure_password
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  postgres_data:
  redis_data:
```

#### **1.3 Requirements.txt**
```txt
Django==4.2.7
djangorestframework==3.14.0
django-cors-headers==4.3.1
django-environ==0.11.2
celery==5.3.4
redis==5.0.1
psycopg2-binary==2.9.9
gunicorn==21.2.0
channels==4.0.0
channels-redis==4.1.0

# Asterisk integration
pyst2==0.5.1
panoramisk==1.2

# Monitoring and metrics
prometheus-client==0.19.0
django-prometheus==2.3.1

# Testing
pytest-django==4.7.0
factory-boy==3.3.0

# Development
django-debug-toolbar==4.2.0
django-extensions==3.2.3
```

### **FASE 2: Models e Core (2-3 semanas)**

#### **2.1 Django Models**
```python
# apps/campaigns/models.py
from django.db import models
from django.utils import timezone

class Campaign(models.Model):
    STATUS_CHOICES = [
        ('active', 'Ativa'),
        ('paused', 'Pausada'),
        ('finished', 'Finalizada'),
    ]
    
    name = models.CharField(max_length=255)
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='active')
    max_channels = models.IntegerField(default=10)
    impulse_factor = models.FloatField(default=6.0)
    max_multiplier = models.FloatField(default=20.0)
    average_call_time = models.IntegerField(default=180)  # seconds
    
    # Horários
    start_time = models.TimeField()
    end_time = models.TimeField()
    
    # Métricas
    total_contacts = models.IntegerField(default=0)
    processed_contacts = models.IntegerField(default=0)
    successful_calls = models.IntegerField(default=0)
    
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    
    class Meta:
        db_table = 'discador_campanhas'
    
    def is_in_working_hours(self):
        now = timezone.now().time()
        return self.start_time <= now <= self.end_time
    
    def get_success_rate(self):
        if self.processed_contacts == 0:
            return 0
        return (self.successful_calls / self.processed_contacts) * 100

class Contact(models.Model):
    campaign = models.ForeignKey(Campaign, on_delete=models.CASCADE)
    name = models.CharField(max_length=255)
    phone_number = models.CharField(max_length=20)
    alternate_phone = models.CharField(max_length=20, blank=True)
    
    attempts = models.IntegerField(default=0)
    max_attempts = models.IntegerField(default=3)
    last_attempt = models.DateTimeField(null=True, blank=True)
    next_attempt = models.DateTimeField(null=True, blank=True)
    
    status = models.CharField(max_length=50, default='pending')
    result = models.CharField(max_length=100, blank=True)
    
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    
    class Meta:
        db_table = 'discador_contatos'
    
    def can_call_now(self):
        if self.attempts >= self.max_attempts:
            return False
        if self.next_attempt and timezone.now() < self.next_attempt:
            return False
        return True

# apps/extensions/models.py
class Extension(models.Model):
    STATUS_CHOICES = [
        ('free', 'Livre'),
        ('busy', 'Ocupado'),
        ('unregistered', 'Não Registrado'),
    ]
    
    number = models.CharField(max_length=10, unique=True)
    name = models.CharField(max_length=255)
    
    # Status atual
    status_line1 = models.CharField(max_length=20, choices=STATUS_CHOICES, default='free')
    status_line2 = models.CharField(max_length=20, choices=STATUS_CHOICES, default='free')
    
    # Última atividade
    last_activity = models.DateTimeField(auto_now=True)
    registered = models.BooleanField(default=False)
    
    class Meta:
        db_table = 'painel_ramais'
    
    def is_available(self):
        return self.registered and (
            self.status_line1 == 'free' or self.status_line2 == 'free'
        )
```

#### **2.2 Asterisk Integration**
```python
# core/asterisk/ami.py
import asyncio
import logging
from typing import Dict, Any, Callable
from panoramisk import Manager

logger = logging.getLogger(__name__)

class AMIConnection:
    def __init__(self, host='localhost', port=5038, username='admin', secret='admin'):
        self.host = host
        self.port = port
        self.username = username
        self.secret = secret
        self.manager = None
        self.event_handlers = {}
    
    async def connect(self):
        """Establish AMI connection"""
        try:
            self.manager = Manager(
                host=self.host,
                port=self.port,
                username=self.username,
                secret=self.secret
            )
            await self.manager.connect()
            logger.info("AMI connection established")
            return True
        except Exception as e:
            logger.error(f"AMI connection failed: {e}")
            return False
    
    async def originate(self, channel: str, extension: str, context: str, 
                       variables: Dict[str, Any] = None) -> Dict[str, Any]:
        """Originate a call"""
        action = {
            'Action': 'Originate',
            'Channel': channel,
            'Exten': extension,
            'Context': context,
            'Priority': 1,
            'Timeout': 45000,
            'Async': 'true'
        }
        
        if variables:
            for key, value in variables.items():
                action[f'Variable'] = f"{key}={value}"
        
        try:
            response = await self.manager.send_action(action)
            return response
        except Exception as e:
            logger.error(f"Originate failed: {e}")
            raise
    
    def register_event_handler(self, event_type: str, handler: Callable):
        """Register handler for specific event type"""
        if event_type not in self.event_handlers:
            self.event_handlers[event_type] = []
        self.event_handlers[event_type].append(handler)
    
    async def listen_events(self):
        """Listen for AMI events"""
        async for event in self.manager.events:
            event_type = event.get('Event')
            if event_type in self.event_handlers:
                for handler in self.event_handlers[event_type]:
                    try:
                        await handler(event)
                    except Exception as e:
                        logger.error(f"Event handler error: {e}")

# core/asterisk/events.py
from django.utils import timezone
from apps.extensions.models import Extension
from apps.calls.models import Call

class EventProcessor:
    def __init__(self, ami_connection):
        self.ami = ami_connection
        self.register_handlers()
    
    def register_handlers(self):
        self.ami.register_event_handler('PeerStatus', self.handle_peer_status)
        self.ami.register_event_handler('Dial', self.handle_dial)
        self.ami.register_event_handler('Bridge', self.handle_bridge)
        self.ami.register_event_handler('Hangup', self.handle_hangup)
    
    async def handle_peer_status(self, event):
        """Handle peer registration status"""
        peer = event.get('Peer', '').split('/')[-1]
        status = event.get('PeerStatus')
        
        try:
            extension = await Extension.objects.aget(number=peer)
            extension.registered = (status == 'Registered')
            if not extension.registered:
                extension.status_line1 = 'unregistered'
                extension.status_line2 = 'unregistered'
            await extension.asave()
            
            logger.info(f"Extension {peer} status: {status}")
        except Extension.DoesNotExist:
            logger.warning(f"Unknown extension: {peer}")
    
    async def handle_dial(self, event):
        """Handle call initiation"""
        source = self.extract_extension(event.get('CallerIDNum', ''))
        destination = self.extract_extension(event.get('Exten', ''))
        unique_id = event.get('Uniqueid')
        
        if source and destination:
            call = Call(
                unique_id=unique_id,
                source_extension=source,
                destination_extension=destination,
                status='ringing',
                start_time=timezone.now()
            )
            await call.asave()
            
            # Update extension status
            await self.update_extension_status(source, 'busy')
    
    async def handle_bridge(self, event):
        """Handle call answered"""
        unique_id = event.get('Uniqueid1') or event.get('Uniqueid2')
        
        try:
            call = await Call.objects.aget(unique_id=unique_id)
            call.status = 'connected'
            call.answer_time = timezone.now()
            await call.asave()
        except Call.DoesNotExist:
            pass
    
    async def handle_hangup(self, event):
        """Handle call termination"""
        unique_id = event.get('Uniqueid')
        
        try:
            call = await Call.objects.aget(unique_id=unique_id)
            call.status = 'finished'
            call.end_time = timezone.now()
            call.duration = (call.end_time - call.start_time).total_seconds()
            await call.asave()
            
            # Update extension status
            await self.update_extension_status(call.source_extension, 'free')
        except Call.DoesNotExist:
            pass
    
    @staticmethod
    def extract_extension(value):
        """Extract extension number from various formats"""
        # Implementation to extract extension from SIP/1001, Local/1001, etc.
        if '/' in value:
            return value.split('/')[-1]
        return value if value.isdigit() else None
    
    async def update_extension_status(self, extension_number, status):
        """Update extension status"""
        try:
            extension = await Extension.objects.aget(number=extension_number)
            if extension.status_line1 == 'free':
                extension.status_line1 = status
            else:
                extension.status_line2 = status
            await extension.asave()
        except Extension.DoesNotExist:
            pass
```

### **FASE 3: Sistema de Monitoramento com Celery (2-3 semanas)**

#### **3.1 Celery Tasks**
```python
# apps/monitoring/tasks.py
from celery import shared_task
from django.utils import timezone
from django.db import transaction
from apps.campaigns.models import Campaign, Contact
from apps.extensions.models import Extension
from core.asterisk.ami import AMIConnection
import logging

logger = logging.getLogger(__name__)

@shared_task
def run_dialer_engine():
    """Main dialer engine task"""
    logger.info("Starting dialer engine cycle")
    
    active_campaigns = Campaign.objects.filter(status='active')
    
    for campaign in active_campaigns:
        if not campaign.is_in_working_hours():
            continue
            
        try:
            process_campaign.delay(campaign.id)
        except Exception as e:
            logger.error(f"Failed to process campaign {campaign.id}: {e}")

@shared_task
def process_campaign(campaign_id):
    """Process individual campaign"""
    try:
        campaign = Campaign.objects.get(id=campaign_id)
    except Campaign.DoesNotExist:
        return
    
    # Calculate available agents
    available_agents = Extension.objects.filter(
        registered=True,
        status_line1='free'
    ).count()
    
    if available_agents == 0:
        return
    
    # Calculate calls needed
    calls_needed = min(
        int(available_agents * campaign.impulse_factor),
        campaign.max_channels
    )
    
    # Get contacts to call
    contacts = Contact.objects.filter(
        campaign=campaign,
        status='pending'
    ).filter(
        models.Q(next_attempt__isnull=True) | 
        models.Q(next_attempt__lte=timezone.now())
    )[:calls_needed]
    
    # Originate calls
    for contact in contacts:
        originate_call.delay(contact.id)

@shared_task
def originate_call(contact_id):
    """Originate a single call"""
    try:
        contact = Contact.objects.get(id=contact_id)
    except Contact.DoesNotExist:
        return
    
    ami = AMIConnection()
    
    try:
        # Connect to AMI
        if not await ami.connect():
            raise Exception("Failed to connect to AMI")
        
        # Originate call
        response = await ami.originate(
            channel=f"Local/{contact.phone_number}",
            extension=contact.phone_number,
            context="discador-out",
            variables={
                'CONTACT_ID': contact.id,
                'CAMPAIGN_ID': contact.campaign.id
            }
        )
        
        # Update contact
        with transaction.atomic():
            contact.attempts += 1
            contact.last_attempt = timezone.now()
            contact.status = 'calling'
            contact.save()
        
        logger.info(f"Call originated for contact {contact.id}")
        
    except Exception as e:
        logger.error(f"Failed to originate call for contact {contact_id}: {e}")
        
        # Schedule retry
        contact.next_attempt = timezone.now() + timezone.timedelta(minutes=30)
        contact.save()

@shared_task
def monitor_extensions():
    """Monitor extension status via AMI"""
    ami = AMIConnection()
    
    try:
        if not await ami.connect():
            return
        
        # Get SIP peers status
        response = await ami.manager.send_action({'Action': 'SIPpeers'})
        
        # Process response and update extension status
        # Implementation details...
        
    except Exception as e:
        logger.error(f"Extension monitoring failed: {e}")

@shared_task
def calculate_metrics():
    """Calculate campaign metrics"""
    for campaign in Campaign.objects.filter(status='active'):
        try:
            # Calculate success rate
            total_processed = Contact.objects.filter(
                campaign=campaign,
                status__in=['completed', 'failed']
            ).count()
            
            successful = Contact.objects.filter(
                campaign=campaign,
                status='completed'
            ).count()
            
            # Update campaign metrics
            campaign.processed_contacts = total_processed
            campaign.successful_calls = successful
            campaign.save()
            
        except Exception as e:
            logger.error(f"Metrics calculation failed for campaign {campaign.id}: {e}")
```

#### **3.2 Celery Beat Schedule**
```python
# pbx_system/settings/base.py
from celery.schedules import crontab

CELERY_BEAT_SCHEDULE = {
    'run-dialer-engine': {
        'task': 'apps.monitoring.tasks.run_dialer_engine',
        'schedule': 30.0,  # Every 30 seconds
    },
    'monitor-extensions': {
        'task': 'apps.monitoring.tasks.monitor_extensions',
        'schedule': 60.0,  # Every minute
    },
    'calculate-metrics': {
        'task': 'apps.monitoring.tasks.calculate_metrics',
        'schedule': crontab(minute='*/5'),  # Every 5 minutes
    },
    'cleanup-old-calls': {
        'task': 'apps.monitoring.tasks.cleanup_old_calls',
        'schedule': crontab(hour=2, minute=0),  # Daily at 2 AM
    },
}

CELERY_TIMEZONE = 'America/Sao_Paulo'
```

### **FASE 4: API REST e Frontend (2-3 semanas)**

#### **4.1 Django REST Framework**
```python
# apps/api/serializers.py
from rest_framework import serializers
from apps.campaigns.models import Campaign, Contact
from apps.extensions.models import Extension

class CampaignSerializer(serializers.ModelSerializer):
    success_rate = serializers.ReadOnlyField()
    
    class Meta:
        model = Campaign
        fields = '__all__'

class ContactSerializer(serializers.ModelSerializer):
    class Meta:
        model = Contact
        fields = '__all__'

class ExtensionSerializer(serializers.ModelSerializer):
    is_available = serializers.ReadOnlyField()
    
    class Meta:
        model = Extension
        fields = '__all__'

# apps/api/views.py
from rest_framework import viewsets, status
from rest_framework.decorators import action
from rest_framework.response import Response
from django.db.models import Count, Avg
from .serializers import CampaignSerializer, ContactSerializer, ExtensionSerializer

class CampaignViewSet(viewsets.ModelViewSet):
    queryset = Campaign.objects.all()
    serializer_class = CampaignSerializer
    
    @action(detail=True, methods=['post'])
    def start(self, request, pk=None):
        campaign = self.get_object()
        campaign.status = 'active'
        campaign.save()
        return Response({'status': 'Campaign started'})
    
    @action(detail=True, methods=['post'])
    def pause(self, request, pk=None):
        campaign = self.get_object()
        campaign.status = 'paused'
        campaign.save()
        return Response({'status': 'Campaign paused'})
    
    @action(detail=True)
    def metrics(self, request, pk=None):
        campaign = self.get_object()
        
        metrics = {
            'total_contacts': campaign.total_contacts,
            'processed_contacts': campaign.processed_contacts,
            'success_rate': campaign.get_success_rate(),
            'calls_per_hour': self.get_calls_per_hour(campaign),
            'average_call_duration': self.get_avg_call_duration(campaign)
        }
        
        return Response(metrics)
    
    def get_calls_per_hour(self, campaign):
        # Implementation to calculate calls per hour
        pass
    
    def get_avg_call_duration(self, campaign):
        # Implementation to calculate average call duration
        pass

class ExtensionViewSet(viewsets.ModelViewSet):
    queryset = Extension.objects.all()
    serializer_class = ExtensionSerializer
    
    @action(detail=False)
    def dashboard(self, request):
        extensions = Extension.objects.all()
        
        dashboard_data = {
            'total': extensions.count(),
            'registered': extensions.filter(registered=True).count(),
            'available': extensions.filter(
                registered=True,
                status_line1='free'
            ).count(),
            'busy': extensions.filter(
                status_line1='busy'
            ).count(),
            'extensions': ExtensionSerializer(extensions, many=True).data
        }
        
        return Response(dashboard_data)
```

#### **4.2 WebSocket para Real-time Updates**
```python
# apps/api/consumers.py
import json
from channels.generic.websocket import AsyncWebsocketConsumer
from channels.db import database_sync_to_async
from apps.extensions.models import Extension
from apps.campaigns.models import Campaign

class DashboardConsumer(AsyncWebsocketConsumer):
    async def connect(self):
        await self.channel_layer.group_add("dashboard", self.channel_name)
        await self.accept()
    
    async def disconnect(self, close_code):
        await self.channel_layer.group_discard("dashboard", self.channel_name)
    
    async def receive(self, text_data):
        data = json.loads(text_data)
        message_type = data.get('type')
        
        if message_type == 'get_extensions_status':
            extensions_data = await self.get_extensions_status()
            await self.send(text_data=json.dumps({
                'type': 'extensions_status',
                'data': extensions_data
            }))
    
    @database_sync_to_async
    def get_extensions_status(self):
        extensions = Extension.objects.all()
        return [
            {
                'number': ext.number,
                'name': ext.name,
                'status_line1': ext.status_line1,
                'status_line2': ext.status_line2,
                'registered': ext.registered
            }
            for ext in extensions
        ]
    
    # WebSocket message handlers
    async def extension_status_update(self, event):
        await self.send(text_data=json.dumps({
            'type': 'extension_status',
            'extension': event['extension'],
            'status': event['status']
        }))
    
    async def campaign_metrics_update(self, event):
        await self.send(text_data=json.dumps({
            'type': 'campaign_metrics',
            'campaign_id': event['campaign_id'],
            'metrics': event['metrics']
        }))

# Signal handlers to send real-time updates
from django.db.models.signals import post_save
from django.dispatch import receiver
from channels.layers import get_channel_layer
from asgiref.sync import async_to_sync

@receiver(post_save, sender=Extension)
def extension_status_changed(sender, instance, **kwargs):
    channel_layer = get_channel_layer()
    async_to_sync(channel_layer.group_send)(
        "dashboard",
        {
            "type": "extension_status_update",
            "extension": instance.number,
            "status": instance.status_line1
        }
    )
```

---

## 📊 Comparação entre as Opções

| Aspecto | PHP Moderna + Docker | Python/Django |
|---------|---------------------|----------------|
| **Tempo de Implementação** | 8-10 semanas | 12-15 semanas |
| **Risco de Migração** | ⭐⭐ (Baixo) | ⭐⭐⭐⭐ (Alto) |
| **Reaproveitamento de Código** | 70-80% | 10-20% |
| **Performance** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Escalabilidade** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Manutenibilidade** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Ecossistema** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Curva de Aprendizado** | ⭐⭐ (Baixa) | ⭐⭐⭐⭐ (Alta) |
| **Custo de Desenvolvimento** | Menor | Maior |
| **Futuro-prova** | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

---

## 🎯 Recomendação Final

### **ABORDAGEM HÍBRIDA RECOMENDADA:**

**FASE 1 (Imediato): Migração PHP + Docker** 
- Containerizar sistema atual para ganhar estabilidade
- Modernizar PHP para versão 8.2/8.3
- Implementar CI/CD e monitoramento
- **Prazo: 8-10 semanas**
- **ROI: Imediato**

**FASE 2 (Médio Prazo): Migração Gradual para Python**
- Migrar módulos não-críticos primeiro (relatórios, dashboards)
- Manter core PHP funcionando
- Implementar APIs REST em Django
- **Prazo: 6-8 meses**
- **ROI: Alto**

**FASE 3 (Longo Prazo): Sistema Totalmente Python**
- Migrar motor do discador
- Unificar toda stack em Python/Django
- Implementar microserviços
- **Prazo: 12-18 meses**
- **ROI: Máximo**

### **Benefícios da Abordagem Híbrida:**

✅ **Risco Minimizado** - Sistema continua operando durante toda migração
✅ **ROI Incremental** - Benefícios visíveis a cada fase
✅ **Aprendizado Gradual** - Team se familiariza com novas tecnologias
✅ **Flexibilidade** - Pode pausar/ajustar estratégia conforme necessário
✅ **Investimento Protegido** - Não perde investimento em código existente

### **Cronograma Sugerido:**

```
Mês 1-2: Setup Docker + PHP 8.2
Mês 3-4: Modernização código PHP
Mês 5-6: Implementação monitoring moderno
Mês 7-9: Setup Django paralelo
Mês 10-12: Migração dashboards para Django
Mês 13-15: APIs REST Django
Mês 16-18: Migração motor discador
```

Esta abordagem oferece o melhor custo-benefício, minimiza riscos e garante que o sistema continue operacional durante toda a modernização! 🚀
