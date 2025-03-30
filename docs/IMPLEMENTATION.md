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
- **MySQL 8.0** : Base de données relationnelle fiable avec support de réplication

### Infrastructure
- **HAProxy** : Solution de load balancing légère et performante
- **Serveurs dédiés** : Installation directe sur les machines sans conteneurisation comme spécifié dans le sujet

## 2. Justification des choix architecturaux

### Architecture Web à trois niveaux
Nous avons opté pour une architecture classique à trois niveaux (présentation, logique métier, données) pour sa simplicité et sa robustesse.

### Haute disponibilité
- **Load Balancing** : HAProxy a été choisi pour sa performance, sa fiabilité et sa gestion avancée des sessions
- **Réplication de base de données** : La configuration Master-Slave offre une redondance et une disponibilité accrues
- **Sticky Sessions** : Garantissent une expérience utilisateur cohérente même avec plusieurs serveurs

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

### Choix de l'installation directe (sans Docker)
Conformément aux exigences du projet, nous avons opté pour une installation directe sur les serveurs plutôt que d'utiliser des conteneurs Docker:
- Configuration standard des services (Apache, MySQL, HAProxy)
- Utilisation d'un sandbox de sécurité personnalisé pour l'exécution du code
- Limitation des ressources via les outils standard du système (`ulimit`, `nice`, `timeout`)

## 3. Sécurité

### Authentification
- Hachage des mots de passe avec bcrypt (via PHP `password_hash`)
- Protection contre la fixation de session
- Cookies HTTP Only et Secure

### Exécution du code
- Timeout pour éviter les boucles infinies
- Limites de mémoire pour prévenir les fuites
- Isolation des processus via un sandbox personnalisé
- Exécution avec privilèges minimaux
- Nettoyage automatique des fichiers après exécution

### Communication
- HTTPS obligatoire pour toutes les communications
- En-têtes de sécurité (HSTS, X-Frame-Options, Content-Security-Policy)
- Protection contre les attaques XSS et CSRF

## 4. Scalabilité

Le système est conçu pour être facilement scalable :
- Ajout de serveurs web supplémentaires derrière le load balancer
- Extension possible vers une architecture de base de données en cluster
- Séparation claire des composants permettant une mise à l'échelle indépendante

## 5. Monitoring et maintenance

- Points de vérification de santé pour HAProxy
- Logs détaillés pour tous les composants
- Scripts d'administration pour faciliter la maintenance

## 6. Améliorations futures potentielles

- Implémentation d'un cache distribué (Redis/Memcached)
- Extension du support à d'autres langages de programmation
- Mise en place d'un système de métriques
- Évolution vers une architecture en conteneurs quand le projet le permettra
