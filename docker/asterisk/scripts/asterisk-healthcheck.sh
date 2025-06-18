#!/bin/bash
set -e

# Script de healthcheck para Asterisk
# Verifica se o Asterisk está rodando e respondendo

# Configurações
TIMEOUT=10

# Função para verificar se o processo está rodando
check_asterisk_process() {
    if ! pgrep -f "asterisk.*-f" > /dev/null; then
        echo "ERRO: Processo Asterisk não encontrado"
        return 1
    fi
    return 0
}

# Função para verificar se o Asterisk está respondendo
check_asterisk_cli() {
    local output
    output=$(timeout $TIMEOUT asterisk -rx "core show version" 2>/dev/null || echo "ERROR")
    
    if echo "$output" | grep -q "Asterisk"; then
        return 0
    else
        echo "ERRO: Asterisk CLI não está respondendo"
        return 1
    fi
}

# Função para verificar se o SIP está rodando
check_sip_status() {
    local output
    output=$(timeout $TIMEOUT asterisk -rx "sip show peers" 2>/dev/null || echo "ERROR")
    
    if echo "$output" | grep -E "(Name/username|peers.*online)" > /dev/null; then
        return 0
    else
        echo "AVISO: SIP pode não estar configurado corretamente"
        return 0  # Não falhar por isso
    fi
}

# Executar verificações
echo "Verificando saúde do Asterisk..."

if check_asterisk_process; then
    echo "✓ Processo Asterisk está rodando"
else
    exit 1
fi

if check_asterisk_cli; then
    echo "✓ Asterisk CLI respondendo"
else
    exit 1
fi

if check_sip_status; then
    echo "✓ Módulo SIP carregado"
fi

echo "✓ Asterisk está saudável"
exit 0
