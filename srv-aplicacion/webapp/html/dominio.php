<?php
// Para la gestión del dominio del usuario autenticado
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


session_start();
require_once "config/db.php";
require_once "functions/auth.php";
require_once "functions/validar.php";
require_once "functions/ansible.php";

require_login();

$usuario_id = $_SESSION["usuario_id"];
$error = "";
$exito = "";

// Si el usuario pulsa el botón de eliminar dominio...
if (($_GET["accion"] ?? "") === "eliminar") {
    // Consultamos BD para obtener el dominio
    $dominio_actual = usuario_tiene_dominio($pdo, $usuario_id);

    if ($dominio_actual) {
        // Si el usuario tiene dominio, el resultado es ejecutar el playbook para la eliminación
        $resultado = ejecutar_playbook("eliminar_dominio.yml", [
            "dominio" => $dominio_actual["dominio"],
        ]);

        // Si se ejecutó correctamente el playbook...
        if ($resultado["ok"]) {
            // Consultamos a la BD para marcar el dominio como eliminado
            $pdo->prepare(
                "UPDATE dominios SET estado = 'eliminado' WHERE id = ?",
            )->execute([$dominio_actual["id"]]);

            // Resdirigimos al Dashboard y detenemos la ejecución
            header("Location: /dashboard.php");
            exit();
        }

        $error = "Error al eliminar el dominio. Consulta los logs de Ansible.";
    }
}

// Si se envían datos del formulario (quiere decir que el usuario ha registrado su dominio)...
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Comprobamos si el usuario ya tiene dominio
    if (usuario_tiene_dominio($pdo, $usuario_id)) {
        $error =
            "Ya tienes un dominio registrado. Elimínalo antes de crear uno nuevo.";
    } else {
        // Recogemos SLD y TLD del formulario
        $sld = $_POST["sld"] ?? "";
        $tld = $_POST["tld"] ?? "";

        // Validamos TLD y SLD
        $validar_dominio = validar_nombre_dominio($sld, $tld);
        // Obtenemos el estado del dominio
        $estado_dominio = dominio_existe($pdo, $validar_dominio["dominio"]);

        if (!$validar_dominio["ok"]) {
            $error = $validar_dominio["error"];
            // Comprobamos si el dominio existe antes de crearlo
        } elseif ($estado_dominio && $estado_dominio["estado"] == "activo") {
            $error =
                "El dominio está siendo utilizado por otro usuario. Introduce otro.";
        } else {
            // Si las validaciones están bien, se procede a ejecutar el playbook
            $resultado = ejecutar_playbook("crear_dominio.yml", [
                "dominio" => $validar_dominio["dominio"],
            ]);

            if ($resultado["ok"]) {
                // Si el dominio ya existe en la BD con estado eliminado, actualizamos el registro de la BD
                if (
                    $estado_dominio &&
                    $estado_dominio["estado"] == "eliminado"
                ) {
                    $query = $pdo->prepare(
                        "UPDATE dominios SET estado = 'activo' WHERE id = ?",
                    );
                    $query->execute([$estado_dominio["id"]]);
                    // Si no hay registro del dominio, en lugar de actualizar insertamos uno nuevo
                } else {
                    $query = $pdo->prepare(
                        "INSERT INTO dominios (usuario_id, dominio) VALUES (?, ?)",
                    );
                    $query->execute([$usuario_id, $validar_dominio["dominio"]]);
                }

                // Volvemos al dashboard y detenemos la ejecución
                header("Location: /dashboard.php");
                exit();
            }

            $error = "Error al registrar el dominio. Inténtalo de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprar dominio – Nubix Hosting</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/png" href="/assets/img/icon.png">
</head>
<body class="dashboard-page">

<nav class="navbar">
    <div class="navbar-brand">Nubix Hosting</div>
    <a href="/dashboard.php" class="btn btn-sm">← Volver al panel</a>
    <button id="dark-mode-toggle" class="btn btn-sm btn-outline" title="Cambiar tema" style="font-size:1rem; padding:.3rem .6rem;">
        🌙
    </button>
</nav>

<div class="dashboard-container">
    <section class="card">
        <h2>Registra tu dominio</h2>
        <p class="card-desc">Elige el nombre y la extensión para tu dominio.</p>

        <!-- Mensajes de error o éxito -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-success"><?= htmlspecialchars(
                $exito,
            ) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">

            <!-- Formulario para introducir TLD y SLD -->
            <div class="form-group">
                <label for="sld">Nombre del dominio</label>
                <div class="domain-input-row">
                    <input type="text" id="sld" name="sld" placeholder="midominio" pattern="[a-z0-9][a-z0-9\-]*" maxlength="63" required autocomplete="off">

                    <!-- Separador visual entre SLD y TLD -->
                    <span class="domain-dot">.</span>

                    <select name="tld" id="tld" required class="tld-select">
                        <!-- Bucle que recorre los TLD para crear una opción de menú desplegable de cada uno -->
                        <?php foreach (
                            TLDS_DISPONIBLES
                            as $valor => $etiqueta
                        ): ?>
                            <!-- El atributo value tomará el valor de la variable "valor". -->
                            <!-- A continuación se le agregará el atributo "selected" a la opción "es" -->
                            <!-- Por último, se introduce el nombre del TLD al cerrar la etiqueta de apertura-->
                            <option value="<?= htmlspecialchars($valor) ?>"
                                <?= $valor === "es" ? "selected" : "" ?>>
                                <?= htmlspecialchars($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                </div>
                <small class="form-hint">
                    Solo letras minúsculas, números y guiones. Mínimo 2 caracteres.
                </small>
            </div>

            <!-- Vista previa del dominio completo en tiempo real con JS -->
            <div class="domain-preview">
                <span class="domain-preview-label">Tu dominio quedará así:</span>
                <span class="domain-preview-value" id="preview">midominio.es</span>
            </div>

            <button type="submit" class="btn btn-primary">Registrar dominio</button>
        </form>
    </section>
</div>

<!-- Script: Efecto visual, vista previa de dominio en tiempo real -->
<script>

const sldInput  = document.getElementById('sld');
const tldSelect = document.getElementById('tld');
const preview = document.getElementById('preview');

function actualizarPreview() {
    const sld = sldInput.value || 'midominio';
    const tld = tldSelect.value;
    preview.textContent = sld + '.' + tld;
}

sldInput.addEventListener('input', actualizarPreview);
tldSelect.addEventListener('change', actualizarPreview);

actualizarPreview();
</script>
<script src="/assets/js/scripts.js"></script>

</body>
</html>
