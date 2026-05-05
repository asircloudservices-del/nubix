-- SQL para crear la base de datos principal:
CREATE DATABASE IF NOT EXISTS nubix
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE nubix;

-- Tabla de usuarios de la web:
CREATE TABLE usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,       -- hash bcrypt
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de dominios comprados:
CREATE TABLE dominios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT NOT NULL,
    dominio       VARCHAR(255) NOT NULL UNIQUE,
    estado        ENUM('activo','eliminado') DEFAULT 'activo',
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla de subdominios personalizados por dominio
CREATE TABLE subdominios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id  INT NOT NULL,
    subdominio  VARCHAR(63) NOT NULL,
    ip          VARCHAR(15) NOT NULL,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY subdominio_unico (dominio_id, subdominio),
    FOREIGN KEY (dominio_id) REFERENCES dominios(id)
);

-- Tabla de cuentas de correo.
-- Guarda cada cuenta de correo completa.
-- P.ej: juan.luis@midominio.xyz
CREATE TABLE cuentas_correo (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id    INT NOT NULL,
    usuario       VARCHAR(255) NOT NULL,
    dominio       VARCHAR(255) NOT NULL,
    password      VARCHAR(255) NOT NULL,       -- hash SHA512-CRYPT para Dovecot
    buzon         VARCHAR(255) NOT NULL,
    activo        TINYINT(1) DEFAULT 1,
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY cuenta_unica (usuario, dominio),
    FOREIGN KEY (dominio_id) REFERENCES dominios(id)
);

-- Tabla de dominios de correo.
-- Guarda sólo cada dominio para los que existe correo.
-- P.ej.: midominio.xyz
CREATE TABLE dominios_correo (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id    INT NOT NULL,
    dominio       VARCHAR(255) NOT NULL UNIQUE,
    activo        TINYINT(1) DEFAULT 1,
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dominio_id) REFERENCES dominios(id)
);

-- Tabla de sitios de WP:
CREATE TABLE wordpress_sites (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    dominio_id    INT NOT NULL,
    dominio       VARCHAR(255) NOT NULL UNIQUE,
    db_nombre     VARCHAR(255) NOT NULL,
    db_usuario    VARCHAR(255) NOT NULL,
    db_password   VARCHAR(255) NOT NULL,
    estado        ENUM('pendiente','activo','eliminado') DEFAULT 'pendiente',
    creado_en     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dominio_id) REFERENCES dominios(id)
);

-- USUARIOS DE ACCESO A LA BD

-- Usuario para PHP
CREATE USER IF NOT EXISTS 'phpAdmin'@'%'
    IDENTIFIED BY 'php1234';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.usuarios          TO 'phpAdmin'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.dominios          TO 'phpAdmin'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.subdominios TO 'phpAdmin'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.cuentas_correo    TO 'phpAdmin'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.dominios_correo   TO 'phpAdmin'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.wordpress_sites   TO 'phpAdmin'@'%';

-- Usuario para Ansible
CREATE USER IF NOT EXISTS 'ansibleAdmin'@'10.10.10.12'
    IDENTIFIED BY 'ansible1234';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.*                 TO 'ansibleAdmin'@'10.10.10.12';
GRANT CREATE, DROP
    ON `wp\_%`.*                  TO 'ansibleAdmin'@'10.10.10.12';
GRANT ALL PRIVILEGES ON *.* TO 'ansibleAdmin'@'10.10.10.12' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS 'ansibleAdmin'@'10.10.10.11'
    IDENTIFIED BY 'ansible1234';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON nubix.*                 TO 'ansibleAdmin'@'10.10.10.11';
GRANT CREATE, DROP
    ON `wp\_%`.*                  TO 'ansibleAdmin'@'10.10.10.11';
GRANT ALL PRIVILEGES ON *.* TO 'ansibleAdmin'@'10.10.10.11' WITH GRANT OPTION;
FLUSH PRIVILEGES;

-- Usuario read-only para Postfix y Dovecot
CREATE USER IF NOT EXISTS 'mailUser'@'10.10.10.11'
    IDENTIFIED BY 'mail1234';
GRANT SELECT
    ON nubix.cuentas_correo   TO 'mailUser'@'10.10.10.11';
GRANT SELECT
    ON nubix.dominios_correo  TO 'mailUser'@'10.10.10.11';

FLUSH PRIVILEGES;
