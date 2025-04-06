# Guide d'installation de Coursero

Ce document détaille les étapes d'installation de la plateforme Coursero sur un serveur Linux.

## 1. Installation des prérequis sur l'ensemble des VM

```bash
# Mettre à jour le système
apt update
apt upgrade -y

# Installer les paquets essentiels
apt install -y git curl wget unzip vim sudo net-tools

# Créer les répertoires nécessaires pour les logs
mkdir -p /var/log/mysql
chmod 755 /var/log/mysql
```

## 2. Installation de la pile LAMP sur lb01, web01 et web02

```bash
# Installer Apache
apt install -y apache2

# Ajouter le dépôt PHP 8.0 (pour Debian/Ubuntu)
apt install -y lsb-release apt-transport-https ca-certificates
curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt update

# Installer PHP et ses extensions
apt install -y php8.0 php8.0-mysql php8.0-gd php8.0-intl php8.0-mbstring php8.0-xml php8.0-zip php8.0-curl libapache2-mod-php8.0
```

## 3. Installation de MariaDB sur db01 (Master) et db02 (Slave)

```bash
# Installer MariaDB (et non MySQL)
apt install -y mariadb-server

# Créer et configurer les répertoires de logs
mkdir -p /var/log/mysql
chown mysql:mysql /var/log/mysql
```

## 4. Configuration du serveur de base de données MASTER (db01)

```bash
# Configurer MariaDB pour la réplication (Master)
cat > /etc/mysql/mariadb.conf.d/99-master.cnf << 'EOF'
[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
binlog_do_db = coursero
max_binlog_size = 100M

# Permettre les connexions depuis toutes les interfaces
bind-address = 0.0.0.0

# Optimisations
max_connections = 500
innodb_buffer_pool_size = 512M
innodb_flush_log_at_trx_commit = 1
innodb_flush_method = O_DIRECT
EOF

# Redémarrer MariaDB
systemctl restart mariadb
systemctl enable mariadb

# Cloner le dépôt pour accéder au schéma de BDD
cd /var/www
git clone --branch ajoutreplication --single-branch https://github.com/xSupr3me/CodeRating.git

# Créer la base de données et les utilisateurs
mysql << 'EOF'
CREATE DATABASE IF NOT EXISTS coursero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer l'utilisateur pour l'application
CREATE USER IF NOT EXISTS 'coursero_user'@'%' IDENTIFIED BY 'root';
GRANT ALL PRIVILEGES ON coursero.* TO 'coursero_user'@'%';

-- Créer l'utilisateur de réplication
CREATE USER IF NOT EXISTS 'repl_user'@'%' IDENTIFIED BY 'repl_password';
GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%';

FLUSH PRIVILEGES;
EOF

# IMPORTANT: Créer explicitement toutes les tables AVANT la réplication
mysql coursero << 'EOF'
-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des cours
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des exercices
CREATE TABLE IF NOT EXISTS exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    exercise_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_exercise (course_id, exercise_number)
);

-- Table des langages de programmation supportés
CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    extension VARCHAR(10) NOT NULL
);

-- Table des tests de référence pour les exercices
CREATE TABLE IF NOT EXISTS reference_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    test_number INT NOT NULL,
    arguments TEXT,
    expected_output TEXT,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id),
    UNIQUE KEY unique_test (exercise_id, language_id, test_number)
);

-- Table des soumissions
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    file_path VARCHAR(255),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    score DECIMAL(5,2),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id)
);

-- IMPORTANT: Table de test pour la réplication
CREATE TABLE IF NOT EXISTS replication_test (
    id INT,
    value VARCHAR(255)
);
EOF

# Insérer les données de base
mysql coursero << 'EOF'
-- Langages
INSERT IGNORE INTO languages (id, name, extension) VALUES (1, 'C', 'c');
INSERT IGNORE INTO languages (id, name, extension) VALUES (2, 'Python', 'py');

-- Admin user (password = 'password')
INSERT IGNORE INTO users (id, email, password) 
VALUES (1, 'admin@coursero.local', '$2y$10$XQHgtV.vVEPN8GH7bHOA2.6o0.O4x1Hd5iJ9KsC5EdWXpKHOOO/62');

-- Cours et exercices
INSERT IGNORE INTO courses (id, name, description) 
VALUES (1, 'Introduction à la programmation', 'Cours de base pour apprendre la programmation');

INSERT IGNORE INTO exercises (id, course_id, exercise_number, title, description) 
VALUES (1, 1, 1, 'Hello World', 'Afficher "Hello, World!" à l''écran'),
       (2, 1, 2, 'Calcul de moyenne', 'Calculer la moyenne de deux nombres');

-- Tests de référence
INSERT IGNORE INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output) 
VALUES (1, 1, 1, '', 'Hello, World!'),
       (1, 2, 1, '', 'Hello, World!'),
       (2, 1, 1, '10 20', '15.0'),
       (2, 1, 2, '5 7', '6.0'),
       (2, 2, 1, '10 20', '15.0'),
       (2, 2, 2, '5 7', '6.0');

-- IMPORTANT: Table de test pour la réplication
INSERT INTO replication_test (id, value) VALUES (1, 'Test de réplication initial');
EOF

# IMPORTANT: Obtenir les coordonnées précises du binlog
echo "=== Coordonnées de réplication pour configurer l'esclave ==="
mysql -e "SHOW MASTER STATUS\G"
echo "=== NOTEZ PRÉCISÉMENT LES VALEURS File ET Position ==="
```

## 5. Configuration du serveur de base de données SLAVE (db02)

```bash
# Configurer MariaDB pour la réplication (Slave)
cat > /etc/mysql/mariadb.conf.d/99-slave.cnf << 'EOF'
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
EOF

# Redémarrer MariaDB
systemctl restart mariadb
systemctl enable mariadb

# Cloner le dépôt
cd /var/www
git clone --branch ajoutreplication --single-branch https://github.com/xSupr3me/CodeRating.git

# IMPORTANT: Créer d'abord la base de données VIDE
mysql -e "CREATE DATABASE IF NOT EXISTS coursero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# IMPORTANT: Créer les mêmes tables que sur le master AVANT de configurer la réplication
mysql coursero << 'EOF'
-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des cours
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des exercices
CREATE TABLE IF NOT EXISTS exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    exercise_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_exercise (course_id, exercise_number)
);

-- Table des langages de programmation supportés
CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    extension VARCHAR(10) NOT NULL
);

-- Table des tests de référence pour les exercices
CREATE TABLE IF NOT EXISTS reference_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    test_number INT NOT NULL,
    arguments TEXT,
    expected_output TEXT,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id),
    UNIQUE KEY unique_test (exercise_id, language_id, test_number)
);

-- Table des soumissions
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    file_path VARCHAR(255),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    score DECIMAL(5,2),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id)
);

-- IMPORTANT: Table de test pour la réplication
CREATE TABLE IF NOT EXISTS replication_test (
    id INT,
    value VARCHAR(255)
);
EOF

# Vider toutes les tables pour éviter les conflits avec les données du master
mysql coursero -e "
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE users;
TRUNCATE TABLE courses;
TRUNCATE TABLE exercises;
TRUNCATE TABLE languages;
TRUNCATE TABLE reference_tests;
TRUNCATE TABLE submissions;
TRUNCATE TABLE replication_test;
SET FOREIGN_KEY_CHECKS = 1;
"

# Configurer la réplication avec les valeurs obtenées du Master
# IMPORTANT - Remplacez XXXXX et YYYYY par les valeurs exactes notées précédemment
mysql << EOF
STOP SLAVE;
RESET SLAVE;
CHANGE MASTER TO
  MASTER_HOST='192.168.223.150',
  MASTER_USER='repl_user',
  MASTER_PASSWORD='repl_password',
  MASTER_LOG_FILE='mysql-bin.XXXXX',  # REMPLACEZ par la valeur exacte du master
  MASTER_LOG_POS=YYYYY;               # REMPLACEZ par la valeur exacte du master

START SLAVE;
EOF

# Vérifier l'état de la réplication
mysql -e "SHOW SLAVE STATUS\G"

# Vérifier que la réplication fonctionne après quelques secondes
sleep 5
mysql -e "USE coursero; SELECT * FROM replication_test;"
# Cette commande devrait afficher la ligne "Test de réplication initial"
```

## 6. Déploiement de l'application sur web01 et web02

```bash
# Créer les répertoires nécessaires
mkdir -p /var/www/coursero
mkdir -p /var/www/uploads
mkdir -p /var/www/logs
mkdir -p /var/www/tmp
mkdir -p /var/www/scripts/queue  # Important pour les scripts de traitement

# Cloner le dépôt
cd /var/www
git clone --branch ajoutreplication --single-branch https://github.com/xSupr3me/CodeRating.git

# Copier les fichiers web
cp -r /var/www/CodeRating/web/* /var/www/coursero/

# Copier le script real_process.php au bon emplacement
cp -r /var/www/CodeRating/scripts/queue/real_process.php /var/www/scripts/queue/
chmod 755 /var/www/scripts/queue/real_process.php

# Créer un lien symbolique pour les scripts de file d'attente
mkdir -p /var/www/coursero/scripts/queue
ln -sf /var/www/scripts/queue/real_process.php /var/www/coursero/scripts/queue/real_process.php

# Création du fichier de configuration
cat > /var/www/coursero/config.php << 'EOF'
<?php
// Configuration commune en un seul fichier

// Activer les journaux d'erreurs détaillés pendant le développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration de la base de données avec haute disponibilité
$db_config = [
    'master' => [
        'host' => '192.168.223.150',  // db01
        'user' => 'coursero_user',
        'pass' => 'root',
        'name' => 'coursero',
    ],
    'slave' => [
        'host' => '192.168.223.151',  // db02
        'user' => 'coursero_user',
        'pass' => 'root',
        'name' => 'coursero',
    ],
];

// Utilisation par défaut du serveur maître
define('DB_HOST', $db_config['master']['host']);
define('DB_USER', $db_config['master']['user']);
define('DB_PASS', $db_config['master']['pass']);
define('DB_NAME', $db_config['master']['name']);

// Configuration de l'application
define('APP_NAME', 'Coursero - Évaluation de Code');
define('APP_URL', 'https://coursero.local');
define('UPLOAD_DIR', '/var/www/uploads/');
define('TEMP_DIR', '/var/www/tmp/');
define('LOG_DIR', '/var/www/logs/');
define('MAX_UPLOAD_SIZE', 1024 * 1024); // 1MB

// Configuration de journalisation
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
define('ERROR_LOG', LOG_DIR . 'error.log');
define('ACCESS_LOG', LOG_DIR . 'access.log');

// Fonction de journalisation personnalisée
function log_message($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(ERROR_LOG, $log_entry, FILE_APPEND);
}

session_start();

// Connexion à la base de données
function db_connect($read_only = false) {
    global $db_config;
    
    // Choix du serveur en fonction du type d'opération
    $config = $read_only ? $db_config['slave'] : $db_config['master'];
    
    try {
        $pdo = new PDO(
            'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        log_message('ERROR', 'Erreur de connexion à la base de données: ' . $e->getMessage());
        
        // En cas d'erreur sur le serveur préféré, essayer l'autre serveur
        if ($read_only) {
            // Si l'esclave est indisponible, utiliser le maître
            return db_connect(false);
        } elseif ($config['host'] === $db_config['master']['host']) {
            // Si le maître est indisponible, essayer l'esclave (uniquement en lecture)
            log_message('WARNING', 'Tentative de connexion au serveur esclave');
            try {
                $config = $db_config['slave'];
                $pdo = new PDO(
                    'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
                    $config['user'],
                    $config['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                return $pdo;
            } catch (PDOException $e2) {
                log_message('ERROR', 'Erreur de connexion au serveur esclave: ' . $e2->getMessage());
                die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
            }
        } else {
            die('Erreur de connexion à la base de données: ' . $e->getMessage());
        }
    }
}

// Fonction pour vérifier si l'utilisateur est connecté
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Fonction de redirection
function redirect($path) {
    header('Location: ' . APP_URL . $path);
    exit;
}

// Création des dossiers nécessaires s'ils n'existent pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>
EOF

# Créer le fichier health.php pour les health checks
cat > /var/www/coursero/health.php << 'EOF'
<?php
header('Content-Type: text/plain');
echo "OK";
?>
EOF

# Configurer les permissions
chown -R www-data:www-data /var/www/coursero /var/www/uploads /var/www/logs /var/www/tmp /var/www/scripts
chmod -R 755 /var/www/coursero
chmod -R 777 /var/www/uploads /var/www/tmp /var/www/logs

# Générer un certificat SSL pour Apache
mkdir -p /etc/ssl/private
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/coursero.key \
  -out /etc/ssl/certs/coursero.crt \
  -subj "/C=FR/ST=Paris/L=Paris/O=Coursero/OU=IT/CN=coursero.local"

# Configurer le VirtualHost Apache
cat > /etc/apache2/sites-available/coursero.conf << 'EOF'
<VirtualHost *:80>
    ServerName coursero.local
    Redirect permanent / https://coursero.local/
</VirtualHost>

<VirtualHost *:443>
    ServerName coursero.local
    DocumentRoot /var/www/coursero
    
    <Directory /var/www/coursero>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Configuration SSL
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/coursero.crt
    SSLCertificateKeyFile /etc/ssl/private/coursero.key
    
    ErrorLog ${APACHE_LOG_DIR}/coursero_error.log
    CustomLog ${APACHE_LOG_DIR}/coursero_access.log combined
</VirtualHost>
EOF

# Activer le site et désactiver le site par défaut
a2enmod ssl rewrite headers
a2ensite coursero.conf
a2dissite 000-default.conf

# Installation des paquets de développement pour la correction
apt install -y build-essential gcc g++ python3 python3-dev

# Service de file d'attente
cat > /etc/systemd/system/coursero-queue.service << 'EOF'
[Unit]
Description=Coursero Queue Processor Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/coursero/scripts/queue
ExecStart=/usr/bin/php /var/www/scripts/queue/real_process.php
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=coursero-queue

[Install]
WantedBy=multi-user.target
EOF

# Activer et démarrer les services
systemctl daemon-reload
systemctl enable coursero-queue
systemctl restart apache2
systemctl start coursero-queue
```

## 7. Configuration de HAProxy (lb01)

```bash
# Installer HAProxy
apt install -y haproxy

# Configurer HAProxy
cat > /etc/haproxy/haproxy.cfg << 'EOF'
global
    log /dev/log    local0
    log /dev/log    local1 notice
    chroot /var/lib/haproxy
    stats socket /run/haproxy/admin.sock mode 660 level admin expose-fd listeners
    stats timeout 30s
    user haproxy
    group haproxy
    daemon

    # Paramètres SSL
    ssl-default-bind-options no-sslv3 no-tlsv10 no-tlsv11
    ssl-default-bind-ciphersuites TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256
    ssl-default-bind-ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384

defaults
    log     global
    mode    http
    option  httplog
    option  dontlognull
    timeout connect 5000
    timeout client  50000
    timeout server  50000
    errorfile 400 /etc/haproxy/errors/400.http
    errorfile 403 /etc/haproxy/errors/403.http
    errorfile 408 /etc/haproxy/errors/408.http
    errorfile 500 /etc/haproxy/errors/500.http
    errorfile 502 /etc/haproxy/errors/502.http
    errorfile 503 /etc/haproxy/errors/503.http
    errorfile 504 /etc/haproxy/errors/504.http

# Stats d'administration
listen stats
    bind *:8404
    stats enable
    stats uri /stats
    stats refresh 10s
    stats auth admin:coursero2023
    stats hide-version

# Frontend HTTP (redirection vers HTTPS)
frontend http-in
    bind *:80
    mode http
    option forwardfor
    http-request redirect scheme https unless { ssl_fc }

# Frontend HTTPS
frontend https-in
    bind *:443 ssl crt /etc/ssl/private/coursero.pem
    mode http
    option forwardfor
    
    # Options de sécurité
    http-response set-header Strict-Transport-Security max-age=63072000
    http-response set-header X-Frame-Options DENY
    http-response set-header X-Content-Type-Options nosniff
    
    # Utilise l'algorithme leastconn pour le load balancing
    default_backend coursero-servers

# Backend pour les serveurs web
backend coursero-servers
    mode http
    balance leastconn
    option httpchk GET /health.php
    http-check expect status 200
    
    # Détection des serveurs morts
    default-server inter 3s fall 3 rise 2
    
    # Serveurs (en sticky session avec cookie)
    cookie SERVERID insert indirect nocache
    server web01 192.168.223.148:443 ssl verify none check cookie web01
    server web02 192.168.223.149:443 ssl verify none check cookie web02
EOF

# Générer un certificat SSL pour HAProxy
mkdir -p /etc/ssl/private
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/coursero.key \
  -out /etc/ssl/certs/coursero.crt \
  -subj "/C=FR/ST=Paris/L=Paris/O=Coursero/OU=IT/CN=coursero.local"

# Combiner certificat et clé pour HAProxy
cat /etc/ssl/certs/coursero.crt /etc/ssl/private/coursero.key > /etc/ssl/private/coursero.pem
chmod 600 /etc/ssl/private/coursero.pem

# Redémarrer HAProxy
systemctl restart haproxy
systemctl enable haproxy
```

## 8. Vérification et tests

### Vérification de la réplication

Sur db01 (Master):
```bash
# Insérer une nouvelle entrée de test
mysql -e "USE coursero; INSERT INTO replication_test VALUES (2, 'Test supplémentaire');"

# Vérifier que l'entrée a été ajoutée
mysql -e "USE coursero; SELECT * FROM replication_test;"
```

Sur db02 (Slave):
```bash
# Vérifier que l'entrée a été répliquée
mysql -e "USE coursero; SELECT * FROM replication_test;"
# Vous devriez voir les deux entrées, y compris "Test supplémentaire"
```

### Test des serveurs web

```bash
# Tester chaque serveur web
curl -k https://web01/health.php  # Devrait renvoyer "OK"
curl -k https://web02/health.php  # Devrait renvoyer "OK"
```

### Test du load balancer

```bash
# Tester le load balancer
curl -k https://lb01/health.php  # Devrait renvoyer "OK"
```

## 9. Accès à l'application

Ajoutez une entrée dans votre fichier hosts pour accéder à l'application:
