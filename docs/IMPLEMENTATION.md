# Choix d'implémentation

Ce document détaille les choix techniques réalisés pour l'implémentation de la plateforme Coursero, ainsi que les justifications de ces choix.

## 1. Technologies utilisées

### Frontend
- **HTML5/CSS3/JavaScript** : Technologies standard du web pour l'interface utilisateur
- **CSS personnalisé** plutôt qu'un framework : Pour garantir des performances optimales et un code léger
- **JavaScript natif** : Utilisé pour les interactions côté client, sans dépendance à des bibliothèques lourdes

### Backend
- **PHP 8.0** : Langage mature, largement supporté et adapté au développement web
- **Apache 2.4** : Serveur web robuste avec support HTTPS et module de réécriture
- **MariaDB** : Base de données relationnelle fiable avec support de réplication

### Infrastructure
- **HAProxy** : Load balancer robuste pour répartir le trafic et assurer la haute disponibilité
- **Réplication MariaDB Master-Slave** : Pour la redondance des données et la répartition de la charge

## 2. Justification des choix architecturaux

### Architecture Web à trois niveaux
Nous avons opté pour une architecture classique à trois niveaux (présentation, logique métier, données) pour sa simplicité et sa robustesse.

### Architecture haute disponibilité
- **Load balancer HAProxy** : Garantit la continuité de service en répartissant le trafic et en détectant les serveurs défaillants
- **Sticky sessions** : Assurent la persistance des sessions utilisateurs sur le même serveur
- **Réplication de base de données** : Offre une redondance des données et permet de répartir la charge de lecture

### Système de file d'attente
Nous avons implémenté notre propre système de file d'attente pour:
- Simplifier le déploiement
- Réduire les dépendances externes
- Adapter précisément le système à nos besoins spécifiques

### Système de correction
Le système de correction a été conçu pour être :
- **Sécurisé** : Isolation des processus avec des limites strictes de ressources et d'accès
- **Flexible** : Support facile de nouveaux langages
- **Précis** : Comparaison détaillée des sorties

## 3. Sécurité

### Authentification
- Hachage des mots de passe avec bcrypt (via PHP `password_hash`)
- Protection contre la fixation de session
- Cookies HTTP Only et Secure

### Exécution du code
- Timeout pour éviter les boucles infinies
- Limites de mémoire pour prévenir les fuites
- Exécution avec privilèges minimaux
- Nettoyage automatique des fichiers après exécution

### Communication
- HTTPS pour toutes les communications
- Protection contre les attaques XSS et CSRF
- Terminaison SSL sur le load balancer pour une gestion centralisée des certificats

## 4. Haute disponibilité

### Load balancing
- Algorithme de répartition de charge least-connections pour optimiser l'utilisation des resseurs
- Health checks pour détecter et isoler automatiquement les serveurs défaillants
- Configuration sticky session pour garantir la cohérence des sessions utilisateurs

### Réplication de base de données
- Configuration Master-Slave asynchrone pour la redondance des données
- Fonctionnalité de failover pour basculer vers le Slave en cas de défaillance du Master
- Scripts de récupération automatique pour maintenir la synchronisation

## 5. Monitoring et maintenance

- Logs détaillés pour tous les composants
- Scripts d'administration pour faciliter la maintenance
- Interface de statistiques HAProxy pour surveiller l'état du système en temps réel
- Mécanismes de reprise automatique après défaillance
