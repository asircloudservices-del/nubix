<?php
// Se encarga de gestionar y crear las direcciones de correo

session_start();
require_once "config/db.php";
require_once "functions/auth.php";
require_once "functions/validar.php";
require_once "functions/ansible.php";

require_login();

$usuario_id = $_SESSION["usuario_id"];
$error = "";
$exito = "";

// Comprobamos que el usuario tenga dominio
$dominio_actual = usuario_tiene_dominio($pdo, $usuario_id);
if (!$dominio_actual) {
    header("Location: /dashboard.php?error=sin_dominio");
    exit();
}

// Comprobamos si el usuario ha enviado algún formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // En caso afirmativo, guardamos los datos que introdujo en variables
    $usuario_correo = $_POST["usuario"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirma_password = $_POST["confirmar"] ?? "";

    // Validamos el nombre de usuario del correo con la funcióm
    $validar_correo = validar_nombre_correo($usuario_correo);
    if (!$validar_correo["ok"]) {
        // Error de validación
        $error = $validar_correo["error"];
    } elseif ($password !== $confirma_password) {
        // Error de coincidencia
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 8) {
        // Error de longitud
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (
        correo_existe(
            $pdo,
            $validar_correo["usuario"],
            $dominio_actual["dominio"],
        )
    ) {
        // Error porque ya existe
        $error = "Esa dirección de correo ya existe en tu dominio.";
    } else {
        // Sin errores. Llamamos a la función para ejecutar el playbook con los parámetros necesarios
        $resultado = ejecutar_playbook("crear_correo.yml", [
            "dominio" => $dominio_actual["dominio"],
            "usuario_correo" => $validar_correo["usuario"],
            "password_correo" => $password,
        ]);

        if ($resultado["ok"]) {
            // Si se ejecutó correctamente el Playbook, redirigimos al Dashboard y terminamos la ejecución
            header("Location: /dashboard.php");
            exit();
        }

        $error = "Error al crear la cuenta de correo. Inténtalo de nuevo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear correo – Nubix Hosting</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/png" href="/assets/img/icon.png">
</head>
<body class="dashboard-page">

<nav class="navbar">
 <!-- Logo / nombre de la plataforma -->
    <a href="inicio.php" class="inicio-brand">
       <img src="assets/img/icon.png" alt="Logo Nubix" class="inicio-icono">
       <span class="inicio-logo">Nubix</span>
    </a>
    <a href="/dashboard.php" class="btn btn-sm btn-outline">← Volver al panel</a>
    <button id="dark-mode-toggle" class="btn btn-sm btn-outline" title="Cambiar tema" style="font-size:1rem; padding:.3rem .6rem;">
        🌙
    </button>
</nav>

<div class="dashboard-container">
    <section class="card">
        <h2>Crear cuenta de correo</h2>
        <p class="card-desc">
            Añade una nueva dirección de correo a tu dominio
            <strong><?= htmlspecialchars($dominio_actual["dominio"]) ?></strong>
        </p>

        <!-- Mostrar mensaje de error de haberlo -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">

            <div class="form-group">
                <label for="usuario">Dirección de correo</label>
                <div class="email-input-row">
                    <!-- Introducir usuario de correo -->
                    <input type="text" id="usuario" name="usuario" placeholder="usuario" pattern="[a-z0-9._\-]*" maxlength="64" required autocomplete="off">

                    <!-- Separador -->
                    <span class="email-at">@</span>

                    <!-- Mostrar dominio (no editable) -->
                    <span class="email-domain">
                        <?= htmlspecialchars($dominio_actual["dominio"]) ?>
                    </span>
                </div>
                <small class="form-hint">
                    Solo letras minúsculas, números, puntos y guiones.
                </small>
            </div>

            <!-- Vista previa del correo -->
            <div class="email-preview">
                <span class="email-preview-label">Tu correo será:</span>
                <span class="email-preview-value" id="email-preview">
                    usuario@<?= htmlspecialchars($dominio_actual["dominio"]) ?>
                </span>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <small class="form-hint">Mínimo 8 caracteres.</small>
            </div>

            <div class="form-group">
                <label for="confirmar">Confirmar contraseña</label>
                <input type="password" id="confirmar" name="confirmar" required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary">
                Crear dirección de correo
            </button>
        </form>
    </section>
</div>

<script>
// Script: Vista previa de dirección de correo en tiempo real
const usuarioInput = document.getElementById('usuario');
const preview = document.getElementById('email-preview');
const dominio = '<?= htmlspecialchars($dominio_actual["dominio"]) ?>';

function actualizarPreview() {
    const usuario = usuarioInput.value || 'usuario';
    preview.textContent = usuario + '@' + dominio;
}

usuarioInput.addEventListener('input', actualizarPreview);
actualizarPreview(); // Inicializar
</script>
<script src="/assets/js/scripts.js"></script>

</body>
</html>
