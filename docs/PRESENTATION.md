# Guide de présentation

Ce document fournit les éléments clés pour présenter le système Coursero lors de la démonstration orale.

## 1. Structure de la présentation

### Introduction (2-3 minutes)
- Présentation du projet et de ses objectifs
- Vue d'ensemble de l'architecture
- Technologies utilisées

### Architecture technique (5 minutes)
- Diagramme d'infrastructure
- Composants principaux (Load Balancer, Web Servers, Databases)
- Flux de données

### Démonstration (10 minutes)
- Inscription/connexion utilisateur
- Sélection de cours et exercices
- Soumission de code
- Visualisation des résultats
- Démonstration de la haute disponibilité

### Aspects techniques avancés (5 minutes)
- Sécurité et isolation
- Processus de correction
- Réplication de base de données

### Réponses aux questions (5 minutes)

## 2. Points clés à souligner

### Fonctionnalités principales
- Authentification sécurisée
- Interface intuitive
- Évaluation automatique
- Haute disponibilité

### Aspects techniques
- Exécution sécurisée du code
- Load balancing avec HAProxy
- Réplication Master-Slave
- File d'attente de traitement

### Sécurité
- HTTPS
- Protection contre les attaques courantes
- Isolation des processus d'exécution

## 3. Exemples de démonstration

### Exemple 1: Hello World (C)
```c
#include <stdio.h>

int main() {
    printf("Hello, World!\n");
    return 0;
}
```

### Exemple 2: Hello World (Python)
```python
def main():
    print("Hello, World!")

if __name__ == "__main__":
    main()
```

### Exemple 3: Moyenne (C)
```c
#include <stdio.h>
#include <stdlib.h>

int main(int argc, char *argv[]) {
    if (argc != 3) {
        printf("Usage: %s <nombre1> <nombre2>\n", argv[0]);
        return 1;
    }
    
    double num1 = atof(argv[1]);
    double num2 = atof(argv[2]);
    double moyenne = (num1 + num2) / 2.0;
    
    printf("%.1f\n", moyenne);
    return 0;
}
```

### Exemple 4: Moyenne (Python)
```python
import sys

def main():
    if len(sys.argv) != 3:
        print(f"Usage: {sys.argv[0]} <nombre1> <nombre2>")
        sys.exit(1)
    
    try:
        num1 = float(sys.argv[1])
        num2 = float(sys.argv[2])
        moyenne = (num1 + num2) / 2
        
        print(f"{moyenne:.1f}")
    except ValueError:
        print("Erreur: les arguments doivent être des nombres")
        sys.exit(1)

if __name__ == "__main__":
    main()
```

### Exemple 5: Programme avec erreur (C)
```c
#include <stdio.h>

int main() {
    // Boucle infinie pour tester le timeout
    while(1) {
        printf("Test timeout\n");
    }
    return 0;
}
```

### Exemple 6: Programme avec erreur (Python)
```python
def main():
    # Erreur de syntaxe
    print("Test d'erreur")
    while True
        print("Cette ligne ne sera jamais atteinte")

if __name__ == "__main__":
    main()
```

## 4. Démonstration de haute disponibilité

### Scénario 1: Défaillance d'un serveur web
1. Montrer le fonctionnement normal
2. Arrêter un serveur web: `docker-compose stop web1`
3. Montrer que le service reste disponible via le second serveur

### Scénario 2: Défaillance du serveur de base de données principal
1. Montrer le fonctionnement normal
2. Simuler une panne du serveur Master
3. Montrer que les lectures continuent via le serveur Slave

## 5. Questions fréquentes

### Comment le système gère-t-il les boucles infinies?
- Utilisation de timeouts pour limiter le temps d'exécution
- Surveillance des ressources système

### Comment garantir que le code exécuté ne peut pas endommager le système?
- Isolation des processus
- Limitation des droits d'exécution
- Nettoyage des fichiers après évaluation

### Comment le système pourrait-il évoluer pour supporter plus d'utilisateurs?
- Ajout de serveurs web supplémentaires
- Mise en place d'un cluster de bases de données
- Configuration d'un CDN pour les ressources statiques
