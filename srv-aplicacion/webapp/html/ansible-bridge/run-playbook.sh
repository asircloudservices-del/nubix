#!/bin/bash
# ansible-bridge/run_playbook.sh
# Ejecutado por PHP via sudo, corre Ansible como usuario ansible

PLAYBOOK=$1
shift
EXTRA_VARS="$@"

if [ -z "$PLAYBOOK" ]; then
    echo "ERROR: No se especificó playbook"
    exit 1
fi

if [ ! -f "$PLAYBOOK" ]; then
    echo "ERROR: Playbook no encontrado: $PLAYBOOK"
    exit 1
fi

# Ejecutar como usuario ansible
sudo -u ansible ansible-playbook "$PLAYBOOK" \
    --extra-vars "$EXTRA_VARS" \
    -i /home/ansible/ansible/inventory/hosts.ini

exit $?
