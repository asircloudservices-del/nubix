<?php
// Se encarga de conectar con la BD. Ofrece conectividad con BD a todas las páginas que lo necesiten.

// Se recogen las variables de entorno definidas en docker-compose.yml
$host = getenv("DB_HOST");
$dbname = getenv("DB_NAME");
$user = getenv("DB_USER");
$password = getenv("DB_PASSWORD");

// Comenzamos con un bloque try para intentar la conexión.
try {
    // Conexión a BD con PDO. Es una función que prepara las consultas por separado en vez de guardarlas en variables de PHP.
    // Soporta muchos motores de BD y al gestionar las consultas de dicha forma nos protege contra inyecciones SQL.
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            // En caso de errores, PDO lanzará una excepción.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Resultados de consultas guardados como arrays asociativos.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Se configura este parámetro para indicar cómo se ejecutarán las consultas.
            // Con false, le estamos indicando que las consultas preparadas se verifiquen y consilten en el servidor, evitando posibles inyecciones de código.
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    // Si se detecta algún fallo durante el intento de conexión...
} catch (PDOException $e) {
    // Si falla devolvemos un error HTTP 500 (fallo en el servidor al procesar la solicitud).
    http_response_code(500);
    // Detiene la ejecución e imprime en pantalla un JSON.
    die(json_encode(["error" => "Error de conexión a la base de datos."]));
}
