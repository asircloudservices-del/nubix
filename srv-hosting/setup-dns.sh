#!/bin/bash
# Configuración inicial de DNS
set -e

# Varibles
IP_HOSTING="10.10.10.12"
IP_ACCESO="192.168.232.122"
IP_RED="10.10.10.0"
MASCARA="24"
DOMINIO_BASE="nubix.es"

echo "[1/6] Creando directorio de zonas..."
sudo mkdir -p /etc/bind/zones

echo "[2/6] Escribiendo named.conf.options..."
sudo tee /etc/bind/named.conf.options > /dev/null <<EOF
options {
    directory "/var/cache/bind";

    listen-on { any; };

    allow-transfer { none; };

    allow-recursion { any; };

    allow-query { any; };

    forwarders {
        8.8.8.8;
        1.1.1.1;
    };
    forward only;

    dnssec-validation no;
    recursion yes;
};
EOF

echo "[3/6] Escribiendo named.conf.local..."
sudo tee /etc/bind/named.conf.local > /dev/null <<EOF
# Zona base del nubix.es
zone "${DOMINIO_BASE}" {
    type master;
    file "/etc/bind/zones/${DOMINIO_BASE}.db";
    allow-update { none; };
};
EOF

echo "[4/6] Creando archivo de zona ${DOMINIO_BASE}..."

SERIAL=$(date +%Y%m%d%H)

sudo tee /etc/bind/zones/${DOMINIO_BASE}.db > /dev/null <<EOF
; Configuración de la zona: ${DOMINIO_BASE}
\$TTL 60
@   IN  SOA     dns.${DOMINIO_BASE}. admin.${DOMINIO_BASE}. (
                ${SERIAL}   ; Serial
                3600        ; Refresh
                1800        ; Retry
                604800      ; Expire
                60 )     ; Negative TTL

; Servidores de nombres
@  IN  NS  dns.${DOMINIO_BASE}.

; Registro A del nameserver
dns  IN  A  ${IP_ACCESO}

; Servidor de acceso
@  IN  A  ${IP_ACCESO}
${DOMINIO_BASE}. IN A  ${IP_ACCESO}

; Subdominio www
www  IN  A  ${IP_ACCESO}

; Servidor de correo
@  IN  MX 10 mail.${DOMINIO_BASE}.
mail.${DOMINIO_BASE}.  IN  A  ${IP_ACCESO}
EOF

echo "[5/6] Ajustando permisos..."
sudo chown -R bind:bind /etc/bind/zones
sudo chmod 755 /etc/bind/zones
sudo chmod 644 /etc/bind/zones/${DOMINIO_BASE}.db

echo "[6/6] Verificando configuración..."

echo "> Comprobando named.conf..."
sudo named-checkconf && echo "  named.conf: OK"

echo "> Comprobando zona ${DOMINIO_BASE}..."
sudo named-checkzone ${DOMINIO_BASE} /etc/bind/zones/${DOMINIO_BASE}.db

echo "> Reiniciando BIND9..."
sudo systemctl restart bind9

echo "> Prueba de resolución local:"
dig @127.0.0.1 ${DOMINIO_BASE} +short || echo "> AVISO: dig no encontrado, instala dnsutils"
