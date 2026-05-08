<?php
// Se encarga de la gestión de subdominios personalizados del usuario

session_start();
require_once 'config/db.php';
require_once 'functions/auth.php';
require_once 'functions/validar.php';
require_once 'functions/ansible.php';

require_login();

$usuario_id = $_SESSION['usuario_id'];

// Comprobamos el dominio actual del usuario
$dominio_actual = usuario_tiene_dominio($pdo, $usuario_id);
// Si no tiene, redirigimos al Dashboard con error
if (!$dominio_actual) {
    header('Location: /dashboard.php?error=sin_dominio');
    exit;
}

$dominio_id = $dominio_actual['id'];
$dominio_nombre = $dominio_actual['dominio'];

// Si no se envía ningún formulario a este fichero, detenemos la ejecución y resirigimos al Dashboard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}

// Guardamos la acción solicitada
$accion = $_POST['accion'] ?? '';

// Creación de subdominio
if ($accion === 'crear') {

    // Contamos los subdominios del usuario
    $contador_subdominios = contar_subdominios($pdo, $dominio_id);

    // Si se ha llevado al límite de subdominios, redirigimos al Dashboard con el error y detenemos la ejecución
    if ($contador_subdominios >= MAX_SUBDOMINIOS) {
        header('Location: /dashboard.php?dns_error=' .
            urlencode('Has alcanzado el límite de ' . MAX_SUBDOMINIOS . ' subdominios.'));
        exit;
    }

    // Comprobamos que el nombre del subdominio introducido cumpla con los requisitos de formato
    $validar_subdominio = validar_nombre_subdominio($_POST['subdominio'] ?? '');
    // Si no cumple, redirigimos al Dashboard con el respectivo error
    if (!$validar_subdominio['ok']) {
        header('Location: /dashboard.php?dns_error=' . urlencode($validar_subdominio['error']));
        exit;
    }

    // Comprobamos la sintaxis de la dirección IP introducida
    $validar_ip = validar_ip($_POST['ip'] ?? '');
    if (!$validar_ip['ok']) {
        header('Location: /dashboard.php?dns_error=' . urlencode($validar_ip['error']));
        exit;
    }

    $subdominio = $validar_subdominio['subdominio'];
    $ip = $validar_ip['ip'];

    // Comprobar que el subdominio no exista en el dominio del usuario
    if (subdominio_existe($pdo, $dominio_id, $subdominio)) {
        header('Location: /dashboard.php?dns_error=' .
            urlencode("El subdominio '$subdominio' ya existe en tu dominio."));
        exit;
    }

    // Si todo sale bien, llamamos a la función para ejecutar el respectivo playbook
    $resultado = ejecutar_playbook('crear_subdominio.yml', [
        'dominio'    => $dominio_nombre,
        'subdominio' => $subdominio,
        'ip_destino' => $ip,
    ]);

    if ($resultado['ok']) {
        // Si se ejecutó correctamente, registramos el subdominio en la BD
        $query = $pdo->prepare(
            "INSERT INTO subdominios (dominio_id, subdominio, ip)
             VALUES (?, ?, ?)"
        );
        $query->execute([$dominio_id, $subdominio, $ip]);

        header('Location: /dashboard.php?dns_ok=' .
            urlencode("Subdominio $subdominio.$dominio_nombre creado correctamente."));
        exit;
    }

    header('Location: /dashboard.php?dns_error=' .
        urlencode('Error al crear el subdominio.'));
    exit;
}

// Eliminación de subdominio
if ($accion === 'eliminar') {
    
    $subdominio_id = (int) ($_POST['subdominio_id'] ?? 0);

    if ($subdominio_id <= 0) {
        header('Location: /dashboard.php?dns_error=' .
            urlencode('Subdominio no válido.'));
        exit;
    }

    // Sección para comprobar que el subdominio exista y pertenezca al usuario (evitamos eliminaciones de subdominios de otros usuarios vía inyección de código)
    $query = $pdo->prepare(
        "SELECT s.id, s.subdominio, s.ip
         FROM subdominios s
         WHERE s.id = ? AND s.dominio_id = ?"
    );
    $query->execute([$subdominio_id, $dominio_id]);
    $subdominio = $query->fetch();

    if (!$subdominio) {
        // No existe o no pertenece al usuario
        header('Location: /dashboard.php?dns_error=' .
            urlencode('Subdominio no encontrado.'));
        exit;
    }

    // Si todo sale bien, llamamos a la función para ejecutar el playbook
    $resultado = ejecutar_playbook('eliminar_subdominio.yml', [
        'dominio'    => $dominio_nombre,
        'subdominio' => $subdominio['subdominio'],
    ]);

    if ($resultado['ok']) {
        // Si se ejecuta correctamente, eliminamos el subdominio de la BD
        $pdo->prepare("DELETE FROM subdominios WHERE id = ?")->execute([$subdominio_id]);

        header('Location: /dashboard.php?dns_ok=' .
            urlencode("Subdominio {$subdominio['subdominio']}.$dominio_nombre eliminado."));
        exit;
    }

    header('Location: /dashboard.php?dns_error=' .
        urlencode('Error al eliminar el subdominio.'));
    exit;
}

// Si el usuario intenta acceder manualmente a este fichero, lo redirigimos al Dashboard.
header('Location: /dashboard.php');
exit;
