```markdown
# Architecture de l'infrastructure Coursero

## Vue d'ensemble

Coursero est une plateforme d'évaluation automatique de code qui s'appuie sur une architecture distribuée à haute disponibilité. Le système est conçu pour être robuste, évolutif et sécurisé, tout en offrant une expérience utilisateur fluide pour l'évaluation de code.

## Diagramme d'infrastructure

## Diagramme d'infrastructure

                              ┌─────────────┐
                              │   Internet  │
                              └──────┬──────┘
                                     │
                                     ▼
                          ┌────────────────────┐
                          │      HAProxy       │
                          │   Load Balancer    │
                          │     (lb01)         │
                          └─────────┬──────────┘
                                    │
               ┌────────────────────┴─────────────────────┐
               │                                           │
               ▼                                           ▼
      ┌─────────────────┐                        ┌─────────────────┐
      │   Serveur Web   │                        │   Serveur Web   │
      │     (web01)     │                        │     (web02)     │
      └────────┬────────┘                        └────────┬────────┘
               │                                           │
               │                                           │
               │                                           │
               │      ┌────────────────────┐              │
               │      │  Serveur de file   │              │
               └──────┤    d'attente et    │◄─────────────┘
                      │    correction      │              
                      └─────────┬──────────┘              
                                │                          
               ┌────────────────┴─────────────────┐       
               │                                   │       
               ▼                                   ▼       
     ┌────────────────────┐             ┌────────────────────┐
     │     MariaDB Master │◄───────────►│     MariaDB Slave  │
     │      (db01)        │  Réplication│      (db02)        │
     └────────────────────┘             └────────────────────┘


## Composants de l'infrastructure

### 1. HAProxy Load Balancer (lb01)
- **Fonction** : Répartir le trafic entre les serveurs web, gérer les sessions utilisateurs
- **Caractéristiques** :
  - Configuration sticky session pour garantir la persistance des sessions
  - Terminaison SSL pour gérer les certificats et le chiffrement
  - Health checks pour détecter les serveurs défaillants
  - Interface statistique pour le monitoring

### 2. Serveurs Web (web01 et web02)
- **Fonction** : Héberger l'application web et traiter les requêtes utilisateurs
- **Technologies** :
  - Apache 2.4 avec modules PHP et SSL
  - PHP 8.0 avec extensions nécessaires
  - Certificats SSL auto-signés en développement, certificats valides en production

### 3. Base de données (db01 et db02)
- **Fonction** : Stocker les données de l'application de manière persistante et répliquée
- **Configuration** :
  - MariaDB en configuration Master-Slave
  - Réplication asynchrone unidirectionnelle
  - Le Master (db01) traite toutes les écritures
  - Le Slave (db02) fournit des lectures pour équilibrer la charge et assure la redondance

### 4. Système de file d'attente et correction
- **Fonction** : Traiter de façon asynchrone les soumissions de code et les évaluer
- **Composants** :
  - File d'attente gérée via la base de données (table submissions)
  - Service systemd gérant le processus de traitement
  - Scripts d'évaluation sécurisés pour différents langages de programmation

## Flux de données

### 1. Authentification et navigation
1. L'utilisateur accède à `https://coursero.local` via son navigateur
2. HAProxy dirige la requête vers l'un des serveurs web disponibles (web01 ou web02)
3. L'application PHP vérifie l'authentification et présente l'interface utilisateur
4. La session utilisateur est maintenue sur le même serveur web grâce au sticky session

### 2. Soumission de code
1. L'utilisateur soumet un fichier de code via le formulaire de soumission
2. Le serveur web valide la soumission et stocke le fichier dans `/var/www/uploads/`
3. Une entrée est créée dans la table `submissions` avec le statut "pending"
4. L'utilisateur reçoit une confirmation et peut suivre le statut de sa soumission

### 3. Evaluation du code
1. Le service `coursero-queue` s'exécute en arrière-plan sur les serveurs web
2. Ce service interroge périodiquement la base de données pour les soumissions "pending"
3. Pour chaque soumission, le service:
   - Met à jour le statut en "processing"
   - Copie le fichier dans un environnement isolé
   - Exécute le code soumis avec les tests de référence
   - Compare les résultats avec les sorties attendues
   - Calcule un score basé sur les correspondances
   - Met à jour la soumission avec le statut final et le score

### 4. Réplication des données
1. Toutes les écritures de base de données (nouvelles soumissions, mises à jour) sont effectuées sur db01 (Master)
2. Les transactions sont enregistrées dans les logs binaires du Master
3. Le Slave (db02) se connecte au Master et réplique ces transactions en continu
4. En cas de lecture intensive, l'application peut utiliser le Slave pour équilibrer la charge
5. Si le Master devient indisponible, le Slave contient une copie récente des données

## Mécanismes de sécurité

### Sécurité réseau
- Toutes les communications externes utilisent HTTPS
- Les communications entre composants internes sont sécurisées par le réseau privé
- Les ports non essentiels sont fermés par firewall

### Sécurité de l'application
- Validation stricte de toutes les entrées utilisateur
- Protection contre les attaques XSS et CSRF
- Sessions sécurisées avec régénération des identifiants

### Sécurité de l'exécution de code
- Isolation des processus d'exécution
- Limitations strictes des ressources (CPU, mémoire, réseau)
- Timeouts pour éviter les boucles infinies
- Nettoyage des fichiers temporaires après utilisation

## Haute disponibilité et reprise après sinistre

### Disponibilité des serveurs web
- Le load balancer détecte automatiquement les serveurs web indisponibles
- Si un serveur web tombe, le trafic est redirigé vers les serveurs restants
- Le service de file d'attente peut s'exécuter sur n'importe quel serveur web

### Disponibilité de la base de données
- Si le Master devient indisponible, le Slave contient une copie récente des données
- Possibilité de promouvoir le Slave en Master en cas de défaillance prolongée
- Les scripts de promotion sont inclus dans `/var/www/CodeRating/scripts/db/`

### Sauvegardes
- Des sauvegardes automatiques sont configurées sur le Master
- Les scripts de sauvegarde sont dans `/var/www/CodeRating/scripts/backup/`
- Les sauvegardes sont stockées dans `/var/backups/coursero/`

## Surveillance et maintenance

### Surveillance
- HAProxy fournit une interface de statistiques sur le port 8404
- Les logs d'application sont centralisés dans `/var/www/logs/`
- Les logs système standard (Apache, MariaDB) suivent les conventions Linux standard

### Scripts de maintenance
- Des scripts de correction des problèmes courants sont disponibles dans `/var/www/CodeRating/scripts/`
- Des scripts de test pour valider l'infrastructure sont dans `/var/www/CodeRating/scripts/testing/`
- Des utilitaires d'administration sont disponibles dans le répertoire d'administration web

## Extensibilité

L'architecture est conçue pour être extensible de plusieurs façons:

1. **Horizontale** - Possibilité d'ajouter plus de serveurs web pour augmenter la capacité
2. **Verticale** - Les composants individuels peuvent être mis à niveau pour plus de puissance
3. **Fonctionnelle** - Support facile pour l'ajout de nouveaux langages de programmation et types d'exercices

## Limites connues

1. La réplication de base de données est asynchrone, ce qui peut entraîner un léger délai dans la propagation des données
2. Le système actuel ne supporte que l'exécution de code en C et Python
3. L'architecture n'inclut pas de cluster de caches distribués (comme Redis ou Memcached)