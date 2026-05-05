#!/bin/bash
# Instala algunos servicios y configura Ansible
set -e

# Variables:
ANSIBLE_PASSWORD="ansible1234"   # Que sea igual a la del servidor de aplicación.

echo "Script para instalar servicios."

echo "[1/4] Actualizando el sistema..."
sudo apt update && sudo apt upgrade -y

echo "[2/4] Instalando servicios necesarios..."

sudo apt install -y \
    bind9 \
    bind9utils \
    bind9-doc \
    postfix \
    dovecot-core \
    dovecot-imapd \
    dovecot-pop3d \
    mariadb-server \
    python3 \
    python3-pip \
    python3-pymysql \
    curl \
    wget \
    unzip

echo "postfix postfix/mailname string DOMINIO_BASE" | sudo debconf-set-selections
echo "postfix postfix/main_mailer_type string 'Internet Site'" | sudo debconf-set-selections


echo "[3/4] Creando usuario ansible..."

if id "ansible" &>/dev/null; then
    echo "> El usuario ansible ya existe. Omitiendo..."
else
    sudo useradd -m -s /bin/bash ansible
    echo "ansible:${ANSIBLE_PASSWORD}" | sudo chpasswd
    sudo usermod -aG sudo ansible
    echo "> Usuario ansible creado correctamente."
fi

echo "Configurando sudo sin contraseña..."
if ! sudo grep -q "^ansible ALL=(ALL) NOPASSWD:ALL" /etc/sudoers; then
    echo "ansible ALL=(ALL) NOPASSWD:ALL" | sudo tee -a /etc/sudoers > /dev/null
    echo "> Sudoers configurado para ansible."
else
    echo ">Sudoers ya configurado. Omitiendo..."
fi

echo "Creando directorios para la clave SSH..."
ANSIBLE_HOME="/home/ansible"
SSH_DIR="${ANSIBLE_HOME}/.ssh"
sudo mkdir -p "$SSH_DIR"
sudo touch "${SSH_DIR}/authorized_keys"
sudo chmod 700 "$SSH_DIR"
sudo chmod 600 "${SSH_DIR}/authorized_keys"
sudo chown -R ansible:ansible "$SSH_DIR"

echo "[4/4] Habilitando servicios..."

sudo systemctl enable postfix
sudo systemctl enable dovecot
sudo systemctl enable mariadb

echo "> Hecho."
