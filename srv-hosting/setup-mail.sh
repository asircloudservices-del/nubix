#!/bin/bash
set -e

# Variables
IP_ACCESO="192.168.232.122"
IP_APLICACION="10.10.10.12"
IP_RED="10.10.10.0"
MASCARA="24"
DOMINIO_BASE="nubix.es"
HOSTNAME_SERVIDOR="srv-hosting"

DB_NOMBRE="nubix"
DB_USUARIO_CORREO="mailUser"
DB_PASSWORD_CORREO="mail1234"

echo "Script de configuración inicial para Postfix y Dovecot"

echo "[1/7] Instalando cliente MySQL para conexión remota a BD..."
sudo apt install -y mysql-client postfix-mysql dovecot-mysql

echo "[2/7] Creando usuario para buzones virtuales..."

if getent group vmail > /dev/null 2>&1; then
    echo "> El grupo vmail ya existe. Omitiendo..."
else
    sudo groupadd -g 5000 vmail
fi

if id "vmail" &>/dev/null; then
    echo "> El usuario vmail ya existe. Omitiendo..."
else
    sudo useradd -g vmail -u 5000 vmail -d /var/mail/vhosts -m -s /sbin/nologin
fi

echo "> Usuario creado correctamente."

echo "> Creando directorios para buzones virtuales..."
sudo mkdir -p /var/mail/vhosts
sudo chown -R vmail:vmail /var/mail/vhosts

echo "[3/7] Verificando conexión con MySQL en ${IP_APLICACION}..."
if mysql -h "${IP_APLICACION}" -u "${DB_USUARIO_CORREO}" -p"${DB_PASSWORD_CORREO}" \
    -e "USE ${DB_NOMBRE}; SELECT 1;" > /dev/null 2>&1; then
    echo "> Conexión exitosa."
else
    echo "> ADVERTENCIA: Error de conexión: MySQL en ${IP_APLICACION}."
    echo "  Asegúrate de que:"
    echo "    1. El contenedor Docker de SERVIDOR_APLICACION está corriendo"
    echo "    2. El puerto 3306 está expuesto en docker-compose"
    echo "    3. El usuario ${DB_USUARIO_CORREO}@IP_HOSTING existe en MariaDB"
    echo "  Continuando con la configuración de archivos..."
fi

echo "[4/7] Configurando Postfix..."

echo "> Generando certificados autofirmados para cifrar las comunicaciones..."

sudo mkdir -p /etc/ssl/certs

sudo openssl req -x509 -nodes -days 3650 \
    -newkey rsa:2048 \
    -keyout /etc/ssl/certs/correo.key \
    -out    /etc/ssl/certs/correo.crt \
    -subj   "/C=ES/ST=Local/L=Local/O=Nubix Hosting/CN=hosting.nubix.es" \
    -addext "subjectAltName=IP:${IP_ACCESO},DNS:hosting.nubix.es"

sudo chmod 640 /etc/ssl/certs/correo.key
sudo chmod 644 /etc/ssl/certs/correo.crt
sudo chown root:dovecot /etc/ssl/certs/correo.key

sudo mkdir -p /etc/postfix/mysql

# Este fichero se encargará de comprobar qué dominios de correo serán válidos para Postfix.
# Se encargará de comprobar cada dirección de correo que pase por el servidor, extraerá el dominio y preguntará a la base de datos para ver si está registrado.
# Si está registrado, aceptará el correo y entenderá que se trata de un dominio que gestiona.
sudo tee /etc/postfix/mysql/virtual-mailbox-domains.cf > /dev/null <<EOF
user     = ${DB_USUARIO_CORREO}
password = ${DB_PASSWORD_CORREO}
hosts = ${IP_APLICACION}
dbname   = ${DB_NOMBRE}
query    = SELECT dominio FROM dominios_correo WHERE dominio='%s' AND activo=1
EOF

# Tras el fichero anterior, este fichero comprobará cada correo que gestione.
# Este fichero en particular, se encargará de encontrar el buzón de cada usuario en el servidor.
# En otras palabras, se encargará de verificar si el usuario de correo existe en la base de datos.
# Si el usuario existe, ubicará su directorio dentro de /var/mail/vbox/
sudo tee /etc/postfix/mysql/virtual-mailbox-maps.cf > /dev/null <<EOF
user     = ${DB_USUARIO_CORREO}
password = ${DB_PASSWORD_CORREO}
hosts    = ${IP_APLICACION}
dbname   = ${DB_NOMBRE}
query    = SELECT buzon FROM cuentas_correo WHERE usuario='%u' AND dominio='%d' AND activo=1
EOF

# Configuración general de Postfix:
sudo tee /etc/postfix/main.cf > /dev/null <<EOF
# Información de host:
myhostname = ${HOSTNAME_SERVIDOR}.${DOMINIO_BASE}
mydomain = ${DOMINIO_BASE}
myorigin = \$mydomain

# Propiedades de red:
inet_interfaces = all
inet_protocols = ipv4
mydestination = localhost

# Dominios y buzones virtuales
virtual_mailbox_domains = mysql:/etc/postfix/mysql/virtual-mailbox-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql/virtual-mailbox-maps.cf
virtual_mailbox_base = /var/mail/vhosts
virtual_minimum_uid = 100
virtual_uid_maps = static:5000
virtual_gid_maps = static:5000

# Forzamos autenticación de usuario obligatoria antes de poder eviar a través del servidor con SASL.
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes
smtpd_sasl_security_options = noanonymous

# Restricciones, indican quiénes pueden enviar correo y qué destinos
smtpd_recipient_restrictions =
    permit_sasl_authenticated,
    permit_mynetworks,
    reject_unauth_destination

# Red local
mynetworks = 127.0.0.0/8 ${IP_RED}/${MASCARA}

# Límites de tamaño de mensajes
message_size_limit = 10240000

# TLS para los clientes que envían correo:
smtpd_tls_cert_file  = /etc/ssl/certs/correo.crt
smtpd_tls_key_file   = /etc/ssl/certs/correo.key
smtpd_tls_security_level = may
smtpd_tls_protocols  = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1

# TLS para relay
smtp_tls_security_level = may
smtp_tls_protocols   = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1
EOF

# Permisos
sudo chmod 640 /etc/postfix/mysql/*.cf
sudo chown root:postfix /etc/postfix/mysql/*.cf

echo ">> Configurando TLS en Postfix..."
sudo postconf -e "smtpd_tls_cert_file=/etc/ssl/certs/correo.crt"
sudo postconf -e "smtpd_tls_key_file=/etc/ssl/certs/correo.key"
sudo postconf -e "smtpd_tls_security_level=may"
sudo postconf -e "smtpd_tls_protocols=!SSLv2,!SSLv3,!TLSv1,!TLSv1.1"
sudo postconf -e "smtp_tls_security_level=may"
# En teoría es lo mismo que ponerlo al final del fichero de configuración, pero por si acaso...

echo ">> Habilitando SMTP submission..."
echo "submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject
  -o smtpd_tls_auth_only=yes" >> /etc/postfix/master.cf
sudo postconf -e "smtpd_sasl_auth_enable=yes"
sudo postconf -e "smtpd_tls_security_level=may"
sudo postconf -e "smtpd_tls_auth_only=yes"
sudo postconf -e "smtpd_recipient_restrictions=permit_sasl_authenticated,reject"

echo "> Postfix configurado."

echo "[5/7] Configurando Dovecot..."

# Cambiamos opciones del fichero de configuración de autenticación
sudo sed -i 's/^#disable_plaintext_auth.*/disable_plaintext_auth = yes/' \
    /etc/dovecot/conf.d/10-auth.conf
sudo sed -i 's/^auth_mechanisms.*/auth_mechanisms = plain login/' \
    /etc/dovecot/conf.d/10-auth.conf
sudo sed -i 's/^!include auth-system/#!include auth-system/' \
    /etc/dovecot/conf.d/10-auth.conf

if ! grep -q "^!include auth-sql" /etc/dovecot/conf.d/10-auth.conf; then
    echo "!include auth-sql.conf.ext" | sudo tee -a /etc/dovecot/conf.d/10-auth.conf > /dev/null
fi

# Este fichero se encargará de configurar TLS
sudo tee /etc/dovecot/conf.d/10-ssl.conf > /dev/null <<EOF
ssl                       = yes
ssl_cert                  = </etc/ssl/certs/correo.crt
ssl_key                   = </etc/ssl/certs/correo.key
ssl_min_protocol          = TLSv1.2
ssl_prefer_server_ciphers = yes
EOF

# Este fichero de configuración controlará la autenticación para SQL
# El primer parámetro es la fuente de contraseñas, indica a Dovecot que use SQL y la ruta a los parámetros para poder realizar la conexión
# El segundo busca la ruta del correo de cada usuario
sudo tee /etc/dovecot/conf.d/auth-sql.conf.ext > /dev/null <<EOF
passdb {
    driver = sql
    args   = /etc/dovecot/dovecot-sql.conf.ext
}

userdb {
    driver = static
    args   = uid=vmail gid=vmail home=/var/mail/vhosts/%d/%n
}
EOF

# Indica parámetros para que Dovecot pueda realizar la configuración SQL correctamente gracias al fichero de configuración anterior.
sudo tee /etc/dovecot/dovecot-sql.conf.ext > /dev/null <<EOF
driver = mysql
connect = host=${IP_APLICACION} dbname=${DB_NOMBRE} user=${DB_USUARIO_CORREO} password=${DB_PASSWORD_CORREO}
default_pass_scheme = SHA512-CRYPT
password_query      = SELECT password FROM cuentas_correo WHERE usuario='%n' AND dominio='%d' AND activo=1
EOF

sudo chmod 640 /etc/dovecot/dovecot-sql.conf.ext
sudo chown root:dovecot /etc/dovecot/dovecot-sql.conf.ext

# Establece el maildir que se le asignará a cada usuario nuevo
sudo sed -i "s|^mail_location.*|mail_location = maildir:/var/mail/vhosts/%d/%n/|" \
    /etc/dovecot/conf.d/10-mail.conf
if ! grep -q "mail_privileged_group" /etc/dovecot/conf.d/10-mail.conf; then
    # Permite el acceso a los buzones mediante el grupo vmail ya creado
    echo "mail_privileged_group = vmail" | \
        sudo tee -a /etc/dovecot/conf.d/10-mail.conf > /dev/null
fi

# Se encarga de definir los servicios de Dovecot y puertos, así como la comunicación entre ellos y Postfix
sudo tee /etc/dovecot/conf.d/10-master.conf > /dev/null <<'EOF'
service imap-login {
    inet_listener imap {
        port = 143
    }
    inet_listener imaps {
        port = 993
        ssl = yes
    }
}

service pop3-login {
    inet_listener pop3 {
        port = 110
    }
    inet_listener pop3s {
        port = 995
        ssl = yes
    }
}

service lmtp {
    unix_listener lmtp {
        mode = 0666
    }
}

service imap {
}
service pop3 {
}

service auth {
    unix_listener /var/spool/postfix/private/auth {
        mode  = 0660
        user  = postfix
        group = postfix
    }
    unix_listener auth-userdb {
        mode  = 0600
        user  = vmail
    }
    user = dovecot
}

service auth-worker {
    user = vmail
}

service dict {
    unix_listener dict {
        mode  = 0660
        group = vmail
    }
}
EOF

# Configuramos el siguiente fichero para que la interfaz de Thunderbird pueda ver los correos recibidos y enviados:
echo ">> Configurando mailboxes de Dovecot..."

sudo tee /etc/dovecot/conf.d/15-mailboxes.conf > /dev/null <<'EOF'
namespace inbox {
  inbox = yes

  mailbox Sent {
    special_use = \Sent
    auto = subscribe
  }

  mailbox Drafts {
    special_use = \Drafts
    auto = subscribe
  }

  mailbox Trash {
    special_use = \Trash
    auto = subscribe
  }

  mailbox Junk {
    special_use = \Junk
    auto = subscribe
  }
}
EOF

echo "> Dovecot configurado."

echo "[6/7] Añadiendo postfix al grupo dovecot..."
sudo adduser postfix dovecot

echo "[7/7] Reiniciando servicios..."
sudo systemctl restart postfix dovecot
sudo systemctl enable postfix dovecot

echo "> Prueba de consulta remota a MariaDB desde Postfix:"
postmap -q "test.${DOMINIO_BASE}" \
    mysql:/etc/postfix/mysql/virtual-mailbox-domains.cf \
    && echo ">> Consulta ejecutada (vacía es normal si no hay dominios aún)" \
    || echo ">> AVISO: Error en la consulta. Revisa conectividad con ${IP_APLICACION}:3306"
