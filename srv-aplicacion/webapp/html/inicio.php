<!DOCTYPE html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Página de inicio de la plataforma
session_start();

// Comprobamos si hay sesión activa para personalizar la cabecera
$logueado = !empty($_SESSION["usuario_id"]);
$nombre = $_SESSION["nombre"] ?? "";
?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nubix – Tu hosting local</title>
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
<body class="inicio-page">

<!-- Cabecera de la página de inicio -->
<header class="inicio-header">
    <!-- Logo / nombre de la plataforma -->
    <div class="inicio-logo">Nubix</div>

    <!-- Navegación derecha: cambia según si hay sesión o no -->
    <nav class="inicio-nav">
        <!-- Comprobamos si el usuario está logueado para determinar lo que se mostrará en la barra superior -->
        <?php if ($logueado): ?>
            <span class="inicio-nav-saludo">
                Hola, <strong><?= htmlspecialchars($nombre) ?></strong>
            </span>
            <span class="inicio-nav-sep">|</span>
            <a href="/dashboard.php" class="btn btn-primary btn-sm">
                Mi panel
            </a>
            <a href="/logout.php" class="btn btn-outline btn-sm">
                Cerrar sesión
            </a>
        <!-- Si no tiene sesión iniciada, cambiamos para que muestre los botones de login y registro -->
        <?php else: ?>
            <a href="/index.php?modo=login"
               class="btn btn-outline btn-sm">
                Iniciar sesión
            </a>
            <a href="/index.php?modo=registro"
               class="btn btn-primary btn-sm">
                Registrarse
            </a>
        <?php endif; ?>

        <button id="dark-mode-toggle"
                class="btn btn-outline btn-sm"
                title="Cambiar tema"
                style="font-size:1rem; padding:.3rem .6rem;">
            🌙
        </button>
    </nav>
</header>

<!-- Banner principal -->
<section class="inicio-banner">
    <div class="inicio-banner-content">
        <h1 class="inicio-banner-title">Tu dominio.<br>Tu correo.<br>Tu web.</h1>
        <p class="inicio-banner-sub">
            Hosting local gestionado, privado y sin complicaciones.
        </p>
        <!-- Botón que redirige al dashboard -->
        <a href="<?= $logueado
            ? "/dashboard.php"
            : "/index.php?modo=registro" ?>"
           class="btn btn-hero">
            <?= $logueado ? "Ir a mi panel" : "Empieza ahora" ?>
        </a>
    </div>
</section>

<section class="inicio-section">
    <div class="inicio-container">
        <h2 class="inicio-section-title">¿Quiénes somos?</h2>
        <p class="inicio-section-text">
            Nubix es una plataforma de hosting local diseñada para ofrecer
            servicios de alojamiento web, correo electrónico y gestión de
            dominios en entornos de red privada. Nuestro objetivo es poner
            a disposición de cualquier usuario una infraestructura profesional
            sin depender de proveedores externos ni de conexión a internet.
        </p>
        <p class="inicio-section-text">
            Todos los servicios corren en servidores propios dentro de tu red:
            el DNS, el correo y el servidor web están bajo tu control en todo momento.
            Ningún dato sale fuera de tu infraestructura.
        </p>
    </div>
</section>

<section class="inicio-section inicio-section-alt">
    <div class="inicio-container">
        <h2 class="inicio-section-title">¿Qué ofrecemos?</h2>

        <div class="inicio-features">

            <div class="inicio-feature-card">
                <div class="inicio-feature-icon">🌐</div>
                <h3>Dominios propios</h3>
                <p>
                    Registra tu dominio con la extensión que prefieras
                    (.es, .com, .io, .xyz…) y gestiona tus registros DNS
                    desde el panel de control.
                </p>
            </div>

            <div class="inicio-feature-card">
                <div class="inicio-feature-icon">✉️</div>
                <h3>Correo electrónico</h3>
                <p>
                    Crea cuentas de correo con tu propio dominio.
                    Compatibles con cualquier cliente de correo estándar
                    como Thunderbird mediante IMAP y SMTP.
                </p>
            </div>

            <div class="inicio-feature-card">
                <div class="inicio-feature-icon">📝</div>
                <h3>WordPress incluido</h3>
                <p>
                    Instala WordPress en tu dominio con un solo clic.
                    Tu página estará lista para configurar desde el primer
                    momento sin conocimientos técnicos.
                </p>
            </div>

        </div>
    </div>
</section>

<section class="inicio-section">
    <div class="inicio-container">
        <h2 class="inicio-section-title">¿Cómo funciona?</h2>

        <div class="inicio-steps">

            <div class="inicio-step">
                <div class="inicio-step-num">1</div>
                <div class="inicio-step-body">
                    <h3>Crea tu cuenta</h3>
                    <p>
                        Regístrate con tu email y contraseña.
                        En menos de un minuto tienes acceso al panel.
                    </p>
                </div>
            </div>

            <div class="inicio-step">
                <div class="inicio-step-num">2</div>
                <div class="inicio-step-body">
                    <h3>Registra tu dominio</h3>
                    <p>
                        Elige el nombre y la extensión de tu dominio.
                        Se configurará automáticamente en el DNS local.
                    </p>
                </div>
            </div>

            <div class="inicio-step">
                <div class="inicio-step-num">3</div>
                <div class="inicio-step-body">
                    <h3>Añade tus servicios</h3>
                    <p>
                        Crea cuentas de correo, instala WordPress
                        y gestiona tus subdominios desde el panel.
                    </p>
                </div>
            </div>

        </div>

        <div style="text-align:center; margin-top:2.5rem;">
            <a href="<?= $logueado
                ? "/dashboard.php"
                : "/index.php?modo=registro" ?>"
               class="btn btn-primary"
               style="width:auto; padding:.8rem 2.5rem; font-size:1rem;">
                <?= $logueado ? "Ir a mi panel" : "Crear cuenta gratis" ?>
            </a>
        </div>
    </div>
</section>

<footer class="inicio-footer">
    <p>© <?= date("Y") ?> Nubix. Todos los derechos reservados.</p>
</footer>

<script src="/assets/js/scripts.js"></script>
</body>
</html>
