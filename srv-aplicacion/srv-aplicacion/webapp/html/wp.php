<?php
// Instala WP para el dominio del usuario

session_start();
require_once "config/db.php";
require_once "functions/auth.php";
require_once "functions/ansible.php";

require_login();

$usuario_id = $_SESSION["usuario_id"];
$error = "";
$exito = "";

// Extraer el dominio del usuario
$dominio_actual = usuario_tiene_dominio($pdo, $usuario_id);
if (!$dominio_actual) {
    // Si no tiene devolvemos un error
    header("Location: /dashboard.php?error=sin_dominio");
    exit();
}

// Consultamos a la base de datos si el usuario ya tiene WP activo
$query = $pdo->prepare(
    "SELECT id FROM wordpress_sites
     WHERE dominio_id = ? AND estado != 'eliminado'",
);
$query->execute([$dominio_actual["id"]]);
if ($query->fetch()) {
    // Si tiene, devolvemos un error
    header("Location: /dashboard.php?error=wordpress_existe");
    exit();
}

// Comprobamos si el usuario solicitó instalación pulsando algún botón
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // La siguiente función generará 16 bytes aleatorios bin2hex los pasará a hexadecimal, formando una cadena de caracteres aleatoria.
    // Esta será la contraseña para la base de datos de WP que se creará para este sitio (ya que cada sitio WP necesita una base de datos asociada)
    $wp_password = bin2hex(random_bytes(16));

    // Llamamos a la función para ejecutar el playbook que crea el sitio WP
    $resultado = ejecutar_playbook("crear_wordpress.yml", [
        "dominio" => $dominio_actual["dominio"],
        "wp_password" => $wp_password,
    ]);

    if ($resultado["ok"]) {
        // Todo correcto. Se redigirá al usuario al Dashboard y terminará la ejecución
        header("Location: /dashboard.php");
        exit();
    }

    $error = "Error al instalar WordPress. Inténtalo de nuevo.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar WordPress – Nubix Hosting</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/png" href="/assets/img/icon.png">
</head>
<body class="dashboard-page">

<nav class="navbar">
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
        <h2>Instalar WordPress</h2>
        <p class="card-desc">
            Crea tu página web en el dominio
            <strong><?= htmlspecialchars($dominio_actual["dominio"]) ?></strong>
        </p>

        <!-- Mensajes de error -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Información sobre lo que incluye la creación del sitio -->
        <div class="info-box">
            <h3>¿Qué incluye?</h3>
            <ul class="feature-list">
                <li>Instalación completa de WordPress actualizado</li>
                <li>Base de datos independiente para tu sitio</li>
                <li>Acceso automático a panel de administración</li>
                <li>Dominio configurado y listo para usar</li>
            </ul>
        </div>

        <!-- Información sobre el proceso de instalación -->
        <div class="warning-box">
            <h3>⏱ Después de instalar</h3>
            <p>
                La instalación puede tardar algunos minutos.<br> Una vez completada, accede a <strong><?= htmlspecialchars(
                    $dominio_actual["dominio"],
                ) ?>/wp-admin</strong> para configurar tu sitio.
            </p>
        </div>

        <form method="POST" class="auth-form">
            <!-- Casilla de confirmación -->
            <label class="checkbox-label">
                <input type="checkbox" name="confirmar" required>
                Entiendo que se instalará WordPress en mi dominio
            </label>

            <button type="submit" class="btn btn-primary">
                Instalar WordPress ahora
            </button>
        </form>
    </section>
</div>

<script src="/assets/js/scripts.js"></script>
</body>
</html>
