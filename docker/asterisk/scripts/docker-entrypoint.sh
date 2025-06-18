#!/bin/bash
set -e

echo "Inicializando container Asterisk..."

# Aguardar banco de dados estar disponível
echo "Aguardando conexão com o banco de dados..."
while ! nc -z ${DB_HOST:-database} ${DB_PORT:-3306}; do
    echo "Banco de dados não disponível ainda. Aguardando..."
    sleep 2
done
echo "✓ Banco de dados disponível"

# Criar diretórios necessários
mkdir -p /var/log/asterisk \
         /var/spool/asterisk \
         /var/run/asterisk \
         /var/lib/asterisk/sounds \
         /var/lib/asterisk/moh \
         /var/lib/asterisk/agi-bin

# Ajustar permissões
chown -R asterisk:asterisk /var/log/asterisk \
                          /var/spool/asterisk \
                          /var/run/asterisk \
                          /var/lib/asterisk

# Verificar se as configurações existem
if [ ! -f "/etc/asterisk/asterisk.conf" ]; then
    echo "ERRO: Configurações do Asterisk não encontradas"
    exit 1
fi

# Testar configuração do Asterisk
echo "Testando configuração do Asterisk..."
asterisk -T -C /etc/asterisk/asterisk.conf || {
    echo "ERRO: Configuração do Asterisk inválida"
    exit 1
}

echo "✓ Configuração do Asterisk válida"

# Inicializar banco de dados do Asterisk se necessário
if [ -n "${DB_HOST}" ]; then
    echo "Configurando integração com banco de dados..."
    # Aqui você pode adicionar scripts para inicializar tabelas do Asterisk
fi

echo "Iniciando Asterisk..."
exec "$@"
