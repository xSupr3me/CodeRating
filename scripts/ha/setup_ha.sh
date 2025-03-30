#!/bin/bash
# Script pour configurer la haute disponibilité sans Docker

# Vérifier si le script est exécuté en tant que root
if [ "$EUID" -ne 0 ]; then
  echo "Ce script doit être exécuté en tant que root."
  exit 1
fi

# Variables de configuration
WEB_SERVER_1="web1.coursero.local"
WEB_SERVER_2="web2.coursero.local"
DB_MASTER="db-master.coursero.local"
DB_SLAVE="db-slave.coursero.local"
HAPROXY_SERVER="lb.coursero.local"
APP_PATH="/var/www/coursero"
DOMAIN="coursero.local"

# Installation des paquets nécessaires
echo "Installation des paquets nécessaires..."
apt-get update
apt-get install -y apache2 php8.0 php8.0-mysql php8.0-gd php8.0-intl php8.0-mbstring php8.0-xml \
  php8.0-zip php8.0-curl libapache2-mod-php8.0 mysql-server haproxy

# Activer les modules Apache nécessaires
a2enmod ssl rewrite headers

# Configuration de HAProxy
echo "Configuration de HAProxy..."
cat > /etc/haproxy/haproxy.cfg << EOF
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

defaults
    log     global
    mode    http
    option  httplog
    option  dontlognull
    timeout connect 5000
    timeout client  50000
    timeout server  50000

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
    server web1 ${WEB_SERVER_1}:443 ssl verify none check cookie web1
    server web2 ${WEB_SERVER_2}:443 ssl verify none check cookie web2
EOF

# Configurer Apache pour les deux serveurs web
# Configuration pour le serveur 1
echo "Configuration d'Apache..."

# Création d'un certificat SSL
mkdir -p /etc/ssl/private
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/coursero.key \
  -out /etc/ssl/certs/coursero.crt \
  -subj "/C=FR/ST=Paris/L=Paris/O=Coursero/OU=IT/CN=${DOMAIN}"

# Concaténer pour HAProxy
cat /etc/ssl/certs/coursero.crt /etc/ssl/private/coursero.key > /etc/ssl/private/coursero.pem
chmod 600 /etc/ssl/private/coursero.pem

# Créer le VirtualHost
cat > /etc/apache2/sites-available/coursero.conf << EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    
    # Redirection vers HTTPS
    Redirect permanent / https://${DOMAIN}/
</VirtualHost>

<VirtualHost *:443>
    ServerName ${DOMAIN}
    ServerAlias www.${DOMAIN}
    
    DocumentRoot ${APP_PATH}
    
    <Directory ${APP_PATH}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Configuration SSL
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/coursero.crt
    SSLCertificateKeyFile /etc/ssl/private/coursero.key
    
    # Configuration des logs
    ErrorLog \${APACHE_LOG_DIR}/coursero_error.log
    CustomLog \${APACHE_LOG_DIR}/coursero_access.log combined
</VirtualHost>
EOF

# Activer le site et désactiver le site par défaut
a2ensite coursero.conf
a2dissite 000-default.conf

# Configuration de MySQL pour la réplication
echo "Configuration de MySQL pour la réplication..."

# Configuration du maître
cat > /etc/mysql/mysql.conf.d/master.cnf << EOF
[mysqld]
server-id = 1
log_bin = mysql-bin
binlog_format = ROW
binlog_do_db = coursero

# Optimisations basiques
max_connections = 500
innodb_buffer_pool_size = 1G
EOF

# Redémarrer les services
echo "Redémarrage des services..."
systemctl restart apache2
systemctl restart haproxy
systemctl restart mysql

# Instructions pour configurer le second serveur web
echo "===================== INSTRUCTIONS ====================="
echo "Pour configurer le second serveur web et la réplication MySQL:"
echo ""
echo "1. Installez Apache, PHP et MySQL sur le second serveur web"
echo "2. Copiez les fichiers de l'application vers ${APP_PATH}"
echo "3. Copiez le certificat SSL et configurez Apache"
echo "4. Configurez MySQL en tant qu'esclave avec:"
echo "   - server-id = 2"
echo "   - replicate_do_db = coursero"
echo "   - read_only = 1"
echo ""
echo "5. Exécutez les commandes suivantes sur le maître MySQL:"
echo "   GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%' IDENTIFIED BY 'password';"
echo "   FLUSH PRIVILEGES;"
echo "   SHOW MASTER STATUS; # Notez le File et Position"
echo ""
echo "6. Exécutez les commandes suivantes sur l'esclave MySQL:"
echo "   CHANGE MASTER TO MASTER_HOST='${DB_MASTER}', MASTER_USER='repl',"
echo "   MASTER_PASSWORD='password', MASTER_LOG_FILE='fichier_du_master',"
echo "   MASTER_LOG_POS=position_du_master;"
echo "   START SLAVE;"
echo "======================================================="
