# État actuel de la réplication MariaDB

## Paramètres du serveur maître (db01)

- **Fichier de journal binaire actuel**: mysql-bin.000002
- **Position**: 342
- **Base de données répliquée**: coursero
- **Écoute sur**: Toutes les interfaces (0.0.0.0)
- **Utilisateur de réplication**: repl_user

## État du serveur esclave (db02)

- **État de la réplication**: Fonctionnelle
- **Statut IO Thread**: Running (En cours d'exécution)
- **Statut SQL Thread**: Running (En cours d'exécution)
- **Délai de réplication**: 0 secondes (synchronisé)
- **Erreurs**: Aucune

## Vérification et maintenance de la réplication

### 1. Vérifier l'état de la réplication

Sur le serveur esclave (db02):
```bash
mysql -e "SHOW SLAVE STATUS\G"
```

Assurez-vous que:
- `Slave_IO_Running` et `Slave_SQL_Running` sont tous deux à "Yes"
- `Seconds_Behind_Master` est à 0 ou une valeur faible
- `Last_Errno` et `Last_Error` sont vides

### 2. Tester la réplication

Sur le serveur maître (db01):
```bash
mysql -e "USE coursero; CREATE TABLE IF NOT EXISTS replication_test (id INT, value VARCHAR(255));"
mysql -e "USE coursero; INSERT INTO replication_test VALUES (1, 'Test effectué le $(date)');"
```

Sur le serveur esclave (db02):
```bash
mysql -e "USE coursero; SELECT * FROM replication_test;"
```

### 3. Arrêter la réplication en cas de besoin

Sur le serveur esclave (db02):
```bash
mysql -e "STOP SLAVE;"
```

### 4. Redémarrer la réplication

Sur le serveur esclave (db02):
```bash
mysql -e "START SLAVE;"
```

### 5. Réinitialiser la réplication

Si vous devez reconfigurer la réplication à partir de zéro:
1. Arrêtez la réplication sur l'esclave: `STOP SLAVE;`
2. Obtenez les nouvelles coordonnées du maître: `SHOW MASTER STATUS;`
3. Configurez l'esclave avec les nouvelles coordonnées:
   ```sql
   CHANGE MASTER TO
     MASTER_HOST='192.168.223.150',
     MASTER_USER='repl_user',
     MASTER_PASSWORD='repl_password',
     MASTER_LOG_FILE='[nouveau_fichier]',
     MASTER_LOG_POS=[nouvelle_position];
   ```
4. Démarrez la réplication: `START SLAVE;`
