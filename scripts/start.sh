#!/bin/bash

# Script para inicializar o ambiente Docker do Discador v2
# Para uso no Windows com WSL2

echo "🚀 Inicializando Sistema Discador v2.0"
echo "======================================"

# Verificar se o Docker está rodando
if ! docker info >/dev/null 2>&1; then
    echo "❌ ERRO: Docker não está rodando ou não está acessível"
    echo "   Certifique-se de que o Docker Desktop está iniciado"
    exit 1
fi

echo "✅ Docker está rodando"

# Verificar se o arquivo .env existe
if [ ! -f ".env" ]; then
    echo "❌ ERRO: Arquivo .env não encontrado"
    echo "   Copie o arquivo .env.example para .env e configure as variáveis"
    exit 1
fi

echo "✅ Arquivo .env encontrado"

# Criar volumes se não existirem
echo "📦 Criando volumes Docker..."
docker volume create discador_mariadb_data 2>/dev/null || true
docker volume create discador_redis_data 2>/dev/null || true
docker volume create discador_asterisk_sounds 2>/dev/null || true
docker volume create discador_asterisk_spool 2>/dev/null || true
docker volume create discador_asterisk_recordings 2>/dev/null || true
docker volume create discador_nginx_ssl 2>/dev/null || true
docker volume create discador_portainer_data 2>/dev/null || true

echo "✅ Volumes criados"

# Parar containers existentes se estiverem rodando
echo "🛑 Parando containers existentes..."
docker-compose down 2>/dev/null || true

# Construir imagens
echo "🔨 Construindo imagens Docker..."
docker-compose build --no-cache

if [ $? -ne 0 ]; then
    echo "❌ ERRO: Falha ao construir as imagens"
    exit 1
fi

echo "✅ Imagens construídas com sucesso"

# Iniciar os serviços
echo "🚀 Iniciando serviços..."
docker-compose up -d

if [ $? -ne 0 ]; then
    echo "❌ ERRO: Falha ao iniciar os serviços"
    exit 1
fi

echo "✅ Serviços iniciados"

# Aguardar todos os serviços ficarem saudáveis
echo "⏳ Aguardando serviços ficarem prontos..."
sleep 10

# Verificar status dos containers
echo ""
echo "📊 Status dos Containers:"
echo "========================"
docker-compose ps

# Verificar logs se algum container falhou
echo ""
echo "📋 Verificando saúde dos serviços..."

# Função para verificar saúde
check_service() {
    local service=$1
    local url=$2
    
    echo -n "   $service: "
    
    for i in {1..30}; do
        if curl -s "$url" >/dev/null 2>&1; then
            echo "✅ OK"
            return 0
        fi
        sleep 2
        echo -n "."
    done
    
    echo "❌ TIMEOUT"
    return 1
}

# Verificar serviços
check_service "MariaDB" "tcp://localhost:3306" || true
check_service "Redis" "tcp://localhost:6379" || true
check_service "Nginx/PHP" "http://localhost" || true

echo ""
echo "🎉 Sistema Discador v2.0 iniciado!"
echo ""
echo "📱 Acesso ao Sistema:"
echo "   🌐 Web Interface: http://localhost"
echo "   🌐 HTTPS: https://localhost"
echo "   🗄️  MariaDB: localhost:3306"
echo "   ⚡ Redis: localhost:6379"
echo "   📞 Asterisk SIP: localhost:5060"
echo "   🐳 Portainer: http://localhost:9000"
echo ""
echo "📝 Comandos úteis:"
echo "   Parar: docker-compose down"
echo "   Logs: docker-compose logs -f [serviço]"
echo "   Status: docker-compose ps"
echo "   Reiniciar: docker-compose restart [serviço]"
echo ""
echo "📁 Diretórios importantes:"
echo "   Código fonte: ./src/"
echo "   Logs: ./logs/"
echo "   Configurações: ./config/"
echo ""
