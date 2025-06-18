#!/bin/bash

# Script para ver logs dos serviços

SERVICE=${1:-""}

if [ -z "$SERVICE" ]; then
    echo "📋 Logs de todos os serviços:"
    echo "============================"
    docker-compose logs -f --tail=100
else
    echo "📋 Logs do serviço: $SERVICE"
    echo "============================"
    docker-compose logs -f --tail=100 "$SERVICE"
fi
