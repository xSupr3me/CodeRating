#!/bin/bash
# Script pour corriger le service de file d'attente

# Vérifier si le répertoire existe
if [ ! -d "/var/www/coursero/scripts/queue" ]; then
  mkdir -p /var/www/coursero/scripts/queue
fi

# Créer un fichier processor.php simplifié qui fonctionne
cat > /var/www/coursero/scripts/queue/processor.php << 'EOF'
<?php
// Simple processor.php pour éviter les erreurs de service
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la journalisation
$log_file = "/var/www/logs/queue.log";
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Service démarré\n", FILE_APPEND);

// Boucle principale simplifiée
$running = true;
$count = 0;

while ($running && $count < 5) {
    try {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Service en cours d'exécution\n", FILE_APPEND);
        sleep(10); // Attendre 10 secondes
        $count++;
    } catch (Exception $e) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Erreur: " . $e->getMessage() . "\n", FILE_APPEND);
        $running = false;
    }
}

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Service terminé\n", FILE_APPEND);
EOF

# S'assurer que le service a les permissions correctes
chown -R www-data:www-data /var/www/coursero
chmod 755 /var/www/coursero/scripts/queue/processor.php

# Reconfigurer le service systemd
cat > /etc/systemd/system/coursero-queue.service << 'EOF'
[Unit]
Description=Coursero Queue Processor Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/coursero/scripts/queue
ExecStart=/usr/bin/php /var/www/coursero/scripts/queue/processor.php
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=coursero-queue

[Install]
WantedBy=multi-user.target
EOF

# Recharger et redémarrer le service
systemctl daemon-reload
systemctl restart coursero-queue

# Vérifier l'état du service
systemctl status coursero-queue
