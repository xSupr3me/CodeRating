#!/bin/bash
# Script de déploiement pour Coursero

# Chemins
SOURCE_DIR="/var/www/CodeRating"
WEB_DIR="/var/www/coursero"
CONFIG_SAMPLE="$SOURCE_DIR/web/config.sample.php"
CONFIG_FILE="$WEB_DIR/config.php"

# Vérifier si les répertoires existent
if [ ! -d "$SOURCE_DIR" ]; then
    echo "Erreur: Le répertoire source $SOURCE_DIR n'existe pas"
    exit 1
fi

# Créer les répertoires nécessaires
mkdir -p "$WEB_DIR"
mkdir -p "$WEB_DIR/uploads"
mkdir -p "$WEB_DIR/tmp"
mkdir -p "$WEB_DIR/logs"

# Synchroniser les fichiers web
echo "Synchronisation des fichiers web..."
rsync -av --exclude=".git/" "$SOURCE_DIR/web/" "$WEB_DIR/"

# Installer les scripts
echo "Installation des scripts..."
rsync -av "$SOURCE_DIR/scripts/" "$WEB_DIR/scripts/"

# Configurer les permissions
echo "Configuration des permissions..."
chown -R www-data:www-data "$WEB_DIR"
chmod -R 755 "$WEB_DIR"
chmod -R 777 "$WEB_DIR/uploads" "$WEB_DIR/tmp" "$WEB_DIR/logs"

# Configurer le service de file d'attente
echo "Configuration du service de file d'attente..."
cp "$SOURCE_DIR/scripts/queue/queue.service" /etc/systemd/system/coursero-queue.service
systemctl daemon-reload
systemctl enable coursero-queue
systemctl restart coursero-queue

echo "Déploiement terminé !"
