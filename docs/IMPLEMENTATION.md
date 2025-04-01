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
- **MySQL 8.0** : Base de données relationnelle fiable

### Infrastructure
- **Installation directe** : Installation directe sur la machine virtuelle

## 2. Justification des choix architecturaux

### Architecture Web à trois niveaux
Nous avons opté pour une architecture classique à trois niveaux (présentation, logique métier, données) pour sa simplicité et sa robustesse.

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

## 4. Monitoring et maintenance

- Logs détaillés pour tous les composants
- Scripts d'administration pour faciliter la maintenance
