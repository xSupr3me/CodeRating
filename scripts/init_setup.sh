#!/bin/bash
# Script initial pour préparer l'environnement Coursero

# Créer les répertoires nécessaires
mkdir -p /var/www
mkdir -p /var/log/mysql
mkdir -p /var/www/coursero
mkdir -p /var/www/uploads
mkdir -p /var/www/logs
mkdir -p /var/www/tmp

# Configurer les permissions
chown -R www-data:www-data /var/www/coursero /var/www/uploads /var/www/logs /var/www/tmp
chmod -R 755 /var/www/coursero
chmod -R 777 /var/www/uploads /var/www/tmp /var/www/logs

# Pour MySQL
if id mysql &>/dev/null; then
  chown mysql:mysql /var/log/mysql
fi

echo "Répertoires de base créés avec succès"
