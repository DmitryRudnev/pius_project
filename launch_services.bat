@echo off
title Launching microservices and LocalTunnel

echo Запуск telegrambot-service на порту 8000...
start "telegrambot-service" cmd /k "cd telegrambot-service && php artisan serve --port=8000"

echo Запуск database-service на порту 8001...
start "database-service" cmd /k "cd database-service && php artisan serve --port=8001"

echo Запуск deepseek-service на порту 8002...
start "deepseek-service" cmd /k "cd deepseek-service && php artisan serve --port=8002"

echo Запуск LocalTunnel на порту 8000 с сабдоменом piusbot...
start "LocalTunnel" cmd /k "lt --port 8000 --subdomain piusbot"

echo Все сервисы запущены.
rem pause
