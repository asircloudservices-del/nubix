#!/bin/bash
set -e

echo "Script para instalar y configurar Docker."

echo "[1/3] Actualizando el sistema..."
sudo apt update && sudo apt upgrade -y

echo "[2/3] Instalando Docker y dependencias..."
sudo apt install -y docker.io docker-compose curl

echo "> Añadiendo el usuario al grupo docker..."
sudo usermod -aG docker "$USER"
echo ">> Usuario $USER añadido al grupo docker."

echo "[3/3] Habilitando Docker..."
sudo systemctl enable docker
sudo systemctl start docker
