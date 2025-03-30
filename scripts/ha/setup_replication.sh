#!/bin/bash

# Script pour configurer la réplication MySQL
# Note: Ce script doit être exécuté après la création de la base de données sur le serveur maître

# Variables de configuration
MASTER_HOST="db-master"
MASTER_USER="replication_user"
MASTER_PASSWORD="replication_password"
SLAVE_HOST="db-slave"
DB_NAME="coursero"
DB_USER="root"
DB_PASSWORD=""

echo "Configuration de la réplication MySQL entre $MASTER_HOST et $SLAVE_HOST"

# Vérifier que MySQL est en cours d'exécution sur les deux serveurs
echo "Vérification de la disponibilité des serveurs MySQL..."
mysql -h $MASTER_HOST -u $DB_USER -p$DB_PASSWORD -e "SELECT 1" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Erreur: Impossible de se connecter au serveur maître MySQL"
    exit 1
fi

mysql -h $SLAVE_HOST -u $DB_USER -p$DB_PASSWORD -e "SELECT 1" > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "Erreur: Impossible de se connecter au serveur esclave MySQL"
    exit 1
fi

# Créer l'utilisateur de réplication sur le serveur maître
echo "Création de l'utilisateur de réplication sur le serveur maître..."
mysql -h $MASTER_HOST -u $DB_USER -p$DB_PASSWORD << EOF
CREATE USER IF NOT EXISTS '$MASTER_USER'@'%' IDENTIFIED BY '$MASTER_PASSWORD';
GRANT REPLICATION SLAVE ON *.* TO '$MASTER_USER'@'%';
FLUSH PRIVILEGES;
EOF

# Obtenir la position actuelle du binlog
echo "Récupération des informations du binlog..."
BINLOG_INFO=$(mysql -h $MASTER_HOST -u $DB_USER -p$DB_PASSWORD -e "SHOW MASTER STATUS\G")
BINLOG_FILE=$(echo "$BINLOG_INFO" | grep "File:" | awk '{print $2}')
BINLOG_POS=$(echo "$BINLOG_INFO" | grep "Position:" | awk '{print $2}')

if [ -z "$BINLOG_FILE" ] || [ -z "$BINLOG_POS" ]; then
    echo "Erreur: Impossible de récupérer les informations du binlog"
    exit 1
fi

echo "Binlog: $BINLOG_FILE, Position: $BINLOG_POS"

# Création d'un dump de la base de données
echo "Création d'un dump de la base de données $DB_NAME..."
mkdir -p /tmp/replicate
mysqldump -h $MASTER_HOST -u $DB_USER -p$DB_PASSWORD --single-transaction --master-data=2 $DB_NAME > /tmp/replicate/$DB_NAME.sql

# Importer le dump sur le serveur esclave
echo "Importation du dump sur le serveur esclave..."
mysql -h $SLAVE_HOST -u $DB_USER -p$DB_PASSWORD -e "CREATE DATABASE IF NOT EXISTS $DB_NAME"
mysql -h $SLAVE_HOST -u $DB_USER -p$DB_PASSWORD $DB_NAME < /tmp/replicate/$DB_NAME.sql

# Configurer la réplication sur l'esclave
echo "Configuration de la réplication sur le serveur esclave..."
mysql -h $SLAVE_HOST -u $DB_USER -p$DB_PASSWORD << EOF
STOP SLAVE;
RESET SLAVE;
CHANGE MASTER TO
  MASTER_HOST='$MASTER_HOST',
  MASTER_USER='$MASTER_USER',
  MASTER_PASSWORD='$MASTER_PASSWORD',
  MASTER_LOG_FILE='$BINLOG_FILE',
  MASTER_LOG_POS=$BINLOG_POS;
START SLAVE;
EOF

# Vérifier l'état de la réplication
echo "Vérification de l'état de la réplication..."
SLAVE_STATUS=$(mysql -h $SLAVE_HOST -u $DB_USER -p$DB_PASSWORD -e "SHOW SLAVE STATUS\G")
SLAVE_IO_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_IO_Running:" | awk '{print $2}')
SLAVE_SQL_RUNNING=$(echo "$SLAVE_STATUS" | grep "Slave_SQL_Running:" | awk '{print $2}')

if [ "$SLAVE_IO_RUNNING" = "Yes" ] && [ "$SLAVE_SQL_RUNNING" = "Yes" ]; then
    echo "Réplication configurée avec succès!"
else
    echo "Erreur: La réplication n'a pas pu être configurée correctement"
    echo "Slave_IO_Running: $SLAVE_IO_RUNNING"
    echo "Slave_SQL_Running: $SLAVE_SQL_RUNNING"
    exit 1
fi

echo "Nettoyage..."
rm -rf /tmp/replicate

echo "Configuration terminée"
exit 0
