@echo off
echo === REINICIANDO APACHE XAMPP ===

echo Parando Apache...
net stop Apache2.4 2>nul
taskkill /F /IM httpd.exe 2>nul

echo.
echo Aguardando 3 segundos...
timeout /t 3 /nobreak >nul

echo Iniciando Apache...
net start Apache2.4

echo.
echo Verificando status...
sc query Apache2.4

echo.
echo === PROCESSO CONCLUIDO ===
echo.
echo Para verificar se o Redis foi carregado:
echo php -m | findstr redis
echo.
pause
