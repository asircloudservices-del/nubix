<?php
// Muestra el Dashboard del usuario

session_start();
require_once "config/db.php";
require_once "functions/auth.php";
require_once "functions/validar.php";

require_login();

// Recogemos datos desde la sesión iniciada
$usuario_id = $_SESSION["usuario_id"];
$nombre = $_SESSION["nombre"];

// Consultamos la función para comprobar si el usuario tiene dominio
$dominio = usuario_tiene_dominio($pdo, $usuario_id);

// Inicializamos variables
$correos = [];
$wp = null;

if ($dominio) {
    // Consultamos a la base de datos para comprobar las cuentas de correo que tenga el usuario.
    $query = $pdo->prepare(
        "SELECT usuario, dominio, creado_en
         FROM cuentas_correo
         WHERE dominio_id = ? AND activo = 1
         ORDER BY creado_en ASC",
    );
    $query->execute([$dominio["id"]]);
    $correos = $query->fetchAll(); // fetchAll devuelve array con todas las filas

    // Consultamos a la base de datos para comprobar si el usuario tiene sitio WP creado
    $query = $pdo->prepare(
        "SELECT estado, creado_en
         FROM wordpress_sites
         WHERE dominio_id = ? AND estado != 'eliminado'
         LIMIT 1",
    );
    $query->execute([$dominio["id"]]);
    $wp = $query->fetch();

    // Esta variable se encargará de comprobar los subdominios que haya agregado el usuario a su dominio
    $subdominios = obtener_subdominios($pdo, $dominio["id"]);
    // Recoge el número total de subdominios para saber cuántos más puede crear
    $total_subdominios = count($subdominios);
}

// Mensajes de feedback para operaciones de subdominio
// Se pasan como parámetros GET desde subdominio.php
$dns_error = $_GET["dns_error"] ?? "";
$dns_ok = $_GET["dns_ok"] ?? "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel – Nubix Hosting</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/png" href="/assets/img/icon.png">
</head>
<body class="dashboard-page">

<!-- Barra de navegación -->
<nav class="navbar">
     <!-- Logo / nombre de la plataforma -->
    <a href="inicio.php" class="inicio-brand">
       <img src="assets/img/icon.png" alt="Logo Nubix" class="inicio-icono">
       <span class="inicio-logo">Nubix</span>
    </a>

    <div class="navbar-user">
        <!-- Mostramos el nombre del usuario guardado en sesión -->
        <span class="inicio-nav-saludo">
           Hola, <strong><?= htmlspecialchars($nombre) ?></strong>
        </span>
        <span class="inicio-nav-sep">|</span>
        <a href="/logout.php" class="btn btn-outline btn-sm">
           Cerrar sesión
        </a>
        <button id="dark-mode-toggle" class="btn btn-sm btn-outline" title="Cambiar tema" style="font-size:1rem; padding:.3rem .6rem;">
            🌙
        </button>
    </div>
</nav>

<div class="dashboard-container">

    <!-- Sección de dominio -->
    <section class="card">
        <h2>Tu dominio</h2>

        <?php if ($dominio): ?>
            <!-- Mostramos su dominio activo -->
            <p class="card-desc">Este es tu dominio registrado en la plataforma.</p>
            <p class="domain-badge"><?= htmlspecialchars(
                $dominio["dominio"],
            ) ?></p>
            <!-- SUBSECCIÓN: REGISTROS DNS -->
        <div class="dns-section">
            <h3 class="dns-title">Registros DNS</h3>
            <?php if ($dns_error): ?>
                <div class="alert alert-error"><?= htmlspecialchars(
                    $dns_error,
                ) ?></div>
            <?php endif; ?>
            <?php if ($dns_ok): ?>
                <div class="alert alert-success"><?= htmlspecialchars(
                    $dns_ok,
                ) ?></div>
            <?php endif; ?>

            <!-- Subsección: Registros DNS -->
            <table class="tabla dns-tabla">
                <thead>
                    <tr>
                        <th>Subdominio</th>
                        <th>→</th>
                        <th>IP de destino</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Se recorren todos los subdominios del usuario ya guardados en la variable, para poder imprimirlos en pantalla -->
                    <?php foreach ($subdominios as $subdominio): ?>
                    <tr class="dns-row-existing">
                        <td class="dns-fqdn">
                            <span class="dns-sub"><?= htmlspecialchars(
                                $subdominio["subdominio"],
                            ) ?></span>
                            <span class="dns-domain-suffix">.<?= htmlspecialchars(
                                $dominio["dominio"],
                            ) ?></span>
                        </td>
                        <td class="dns-arrow">→</td>
                        <td class="dns-ip"><?= htmlspecialchars(
                            $subdominio["ip"],
                        ) ?></td>
                        <!-- Para los registros DNS ya existentes agregamos una opción para eliminarlos -->
                        <td class="dns-actions">
                            <form method="POST" action="/subdominio.php"
                          onsubmit="return confirm('¿Eliminar el subdominio <?= htmlspecialchars(
                              $subdominio["subdominio"],
                          ) ?>?')">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="subdominio_id" value="<?= $subdominio[
                                    "id"
                                ] ?>">
                                <button type="submit" class="btn btn-danger btn-xs">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- Condicional para comprobar si el usuario puede crear más registros DNS o ya ha alcanzado el límite -->
                    <?php if ($total_subdominios < MAX_SUBDOMINIOS): ?>
                    <tr class="dns-row-new">
                        <td class="dns-fqdn">
                            <div class="dns-new-input">
                                <input type="text" id="nuevo-subdominio" placeholder="nombre" pattern="[a-z0-9][a-z0-9\-]*" maxlength="63" class="dns-input-sub" autocomplete="off">
                                <span class="dns-domain-suffix">.<?= htmlspecialchars(
                                    $dominio["dominio"],
                                ) ?></span>
                            </div>
                        </td>
                        <td class="dns-arrow">→</td>
                        <td>
                            <input type="text" id="nueva-ip" placeholder="192.168.1.100"
                           pattern="\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}"
                           maxlength="15" class="dns-input-ip" autocomplete="off">
                        </td>
                        <!-- Cuando se introduce el registro y se guarda, se ejecuta una función de JavaScript.
                        Esta función se encarga de recoger los datos introducidos en los inputs de arriba, y los manda como valores del formulario vacío más abajo -->
                        <td class="dns-actions">
                            <button type="button" class="btn btn-primary btn-xs"
                            onclick="crearSubdominio()">Guardar</button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Se envían los datos desde un formulario vacío, el código JavaScript se encarga de darle valor a estos datos.
            Esto se hace ya que los formularios pueden funcionar mal si los datos se recogen de una tabla HTML, de esta forma evitamos errores -->
            <form id="form-crear-subdominio" method="POST" action="/subdominio.php" style="display:none;">
                <input type="hidden" name="accion"     value="crear">
                <input type="hidden" name="subdominio" id="form-subdominio">
                <input type="hidden" name="ip"         id="form-ip">
            </form>

            <!-- Contamos los registros DNS disponibles para mostrar un mensaje que indique cuántos más se pueden crear -->
            <p class="dns-contador">
                <?php $disponibles = MAX_SUBDOMINIOS - $total_subdominios; ?>
                <?php if ($disponibles === 0): ?>
                    <span class="dns-contador-agotado">
                Has alcanzado el límite de <?= MAX_SUBDOMINIOS ?> registros.
                Elimina uno para poder añadir otro.
                    </span>
                <?php elseif ($disponibles === 1): ?>
                    Queda <strong><?= $disponibles ?></strong> registro disponible.
                <?php else: ?>
                    Quedan <strong><?= $disponibles ?></strong>
                        registros disponibles.
                <?php endif; ?>
            </p>
        </div>
                <!-- Eliminación del dominio -->
                <div class="card-actions">
                <!-- Se pide confirmación al pulsar el botón (onclick) -->
                <a href="/dominio.php?accion=eliminar" class="btn btn-danger" onclick="return confirm('¿Seguro que quieres eliminar tu dominio?\nSe borrarán también todas tus cuentas de correo y tu WordPress.')">
                    Eliminar dominio
                </a>
            </div>

        <?php else: ?>
            <!-- Si el usuario no tiene dominio mostramos la opción para comprarlo. -->
            <p class="card-desc">
                Aún no tienes un dominio registrado.<br>
                Regístralo para poder crear tu correo y tu página web.
            </p>
            <a href="/dominio.php" class="btn btn-primary" style="width:auto;">
                Registrar dominio
            </a>
        <?php endif; ?>
    </section>

    <!-- Sección de correo -->
    <?php if ($dominio): ?>
    <section class="card">
        <h2>Cuentas de correo</h2>

        <?php if ($correos): ?>
            <!-- Listamos las cuentas de correo creadas por el usuario -->
            <p class="card-desc">
                Estas son las cuentas de correo asociadas a tu dominio.
            </p>
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Dirección de correo</th>
                        <th>Fecha de creación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($correos as $cuenta): ?>
                        <tr>
                            <!-- Construimos la dirección completa de correo -->
                            <td>
                                <?= htmlspecialchars(
                                    $cuenta["usuario"] .
                                        "@" .
                                        $cuenta["dominio"],
                                ) ?>
                            </td>
                            <td><?= htmlspecialchars(
                                $cuenta["creado_en"],
                            ) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>
            <!-- Si todavía no hay cuentas no mostraremos nada -->
            <p class="card-desc">
                Aún no tienes cuentas de correo.<br>
                Crea una dirección asociada a tu dominio.
            </p>
        <?php endif; ?>

        <!-- Botón para crear dirección de correo que siempre será visible si hay un dominio asociado -->
        <a href="/correo.php" class="btn btn-primary" style="width:auto;">
            Añadir cuenta de correo
        </a>

        <!-- Guía de Thunderbird para el usuario -->
        <div class="thunderbird-guide">
            <h3 class="thunderbird-title">
                📧 Cómo configurar Thunderbird
            </h3>
            <p class="thunderbird-intro">
                Una vez creada tu cuenta de correo, sigue estos pasos en
                Mozilla Thunderbird para empezar a enviar y recibir mensajes:
            </p>

            <!-- Paso 1 -->
            <div class="tb-step">
                <div class="tb-step-num">1</div>
                <div class="tb-step-body">
                    <h4>Abre la configuración de nueva cuenta</h4>
                    <p>
                        En Thunderbird ve a <strong>Ajustes → Configuración de la cuenta → seleccione la cuenta</strong>
                        o pulsa el botón <strong>+ Nueva cuenta → Cuenta de correo</strong> en la barra lateral.
                    </p>
                </div>
            </div>

            <!-- Paso 2 -->
            <div class="tb-step">
                <div class="tb-step-num">2</div>
                <div class="tb-step-body">
                    <h4>Introduce tus datos personales</h4>
                    <p>
                        Rellena los campos con tu nombre visible, tu dirección de correo
                        completa (<em>usuario@<?= htmlspecialchars(
                            $dominio["dominio"],
                        ) ?></em>)
                        . Puede que tarde o no detecte los servidores. En dicho caso, seleccionamos
                        <strong>Configuración avanzada</strong> y seguimos con los siguientes pasos.
                    </p>
                </div>
            </div>

            <!-- Paso 3 -->
            <div class="tb-step">
                <div class="tb-step-num">3</div>
                <div class="tb-step-body">
                    <h4>Configura manualmente los servidores</h4>
                    <p>
                        Dentro de la configuración avanzada o en <strong>Configuración de la cuenta</strong> podemos introducir los siguientes valores:
                    </p>

                    <!-- Tabla de configuración de servidores -->
                    <table class="tabla tb-config-tabla">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Servidor</th>
                                <th>Puerto</th>
                                <th>Seguridad</th>
                                <th>Autenticación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Entrante (IMAP)</strong></td>
                                <td class="tb-server"><code><?= htmlspecialchars(
                                    $dominio["dominio"],
                                ) ?></code></td>
                                <td><code>993</code></td>
                                <td>SSL/TLS</td>
                                <td>Contraseña normal</td>
                            </tr>
                            <tr>
                                <td><strong>Saliente (SMTP)</strong></td>
                                <td class="tb-server"><code><?= htmlspecialchars(
                                    $dominio["dominio"],
                                ) ?></code></td>
                                <td><code>587</code></td>
                                <td>STARTTLS</td>
                                <td>Contraseña normal</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paso 4 -->
            <div class="tb-step">
                <div class="tb-step-num">4</div>
                <div class="tb-step-body">
                    <h4>Acepta el certificado autofirmado</h4>
                    <p>
                        Al conectar por primera vez Thunderbird mostrará un aviso
                        sobre el certificado de seguridad. Pulsa
                        <strong>Confirmar excepción de seguridad</strong> para continuar.
                        Este aviso es normal porque usamos un certificado local,
                        no de una autoridad pública.
                    </p>
                </div>
            </div>

            <!-- Paso 5 -->
            <div class="tb-step">
                <div class="tb-step-num">5</div>
                <div class="tb-step-body">
                    <h4>Prueba la conexión</h4>
                    <p>
                        Al acceder a la cuenta y enviar un correo, te pedirá la contraseña.
                        Una vez introducida puedes probar a enviar un correo.
                        Si ha seguido las instrucciones correctamente podrá enviar y recibir correo.
                    </p>
                    <p class="tb-note">
                        ⚠️ Asegúrate de que tu equipo tiene configurado como DNS la
                        IP del servidor de acceso (<code>192.168.232.122</code>) para
                        que los nombres de dominio se resuelvan correctamente en la red local.
                    </p>
                </div>
            </div>

        </div>

    </section>

    <!-- Sección de WP -->
    <section class="card">
        <h2>Página web (WordPress)</h2>

        <?php if ($wp): ?>
            <?php if ($wp["estado"] === "activo"): ?>
                <!-- WordPress desplegado y funcionando -->
                <p class="card-desc">
                    Tu página web está activa. Puedes acceder a ella o ir al panel de administración de WordPress.
                </p>
                <div class="card-actions" style="display:flex; gap:.75rem; flex-wrap:wrap;">
                    <!-- Enlace a la URL del sitio web -->
                    <a href="http://<?= htmlspecialchars(
                        $dominio["dominio"],
                    ) ?>" class="btn btn-primary" style="width:auto;" target="_blank">
                        Ver mi web
                    </a>
                    <!-- Enlace al panel de administración de WordPress -->
                    <a href="http://<?= htmlspecialchars(
                        $dominio["dominio"],
                    ) ?>/wp-admin"class="btn btn-outline" style="width:auto;" target="_blank">
                        Administrar WordPress
                    </a>
                </div>

            <?php elseif ($wp["estado"] === "pendiente"): ?>
                <!-- WordPress en proceso de instalación -->
                <p class="card-desc">
                    Tu WordPress está siendo instalado. Este proceso puede tardar unos minutos. Recarga la página para ver si ya está listo.
                </p>
                <!-- Spinner visual para indicar que hay proceso en curso -->
                <div class="spinner"></div>

            <?php endif; ?>

        <?php else: ?>
            <!-- No existe sitio WP -->
            <p class="card-desc">
                Aún no tienes una página web instalada.<br> Instala WordPress para tu dominio con un solo click.
            </p>
            <a href="/wp.php" class="btn btn-primary" style="width:auto;">
                Instalar WordPress
            </a>
        <?php endif; ?>
    </section>
    <?php endif; ?>

</div>

<!-- Script para recoger datos de los nuevos registros DNS cuando el usuario cree una nueva entrada. -->
<script>
// Al pulsar el botón para guardar un registro DNS, se llamará a la siguiente función:
function crearSubdominio() {
    // Recogemos el registro, formado por el subdominio e IP introducidas en la tabla por el usuario:
    const subdominioInput = document.getElementById('nuevo-subdominio');
    const ipInput  = document.getElementById('nueva-ip');
    // Quitamos espacios sobrantes:
    const subdominio = subdominioInput.value.trim();
    const ip         = ipInput.value.trim();
    // Comprobar si los campos no están vacíos:
    if (!subdominio) { alert('Introduce un nombre para el subdominio.'); subdominioInput.focus(); return; }
    if (!ip)         { alert('Introduce una dirección IP de destino.'); ipInput.focus(); return; }
    // Comprobamos que la IP cumpla los requisitos de formato:
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip)) {
        alert('La IP no tiene un formato válido. Usa el formato 192.168.1.100');
        ipInput.focus();
        return;
    }
    // Si las comprobaciones salen bien, le damos valores a cada elemento del formulario vacío, que se enviará para la creación del registro.
    document.getElementById('form-subdominio').value = subdominio;
    document.getElementById('form-ip').value         = ip;
    document.getElementById('form-crear-subdominio').submit();
}

// El sifguiente fragmento se encarga de que al pulsar enter en los campos rellenables de la tabla, se envíen directamente los datos a la función para crear el registro.
document.addEventListener('DOMContentLoaded', function() {
    ['nuevo-subdominio', 'nueva-ip'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); crearSubdominio(); }
        });
    });
});
</script>
<script src="/assets/js/scripts.js"></script>
</body>
</html>

