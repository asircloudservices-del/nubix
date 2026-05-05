#!/bin/bash
# ANTES DE EJECUTAR: Se hace necesario el funcionamiento de una VPN para la red interna. De otra manera, es posible que perdamos el acceso remoto.

# Se encarga de configurar forwarding en el servidor de acceso para que los accesos de los usuarios puedan redirigirse a los servidores detrás del Firewall
# Crea reglas de entrada para mantener la seguridad en las conexiones que realicen usuarios externos
# Configura la herramienta de detección CrowdSec para evitar amenazas en las conexiones

set -e

# Variables del script
IFACE_WAN="ens18" # Interfaz externa
IFACE_LAN="ens19" # Interfaz interna

RED_VPN="192.168.33.0"
IP_ACCESO_LAN="10.10.10.10"
IP_APLICACION="10.10.10.12"
IP_HOSTING="10.10.10.11"
IP_RED_INTERNA="10.10.10.0"
MASCARA="24"

echo "Ejecutando script de configuración de Firewall..."

# 1. Condigurar IP Forwarding
# Habilitamos el IP forwarding, que nos permite redirigir todo el tráfico de la red interna de servidores hacia el exterior y viceversa
echo "[1/6] Activando IP forwarding..."

# Añadimos el parámetro de reenvío IPv4 en sysctl.conf
if ! grep -q "^net.ipv4.ip_forward=1" /etc/sysctl.conf; then
    echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf > /dev/null
fi

# Aplicamos el cambio
sudo sysctl -p

echo "> IP forwarding activado."

# 2. Configurar políticas base de UFW para definir qué acciones se hacen por defecto con todo el tráfico
echo "[2/6] Configurando políticas base de UFW..."

# Denegamos toda conexión entrante a la interfaz WAN
# Más adelante se indica qué conexiones entrantos vamos a permitir
sudo ufw default deny incoming

# Permitimos por defecto todo tráfico saliente y de forwarding entre interfaces, ya que el tráfico saliente por la WAN es de los servidores y usuarios de la VPN
sudo ufw default allow outgoing
sudo ufw default allow forward

echo "> Políticas base configuradas."

# 3. Habilitar forwarding en la configuración interna de UFW
echo "[3/6] Habilitando forwarding en archivos de UFW..."

# La siguiente configuración permite el forwarding entre interfaces por UFW
sudo sed -i \
    's/^DEFAULT_FORWARD_POLICY=.*/DEFAULT_FORWARD_POLICY="ACCEPT"/' \
    /etc/default/ufw

# También se habilita el reenvío IPv4 en la configuración sysctl asociada a UFW
sudo sed -i \
    's|^#net/ipv4/ip_forward.*|net/ipv4/ip_forward=1|' \
    /etc/ufw/sysctl.conf

echo "      Forwarding habilitado en UFW."

# 4. Configurar reglas NAT en before.rules
# Lo que nos permite redireccionar el tráfico interno de los servidores hacia fuera y enmascarar las direcciones con la IP del Firewall.
# También podremos hacer redirecciones externas hacia los distintos servidores
echo "[4/6] Configurando reglas NAT..."

BEFORE_RULES="/etc/ufw/before.rules"

NAT_BLOCK="# Reglas NAT
*nat
:PREROUTING ACCEPT [0:0]
:POSTROUTING ACCEPT [0:0]

# Se acepta el tráfico entrante hacia la interfaz asociada a la WAN. El tráfico debe ser por UDP hacia el puerto 53 (DNS)
# Se cambia el paquete para que el destino pase a ser la dirección IP interna del servidor de hosting
-A PREROUTING -i ${IFACE_WAN} -p udp --dport 53 -j DNAT \
    --to-destination ${IP_HOSTING}:53

# Lo mismo pero por TCP
-A PREROUTING -i ${IFACE_WAN} -p tcp --dport 53 -j DNAT \
    --to-destination ${IP_HOSTING}:53

# Se acepta trafico entrante hacia la interfaz asociada a la WAN. Aplica para las conexiones TCP hacia el puerto 993 (IMAP seguro)
# Redirige los paquetes al servidor de hosting para que se pueda acceder a los servicios de correo desde el exterior
-A PREROUTING -i ${IFACE_WAN} -p tcp --dport 993 -j DNAT \
    --to-destination ${IP_HOSTING}:993

# Lo mismo pero por el puerto 587 (SMTP seguro)
-A PREROUTING -i ${IFACE_WAN} -p tcp --dport 587 -j DNAT \
    --to-destination ${IP_HOSTING}:587


# Después de enrutar el tráfico de la red interna de los servidores, saldrá por la interfaz WAN si es una conexión hacia fuera
# Se enmascara la dirección interna de los servidores con la IP asociada a la interfaz WAN
-A POSTROUTING -s ${IP_RED_INTERNA}/${MASCARA} -o ${IFACE_WAN} \
    -j MASQUERADE

COMMIT
# Fin de las reglas NAT"

# Se comprueba si las reglas NAT ya están implementadas. Si no, se introducen al principio del fichero
if ! grep -q "Reglas NAT" "$BEFORE_RULES"; then
    CONTENIDO_ACTUAL=$(cat "$BEFORE_RULES")
    echo "$NAT_BLOCK" | sudo tee "$BEFORE_RULES" > /dev/null
    echo "$CONTENIDO_ACTUAL" | sudo tee -a "$BEFORE_RULES" > /dev/null
    echo "> Se agregaron las reglas NAT en before.rules."
else
    echo "> Ya existen las reglas. Omitiendo..."
fi

echo ">> NAT configurado."

# 5. Reglas de entrada por WAN
# Configuramos las reglas para indicar el tráfico que vamos a permitir de parte de los clientes
echo "[5/6] Configurando reglas de entrada por WAN..."

# Permitimos conexiones entrantes a la interfaz WAN provenientes desde cualquier dirección IP.
# Las conexiones tienen que ser por TCP hacia HTTP
sudo ufw allow in on "${IFACE_WAN}" \
    to any port 80 proto tcp \
    comment "Se permite HTTP para el acceso a sitios web"

# Lo mismo para el puerto 443 (HTTPS)
sudo ufw allow in on "${IFACE_WAN}" \
    to any port 443 proto tcp \
    comment "HTTPS - trafico web principal via NPM"

# Permitimos conexiones DNS
sudo ufw allow in on "${IFACE_WAN}" \
    to any port 53 proto udp \
    comment "DNS UDP"

sudo ufw allow in on "${IFACE_WAN}" \
    to any port 53 proto tcp \
    comment "DNS TCP"

# Permitimos IMAPS y SMTP
sudo ufw allow in on "${IFACE_WAN}" \
    to any port 993 proto tcp \
    comment "IMAPS - DNAT redirige a Dovecot en SERVIDOR_SERVICIOS"

sudo ufw allow in on "${IFACE_WAN}" \
    to any port 587 proto tcp \
    comment "SMTP submission"

# Permitimos SSH desde la red de la VPN
sudo ufw allow from "${RED_VPN}" to any port 22 proto tcp

# Aplicamos las reglas
echo "y" | sudo ufw enable
sudo ufw reload

echo "> Reglas de entrada WAN configuradas."

# 6. Instalar y configurar IPS CrowdSec
echo "[6/6] Instalando y configurando CrowdSec..."

# Añadir repositorio oficial y clave GPG de CrowdSec
curl -s https://install.crowdsec.net | sudo sh

# crowdsec: el motor principal de análisis y detección
# crowdsec-firewall-bouncer-iptables: traduce las decisiones de CrowdSec en reglas de iptables que bloquean las IPs
sudo apt install -y crowdsec crowdsec-firewall-bouncer-iptables

# Integración con NGINX
sudo cscli collections install crowdsecurity/nginx

# Integración con SSH
sudo cscli collections install crowdsecurity/sshd

# Integración con HTTP para detectar intentos de explotación de CVE
sudo cscli collections install crowdsecurity/http-cve

# Integración con iptables
sudo cscli collections install crowdsecurity/iptables

# Detecciones generales en el sistema
sudo cscli collections install crowdsecurity/linux

# Fichero de fuentes de logs:
sudo tee /etc/crowdsec/acquis.yaml > /dev/null <<EOF
# Logs de acceso de NPM: un archivo por cada proxy host.
- filename: /var/lib/docker/volumes/npm_npm_data/_data/logs/proxy-host-*_access.log
  labels:
    type: nginx

# Logs de error de NPM:
- filename: /var/lib/docker/volumes/npm_npm_data/_data/logs/proxy-host-*_error.log
  labels:
    type: nginx

# Logs de intentos de login/autenticación en el sistema:
- filename: /var/log/auth.log
  labels:
    type: syslog

# Logs del kernel:
- filename: /var/log/kern.log
  labels:
    type: syslog
EOF

# Habilitamos y arrancamos los servicios
sudo systemctl enable crowdsec
sudo systemctl enable crowdsec-firewall-bouncer
sudo systemctl restart crowdsec
sudo systemctl restart crowdsec-firewall-bouncer

echo "> CrowdSec instalado y configurado."
sleep 5

echo "Reglas UFW:"
sudo ufw status numbered

echo "Reglas NAT:"
sudo iptables -t nat -L PREROUTING -n --line-numbers 2>/dev/null \
    | grep "DNAT" \
    || echo "No hay reglas visibles. Puedes probar a reiniciar o volver a ejecutar el script."
