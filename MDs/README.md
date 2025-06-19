# Sistema Discador v2.0 - Ambiente Dockerizado

Sistema de discador modernizado executando em containers Docker com PHP 8.2, Nginx, MariaDB, Redis e Asterisk.

## 🏗️ Arquitetura

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

## 📋 Pré-requisitos

- Windows 10/11 com WSL2 ativado
- Docker Desktop para Windows
- 4GB RAM mínimo (8GB recomendado)
- 10GB espaço em disco

## 🚀 Iniciando o Ambiente

### 1. Configuração Inicial

```bash
# Clone ou acesse o diretório do projeto
cd discador_v2

# Copie e configure as variáveis de ambiente
cp .env.example .env
# Edite o .env conforme necessário
```

### 2. Iniciando os Serviços

```bash
# Linux/WSL2
chmod +x scripts/*.sh
./scripts/start.sh

# Ou manualmente
docker-compose up -d --build
```

### 3. Verificando o Status

Acesse: http://localhost para ver o painel de status dos serviços.

## 🐳 Serviços Disponíveis

| Serviço | Porta | Acesso | Descrição |
|---------|-------|---------|-----------|
| **Nginx** | 80, 443 | http://localhost | Servidor web |
| **PHP-FPM** | 9000 | Interno | Processador PHP |
| **MariaDB** | 3306 | localhost:3306 | Banco de dados |
| **Redis** | 6379 | localhost:6379 | Cache em memória |
| **Asterisk** | 5060 | localhost:5060 | PBX/SIP |
| **Portainer** | 9000 | http://localhost:9000 | Gerenciamento Docker |

## 📁 Estrutura de Diretórios

```
discador_v2/
├── docker/                 # Configurações Docker
│   ├── php/                # PHP-FPM container
│   ├── nginx/              # Nginx container
│   └── asterisk/           # Asterisk container
├── src/                    # Código fonte da aplicação
├── config/                 # Configurações dos serviços
├── scripts/                # Scripts utilitários
├── logs/                   # Logs dos serviços
├── data/                   # Dados persistentes
├── docker-compose.yml      # Configuração principal
└── .env                    # Variáveis de ambiente
```

## 🔧 Comandos Úteis

### Gerenciamento de Containers

```bash
# Iniciar todos os serviços
./scripts/start.sh

# Parar todos os serviços
./scripts/stop.sh

# Parar e remover volumes
./scripts/stop.sh --volumes

# Ver logs de todos os serviços
./scripts/logs.sh

# Ver logs de um serviço específico
./scripts/logs.sh php
./scripts/logs.sh nginx
./scripts/logs.sh database
./scripts/logs.sh asterisk

# Status dos containers
docker-compose ps

# Reiniciar um serviço
docker-compose restart php

# Acessar shell de um container
docker-compose exec php bash
docker-compose exec database mysql -u root -p
```

### Desenvolvimento

```bash
# Rebuild apenas um serviço
docker-compose build php
docker-compose up -d php

# Acompanhar logs em tempo real
docker-compose logs -f php

# Executar comandos PHP
docker-compose exec php php -v
docker-compose exec php composer install
```

## 🔒 Configurações de Segurança

### Produção

Antes de usar em produção, altere:

1. **Senhas no .env**:
   - `DB_ROOT_PASSWORD`
   - `DB_PASSWORD`
   - `REDIS_PASSWORD`
   - `ASTERISK_MANAGER_PASSWORD`

2. **SSL/HTTPS**:
   - Gere certificados válidos
   - Configure nginx para redirecionar HTTP → HTTPS

3. **Firewall**:
   - Exponha apenas portas necessárias
   - Configure iptables/ufw

## 🐛 Solução de Problemas

### Container não inicia

```bash
# Ver logs detalhados
docker-compose logs [serviço]

# Verificar configuração
docker-compose config

# Rebuild sem cache
docker-compose build --no-cache [serviço]
```

### Problemas de permissão

```bash
# No WSL2, ajustar permissões
sudo chown -R $USER:$USER src/ logs/ data/
```

### Banco não conecta

```bash
# Verificar se MariaDB está rodando
docker-compose exec database mysql -u root -p

# Recriar banco
docker-compose down
docker volume rm discador_mariadb_data
docker-compose up -d
```

### Asterisk não inicia

```bash
# Verificar configuração
docker-compose exec asterisk asterisk -T

# Ver logs específicos
docker-compose logs asterisk
```

## 📊 Monitoramento

### Health Checks

Todos os serviços incluem health checks automáticos:

```bash
# Ver status de saúde
docker-compose ps
```

### Logs

```bash
# Localização dos logs
./logs/
├── nginx/          # Logs do Nginx
├── php/            # Logs do PHP
├── mariadb/        # Logs do MariaDB
└── asterisk/       # Logs do Asterisk
```

### Métricas

- **Portainer**: Interface web para monitorar containers
- **Status Page**: http://localhost/status (página customizada)

## 🔄 Backup e Restore

### Backup

```bash
# Backup do banco de dados
docker-compose exec database mysqldump -u root -p discador > backup_$(date +%Y%m%d).sql

# Backup dos volumes
docker run --rm -v discador_mariadb_data:/data -v $(pwd):/backup alpine tar czf /backup/mariadb_backup.tar.gz /data
```

### Restore

```bash
# Restore do banco
cat backup_20241217.sql | docker-compose exec -T database mysql -u root -p discador
```

## 🆙 Atualizações

### Atualizar imagens base

```bash
# Baixar novas versões
docker-compose pull

# Rebuild com novas versões
docker-compose up -d --build
```

### Migração de dados

Ver documentação específica em `docs/migration.md`

## 📞 Suporte

- **Logs**: Sempre verificar logs primeiro
- **Status**: http://localhost para status dos serviços
- **Documentação**: Ver pasta `docs/` para mais detalhes

## 🎯 Próximos Passos

1. **Migrar código legado** para `src/`
2. **Configurar Asterisk** com ramais/troncos
3. **Implementar APIs** para integração
4. **Configurar monitoramento** em produção
5. **Implementar CI/CD** para deploys automatizados

---

**Versão**: 2.0.0  
**Data**: Dezembro 2024  
**Docker**: Engine 20.10+  
**Compose**: v2.0+
