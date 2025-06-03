@echo off
title Launching microservices and LocalTunnel

echo Starting telegrambot-service on port 8000...
start "php8000" cmd /c "cd telegrambot-service && php artisan serve --port=8000"

echo Starting database-service on port 8001...
start "php8001" cmd /c "cd database-service && php artisan serve --port=8001"

echo Starting deepseek-service on port 8002...
start "php8002" cmd /c "cd deepseek-service && php artisan serve --port=8002"

echo Starting LocalTunnel on port 8000 with subdomain piusbot...
start "lt8000" cmd /c "lt --port 8000 --subdomain piusbot"

echo.
echo All services are running. Press any key to stop them all...
pause >nul

echo Stopping all services...

rem Kill artisan servers by port
for /f "tokens=5" %%a in ('netstat -aon ^| find ":8000" ^| find "LISTENING"') do taskkill /PID %%a /F
for /f "tokens=5" %%a in ('netstat -aon ^| find ":8001" ^| find "LISTENING"') do taskkill /PID %%a /F
for /f "tokens=5" %%a in ('netstat -aon ^| find ":8002" ^| find "LISTENING"') do taskkill /PID %%a /F

rem Kill localtunnel process
taskkill /IM node.exe /F >nul 2>&1

rem echo All services have been stopped.
rem pause
