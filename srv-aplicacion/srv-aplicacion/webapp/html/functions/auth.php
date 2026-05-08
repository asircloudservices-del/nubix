<?php
// Contiene las funciones necesarias para la autenticación

// Función para registrar al usuario en la BD
function registrar_usuario(
    PDO $pdo,
    string $nombre,
    string $email,
    string $password,
): array {
    // Comprobamos si el email del usuario existe en la BD:
    $query = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    // Se define la consulta y luego se ejecuta. Cómo parámetro indicamos la variable, que remplazará al caracter "?" de la consulta.
    $query->execute([$email]);

    if ($query->fetch()) {
        // Si existe devolvemos el error y se dejará de ejecutar el código.
        return ["ok" => false, "error" => "El email ya está registrado."];
    }

    // Por seguridad, guardamos el hash de la contraseña (extraído con función PASSWORD_BCRYPT) en la base de datos.
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Preparamos la consulta para introducir los datos en la BD
    $query = $pdo->prepare(
        "INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)",
    );
    $query->execute([$nombre, $email, $hash]);

    // lastInsertId() devuelve el ID autoincremental asignado al nuevo registro
    return ["ok" => true, "id" => $pdo->lastInsertId()];
}

// Comprobar login en la BD
function login_usuario(PDO $pdo, string $email, string $password): array
{
    // Obtenemos el hash del usuario
    $query = $pdo->prepare(
        "SELECT id, nombre, password FROM usuarios WHERE email = ?",
    );
    $query->execute([$email]);
    // Fetch recoge una línea del resultado y guarda los datos en un array.
    $usuario = $query->fetch();

    // Se comprueba si la contraseña del usuario de la BD coincide.
    // La función password_verify permite hacer la comparación de la contraseña en texto claro y el hash.
    if (!$usuario || !password_verify($password, $usuario["password"])) {
        // Se devuelve error de email o contraseña. Se proporciona dicha ambigüedad para no revelar correos de usuarios.
        return ["ok" => false, "error" => "Email o contraseña incorrectos."];
    }

    // Si todo está bien, se devuelven los datos de usuario y se termina la ejecución.
    return ["ok" => true, "usuario" => $usuario];
}

// Comprueba la sesión del usuario para evitar el acceso a páginas concretas sin estar logueado
// void indica que la función no retorna ningún valor.
function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Comprobamos el login con ayuda de la variable de sesión guardada.
    if (empty($_SESSION["usuario_id"])) {
        // De no existir la sesión, lo redirigimos al login y detenemos la ejecución.
        header("Location: /index.php?msg=login_required");
        exit();
    }
}

// Consulta si el usuario ya tiene un dominio comprado
function usuario_tiene_dominio(PDO $pdo, int $usuario_id): ?array
{
    $query = $pdo->prepare(
        "SELECT id, dominio FROM dominios
         WHERE usuario_id = ? AND estado = 'activo'
         LIMIT 1",
    );
    $query->execute([$usuario_id]);

    // fetch() devuelve el array con los datos, pero si no los encuentra devolverá null.
    return $query->fetch() ?: null;
}
