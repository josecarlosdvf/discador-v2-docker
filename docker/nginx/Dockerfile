FROM nginx:1.24-alpine

# Instalar dependências
RUN apk add --no-cache \
    curl \
    openssl \
    && rm -rf /var/cache/apk/*

# Remover configuração padrão
RUN rm /etc/nginx/conf.d/default.conf

# Copiar configurações personalizadas
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf

# Criar diretórios necessários
RUN mkdir -p /var/cache/nginx \
    /var/log/nginx \
    /etc/nginx/ssl \
    /var/www/html \
    && chown -R nginx:nginx /var/cache/nginx /var/log/nginx

# Gerar certificado SSL autoassinado para desenvolvimento
RUN openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/discador.key \
    -out /etc/nginx/ssl/discador.crt \
    -subj "/C=BR/ST=SP/L=SaoPaulo/O=Discador/OU=IT/CN=localhost"

# Criar página de saúde
RUN mkdir -p /var/www/html && echo '<?php echo "OK"; ?>' > /var/www/html/health.php

# Expor portas
EXPOSE 80 443

# Comando padrão
CMD ["nginx", "-g", "daemon off;"]
