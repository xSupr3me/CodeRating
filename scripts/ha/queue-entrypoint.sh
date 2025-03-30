#!/bin/bash
set -e

# Attendre que MySQL soit prêt
echo "Attente de la disponibilité de la base de données..."
timeout=60
while ! php -r "try { new PDO('mysql:host=db-master;dbname=coursero', 'root', ''); echo 'OK'; } catch (PDOException \$e) { exit(1); }" > /dev/null 2>&1; do
    timeout=$((timeout - 1))
    if [ $timeout -eq 0 ]; then
        echo "Erreur: La base de données n'est pas disponible après 60 secondes d'attente."
        exit 1
    fi
    sleep 1
done

# Création des dossiers nécessaires
mkdir -p /var/www/logs
mkdir -p /var/www/tmp

# Démarrer le processeur de file d'attente
echo "Démarrage du processeur de file d'attente..."
cd /var/www/scripts/queue
php queue_processor.php
