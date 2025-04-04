# Test de réplication MariaDB

Ce document enregistre les résultats du test de réplication entre les serveurs de base de données.

## Test réalisé le 04 avril 2025

### Commandes exécutées sur le serveur maître (db01)

```bash
mysql -e "USE coursero; CREATE TABLE IF NOT EXISTS replication_test (id INT, value VARCHAR(255));"
mysql -e "USE coursero; INSERT INTO replication_test VALUES (1, 'Test effectué le $(date)');"
```

### Vérification sur le serveur esclave (db02)

```bash
mysql -e "USE coursero; SELECT * FROM replication_test;"
```

### Résultat

