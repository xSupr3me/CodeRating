# Architecture de l'infrastructure Coursero

## Vue d'ensemble

Coursero est une plateforme d'évaluation automatique de code qui s'appuie sur une architecture à haute disponibilité. Le système est conçu pour être robuste, évolutif et sécurisé.

## Diagramme d'infrastructure

```
                                  ┌─────────────┐
                                  │   Internet  │
                                  └──────┬──────┘
                                         │
                                         ▼
                              ┌────────────────────┐
                              │      HAProxy       │
                              │   Load Balancer    │
                              └─────────┬──────────┘
                                        │
                   ┌────────────────────┴─────────────────────┐
                   │                                           │
                   ▼                                           ▼
          ┌─────────────────┐                        ┌─────────────────┐
          │   Serveur Web   │                        │   Serveur Web   │
          │      (Web1)     │                        │      (Web2)     │
          └────────┬────────┘                        └────────┬────────┘
                   │                                           │
                   └─────────────┬─────────────────────┬──────┘
                                 │                     │
                                 ▼                     ▼
                     ┌────────────────────┐ ┌────────────────────┐
                     │     MySQL Master   │ │     MySQL Slave    │
                     │    (Base données   │ │    (Base données   │
                     │      primaire)     │ │     répliquée)     │
                     └────────┬───────────┘ └────────────────────┘
                              │                        ▲
                              └────────────────────────┘
                                   Réplication

                     ┌────────────────────┐
                     │  Service de file   │
                     │    d'attente et    │
                     │     correction     │
                     └────────────────────┘
```

## Composants principaux

### 1. HAProxy (Load Balancer)
- Répartit le trafic entre les deux serveurs web
- Gère les certificats SSL et termine les connexions HTTPS
- Assure la vérification de santé des serveurs
- Implémente des sticky sessions pour garantir la persistance des sessions utilisateur

### 2. Serveurs Web (Web1 et Web2)
- Exécutent Apache avec PHP
- Hébergent l'interface utilisateur et l'API
- Traitent les requêtes d'authentification et de soumission
- Servent de point d'entrée pour les utilisateurs

### 3. Bases de données (Master et Slave)
- Configuration MySQL en mode Master-Slave
- Réplication asynchrone pour assurer la haute disponibilité
- Le Master traite toutes les écritures
- Le Slave peut prendre le relais en lecture si le Master tombe

### 4. Service de file d'attente et correction
- Traite les soumissions de code de façon asynchrone
- Exécute le code soumis dans un environnement contrôlé
- Compare les résultats avec les solutions de référence
- Calcule les scores et met à jour la base de données

## Flux de données

1. L'utilisateur se connecte via HTTPS au load balancer
2. Le load balancer dirige la requête vers un des serveurs web
3. L'utilisateur s'authentifie et soumet son code
4. Le serveur web stocke le code et crée une entrée dans la file d'attente
5. Le service de correction récupère les soumissions et les évalue
6. Les résultats sont stockés dans la base de données Master
7. Les données sont répliquées vers la base Slave
8. L'utilisateur peut consulter ses résultats via l'interface web

## Mesures de sécurité

- Communication HTTPS pour toutes les interactions
- Isolation des processus d'exécution du code
- Limitations de ressources (CPU, mémoire, temps)
- Validation des entrées utilisateur
- Protection contre les attaques courantes (XSS, CSRF, injection SQL)
