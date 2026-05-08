# Nubix

Plataforma de hosting local automatizada para gestionar dominios, subdominios DNS, correo y despliegue de WordPress desde un panel web en PHP.

## 1. Objetivo del proyecto

Este repositorio documenta e implementa un sistema de hosting privado en red local con 3 servidores:

- `srv-acceso`: punto de entrada, firewall/NAT y proxy inverso.
- `srv-aplicacion`: panel web, base de datos central y nodo de control Ansible.
- `srv-hosting`: DNS, correo y sitios WordPress.

La idea principal es que un usuario gestione servicios desde el panel y que cada accion se aplique de forma automatica con Ansible.

## 2. Arquitectura

```text
Cliente LAN/VPN
    |
    v
[srv-acceso]
  - UFW + NAT + forwarding
  - CrowdSec
  - Nginx Proxy Manager (80/443/81)
  - DNAT DNS(53), IMAPS(993), SMTP submission(587)
    |
    +----------------------------+
    |                            |
    v                            v
[srv-aplicacion]            [srv-hosting]
  - Docker: webapp + DB       - BIND9
  - PHP panel                 - Postfix + Dovecot
  - Ansible control node      - Nginx + PHP-FPM
  - SSH bridge                - WordPress por dominio
```

## 3. Estructura del repositorio

```text
.
|- README.md
|- srv-acceso
|  |- docker-compose.yml
|  `- scripts
|     |- setup-docker.sh
|     `- setup-firewall.sh
|- srv-aplicacion
|  |- ansible
|  |  |- ansible.cfg
|  |  |- inventory/hosts.ini
|  |  |- playbooks/
|  |  `- variables/global.yml
|  |- scripts/setup-ansible.sh
|  `- webapp
|     |- docker-compose.yml
|     |- mariadb/init/schema.sql
|     |- php.ini
|     `- html
|        |- index.php
|        |- dashboard.php
|        |- dominio.php
|        |- correo.php
|        |- subdominio.php
|        |- wp.php
|        |- config/db.php
|        |- functions/{auth.php,validar.php,ansible.php}
|        `- ansible-bridge/{setup-ansible-bridge.sh,run-playbook.sh}
`- srv-hosting
   |- setup-ansible.sh
   |- setup-dns.sh
   |- setup-mail.sh
   `- setup-wp.sh
```

## 4. Como se implementa este sistema

Esta es la secuencia recomendada de implementacion.

### Paso 0: ajustar variables de red

Antes de ejecutar scripts, revisa:

- `srv-aplicacion/ansible/inventory/hosts.ini`
- `srv-aplicacion/ansible/variables/global.yml`
- variables hardcodeadas dentro de scripts `.sh`

IPs usadas en el proyecto:

- `srv-acceso`: `192.168.232.122`
- `srv-hosting`: `10.10.10.11`
- `srv-aplicacion`: `10.10.10.12`

### Paso 1: preparar servidor hosting

```bash
cd srv-hosting
chmod +x *.sh
./setup-ansible.sh
./setup-dns.sh
./setup-mail.sh
./setup-wp.sh
```

Resultado esperado:

- BIND9 listo para zonas DNS.
- Postfix/Dovecot listos para correo virtual.
- Nginx/PHP-FPM y plantilla WordPress base preparados.

### Paso 2: preparar servidor de acceso

```bash
cd srv-acceso/scripts
chmod +x *.sh
./setup-docker.sh
cd ..
docker compose up -d
cd scripts
./setup-firewall.sh
```

Resultado esperado:

- Nginx Proxy Manager activo (80/443/81).
- Reglas UFW y NAT aplicadas.
- CrowdSec instalado y vinculado a logs de NPM/SSH.

### Paso 3: preparar servidor de aplicacion

```bash
cd srv-aplicacion/scripts
chmod +x setup-ansible.sh
./setup-ansible.sh
```

Copiar contenido de Ansible al home de `ansible`:

```bash
cd /ruta/al/repositorio/nubix-main
sudo mkdir -p /home/ansible
sudo cp -r srv-aplicacion/ansible /home/ansible/ansible
sudo chown -R ansible:ansible /home/ansible/ansible
```

Configurar puente SSH para que el contenedor webapp ejecute playbooks:

```bash
cd srv-aplicacion/webapp/html/ansible-bridge
chmod +x *.sh
./setup-ansible-bridge.sh
```

Nota importante:

- `setup-ansible-bridge.sh` usa por defecto la ruta
  `/opt/srv-aplicacion/webapp/html/ansible-bridge/run-playbook.sh`.
- Si despliegas en otra ruta, ajusta la variable `BRIDGE_SCRIPT`.

Levantar aplicacion:

```bash
cd srv-aplicacion/webapp
docker compose up -d
```

Resultado esperado:

- Panel web en PHP accesible.
- MariaDB inicializada con `mariadb/init/schema.sql`.

## 5. Flujo funcional de la plataforma

Flujo general:

1. El usuario hace una accion desde el panel (dominio, subdominio, correo o WordPress).
2. PHP valida datos y prepara parametros.
3. `functions/ansible.php` ejecuta SSH al host (`host.docker.internal`).
4. Se invoca `run-playbook.sh`.
5. Ansible aplica cambios en `srv-hosting` (y en NPM por API cuando corresponde).
6. La base central actualiza estado para reflejarlo en `dashboard.php`.

## 6. Playbooks Ansible incluidos

- `crear_dominio.yml`
- `eliminar_dominio.yml`
- `crear_subdominio.yml`
- `eliminar_subdominio.yml`
- `crear_correo.yml`
- `crear_wordpress.yml`

## 7. Modelo de datos (MariaDB)

Base: `nubix`

Tablas principales:

- `usuarios`
- `dominios`
- `subdominios`
- `dominios_correo`
- `cuentas_correo`
- `wordpress_sites`

Usuarios SQL definidos en `schema.sql`:

- `phpAdmin` para la webapp.
- `ansibleAdmin` para automatizaciones.
- `mailUser` para consultas de Postfix/Dovecot.

## 8. Puertos relevantes

- `srv-acceso`: `80`, `443`, `81`, `53/tcp`, `53/udp`, `993`, `587`
- `srv-aplicacion`: `80`, `3306`
- `srv-hosting`: DNS, correo y web internos segun servicios

## 9. Verificacion y troubleshooting

Comandos utiles:

```bash
# Contenedores
docker ps

# Logs docker
docker logs nubix-webapp
docker logs nubix-db
docker logs nginx-proxy-manager

# Logs de ejecucion de playbooks
ls -lah /var/log/ansible-bridge

# DNS en hosting
sudo named-checkconf
sudo named-checkzone dominio.ejemplo /etc/bind/zones/dominio.ejemplo.db

# Firewall/NAT en acceso
sudo ufw status numbered
sudo iptables -t nat -L PREROUTING -n --line-numbers
```

## 10. Seguridad y hardening recomendado

El estado actual esta orientado a laboratorio. Para un entorno real:

1. Mover credenciales hardcodeadas a secretos (`.env`, vault o secret manager).
2. Reducir privilegios SQL (evitar `ALL PRIVILEGES` globales).
3. Activar verificacion estricta de host SSH.
4. Sustituir certificados autofirmados por certificados validos.
5. Restringir acceso a `3306` con ACL/VPN.
6. Desactivar `display_errors` en produccion.

## 11. Limitaciones actuales

- Maximo 1 dominio activo por usuario.
- Maximo 3 subdominios por dominio.
- Dependencia de IPs y rutas fijas en varios scripts.
- No hay pipeline CI/CD ni tests automaticos.
- La webapp usa imagen `nubixuser/webapp:latest` (no hay Dockerfile local en este repo).
