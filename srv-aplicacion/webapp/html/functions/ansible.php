<?php
// Realiza una conexión SSH al host para poder ejecutar los playbooks

// Variables para usar dentro del contenedor:
define('ANSIBLE_SSH_KEY',  '/var/www/ansible-ssh/id_ed25519');
define('ANSIBLE_SSH_USER', 'ansible');
define('ANSIBLE_SSH_HOST', 'host.docker.internal');

// Variables que se usan en el host una vez establecida la conexión SSH:
define('ANSIBLE_PLAYBOOKS_DIR', '/home/ansible/ansible/playbooks');
define('ANSIBLE_BRIDGE', '/home/ansible/ansible-bridge/run-playbook.sh');
define('ANSIBLE_LOG_DIR', '/var/log/ansible-bridge');
define('LOG_DIR_CONTAINER', '/var/log/ansible-bridge');

function ejecutar_playbook(string $playbook, array $vars = []): array
{
    // Verifica si la clave SSH existe en el contendor
    if (!file_exists(ANSIBLE_SSH_KEY)) {
        error_log(
            "[Ansible] ERROR: Clave SSH no encontrada en " . ANSIBLE_SSH_KEY);
        return [
            'ok' => false,
            'log' => '',
            'codigo' => -1,
            'error' => 'Clave SSH no encontrada.'
        ];
    }

    // Variables extra que utilizamos en el playbook:
    $extra_vars_partes = [];
    foreach ($vars as $clave => $valor) {
        // Vamos rellenando las variables con formato clave="valor" y las vamos agregando en un array
        $extra_vars_partes[] = $clave . '=' . escapeshellarg($valor);
    }
    // Separamos los valores del array con espacios en una sola cadena de texto
    $extra_vars = implode(' ', $extra_vars_partes);

    $playbook_path = ANSIBLE_PLAYBOOKS_DIR . '/' . $playbook;

    $log_nombre = $playbook . '_' . date('YmdHis') . '.log';
    $log_path   = ANSIBLE_LOG_DIR . '/' . $log_nombre;

    // Este es el comando que se ejecutará tras realizar la conexión SSH
    $comando_remoto = sprintf(
        'sudo %s %s %s > %s 2>&1',
        escapeshellarg(ANSIBLE_BRIDGE),         // script para ejecutar playbook
        escapeshellarg($playbook_path),     // ruta del playbook
        $extra_vars,                             // variables para Ansible
        escapeshellarg($log_path)                // archivo de log
    );

    // Comando SSH completo que PHP ejecuta localmente
    $comando_ssh = sprintf(
        'ssh -i %s ' .
        '-o StrictHostKeyChecking=no ' .
        '-o BatchMode=yes ' .
        '-o ConnectTimeout=10 ' .
        '%s@%s %s',
        escapeshellarg(ANSIBLE_SSH_KEY),         // clave privada del contenedor
        ANSIBLE_SSH_USER,                        // usuario ansible
        ANSIBLE_SSH_HOST,                        // host.docker.internal
        escapeshellarg($comando_remoto)          // comando a ejecutar por SSH
    );

    // Ejecutamos el comando con exec()
    // output guardará la salida del comando en un array, y return_code nos indicará si falla o no
    $output = [];
    $return_code = 0;
    exec($comando_ssh, $output, $return_code);

    // Si falla PHP al ejecutar el comando, lo guardamos en un log.
    if ($return_code !== 0) {
        error_log(
            "[Ansible] Playbook '$playbook' falló con código $return_code. " .
            "Salida SSH: " . implode(' | ', $output)
        );
    }

    return [
        'ok' => $return_code === 0,
        'log' => $log_path,
        'codigo' => $return_code,
    ];
}
