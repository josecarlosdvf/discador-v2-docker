# Guia de Uso - Discador v2.0 com Docker

## Sistema Configurado ✅

O Discador v2.0 foi modernizado e está funcionando em ambiente Docker com:

- **✅ Containers Docker ativos**
- **✅ Redis configurado e funcionando**  
- **✅ Interface web acessível**
- **✅ GUI de gerenciamento implementada**
- **✅ APIs de controle funcionais**

## Como Usar

### 1. Acesso à Interface Web

**Dashboard Principal:** http://localhost:8080

**Credenciais padrão:**
- Usuário: `admin`
- Senha: `admin123`

### 2. Gerenciamento do Discador v2.0

Na interface web, clique em **"Gerenciamento"** no menu lateral (seção Discador v2.0) para acessar:

#### Status do Sistema
- Status do Master Process
- Workers ativos/total  
- Tarefas na fila
- Status do Redis

#### Controles Disponíveis
- **Iniciar Sistema**: Inicia o processo mestre e workers
- **Parar Sistema**: Para todos os processos
- **Reiniciar Sistema**: Reinicia o sistema completo
- **Status Detalhado**: Informações completas dos componentes

#### Monitoramento
- **Dashboard Completo**: Monitor dedicado em nova janela
- **Status Workers**: Lista todos os workers e seus status
- **Status da Fila**: Mostra tarefas pendentes e processando
- **Ver Logs**: Exibe logs do sistema em tempo real

#### Manutenção
- **Fazer Backup**: Backup completo do sistema
- **Limpeza**: Remove logs antigos e dados temporários
- **Otimizar**: Otimiza banco de dados e cache
- **Diagnóstico**: Testa todos os componentes do sistema

### 3. Monitor Dashboard

**URL:** http://localhost:8080/monitor-dashboard.php

Dashboard dedicado com:
- Métricas em tempo real
- Gráficos de performance
- Status detalhado dos workers
- Console de logs ao vivo
- Auto-refresh a cada 30 segundos

### 4. Controle via Scripts (Opcional)

```batch
# Usar o sistema Docker
start_discador_docker.bat     # Iniciar tudo
stop_discador_docker.bat      # Parar tudo
status_discador_docker.bat    # Ver status
monitor_discador_docker.bat   # Monitor CLI
logs_discador_docker.bat      # Ver logs
```

### 5. Comandos Docker Úteis

```bash
# Ver status dos containers
docker compose ps

# Ver logs em tempo real
docker compose logs -f

# Acessar container PHP
docker compose exec php bash

# Reiniciar todos os serviços
docker compose restart

# Parar tudo
docker compose down

# Iniciar tudo
docker compose up -d
```

## Portas Utilizadas

- **8080**: Interface web principal
- **8443**: HTTPS (se configurado)
- **3307**: MariaDB (acesso externo)
- **6380**: Redis (acesso externo)
- **5060**: Asterisk SIP
- **5038**: Asterisk AMI
- **9000**: Portainer (gerenciamento Docker)

## Próximos Passos

1. **Configurar Asterisk**: Ajustar conexão AMI nas configurações
2. **Criar Campanhas**: Usar a interface web para criar campanhas de discagem
3. **Configurar Integrações**: Conectar com sistemas externos via API
4. **Monitorar**: Usar o dashboard para acompanhar performance

## Arquitetura Master-Worker

O sistema implementa uma arquitetura distribuída:

- **Master Process**: Gerencia workers e monitora sistema
- **Campaign Workers**: Processam campanhas de discagem
- **Monitoring Workers**: Monitoram eventos do Asterisk
- **Redis**: Fila de tarefas e cache distribuído
- **Web Interface**: Controle e monitoramento via browser

## Backup e Manutenção

- **Backups automáticos** configurados
- **Limpeza de logs** automática
- **Monitoramento de saúde** dos containers
- **Recuperação automática** de falhas

## Suporte

- **Logs**: Acesse via interface web ou `docker compose logs`
- **Diagnóstico**: Use a ferramenta de diagnóstico na interface
- **Status**: Monitore pelo dashboard em tempo real

---

**Sistema pronto para uso!** 🚀

O Discador v2.0 está completamente funcional e pode ser gerenciado via interface web moderna.
