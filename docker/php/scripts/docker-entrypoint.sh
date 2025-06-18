#!/bin/bash
set -e

echo "Inicializando container PHP-FPM..."

# Aguardar banco de dados estar disponível
echo "Aguardando conexão com o banco de dados..."
while ! nc -z ${DB_HOST:-database} ${DB_PORT:-3306}; do
    echo "Banco de dados não disponível ainda. Aguardando..."
    sleep 2
done
echo "✓ Banco de dados disponível"

# Aguardar Redis estar disponível
echo "Aguardando conexão com Redis..."
while ! nc -z ${REDIS_HOST:-redis} ${REDIS_PORT:-6379}; do
    echo "Redis não disponível ainda. Aguardando..."
    sleep 2
done
echo "✓ Redis disponível"

# Criar diretórios se não existirem
mkdir -p /var/log/php
mkdir -p /var/run/php
mkdir -p /tmp/sessions

# Ajustar permissões
chown -R www-data:www-data /var/log/php /var/run/php /tmp/sessions || echo "AVISO: Não foi possível alterar proprietário de alguns diretórios"
chmod 755 /var/log/php /var/run/php || echo "AVISO: Não foi possível alterar permissões de alguns diretórios"
chmod 1777 /tmp/sessions || echo "AVISO: Não foi possível alterar permissões do diretório de sessões"

# Verificar se o diretório da aplicação existe e tem conteúdo
if [ ! -f "/var/www/html/index.php" ]; then
    echo "AVISO: index.php não encontrado. Criando arquivo de teste..."
    cat > /var/www/html/index.php << 'EOF'
<?php
phpinfo();
EOF
    chown www-data:www-data /var/www/html/index.php
fi

# Testar conexão com banco de dados
echo "Testando conexão com banco de dados..."
php -r "
try {
    \$pdo = new PDO('mysql:host=${DB_HOST};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASSWORD}');
    echo '✓ Conexão com banco de dados OK\n';
} catch (Exception \$e) {
    echo 'ERRO: Falha na conexão com banco: ' . \$e->getMessage() . '\n';
}
"

# Executar comando passado como argumento
echo "Iniciando PHP-FPM..."
exec "$@"
