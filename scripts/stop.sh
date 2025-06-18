#!/bin/bash

# Script para parar e limpar o ambiente Docker do Discador v2

echo "🛑 Parando Sistema Discador v2.0"
echo "================================="

# Parar todos os containers
echo "📦 Parando containers..."
docker-compose down

if [ "$1" = "--volumes" ] || [ "$1" = "-v" ]; then
    echo "🗑️  Removendo volumes..."
    docker-compose down -v
    docker volume prune -f
    echo "✅ Volumes removidos"
fi

if [ "$1" = "--all" ] || [ "$1" = "-a" ]; then
    echo "🗑️  Removendo tudo (containers, volumes, imagens)..."
    docker-compose down -v --rmi all
    docker volume prune -f
    docker image prune -f
    echo "✅ Tudo removido"
fi

echo "✅ Sistema parado com sucesso"
