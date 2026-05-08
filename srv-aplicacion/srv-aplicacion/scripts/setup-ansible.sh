#!/bin/bash

set -e

echo "* Script de instalación y configuración de Ansible *"

# Variables
IP_HOSTING="10.10.10.11"
IP_ACCESO="192.168.232.122"
ANSIBLE_PASSWORD="ansible1234"

echo "[1/3] Actualizando el sistema..."
sudo apt update && sudo apt upgrade -y

echo "[2/3] Instalando y configurando Docker..."
sudo apt install -y docker.io docker-compose

sudo usermod -aG docker "$USER"
echo "> Añadido usuario $USER al grupo docker."

echo "> Habilitando el servicio Docker..."
sudo systemctl enable docker
sudo systemctl start docker

echo "[3/3] Instalando y configurando Ansible..."
sudo apt install -y curl ansible python3-pip sshpass
echo "> Ansible instalado."

echo "> Creando el usuario para Ansible..."
if id "ansible" &>/dev/null; then
    echo ">> El usuario ansible ya existe. Se omite su creación."
else
    sudo useradd -m -s /bin/bash ansible
    echo "ansible:${ANSIBLE_PASSWORD}" | sudo chpasswd
    sudo usermod -aG sudo ansible
    echo ">> Usuario ansible creado correctamente."
fi

echo "> Editando visudo para que el usuario de Ansible pueda usar sudo sin contraseña..."
if ! sudo grep -q "^ansible ALL=(ALL) NOPASSWD:ALL" /etc/sudoers; then
    echo "ansible ALL=(ALL) NOPASSWD:ALL" | sudo tee -a /etc/sudoers > /dev/null
    echo ">> Finalizada configuración de sudoers."
else
    echo ">> Configuración de sudoers ya realizada. Omitiendo..."
fi

echo "> Generando par de clave SSH para usuario ansible..."

ANSIBLE_HOME="/home/ansible"
SSH_DIR="${ANSIBLE_HOME}/.ssh"

sudo mkdir -p "$SSH_DIR"
sudo chown ansible:ansible "$SSH_DIR"
sudo chmod 700 "$SSH_DIR"

if [ ! -f "${SSH_DIR}/id_ed25519" ]; then
    sudo -u ansible ssh-keygen -t ed25519 -C "ansible@servidor-aplicacion" \
        -f "${SSH_DIR}/id_ed25519" -N ""
    echo ">> Par de claves SSH generadas."
else
    echo "Las claves SSH ya existen. Omitiendo generación..."
fi

sudo chmod 700 "$SSH_DIR"
sudo chmod 600 "${SSH_DIR}/id_ed25519"
sudo chmod 644 "${SSH_DIR}/id_ed25519.pub"
sudo chown -R ansible:ansible "$SSH_DIR"

echo "> Copiando clave pública al servidor de hosting (${IP_HOSTING})..."
echo "AVISO: Se utilizará la misma contraseña y usuario de Ansible para el servidor de hosting."
echo "Asegure que el usuario ansible con contraseña existe en el servidor."

sudo -u ansible bash -c "
    sshpass -p '${ANSIBLE_PASSWORD}' ssh-copy-id \
        -o StrictHostKeyChecking=no \
        -i ${SSH_DIR}/id_ed25519.pub \
        ansible@${IP_HOSTING}
"

echo "> Verificando conexión SSH sin contraseña..."
if sudo -u ansible ssh -o StrictHostKeyChecking=no \
    -o BatchMode=yes \
    -i "${SSH_DIR}/id_ed25519" \
    ansible@"${IP_HOSTING}" "echo OK" 2>/dev/null | grep -q "OK"; then
    echo ">> Se ha podido realizar la conexión SSH."
else
    echo ">> ADVERTENCIA: Error en la conexión SSH."
    echo "   Comprueba manualmente: ssh ansible@${IP_HOSTING}"
fi

echo "> Instalando complementos para poder usar con MySQL..."
sudo -u ansible ansible-galaxy collection install community.mysql
