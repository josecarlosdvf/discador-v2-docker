#!/bin/bash
set -e

# Script de healthcheck simples para PHP-FPM
echo "Verificando saúde do PHP-FPM..."

# Verifica se o socket/porta está ouvindo
if netstat -an | grep ":9000" | grep LISTEN > /dev/null; then
    echo "✓ PHP-FPM está ouvindo na porta 9000"
    exit 0
else
    echo "✗ PHP-FPM não está ouvindo na porta 9000"
    exit 1
fi
        return 1
    fi
    
    return 0
}

# Função para verificar o status do FPM via ping
check_fpm_ping() {
    local response
    response=$(echo -e "GET /fpm-ping HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n" | \
               timeout $TIMEOUT nc -w $TIMEOUT $FPM_HOST $FPM_PORT 2>/dev/null | \
               tail -1 2>/dev/null || echo "ERROR")
    
    if [ "$response" = "pong" ]; then
        return 0
    else
        echo "ERRO: PHP-FPM ping falhou. Resposta: $response"
        return 1
    fi
}

# Executar verificações
echo "Verificando saúde do PHP-FPM..."

if check_fpm_process; then
    echo "✓ Processo PHP-FPM está rodando"
else
    exit 1
fi

if check_fpm_ping; then
    echo "✓ PHP-FPM respondendo ao ping"
else
    exit 1
fi

echo "✓ PHP-FPM está saudável"
exit 0
