FROM debian:bullseye-slim

# Definir argumentos
ARG DEBIAN_FRONTEND=noninteractive
ARG ASTERISK_VERSION=18.26.2

# Instalar dependências
RUN apt-get update && apt-get install -y \
    # Ferramentas básicas
    wget \
    curl \
    gnupg2 \
    build-essential \
    # Dependências de compilação do Asterisk
    libssl-dev \
    libncurses-dev \
    libnewt-dev \
    libxml2-dev \
    libsqlite3-dev \
    uuid-dev \
    libjansson-dev \
    libedit-dev \
    libsrtp2-dev \
    # Dependências de áudio
    sox \
    lame \
    ffmpeg \
    # Dependências de banco de dados
    default-mysql-client \
    libmariadb-dev \
    unixodbc-dev \
    # Utilitários
    vim \
    nano \
    htop \
    telnet \
    netcat-openbsd \
    procps \
    rsyslog \
    logrotate \
    && rm -rf /var/lib/apt/lists/*

# Criar usuário asterisk
RUN useradd -r -d /var/lib/asterisk -s /bin/bash asterisk

# Baixar e compilar Asterisk
WORKDIR /tmp
RUN wget http://downloads.asterisk.org/pub/telephony/asterisk/asterisk-${ASTERISK_VERSION}.tar.gz \
    && tar -xzf asterisk-${ASTERISK_VERSION}.tar.gz \
    && cd asterisk-${ASTERISK_VERSION} \
    && ./configure --with-jansson-bundled --with-pjproject-bundled \
    && make menuselect.makeopts \    && menuselect/menuselect \
        --disable BUILD_NATIVE \
        --enable CORE-SOUNDS-EN-ULAW \
        --enable CORE-SOUNDS-EN-ALAW \
        --enable CORE-SOUNDS-EN-G722 \
        --enable CORE-SOUNDS-EN-GSM \
        --enable MOH-OPSOUND-ULAW \
        --enable MOH-OPSOUND-ALAW \
        --enable MOH-OPSOUND-G722 \
        --enable app_voicemail \
        --enable chan_sip \
        --enable res_musiconhold \
        --enable app_queue \
        --enable app_dial \
        --enable app_playback \
        --enable app_record \
        menuselect.makeopts \
    && make -j$(nproc) \
    && make install \
    && make samples \
    && make config \
    && ldconfig \
    && cd / \
    && rm -rf /tmp/asterisk-${ASTERISK_VERSION}*

# Criar diretórios necessários
RUN mkdir -p \
    /etc/asterisk \
    /var/lib/asterisk \
    /var/log/asterisk \
    /var/spool/asterisk \
    /var/run/asterisk \
    /usr/lib/asterisk/modules

# Ajustar permissões
RUN chown -R asterisk:asterisk \
    /etc/asterisk \
    /var/lib/asterisk \
    /var/log/asterisk \
    /var/spool/asterisk \
    /var/run/asterisk \
    /usr/lib/asterisk

# Copiar configurações personalizadas
COPY asterisk.conf /etc/asterisk/
COPY sip.conf /etc/asterisk/
COPY extensions.conf /etc/asterisk/
COPY manager.conf /etc/asterisk/
COPY musiconhold.conf /etc/asterisk/
COPY queues.conf /etc/asterisk/

# Copiar script de inicialização
COPY scripts/docker-entrypoint.sh /usr/local/bin/
COPY scripts/asterisk-healthcheck.sh /usr/local/bin/asterisk-healthcheck
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/asterisk-healthcheck

# Expor portas
EXPOSE 5060/udp 5060/tcp 5061/udp 5061/tcp 4569/udp 10000-10099/udp

# Definir usuário
USER asterisk

# Definir diretório de trabalho
WORKDIR /var/lib/asterisk

# Script de inicialização
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["asterisk", "-f"]
