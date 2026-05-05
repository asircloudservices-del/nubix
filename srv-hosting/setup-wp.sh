#!/bin/bash
set -e

echo "Script para preparar WP base"

echo "[1/5] Instalando Nginx y PHP..."
sudo apt install -y \
    nginx \
    php8.3-fpm \
    php8.3-mysql \
    php8.3-curl \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-intl \
    mysql-client \
    wget \
    unzip

echo "> Habilitando servicios..."
sudo systemctl enable nginx php8.3-fpm
sudo systemctl start nginx php8.3-fpm
echo "> Instalación finalizada."

echo "[2/5] Descargando WP base..."
sudo mkdir -p /opt/wordpress-base

if [ -f "/opt/wordpress-base/wp-login.php" ]; then
    echo "> Ya instalado. Omitiendo..."
else
    cd /tmp
    wget -q https://wordpress.org/latest.tar.gz -O /tmp/wordpress.tar.gz
    tar -xzf /tmp/wordpress.tar.gz -C /tmp/
    sudo cp -r /tmp/wordpress/* /opt/wordpress-base/
    sudo chown -R www-data:www-data /opt/wordpress-base
    rm -rf /tmp/wordpress /tmp/wordpress.tar.gz
    echo "> WP base descargado en /opt/wordpress-base"
fi

echo "[3/5] Preparando directorios..."
sudo mkdir -p /var/www/wordpress
sudo chown -R www-data:www-data /var/www/wordpress
sudo chmod -R 755 /var/www/wordpress
echo "> Directorios creados correctamente."

echo "[4/5] Configurando Nginx..."

# Desactivar sitio por defecto
if [ -L /etc/nginx/sites-enabled/default ]; then
    sudo unlink /etc/nginx/sites-enabled/default
    echo "> Sitio por defecto desactivado."
fi

# Configuración global
sudo tee /etc/nginx/conf.d/wordpress-global.conf > /dev/null <<'EOF'
fastcgi_cache_path /tmp/nginx-cache levels=1:2
                   keys_zone=WORDPRESS:100m
                   inactive=60m;

fastcgi_cache_key "$scheme$request_method$host$request_uri";
EOF

echo "[5/5] Preparando directorio de configuración Nginx..."
sudo mkdir -p /etc/nginx/sites-available
sudo mkdir -p /etc/nginx/sites-enabled
echo "> Directorios Nginx listos."
