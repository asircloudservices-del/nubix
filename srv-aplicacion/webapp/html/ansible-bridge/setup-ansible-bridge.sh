#!/bin/bash
# Se encarga de configurar la clave SSH para que la web dentro del contenedor pueda conectarse por SSH a Ansible que se encuentra fuera.

set -e  # Detener el script si cualquier comando falla

echo "[1/6] Creando directorio para clave SSH del contenedor..."

sudo mkdir -p /home/ansible/ansible-bridge/ssh
# Acceso para solo el usuario de Ansible por seguridad
sudo chmod 700 /home/ansible/ansible-bridge/ssh
sudo chown ansible:ansible /home/ansible/ansible-bridge/ssh

echo "> Se ha creado el directorio."

echo "[2/6] Generando par de claves SSH para el contenedor..."

CLAVE_PATH="/home/ansible/ansible-bridge/ssh/id_ed25519"

if [ -f "$CLAVE_PATH" ]; then
    echo "> La clave ya existe. Omitiendo..."
else
    # -t ed25519: algoritmo moderno, más seguro y rápido que RSA
    # -C: comentario descriptivo para identificar la clave
    # -f: ruta donde guardar la clave
    # -N "": sin passphrase para que PHP pueda usarla automáticamente
    sudo -u ansible ssh-keygen \
        -t ed25519 \
        -C "webapp-container@servidor-infra" \
        -f "$CLAVE_PATH" \
        -N ""

    echo "> Par de claves generadas"
fi

sudo chmod 600 "${CLAVE_PATH}"
sudo chmod 644 "${CLAVE_PATH}.pub"
sudo chown ansible:ansible "${CLAVE_PATH}" "${CLAVE_PATH}.pub"

echo "> Permisos de clave configurados."

echo "[3/6] Autorizando clave en el usuario ansible del host..."

AUTHORIZED_KEYS="/home/ansible/.ssh/authorized_keys"
CLAVE_PUBLICA=$(cat "${CLAVE_PATH}.pub")

# Creamos el directorio .ssh si no existe
sudo mkdir -p /home/ansible/.ssh
sudo chmod 700 /home/ansible/.ssh
sudo touch "$AUTHORIZED_KEYS"
sudo chmod 600 "$AUTHORIZED_KEYS"

# Solo añadimos la clave si no está ya presente
if sudo grep -qF "$CLAVE_PUBLICA" "$AUTHORIZED_KEYS" 2>/dev/null; then
    echo "> La clave ya estaba autorizada. Omitiendo..."
else
    echo "$CLAVE_PUBLICA" | sudo tee -a "$AUTHORIZED_KEYS" > /dev/null
    echo "> Clave pública añadida a authorized_keys."
fi

sudo chown -R ansible:ansible /home/ansible/.ssh

echo "[4/6] Cambiando permisos para el script de ejecutar playbook..."
BRIDGE_SCRIPT="/opt/srv-aplicacion/webapp/html/ansible-bridge/run-playbook.sh"
# El script debe ser ejecutable por el usuario ansible
sudo chmod 750 "$BRIDGE_SCRIPT"
sudo chown ansible:ansible "$BRIDGE_SCRIPT"

echo "[5/6] Configurando directorio de logs..."

sudo mkdir -p /var/log/ansible-bridge
sudo chown ansible:ansible /var/log/ansible-bridge
sudo chmod 755 /var/log/ansible-bridge

echo "> Directorio de logs configurado"

echo "[6/6] Verificando conexión SSH bridge..."

if sudo -u ansible ssh \
    -i "$CLAVE_PATH" \
    -o StrictHostKeyChecking=no \
    -o BatchMode=yes \
    -o ConnectTimeout=5 \
    ansible@localhost \
    "echo SSH_OK" 2>/dev/null | grep -q "SSH_OK"; then
    echo "> Conexión SSH exitosa"
else
    echo "> Fallo en la conexión SSH."
    echo "  Comprueba manualmente:"
    echo "    sudo -u ansible ssh -i $CLAVE_PATH ansible@localhost echo OK"
fi
