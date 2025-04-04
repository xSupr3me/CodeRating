# Guide d'installation de Coursero

Ce document détaille les étapes d'installation de la plateforme Coursero sur un serveur Linux.

## 1. Installation des prérequis

```bash
# Mettre à jour le système
apt update
apt upgrade -y

# Installer les paquets essentiels
apt install -y git curl wget unzip vim sudo net-tools
```

## 2. Installation de la pile LAMP

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

# Installer MariaDB (et non MySQL)
apt install -y mariadb-server
```

## 3. Configuration de MariaDB

```bash
# Sécuriser l'installation de MariaDB
mysql_secure_installation

# Créer la base de données et l'utilisateur
mysql -u root -p <<EOF
CREATE DATABASE coursero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'coursero_user'@'localhost' IDENTIFIED BY 'root';
GRANT ALL PRIVILEGES ON coursero.* TO 'coursero_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

## 4. Cloner le dépôt Coursero

```bash
# Créer les répertoires nécessaires
mkdir -p /var/www
cd /var/www

# Cloner le dépôt - branche correction spécifique
git clone --branch correction --single-branch https://github.com/xSupr3me/CodeRating.git
```

## 5. Déploiement de l'application

```bash
# Créer le répertoire pour le site web
mkdir -p /var/www/coursero

# Copier les fichiers web
cp -r /var/www/CodeRating/web/* /var/www/coursero/

# Créer les répertoires pour les uploads et les logs
mkdir -p /var/www/uploads
mkdir -p /var/www/logs
mkdir -p /var/www/tmp

# Configurer les permissions
chown -R www-data:www-data /var/www/coursero /var/www/uploads /var/www/logs /var/www/tmp
chmod -R 755 /var/www/coursero
chmod -R 777 /var/www/uploads /var/www/tmp /var/www/logs
```

## 6. Configuration d'Apache

```bash
# Configurer le virtualhost Apache pour HTTP avec redirection vers HTTPS
cat > /etc/apache2/sites-available/coursero.conf << 'EOF'
<VirtualHost *:80>
    ServerName coursero.local
    ServerAlias www.coursero.local
    
    # Méthode 1: Redirection simple vers HTTPS
    Redirect permanent / https://coursero.local/
    
    # Méthode 2 (alternative): Redirection avec mod_rewrite
    # Décommentez ces lignes si la méthode 1 ne fonctionne pas
    # RewriteEngine On
    # RewriteCond %{HTTPS} off
    # RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>
EOF

# Activer les modules nécessaires
a2enmod rewrite ssl
```

## 7. Configuration de HTTPS avec un certificat auto-signé

```bash
# Créer le répertoire pour les certificats si nécessaire
mkdir -p /etc/ssl/private

# Générer un certificat auto-signé valide pour 365 jours
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/coursero.key \
    -out /etc/ssl/certs/coursero.crt \
    -subj "/C=FR/ST=Paris/L=Paris/O=Coursero/OU=IT/CN=coursero.local"

# Créer la configuration du VirtualHost HTTPS
cat > /etc/apache2/sites-available/coursero-ssl.conf << 'EOF'
<VirtualHost *:443>
    ServerName coursero.local
    ServerAlias www.coursero.local
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
    
    # Sécurité HTTPS renforcée
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options nosniff
    
    ErrorLog ${APACHE_LOG_DIR}/coursero_error.log
    CustomLog ${APACHE_LOG_DIR}/coursero_access.log combined
</VirtualHost>
EOF

# Activer les sites et modules
a2ensite coursero.conf coursero-ssl.conf
a2dissite 000-default.conf
a2enmod ssl headers

# Redémarrer Apache pour appliquer les changements
systemctl restart apache2
```

## 8. Configuration de la base de données

```bash
# Importer le schéma de base de données
mysql -u root -p coursero < /var/www/CodeRating/database/schema.sql

# Créer un utilisateur administrateur
mysql -u root -p coursero << EOF
INSERT INTO users (email, password) 
VALUES ('admin@coursero.local', '\$2y\$10\$XQHgtV.vVEPN8GH7bHOA2.6o0.O4x1Hd5iJ9KsC5EdWXpKHOOO/62');
EOF
# Note: Le mot de passe haché ci-dessus correspond à 'password'

# Créer quelques cours et exercices de base
mysql -u root -p coursero << EOF
INSERT INTO courses (name, description) 
VALUES ('Introduction à la programmation', 'Cours de base pour apprendre la programmation');

INSERT INTO exercises (course_id, exercise_number, title, description) 
VALUES (1, 1, 'Hello World', 'Afficher "Hello, World!" à l''écran'),
       (1, 2, 'Calcul de moyenne', 'Calculer la moyenne de deux nombres');

INSERT INTO languages (name, extension) 
VALUES ('C', 'c'), ('Python', 'py');

INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output) 
VALUES (1, 1, 1, '', 'Hello, World!'),
       (1, 2, 1, '', 'Hello, World!'),
       (2, 1, 1, '10 20', '15.0'),
       (2, 1, 2, '5 7', '6.0'),
       (2, 2, 1, '10 20', '15.0'),
       (2, 2, 2, '5 7', '6.0');
EOF
```

## 9. Installation des scripts de correction et de file d'attente

```bash
# Copier les scripts de correction
mkdir -p /var/www/coursero/scripts/correction
cp -r /var/www/CodeRating/scripts/correction/* /var/www/coursero/scripts/correction/

# Copier les scripts de file d'attente
mkdir -p /var/www/coursero/scripts/queue
cp -r /var/www/CodeRating/scripts/queue/real_process.php /var/www/coursero/scripts/queue/processor.php
cp /var/www/CodeRating/scripts/queue/config.php /var/www/coursero/scripts/queue/
cp /var/www/CodeRating/scripts/queue/queue.service /var/www/coursero/scripts/queue/

# Configurer le service systemd pour la file d'attente
cp /var/www/CodeRating/scripts/queue/queue.service /etc/systemd/system/coursero-queue.service
systemctl daemon-reload
systemctl enable coursero-queue
systemctl start coursero-queue
```

## 10. Installation des paquets de développement pour la correction

```bash
# Installer gcc et autres outils
apt install -y build-essential gcc g++ python3 python3-dev
```

## 11. Finalisation et vérification

```bash
# Ajouter une entrée dans le fichier hosts pour le développement local
echo "127.0.0.1 coursero.local www.coursero.local" >> /etc/hosts

# Redémarrer Apache
systemctl restart apache2

# Vérifier l'installation
echo "L'installation est terminée. Vous pouvez accéder à Coursero à l'adresse: https://coursero.local"
```
