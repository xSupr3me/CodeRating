#!/bin/bash
# Script pour réparer la réplication entre db01 et db02

# Vérifier si le script est exécuté en tant que root
if [ "$EUID" -ne 0 ]; then
  echo "Ce script doit être exécuté en tant que root"
  exit 1
fi

echo "=== Réparation de la réplication MySQL/MariaDB ==="

# Déterminer si nous sommes sur le master ou le slave
HOSTNAME=$(hostname)
if [[ "$HOSTNAME" == "db01" ]]; then
  echo "Exécution sur le serveur MASTER (db01)"
  
  # 1. Vérifier que le binlog est activé
  echo "Vérification de la configuration binlog..."
  if ! mysql -e "SHOW VARIABLES LIKE 'log_bin';" | grep -q 'ON'; then
    echo "ERROR: Le binlog n'est pas activé sur le master!"
    
    # Réparer automatiquement
    echo "Création/mise à jour du fichier de configuration..."
    cat > /etc/mysql/mariadb.conf.d/99-master.cnf << 'EOFINNER'
[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
binlog_do_db = coursero
max_binlog_size = 100M

# Permettre les connexions depuis toutes les interfaces
bind-address = 0.0.0.0
skip-networking = 0

# Optimisations
max_connections = 500
innodb_buffer_pool_size = 512M
innodb_flush_log_at_trx_commit = 1
innodb_flush_method = O_DIRECT
EOFINNER
    
    # Créer le répertoire pour les logs si nécessaire
    mkdir -p /var/log/mysql
    chown mysql:mysql /var/log/mysql
    
    # Redémarrer MariaDB
    systemctl restart mariadb
    
    echo "Configuration mise à jour et service redémarré."
  else
    echo "Binlog est correctement activé."
  fi
  
  # 2. Vérifier que l'utilisateur de réplication existe
  echo "Vérification de l'utilisateur de réplication..."
  if ! mysql -e "SELECT User, Host FROM mysql.user WHERE User='repl_user';" | grep -q 'repl_user'; then
    echo "Création de l'utilisateur de réplication..."
    mysql -e "
    CREATE USER 'repl_user'@'%' IDENTIFIED BY 'repl_password';
    GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%';
    FLUSH PRIVILEGES;
    "
  else
    echo "L'utilisateur de réplication existe déjà."
    # S'assurer que les privilèges sont corrects
    mysql -e "GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%'; FLUSH PRIVILEGES;"
  fi
  
  # 3. S'assurer que le schema est créé
  echo "Vérification de la base de données coursero..."
  if ! mysql -e "SHOW DATABASES" | grep -q 'coursero'; then
    mysql -e "CREATE DATABASE IF NOT EXISTS coursero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo "Base de données coursero créée."
  else
    echo "Base de données coursero existe déjà."
  fi
  
  # 4. Importer le schéma si nécessaire
  if ! mysql -e "USE coursero; SHOW TABLES" | grep -q 'users'; then
    echo "Importation du schéma de base..."
    if [ -f "/var/www/CodeRating/database/schema.sql" ]; then
      mysql coursero < /var/www/CodeRating/database/schema.sql
      echo "Schéma importé."
    else
      echo "Création des tables de base..."
      # Créer au moins la table de test de réplication
      mysql -e "
      USE coursero;
      CREATE TABLE IF NOT EXISTS replication_test (
        id INT,
        value VARCHAR(255)
      );
      "
    fi
  fi
  
  # 5. Obtenir et afficher les coordonnées du master
  echo "Coordonnées de réplication actuelles du master:"
  mysql -e "SHOW MASTER STATUS\G"
  
  # 6. Insérer des données de test pour vérifier la réplication
  echo "Insertion de données de test dans la table replication_test..."
  mysql -e "USE coursero; INSERT INTO replication_test VALUES (99, 'Test maître-esclave $(date)');"
  
  # 7. Vérifier la connectivité avec l'esclave
  echo "Vérification de la connectivité avec db02..."
  if ping -c 3 db02 > /dev/null 2>&1; then
    echo "Connectivité OK avec db02."
  else
    echo "ATTENTION: Impossible de joindre db02. Vérifiez la connectivité réseau."
  fi
  
  echo "Configuration du master terminée."
  echo ""
  echo "Maintenant, connectez-vous au serveur slave (db02) et exécutez ce même script."
  
elif [[ "$HOSTNAME" == "db02" ]]; then
  echo "Exécution sur le serveur SLAVE (db02)"
  
  # 1. Vérifier que la configuration slave est correcte
  echo "Vérification de la configuration slave..."
  if ! grep -q "server-id.*=.*2" /etc/mysql/mariadb.conf.d/99-slave.cnf 2>/dev/null; then
    echo "Création/mise à jour du fichier de configuration slave..."
    cat > /etc/mysql/mariadb.conf.d/99-slave.cnf << 'EOFINNER'
[mysqld]
server-id = 2
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
replicate_do_db = coursero
read_only = 1

# Optimisations
max_connections = 500
innodb_buffer_pool_size = 512M
innodb_flush_log_at_trx_commit = 1
innodb_flush_method = O_DIRECT
EOFINNER
    
    # Créer le répertoire pour les logs si nécessaire
    mkdir -p /var/log/mysql
    chown mysql:mysql /var/log/mysql
    
    # Redémarrer MariaDB
    systemctl restart mariadb
    
    echo "Configuration slave mise à jour et service redémarré."
  else
    echo "Configuration slave correcte."
  fi
  
  # 2. Vérifier si la base de données existe
  echo "Vérification de la base de données..."
  if ! mysql -e "SHOW DATABASES;" | grep -q 'coursero'; then
    echo "Création de la base de données coursero..."
    mysql -e "CREATE DATABASE IF NOT EXISTS coursero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  else
    echo "La base de données coursero existe déjà."
  fi
  
  # 3. Vérifier la connectivité avec le master
  echo "Vérification de la connectivité avec db01..."
  if ! ping -c 3 db01 > /dev/null 2>&1; then
    echo "ATTENTION: Impossible de joindre db01. Vérifiez la connectivité réseau."
    echo "La réplication ne peut pas fonctionner sans connectivité au maître."
  else
    echo "Connectivité OK avec db01."
    
    # 4. Vérifier si le port MySQL est accessible
    if nc -z -w5 db01 3306; then
      echo "Port MySQL (3306) accessible sur db01."
    else
      echo "ATTENTION: Port MySQL (3306) non accessible sur db01."
      echo "Vérifiez le pare-feu et la configuration de MariaDB sur db01."
    fi
  fi
  
  # 5. Obtenir les coordonnées du master
  echo "Veuillez entrer les informations du master obtenues précédemment:"
  read -p "MASTER_LOG_FILE (ex: mysql-bin.000001): " MASTER_LOG_FILE
  read -p "MASTER_LOG_POS (ex: 6175): " MASTER_LOG_POS
  
  # 6. Créer la table de réplication si elle n'existe pas 
  echo "Création de la table de test sur l'esclave..."
  mysql -e "
  USE coursero;
  CREATE TABLE IF NOT EXISTS replication_test (
    id INT,
    value VARCHAR(255)
  );
  "
  
  # 7. Vider toutes les tables existantes dans coursero
  echo "Réinitialisation de la base de données pour la réplication..."
  TABLES=$(mysql -N -e "USE coursero; SHOW TABLES")
  for table in $TABLES; do
    mysql -e "USE coursero; TRUNCATE TABLE $table"
  done
  
  # 8. Arrêter et reconfigurer la réplication
  echo "Arrêt de la réplication existante..."
  mysql -e "STOP SLAVE;"
  
  echo "Réinitialisation complète du slave..."
  mysql -e "RESET SLAVE;"
  
  echo "Configuration de la réplication..."
  mysql -e "
  CHANGE MASTER TO
    MASTER_HOST='192.168.223.150',
    MASTER_USER='repl_user',
    MASTER_PASSWORD='repl_password',
    MASTER_LOG_FILE='$MASTER_LOG_FILE',
    MASTER_LOG_POS=$MASTER_LOG_POS;
  "
  
  # 9. Démarrer la réplication
  echo "Démarrage de la réplication..."
  mysql -e "START SLAVE;"
  
  # 10. Vérifier l'état initial
  echo "État initial de la réplication:"
  mysql -e "SHOW SLAVE STATUS\G"
  
  # 11. Attendre que la réplication s'établisse
  echo "Attente de 15 secondes pour permettre à la réplication de s'établir..."
  sleep 15
  
  # 12. Vérifier l'état final
  echo "État final de la réplication:"
  mysql -e "SHOW SLAVE STATUS\G"
  
  # 13. Vérifier si la table de test est répliquée
  echo "Vérification de la table de test de réplication:"
  mysql -e "USE coursero; SELECT * FROM replication_test;"
  
  echo "Configuration du slave terminée."
  
else
  echo "Ce script doit être exécuté sur db01 ou db02."
  exit 1
fi

echo "=== Fin du script de réparation ==="
