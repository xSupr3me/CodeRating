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

# Initialiser les permissions des dossiers
echo "Initialisation des permissions..."
mkdir -p /var/www/coursero/uploads
chown -R www-data:www-data /var/www/coursero
chown -R www-data:www-data /var/lib/php/sessions

# Configurer le nom d'hôte
if [ ! -z "$SERVER_NAME" ]; then
    echo "Configuration du nom d'hôte: $SERVER_NAME"
    echo "$SERVER_NAME" > /etc/hostname
    hostname "$SERVER_NAME"
fi

# Exécuter la commande fournie
exec "$@"
