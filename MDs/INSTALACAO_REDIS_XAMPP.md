# INSTALAÇÃO DA EXTENSÃO REDIS NO XAMPP

## PROBLEMA IDENTIFICADO
O contêiner Redis **está rodando corretamente**, mas a aplicação PHP não consegue conectar porque **a extensão Redis não está instalada no XAMPP**.

## STATUS DOS SERVIÇOS
✅ **Redis Docker**: Rodando na porta 6380 (mapeada de 6379)
✅ **MariaDB Docker**: Rodando na porta 3307 (mapeada de 3306)  
❌ **PHP Redis Extension**: NÃO INSTALADA

## SOLUÇÃO: INSTALAR EXTENSÃO REDIS NO XAMPP

### Método 1: Download Manual (Recomendado)

1. **Identificar sua versão do PHP**:
   ```bash
   php -v
   # Resultado: PHP 8.0.23 ZTS Visual C++ 2019 x64
   ```

2. **Baixar a extensão Redis**:
   - Acesse: https://pecl.php.net/package/redis
   - Baixe: `php_redis-5.3.7-8.0-ts-vs16-x64.zip` (para PHP 8.0 ZTS x64)
   - Ou use link direto: https://windows.php.net/downloads/pecl/releases/redis/5.3.7/

3. **Instalar a extensão**:
   ```bash
   # Extrair o arquivo baixado
   # Copiar php_redis.dll para C:\xampp\php\ext\
   copy php_redis.dll C:\xampp\php\ext\
   ```

4. **Editar php.ini**:
   ```bash
   # Abrir C:\xampp\php\php.ini
   # Adicionar a linha:
   extension=redis
   ```

5. **Reiniciar Apache no XAMPP**

### Método 2: Usando Composer/PECL (Alternativo)

Se tiver o Visual Studio Build Tools instalado:
```bash
pecl install redis
```

### Método 3: Via Docker (Temporário)

Para testes imediatos, execute dentro do contêiner PHP:
```bash
docker exec -it discador_php php test_redis.php
```

## SCRIPTS PARA INSTALAÇÃO AUTOMÁTICA

### Para Windows (PowerShell)
```powershell
# Download automático da extensão Redis
$phpVersion = "8.0"
$architecture = "x64"
$url = "https://windows.php.net/downloads/pecl/releases/redis/5.3.7/php_redis-5.3.7-8.0-ts-vs16-x64.zip"
$tempFile = "$env:TEMP\php_redis.zip"
$extractPath = "$env:TEMP\php_redis"

# Download
Invoke-WebRequest -Uri $url -OutFile $tempFile

# Extract
Expand-Archive -Path $tempFile -DestinationPath $extractPath -Force

# Copy DLL
Copy-Item "$extractPath\php_redis.dll" "C:\xampp\php\ext\"

# Add to php.ini
Add-Content "C:\xampp\php\php.ini" "`nextension=redis"

Write-Host "✅ Extensão Redis instalada! Reinicie o Apache no XAMPP."
```

### Para verificar se funcionou:
```bash
php -m | findstr redis
# Deve mostrar: redis
```

## CONFIGURAÇÃO ATUAL DETECTADA

Baseado na análise do sistema:
- **PHP**: 8.0.23 ZTS x64 (XAMPP)
- **Redis Container**: localhost:6380 
- **Password**: redis123
- **Arquivo de config**: Já configurado para usar localhost:6380

## PRÓXIMOS PASSOS

1. **Instalar extensão Redis** (método 1 recomendado)
2. **Reiniciar Apache** no XAMPP
3. **Testar conexão**: `php test_redis.php`
4. **Verificar dashboard**: Comandos devem funcionar sem "Scripts directory not found"

## ALTERNATIVA TEMPORÁRIA

Se não conseguir instalar a extensão Redis imediatamente, o sistema continuará funcionando com o **script fallback** que não depende do Redis, mas você não terá as funcionalidades avançadas de fila e cache.

Para funcionalidade completa, a extensão Redis é necessária.
