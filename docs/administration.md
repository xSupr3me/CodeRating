# Guide d'administration et maintenance Coursero

Ce document fournit des instructions détaillées pour l'administration et la maintenance du système Coursero. Il est destiné aux administrateurs système responsables de la gestion de l'infrastructure.

## Table des matières
1. [Surveillance et monitoring](#1-surveillance-et-monitoring)
2. [Gestion des sauvegardes](#2-gestion-des-sauvegardes)
3. [Mise à jour du système](#3-mise-à-jour-du-système)
4. [Maintenance de routine](#4-maintenance-de-routine)
5. [Gestion des utilisateurs](#5-gestion-des-utilisateurs)
6. [Résolution des problèmes courants](#6-résolution-des-problèmes-courants)
7. [Sécurité](#7-sécurité)
8. [Gestion du contenu pédagogique](#8-gestion-du-contenu-pédagogique)

## 1. Surveillance et monitoring

### 1.1 Surveillance des services critiques

Vérifiez régulièrement l'état des services essentiels :

```bash
# HAProxy (Load Balancer)
systemctl status haproxy

# Apache (Serveurs Web)
systemctl status apache2

# MariaDB (Base de données)
systemctl status mariadb

# Service de file d'attente
systemctl status coursero-queue
```

### 1.2 Surveillance des logs

Les logs du système sont stockés dans les emplacements suivants :

| Type de log | Emplacement |
|-------------|-------------|
| Logs d'application | `/var/www/logs/error.log` |
| Logs de file d'attente | `/var/www/logs/queue.log` |
| Logs Apache | `/var/log/apache2/coursero_error.log` |
| Logs MariaDB | `/var/log/mysql/error.log` |
| Logs HAProxy | `/var/log/haproxy.log` |

Commandes utiles pour surveiller les logs en temps réel :

```bash
# Logs d'application
tail -f /var/www/logs/error.log

# Logs de file d'attente
tail -f /var/www/logs/queue.log

# Logs Apache
tail -f /var/log/apache2/coursero_error.log

# Logs MariaDB
tail -f /var/log/mysql/error.log
```

### 1.3 Statistiques HAProxy

HAProxy fournit une interface de statistiques accessible via un navigateur :

- URL : `http://lb01:8404/stats`
- Identifiants : admin / coursero2023

Ces statistiques fournissent des informations sur :
- L'état des serveurs backend
- Le nombre de connexions
- Les taux d'erreur
- La répartition du trafic

### 1.4 Vérification de l'état de la réplication

Pour vérifier l'état de la réplication MariaDB :

```bash
# Sur le serveur esclave (db02)
mysql -e "SHOW SLAVE STATUS\G"
```

Les indicateurs clés à surveiller sont :
- `Slave_IO_Running` et `Slave_SQL_Running` doivent être à "Yes"
- `Seconds_Behind_Master` indique le retard de réplication (idéalement 0)
- `Last_Error` doit être vide

## 2. Gestion des sauvegardes

### 2.1 Sauvegarde manuelle de la base de données

Pour effectuer une sauvegarde complète de la base de données :

```bash
# Sur le serveur maître (db01)
mysqldump -u root -p --all-databases > /var/backups/coursero/full_backup_$(date +%Y%m%d).sql

# Sauvegarde de la base de données Coursero uniquement
mysqldump -u root -p coursero > /var/backups/coursero/coursero_$(date +%Y%m%d).sql
```

### 2.2 Sauvegarde automatisée

Créez une tâche cron pour automatiser les sauvegardes quotidiennes :

```bash
# Éditez le fichier crontab de l'utilisateur root
crontab -e

# Ajoutez la ligne suivante pour une sauvegarde quotidienne à 1h du matin
0 1 * * * mysqldump -u root -p$(cat /root/.mysql_password) coursero | gzip > /var/backups/coursero/coursero_$(date +\%Y\%m\%d).sql.gz
```

### 2.3 Sauvegarde des fichiers soumis

Les fichiers de code soumis par les utilisateurs sont stockés dans `/var/www/uploads/`. Pour les sauvegarder :

```bash
# Sauvegarde des fichiers soumis
tar -czf /var/backups/coursero/uploads_$(date +%Y%m%d).tar.gz /var/www/uploads/
```

### 2.4 Restauration depuis une sauvegarde

Pour restaurer la base de données à partir d'une sauvegarde :

```bash
# Sur le serveur maître (db01)
mysql -u root -p coursero < /var/backups/coursero/coursero_YYYYMMDD.sql

# Si la sauvegarde est compressée
zcat /var/backups/coursero/coursero_YYYYMMDD.sql.gz | mysql -u root -p coursero
```

Après une restauration sur le serveur maître, vous devrez reconfigurer la réplication sur le serveur esclave.

## 3. Mise à jour du système

### 3.1 Mise à jour du code source

Pour mettre à jour le code de l'application :

```bash
# Sur chaque serveur web
cd /var/www/CodeRating
git pull origin master

# Copier les fichiers mis à jour
cp -r web/* /var/www/coursero/

# Mettre à jour les permissions
chown -R www-data:www-data /var/www/coursero
```

### 3.2 Mise à jour des scripts d'administration

```bash
# Mettre à jour les scripts
cp -r /var/www/CodeRating/scripts/* /var/www/scripts/

# Mettre à jour les permissions
chmod +x /var/www/scripts/**/*.sh
chmod +x /var/www/scripts/**/*.php
chown -R www-data:www-data /var/www/scripts
```

### 3.3 Mise à jour de la structure de la base de données

Si des changements de schéma sont nécessaires :

```bash
# Appliquer les nouvelles migrations
mysql -u root -p coursero < /var/www/CodeRating/database/migrations/new_migration.sql
```

### 3.4 Mise à jour des paquets système

Mettez régulièrement à jour les paquets système pour bénéficier des correctifs de sécurité :

```bash
apt update
apt upgrade -y
```

Après une mise à jour majeure, redémarrez les services :

```bash
systemctl restart apache2
systemctl restart mariadb
systemctl restart haproxy
systemctl restart coursero-queue
```

## 4. Maintenance de routine

### 4.1 Nettoyage des fichiers temporaires

```bash
# Nettoyer les fichiers temporaires plus vieux de 7 jours
find /var/www/tmp -type f -mtime +7 -delete

# Compresser les logs anciens
find /var/www/logs -name "*.log" -mtime +7 -exec gzip {} \;

# Supprimer les logs très anciens (plus de 30 jours)
find /var/www/logs -name "*.log.gz" -mtime +30 -delete
```

### 4.2 Optimisation de la base de données

```bash
# Optimiser toutes les tables de la base de données
mysqlcheck -u root -p --optimize --all-databases
```

### 4.3 Vérification de l'espace disque

```bash
# Vérifier l'espace disque sur tous les serveurs
df -h

# Identifier les plus gros fichiers/dossiers
du -h --max-depth=1 /var/www | sort -rh | head -10
```

### 4.4 Redémarrage planifié des services

Pour maintenir les performances optimales, envisagez de redémarrer périodiquement les services :

```bash
# Redémarrage du service de file d'attente (hebdomadaire)
systemctl restart coursero-queue
```

## 5. Gestion des utilisateurs

### 5.1 Réinitialisation du mot de passe administrateur

Si vous devez réinitialiser le mot de passe de l'administrateur :

```bash
bash /var/www/CodeRating/scripts/db/reset_admin.sh
```

Ce script réinitialise le compte admin avec :
- Email : admin@coursero.local
- Mot de passe : password

### 5.2 Création d'un nouvel administrateur

```bash
# Générer un hash bcrypt pour le mot de passe
PASSWORD_HASH=$(php -r 'echo password_hash("nouveau_mot_de_passe", PASSWORD_BCRYPT);')

# Insérer un nouvel utilisateur administrateur
mysql -e "USE coursero; INSERT INTO users (email, password) VALUES ('nouvel_admin@coursero.local', '$PASSWORD_HASH');"
```

### 5.3 Suppression d'un utilisateur

```bash
mysql -e "USE coursero; DELETE FROM users WHERE email = 'utilisateur@example.com';"
```

N'oubliez pas que la suppression d'un utilisateur peut entraîner des contraintes de clé étrangère avec les soumissions existantes.

## 6. Résolution des problèmes courants

### 6.1 Soumissions bloquées en état "processing"

Si des soumissions restent bloquées en état "processing" :

```bash
# Vérifier l'état du service de file d'attente
systemctl status coursero-queue

# Réinitialiser les soumissions bloquées
mysql -e "USE coursero; UPDATE submissions SET status = 'pending' WHERE status = 'processing';"

# Redémarrer le service de file d'attente
systemctl restart coursero-queue
```

### 6.2 Problèmes de réplication de base de données

Si la réplication est interrompue :

```bash
# Vérifier l'état
mysql -e "SHOW SLAVE STATUS\G"

# Si vous voyez des erreurs SQL spécifiques, vous pouvez parfois les ignorer
mysql -e "STOP SLAVE; SET GLOBAL SQL_SLAVE_SKIP_COUNTER = 1; START SLAVE;"

# Pour des problèmes plus graves, utilisez le script de réparation
bash /var/www/CodeRating/scripts/db/fix_replication.sh
```

### 6.3 Problèmes de connexion à la base de données

Si l'application ne peut pas se connecter à la base de données :

```bash
# Vérifier que MariaDB est en cours d'exécution
systemctl status mariadb

# Vérifier la connectivité depuis le serveur web
mysql -u coursero_user -proot -h db01 -e "SELECT 1;"

# Vérifier que l'utilisateur a les permissions correctes
mysql -e "SHOW GRANTS FOR 'coursero_user'@'%';"
```

### 6.4 Problèmes avec Apache

Si Apache ne répond pas ou renvoie des erreurs :

```bash
# Vérifier la configuration Apache
apachectl configtest

# Vérifier les erreurs dans les logs
tail -f /var/log/apache2/error.log

# Redémarrer Apache
systemctl restart apache2
```

## 7. Sécurité

### 7.1 Contrôle des accès SSH

Limitez l'accès SSH aux adresses IP autorisées :

```bash
# Éditez le fichier de configuration SSH
nano /etc/ssh/sshd_config

# Ajoutez ces lignes pour restreindre l'accès
AllowUsers user1 user2
AllowGroups admins
```

### 7.2 Surveillance des tentatives d'intrusion

Installez et configurez Fail2ban pour détecter et bloquer les tentatives d'intrusion :

```bash
apt install -y fail2ban
```

Configurez Fail2ban pour surveiller les services critiques :

```bash
# Créez un fichier de configuration personnalisé
nano /etc/fail2ban/jail.local

# Ajoutez la configuration pour protéger SSH et Apache
[sshd]
enabled = true
maxretry = 3
bantime = 3600

[apache-auth]
enabled = true
```

### 7.3 Vérification des permissions des fichiers

Assurez-vous que les permissions des fichiers sont correctes :

```bash
# Définir les permissions correctes
find /var/www/coursero -type f -exec chmod 644 {} \;
find /var/www/coursero -type d -exec chmod 755 {} \;
chown -R www-data:www-data /var/www/coursero
```

### 7.4 Mise à jour régulière des composants de sécurité

```bash
# Installer les mises à jour de sécurité uniquement
apt-get update && apt-get install --only-upgrade
```

## 8. Gestion du contenu pédagogique

### 8.1 Ajout d'un nouveau cours

```sql
INSERT INTO courses (name, description) 
VALUES ('Nom du cours', 'Description détaillée du cours');
```

### 8.2 Ajout d'un exercice à un cours existant

```sql
-- Trouver l'ID du cours
SELECT id, name FROM courses;

-- Ajouter un exercice (remplacez course_id par l'ID approprié)
INSERT INTO exercises (course_id, exercise_number, title, description) 
VALUES (1, 3, 'Titre de l\'exercice', 'Description détaillée de l\'exercice');
```

### 8.3 Ajout de tests de référence pour un exercice

```sql
-- Trouver l'ID de l'exercice
SELECT id, title FROM exercises WHERE course_id = 1;

-- Ajouter des tests de référence (remplacez exercise_id et language_id par les valeurs appropriées)
INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output) 
VALUES (3, 1, 1, 'arg1 arg2', 'Sortie attendue'),
       (3, 1, 2, 'autre_arg', 'Autre sortie attendue');
```

### 8.4 Ajout d'un nouveau langage de programmation

```sql
INSERT INTO languages (name, extension) 
VALUES ('Java', 'java');
```

N'oubliez pas de configurer également le correcteur pour prendre en charge ce nouveau langage.

## 9. Procédures de reprise après sinistre

### 9.1 Perte du serveur maître de base de données

Si le serveur maître db01 tombe en panne de façon irrécupérable :

1. Promouvoir le serveur esclave en maître :
```bash
# Sur db02
mysql -e "STOP SLAVE; RESET MASTER;"
```

2. Reconfigurer l'application pour utiliser db02 comme serveur principal :
```bash
# Sur les serveurs web
sed -i 's/192.168.223.150/192.168.223.151/g' /var/www/coursero/config.php
```

3. Redémarrer les services web :
```bash
systemctl restart apache2
```

### 9.2 Perte d'un serveur web

Si un serveur web tombe en panne :

1. Vérifiez que HAProxy redirige correctement le trafic vers le serveur restant
2. Provisionnez un nouveau serveur web si nécessaire
3. Suivez les étapes d'installation pour configurer le nouveau serveur

### 9.3 Restauration complète du système

Pour une restauration complète après un sinistre majeur :

1. Restaurez la base de données à partir des sauvegardes
2. Réinstallez les serveurs web à partir du dépôt Git
3. Restaurez les fichiers soumis à partir des sauvegardes
4. Reconfigurez les services et les permissions
5. Testez le système avant de le remettre en production