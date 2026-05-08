<?php
// Para validaciones y control de errores

// Guarda los TLD que el cliente puede utilizar para su dominio
const TLDS_DISPONIBLES = [
    "es" => ".es",
    "com" => ".com",
    "net" => ".net",
    "org" => ".org",
    "io" => ".io",
    "ia" => ".ia",
    "xyz" => ".xyz",
    "app" => ".app",
    "dev" => ".dev",
    "cloud" => ".cloud",
    "tech" => ".tech",
    "shop" => ".shop",
    "blog" => ".blog",
    "info" => ".info",
];
// Límite de subdominios personalizados por dominio
const MAX_SUBDOMINIOS = 3;

// Comprueba que el dominio sea uno válido
function validar_nombre_dominio(string $sld, string $tld): array
{
    // Pasar a minúsculas y eliminar espacios
    $sld = strtolower(trim($sld));
    $tld = strtolower(trim($tld));

    // Comprobamos que el SLD (Second Level Domain) no esté vacío
    if (empty($sld)) {
        return [
            "ok" => false,
            "error" => "El nombre de dominio no puede estar vacío.",
        ];
    }

    // Limitamos la longitud para evitar nombres demasiado largos
    if (strlen($sld) < 2 || strlen($sld) > 30) {
        return [
            "ok" => false,
            "error" => "El nombre debe tener entre 2 y 30 caracteres.",
        ];
    }

    // Solo letras, números y guiones. No puede empezar ni terminar con guión (según el RFC 1123)
    if (
        !preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $sld) &&
        !preg_match('/^[a-z0-9]$/', $sld)
    ) {
        return [
            "ok" => false,
            "error" =>
                "Solo letras minúsculas, números y guiones. No puede empezar ni terminar con guión.",
        ];
    }

    // Comprobamos el TLD para evitar introducir uno no deseado si el usuario manipula el código
    if (!array_key_exists($tld, TLDS_DISPONIBLES)) {
        return [
            "ok" => false,
            "error" => "El TLD seleccionado no está disponible.",
        ];
    }

    // Construimos nombre de dominio
    $dominio_completo = $sld . "." . $tld;

    return [
        "ok" => true,
        "sld" => $sld,
        "tld" => $tld,
        "dominio" => $dominio_completo,
    ];
}

// Comprueba en la BD si el dominio existe y su estado
function dominio_existe(PDO $pdo, string $dominio)
{
    $query = $pdo->prepare("SELECT id, estado FROM dominios WHERE dominio = ?");
    $query->execute([$dominio]);

    // Devuelve el estado del dominio en un array asociativo
    return $query->fetch(PDO::FETCH_ASSOC);
}

// Función para validar si el nombre de subdominio introducido es correcto
function validar_nombre_subdominio(string $subdominio): array
{
    // Convertimos sus caracteres en minúsculas
    $subdominio = strtolower(trim($subdominio));

    // Arrojamos error si el usuario no introduce registro
    if (empty($subdominio)) {
        return [
            "ok" => false,
            "error" => "El nombre del subdominio no puede estar vacío.",
        ];
    }
    // Comprobamos requisitos de longitud
    if (strlen($subdominio) < 1 || strlen($subdominio) > 30) {
        return [
            "ok" => false,
            "error" => "El subdominio debe tener entre 1 y 30 caracteres.",
        ];
    }
    // Revisamos si cumple el formato y no contiene caracteres no permitidos
    if (
        !preg_match('/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/', $subdominio) &&
        !preg_match('/^[a-z0-9]$/', $subdominio)
    ) {
        return [
            "ok" => false,
            "error" =>
                "Solo letras minúsculas, números y guiones. No puede empezar ni terminar con guión.",
        ];
    }

    // Nombres reservados que colisionarían con otros registros del sistema
    $reservados = [
        "www",
        "dns",
        "ns1",
        "ns2",
        "mail",
        "smtp",
        "imap",
        "pop",
        "ftp",
        "admin",
    ];
    if (in_array($subdominio, $reservados)) {
        return [
            "ok" => false,
            "error" => "El nombre '$subdominio' está reservado y no puede usarse.",
        ];
    }

    // Si pasa las pruebas, devolvemos el subdominio indicando que todo salió bien
    return ["ok" => true, "subdominio" => $subdominio];
}

// Valida si la IP introducida en la sección de registros DNS cumple con la sintaxis correcta:
function validar_ip(string $ip): array
{
    $ip = trim($ip);

    if (empty($ip)) {
        return [
            "ok" => false,
            "error" => "La dirección IP no puede estar vacía.",
        ];
    }

    // Utilizamos los siguientes parámetros nativos de PHP (FILTER_VALIDATE_IP y FILTER_FLAG_IPV4)
    // Sirven para verificar de forma nativa si algo es una IP o si es una IPv4.
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return [
            "ok" => false,
            "error" =>
                "La dirección IP no es válida. Usa formato IPv4 (ej: 192.168.1.100)",
        ];
    }

    return ["ok" => true, "ip" => $ip];
}

// Comprobar si el registro DNS solicitado ya existe:
function subdominio_existe(PDO $pdo, int $dominio_id, string $subdominio): bool
{
    // Recoge el subdominio del dominio del usuario que indiquemos
    $query = $pdo->prepare(
        "SELECT id FROM subdominios WHERE dominio_id = ? AND subdominio = ?",
    );
    $query->execute([$dominio_id, $subdominio]);
    // Con esto, comprobamos si el registro DNS para ese usuario ya existe.
    // Si la consulta no devuelve nada (no existe), devolveremos false y el usuario podrá crear el registro
    return (bool) $query->fetch();
}

// Cuenta el número de subdominios creados para saber cuántos quedan disponibles:
function contar_subdominios(PDO $pdo, int $dominio_id): int
{
    $query = $pdo->prepare(
        "SELECT COUNT(*) as total FROM subdominios WHERE dominio_id = ?",
    );
    $query->execute([$dominio_id]);
    $fila = $query->fetch();
    return (int) $fila["total"];
}

// Obtiene los subdominios creados por el usuario:
function obtener_subdominios(PDO $pdo, int $dominio_id): array
{
    $query = $pdo->prepare(
        "SELECT id, subdominio, ip, creado_en
         FROM subdominios
         WHERE dominio_id = ?
         ORDER BY creado_en ASC",
    );
    $query->execute([$dominio_id]);
    return $query->fetchAll();
}

// Comprobar si el correo existe en la BD
function correo_existe(PDO $pdo, string $usuario, string $dominio): bool
{
    $query = $pdo->prepare(
        "SELECT id FROM cuentas_correo
         WHERE usuario = ? AND dominio = ? AND activo = 1",
    );
    $query->execute([$usuario, $dominio]);
    return (bool) $query->fetch();
}

// Comprueba si el sitio de WP existe en la BD
function wordpress_existe(PDO $pdo, string $dominio): bool
{
    $query = $pdo->prepare(
        "SELECT id FROM wordpress_sites
         WHERE dominio = ? AND estado != 'eliminado'",
    );
    $query->execute([$dominio]);
    return (bool) $query->fetch();
}

// Valida el usuario del correo
function validar_nombre_correo(string $usuario): array
{
    $usuario = strtolower(trim($usuario));

    if (empty($usuario)) {
        return [
            "ok" => false,
            "error" => "El nombre de correo no puede estar vacío.",
        ];
    }

    // Permitimos letras, números, puntos, guiones y guiones bajos
    // No puede empezar con punto o guión
    if (!preg_match('/^[a-z0-9][a-z0-9._\-]*$/', $usuario)) {
        return ["ok" => false, "error" => "Nombre de correo no válido."];
    }

    return ["ok" => true, "usuario" => $usuario];
}

// Sanitiza la cadena de texto, eliminando espacios, etiquetas PHP y HTML y escapando caracteres HTML especiales.
// Se usa para evitar inyecciones de código
function sanitizar(string $valor): string
{
    // strip_tags() elimina etiquetas HTML y PHP
    return htmlspecialchars(strip_tags(trim($valor)), ENT_QUOTES, "UTF-8");
}
