FROM php:8.2-fpm-bullseye

# Definir argumentos
ARG DEBIAN_FRONTEND=noninteractive

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    # Ferramentas básicas
    curl \
    wget \
    git \
    unzip \
    vim \
    nano \
    htop \
    # Bibliotecas para PHP
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    # Bibliotecas para MariaDB
    default-mysql-client \
    libmariadb-dev \
    # Bibliotecas para Redis
    libssl-dev \
    # Ferramentas para Asterisk
    sox \
    lame \
    ffmpeg \
    # Utilitários de rede
    telnet \
    netcat \
    && rm -rf /var/lib/apt/lists/*

# Configurar extensões GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Instalar extensões PHP
RUN docker-php-ext-install \
    gd \
    zip \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    xml \
    bcmath \
    opcache \
    pcntl \
    sockets

# Instalar Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar usuário www-data
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Criar diretórios necessários
RUN mkdir -p /var/www/html \
    /var/log/php \
    /var/run/php \
    && chown -R www-data:www-data /var/www/html /var/log/php /var/run/php

# Copiar configurações personalizadas
COPY php.ini /usr/local/etc/php/
COPY php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY scripts/php-fpm-healthcheck.sh /usr/local/bin/php-fpm-healthcheck
RUN chmod +x /usr/local/bin/php-fpm-healthcheck

# Copiar script de inicialização
COPY scripts/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Definir diretório de trabalho
WORKDIR /var/www/html

# Expor porta
EXPOSE 9000

# Definir usuário
USER www-data

# Script de inicialização
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
