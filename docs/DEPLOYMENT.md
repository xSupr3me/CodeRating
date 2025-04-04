# Guide de déploiement

Ce document détaille les étapes nécessaires pour déployer la plateforme Coursero en environnement de production.

## Prérequis

- Deux serveurs Linux (Debian/Ubuntu) pour les serveurs web (au moins 2GB de RAM, 20GB d'espace disque)
- Deux serveurs Linux pour les bases de données (au moins 4GB de RAM, 50GB d'espace disque)
- Un serveur Linux pour le load balancer (au moins 1GB de RAM)
- Accès administrateur (root) à tous les serveurs

## 1. Préparation de l'environnement

### Installation des paquets requis

Sur tous les serveurs:
```bash
apt update && apt upgrade -y
```

Sur les serveurs web:
```bash
# Ajouter le dépôt Sury pour PHP 8.0
apt install -y apt-transport-https lsb-release ca-certificates curl
curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt update

# Installer Apache, PHP et les extensions requises
apt install -y apache2 php8.0 php8.0-mysql php8.0-gd php8.0-intl php8.0-mbstring php8.0-xml php8.0-zip php8.0-curl libapache2-mod-php8.0
```

Sur les serveurs de base de données:
```bash
# Installer MariaDB (alternative à MySQL sur Debian)
apt install -y mariadb-server
```

Sur le load balancer:
```bash
apt install -y haproxy
```

### Cloner le dépôt
```bash
git clone --branch correction --single-branch https://github.com/xSupr3me/CodeRating.git
cd coursero
```

### Configuration des variables d'environnement
Créez un fichier `.env` à la racine du projet en vous basant sur le modèle `.env.example` :
```bash
cp .env.example .env
```

Modifiez les variables selon votre environnement :
```
# Configuration générale
APP_URL=https://votre-domaine.com

# Configuration MySQL
MYSQL_ROOT_PASSWORD=mot_de_passe_securise
MYSQL_DATABASE=coursero
MYSQL_USER=coursero_user
MYSQL_PASSWORD=mot_de_passe_securise

# Configuration SSL
SSL_CERT_PATH=/etc/ssl/certs/votre-certificat.crt
SSL_KEY_PATH=/etc/ssl/private/votre-cle.key
```

## 2. Configuration du load balancer (HAProxy)

### Installation et configuration de HAProxy

1. Créez le fichier de configuration HAProxy:
```bash
cp config/haproxy/haproxy.cfg /etc/haproxy/haproxy.cfg
```

2. Configurez les certificats SSL:
```bash
cat /path/to/your/certificate.crt /path/to/your/private.key > /etc/ssl/private/coursero.pem
chmod 600 /etc/ssl/private/coursero.pem
```

3. Redémarrez HAProxy:
```bash
systemctl restart haproxy
```

## 3. Configuration des serveurs web

### Installation et configuration d'Apache

1. Copiez les fichiers du site web:
```bash
mkdir -p /var/www/coursero
cp -r web/* /var/www/coursero/
chown -R www-data:www-data /var/www/coursero
```

2. Créez le répertoire pour les uploads:
```bash
mkdir -p /var/www/uploads
chown -R www-data:www-data /var/www/uploads
```

3. Configurez Apache:
```bash
cp config/apache/virtualhost.conf /etc/apache2/sites-available/coursero.conf
a2ensite coursero.conf
a2dissite 000-default.conf
a2enmod ssl rewrite headers
```

4. Installez les certificats SSL:
```bash
cp config/ssl/coursero.crt /etc/ssl/certs/
cp config/ssl/coursero.key /etc/ssl/private/
```

5. Configurez PHP:
```bash
cp scripts/ha/php.ini /etc/php/8.0/apache2/conf.d/99-coursero.ini
```

6. Redémarrez Apache:
```bash
systemctl restart apache2
```

## 4. Configuration des serveurs de base de données

### Configuration du serveur maître (Master)

1. Modifiez la configuration MySQL:
```bash
cp config/mysql/mysql_master.cnf /etc/mysql/mysql.conf.d/coursero-master.cnf
```

2. Redémarrez MySQL:
```bash
systemctl restart mysql
```

3. Créez un utilisateur de réplication:
```sql
CREATE USER 'repl'@'%' IDENTIFIED BY 'password_securise';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
FLUSH PRIVILEGES;
```

4. Obtenez la position du binlog:
```sql
SHOW MASTER STATUS;
```
Notez le nom du fichier et la position.

### Configuration du serveur esclave (Slave)

1. Modifiez la configuration MySQL:
```bash
cp config/mysql/mysql_slave.cnf /etc/mysql/mysql.conf.d/coursero-slave.cnf
```

2. Redémarrez MySQL:
```bash
systemctl restart mysql
```

3. Importez la base de données du maître:
```bash
mysqldump -h master_ip -u root -p --all-databases --master-data=1 > dump.sql
mysql -u root -p < dump.sql
```

4. Configurez la réplication:
```sql
CHANGE MASTER TO
  MASTER_HOST='master_ip',
  MASTER_USER='repl',
  MASTER_PASSWORD='password_securise',
  MASTER_LOG_FILE='binlog_file_note_precedemment',
  MASTER_LOG_POS=position_notee_precedemment;

START SLAVE;
```

5. Vérifiez l'état de la réplication:
```sql
SHOW SLAVE STATUS\G
```

## 5. Configuration du service de file d'attente

1. Configurez le service systemd pour le processeur de file d'attente:
```bash
cp scripts/queue/queue.service /etc/systemd/system/
```

2. Activez et démarrez le service:
```bash
systemctl enable queue
systemctl start queue
```

## 6. Vérification du déploiement

### Vérification des serveurs web
Accédez à votre domaine dans un navigateur: https://votre-domaine.com

### Vérification du load balancer
Vérifiez l'interface d'administration HAProxy: https://votre-domaine.com:8404/stats

### Vérification de la réplication MySQL
```sql
-- Sur le maître
SHOW MASTER STATUS;

-- Sur l'esclave
SHOW SLAVE STATUS\G
```

## 7. Maintenance

### Scripts de surveillance
```bash
# Surveillance des serveurs web
./scripts/monitoring/check_web_servers.sh

# Surveillance des serveurs de base de données
./scripts/monitoring/check_db_servers.sh

# Surveillance du load balancer
./scripts/monitoring/check_load_balancer.sh
```

### Sauvegarde de la base de données
```bash
./scripts/backup/backup_database.sh
```

### Rotation des logs
```bash
./scripts/maintenance/rotate_logs.sh
```

### Mise à jour du code
```bash
git pull
docker-compose down
docker-compose up -d --build
```

### Sauvegarde de la base de données
```bash
docker-compose exec db-master sh -c 'mysqldump -u root -p coursero > /tmp/backup.sql'
docker cp db-master:/tmp/backup.sql ./backups/$(date +%Y%m%d).sql
```

### Redémarrage des services
```bash
docker-compose restart [service]
```

### Consultation des logs
```bash
docker-compose logs -f [service]
```

## 8. Dépannage

### Problèmes de connexion à la base de données
Vérifiez que les services sont en cours d'exécution :
```bash
docker-compose ps
```

Vérifiez la connectivité entre les conteneurs :
```bash
docker-compose exec web1 ping db-master
```

### Problèmes d'équilibrage de charge
Consultez les statistiques HAProxy et vérifiez l'état des serveurs backend.

### Problèmes d'exécution du code
Vérifiez les logs du service de file d'attente et de correction :
```bash
docker-compose logs queue
```
