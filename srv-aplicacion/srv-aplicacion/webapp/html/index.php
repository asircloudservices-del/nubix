<?php
// Gestiona los inicios de sesión y el acceso inicial al sitio
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once "config/db.php";
require_once "functions/auth.php";

$error = "";
$exito = "";

// Se comprobará si el usuario decidió registrarse o iniciar sesión con el parámetro modo en la petición
// Si no hay nada, por defecto siempre será login
$modo = $_GET["modo"] ?? "login";

// Recogemos en una variable si ya existe una sesión para el usuario, así controlamos si está logueado
$ya_logueado = !empty($_SESSION["usuario_id"]);

// Si no está logueado, procesamos el login o registro
if (!$ya_logueado && $_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";
    if ($accion === "login") {
        $resultado = login_usuario(
            $pdo,
            $_POST["email"] ?? "",
            $_POST["password"] ?? "",
        );

        if ($resultado["ok"]) {
            $_SESSION["usuario_id"] = $resultado["usuario"]["id"];
            $_SESSION["nombre"] = $resultado["usuario"]["nombre"];
            header("Location: /dashboard.php");
            exit();
        }

        $error = $resultado["error"];
        $modo = "login";
    } elseif ($accion === "registro") {
        $nombre = trim($_POST["nombre"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirma = $_POST["confirmar"] ?? "";

        if ($password !== $confirma) {
            $error = "Las contraseñas no coinciden.";
            $modo = "registro";
        } else {
            $resultado = registrar_usuario($pdo, $nombre, $email, $password);

            if ($resultado["ok"]) {
                $exito =
                    "Cuenta creada correctamente. Ya puedes iniciar sesión.";
                $modo = "login";
            } else {
                $error = $resultado["error"];
                $modo = "registro";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nubix – <?= $modo === "login"
        ? "Iniciar sesión"
        : "Crear cuenta" ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/png" href="/assets/img/icon.png">
    <script>
        (function() {
            if (localStorage.getItem('dark-mode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="auth-page">

<div class="auth-container">

    <div class="auth-logo">
        <!-- Botón para que el usuario pueda volver a la página de inicio -->
        <a href="/inicio.php" class="auth-back">← Volver al inicio</a>
        <h1>Nubix</h1>
        <p>Tu hosting local</p>
    </div>

    <?php if ($ya_logueado): ?>
        <!-- Si ya está logueado, mostramos un aviso y opciones para que el usuario decida si quiere ir a su panel -->
        <div class="alert alert-success">
            Ya tienes una sesión iniciada como
            <strong><?= htmlspecialchars($_SESSION["nombre"]) ?></strong>.
        </div>
        <div style="display:flex; flex-direction:column; gap:.75rem; margin-top:1rem;">
            <a href="/dashboard.php" class="btn btn-primary">
                Ir a mi panel
            </a>
            <a href="/logout.php" class="btn btn-outline">
                Cerrar sesión e iniciar con otra cuenta
            </a>
        </div>

    <?php else: ?>
        <!-- Si no está logueado, le ponemos opción para iniciar sesión o registrarse -->
        <div class="auth-tabs">
            <a href="?modo=login"
               class="tab <?= $modo === "login" ? "active" : "" ?>">
                Iniciar sesión
            </a>
            <a href="?modo=registro"
               class="tab <?= $modo === "registro" ? "active" : "" ?>">
                Crear cuenta
            </a>
        </div>

        <!-- Mensajes de error o éxito -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $exito,
            ) ?></div>
        <?php endif; ?>

        <!-- Formulario de login -->
        <?php if ($modo === "login"): ?>
        <form method="POST" class="auth-form">
            <input type="hidden" name="accion" value="login">

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       required autofocus autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary">
                Iniciar sesión
            </button>
        </form>

        <!-- Formulario de registro -->
        <?php else: ?>
        <form method="POST" class="auth-form">
            <input type="hidden" name="accion" value="registro">

            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre"
                       required autofocus autocomplete="name">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       required minlength="8" autocomplete="new-password">
                <small class="form-hint">Mínimo 8 caracteres.</small>
            </div>

            <div class="form-group">
                <label for="confirmar">Confirmar contraseña</label>
                <input type="password" id="confirmar" name="confirmar"
                       required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">
                Crear cuenta
            </button>
        </form>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script src="/assets/js/scripts.js"></script>
</body>
</html>
