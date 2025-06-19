# Sistema Discador VoIP v2.0 - Multi-Tenant

[![Validação](https://img.shields.io/badge/Validação-96.6%25-green)](scripts/validate_offline.php)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange)](https://www.mysql.com/)
[![Docker](https://img.shields.io/badge/Docker-Ready-blue)](https://www.docker.com/)

> **Sistema completo de discador VoIP modernizado com arquitetura multi-tenant, billing avançado e controle de custos em tempo real.**

## 🚀 Características Principais

### ✨ Multi-Tenancy Completo
- **Isolamento por empresa** com subdomínios ou paths
- **Gestão centralizada** de múltiplas empresas
- **Configurações personalizadas** por tenant
- **Escalabilidade** para crescimento

### 💰 Centro de Custos e Billing
- **Cálculo em tempo real** de custos por ligação
- **Tarifação flexível** por tipo de destino
- **Faturas automáticas** mensais
- **Relatórios financeiros** detalhados
- **Alertas de limite** e vencimento

### 👥 Gestão Avançada de Usuários
- **Perfis multi-nível** (Admin Global, Master Empresa, Supervisor, Operador)
- **Permissões granulares** por funcionalidade
- **Vinculação a campanhas** e filas
- **Controle de acesso** baseado em tenant

### 📊 Dashboard e Campanhas
- **Interface moderna** responsiva
- **Estatísticas em tempo real** 
- **Gestão completa** de campanhas
- **Listas de contatos** otimizadas
- **APIs REST** para integração

## 🏗️ Arquitetura

```
📁 Sistema Discador v2.0/
├── 🏢 Multi-Tenant Core
│   ├── TenantManager.php (Gestão de empresas)
│   ├── MultiTenantAuth.php (Autenticação)
│   └── CompanyRegistration.php (Registro)
├── 💰 Billing & Cost Center  
│   ├── BillingManager.php (Lógica de billing)
│   ├── billing.php (Interface web)
│   └── billing-reports.php (API REST)
├── 👥 User Management
│   ├── UserManager.php (Gestão de usuários)
│   └── users.php (Interface CRUD)
├── 📞 Campaign Management
│   ├── CampaignManager.php (Lógica de campanhas)
│   ├── campaigns.php (Interface web)
│   └── ContactListManager.php (Listas)
└── 🗄️ Database Schema
    ├── 02_multi_tenant_schema.sql (446 linhas)
    └── 03_billing_schema.sql (530 linhas)
```

## 🛠️ Instalação

### Pré-requisitos
- **PHP 8.2+** com extensões: PDO, MySQL, Redis
- **MySQL 8.0+** ou MariaDB 10.5+
- **Redis 6.0+** (opcional, para cache)
- **Nginx/Apache** com mod_rewrite

### Instalação Rápida

```bash
# 1. Clone o repositório
git clone [repository-url] discador_v2
cd discador_v2

# 2. Configure ambiente (Docker recomendado)
docker-compose up -d

# 3. Instale schema multi-tenant
php scripts/install_multi_tenant.php

# 4. Instale sistema de billing
php scripts/install_billing.php

# 5. Configure empresa demo (opcional)
php setup_demo_mode.php
```

### Instalação Manual

```bash
# 1. Configure banco de dados
mysql -u root -p < scripts/sql/02_multi_tenant_schema.sql
mysql -u root -p < scripts/sql/03_billing_schema.sql

# 2. Configure variáveis de ambiente
cp .env.example .env
# Edite .env com suas configurações

# 3. Valide instalação
php scripts/validate_offline.php
```

## 🎯 Como Usar

### Acesso Administrativo
1. **Admin Global**: Acesso completo a todas as empresas
   - Login: `admin@discador.com`
   - Cadastrar novas empresas
   - Aprovar registros
   - Gerenciar billing global

### Acesso por Empresa
1. **Registrar Empresa**: `/register-company.php`
2. **Login Dual**: `/login.php`
3. **Dashboard**: `/dashboard.php`

### APIs Disponíveis

```bash
# Estatísticas em tempo real
GET /api/real-time-stats.php?empresa_id=1

# Relatórios de billing  
GET /api/billing-reports.php?action=stats&empresa_id=1
POST /api/billing-reports.php?action=generate_invoice

# Gestão de campanhas
GET /api/campaigns.php?action=list&empresa_id=1
```

## 📊 Funcionalidades por Módulo

### 🏢 Multi-Tenancy
- [x] Isolamento de dados por empresa
- [x] Subdomínios/paths personalizados
- [x] Configurações por tenant
- [x] Usuários multi-nível
- [x] Portal de registro
- [x] Workflow de aprovação

### 💰 Billing & Custos
- [x] Cálculo automático de custos
- [x] Tarifas personalizadas
- [x] Faturas mensais
- [x] Pagamentos e histórico
- [x] Alertas automáticos
- [x] Relatórios financeiros
- [x] APIs REST completas

### 👥 Gestão de Usuários
- [x] CRUD completo multi-tenant
- [x] Perfis e permissões
- [x] Vinculação a campanhas
- [x] Controle de acesso

### 📞 Campanhas e Discador
- [x] Dashboard moderno
- [x] Gestão de campanhas
- [x] Listas de contatos
- [x] Estatísticas em tempo real
- [x] APIs de integração

## 🔧 Configuração

### Variáveis de Ambiente

```bash
# Banco de Dados
DB_HOST=localhost
DB_PORT=3306
DB_NAME=discador_v2
DB_USER=discador_user
DB_PASSWORD=sua_senha

# Redis (opcional)
REDIS_HOST=localhost
REDIS_PORT=6379

# Aplicação
APP_DEBUG=false
APP_URL=https://discador.example.com
```

### Tarifas Padrão

| Tipo | Tarifa/min | Descrição |
|------|------------|-----------|
| Fixo Local | R$ 0,08 | Mesmo DDD |
| Fixo DDD | R$ 0,12 | DDD diferente |
| Celular Local | R$ 0,35 | Celular mesmo DDD |
| Celular DDD | R$ 0,45 | Celular DDD diferente |
| Internacional | R$ 2,50 | Chamadas internacionais |
| Especial | R$ 1,20 | 0800, 4004, etc |

## 📋 Status das Fases

### ✅ FASE 1: MODERNIZAÇÃO (CONCLUÍDA)
- [x] Containerização Docker
- [x] Arquitetura Master-Worker
- [x] Sistema de monitoramento
- [x] APIs modernas
- [x] Interface web atualizada

### ✅ FASE 2: MULTI-TENANT (95% CONCLUÍDA)
- [x] Schema multi-tenant
- [x] Portal de cadastro
- [x] Gestão de usuários
- [x] Dashboard discador
- [x] **Centro de custos e billing**
- [x] APIs REST completas

### ⏳ FASE 3: OTIMIZAÇÃO (PLANEJADA)
- [ ] Testes de performance
- [ ] Configurações de produção
- [ ] Monitoramento avançado
- [ ] Backup estratégico

## 🧪 Validação e Testes

```bash
# Validação offline (sem banco)
php scripts/validate_offline.php

# Validação completa (com banco)  
php scripts/validate_system.php

# Teste de conexão
php test_db_connection.php
```

**Status Atual**: ✅ **96.6% validado e funcional**

## 📝 Documentação

- [Lista Completa de Tarefas](MDs/todo.md)
- [Relatório de Implementação - Billing](MDs/relatorio_implementacao_2.6_billing.md)
- [Relatório de Features 2.3 e 2.4](MDs/relatorio_implementacao_2.3_2.4.md)
- [Planos de Migração](MDs/planos_de_migracao.md)

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo `LICENSE` para detalhes.

## 🆘 Suporte

- **Issues**: [GitHub Issues](../../issues)
- **Documentação**: [Wiki do Projeto](../../wiki)
- **Email**: suporte@discador.com

---

**Sistema Discador VoIP v2.0** - Transformando comunicação empresarial com tecnologia moderna e multi-tenancy avançado.

*Desenvolvido com ❤️ para empresas que precisam de soluções VoIP escaláveis e robustas.*
