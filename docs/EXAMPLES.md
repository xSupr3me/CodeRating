# Exemples de code pour la démonstration

Ce document contient des exemples de code à utiliser pendant la démonstration du système Coursero. Ils couvrent différents cas d'usage et scénarios de test.

## 1. Exemples simples

### Hello World en C
```c
#include <stdio.h>

/**
 * Programme simple qui affiche "Hello, World!"
 */
int main() {
    printf("Hello, World!\n");
    return 0;
}
```

### Hello World en Python
```python
#!/usr/bin/env python3

"""
Programme simple qui affiche "Hello, World!"
"""

def main():
    print("Hello, World!")

if __name__ == "__main__":
    main()
```

## 2. Calcul de moyenne

### Moyenne en C
```c
#include <stdio.h>
#include <stdlib.h>

/**
 * Programme qui calcule la moyenne de deux nombres
 */
int main(int argc, char *argv[]) {
    if (argc != 3) {
        printf("Usage: %s <nombre1> <nombre2>\n", argv[0]);
        return 1;
    }
    
    double num1 = atof(argv[1]);
    double num2 = atof(argv[2]);
    double average = (num1 + num2) / 2.0;
    
    printf("%.1f\n", average);
    return 0;
}
```

### Moyenne en Python
```python
#!/usr/bin/env python3

"""
Programme qui calcule la moyenne de deux nombres
"""

import sys

def main():
    if len(sys.argv) != 3:
        print(f"Usage: {sys.argv[0]} <nombre1> <nombre2>")
        sys.exit(1)
    
    try:
        num1 = float(sys.argv[1])
        num2 = float(sys.argv[2])
        average = (num1 + num2) / 2
        
        print(f"{average:.1f}")
    except ValueError:
        print("Erreur: les arguments doivent être des nombres")
        sys.exit(1)

if __name__ == "__main__":
    main()
```

## 3. Exemples avec erreurs

### Erreur de compilation en C
```c
#include <stdio.h>

int main() {
    // Erreur de syntaxe (point-virgule manquant)
    printf("Hello, World!\n")
    return 0
}
```

### Erreur d'exécution en Python
```python
#!/usr/bin/env python3

def main():
    # Erreur de division par zéro
    result = 10 / 0
    print(result)

if __name__ == "__main__":
    main()
```

## 4. Tests de performance et de sécurité

### Programme avec boucle infinie en C
```c
#include <stdio.h>

int main() {
    // Cette boucle devrait être interrompue par le timeout
    while(1) {
        printf("Test de timeout\n");
    }
    return 0;
}
```

### Utilisation excessive de mémoire en Python
```python
#!/usr/bin/env python3

def main():
    # Ce programme essaie d'allouer trop de mémoire
    data = []
    for i in range(10000000):
        data.append("X" * 1000)
    print("Terminé")

if __name__ == "__main__":
    main()
```

### Tentative d'accès au système de fichiers en C
```c
#include <stdio.h>

int main() {
    // Tentative d'ouverture d'un fichier système
    FILE *file = fopen("/etc/passwd", "r");
    if (file == NULL) {
        printf("Impossible d'accéder au fichier\n");
        return 1;
    }
    
    char buffer[1024];
    while (fgets(buffer, sizeof(buffer), file)) {
        printf("%s", buffer);
    }
    
    fclose(file);
    return 0;
}
```

### Tentative d'exécution de commande système en Python
```python
#!/usr/bin/env python3

import os

def main():
    # Tentative d'exécution d'une commande système
    try:
        os.system("ls -la /")
        print("Commande exécutée avec succès")
    except Exception as e:
        print(f"Erreur: {e}")

if __name__ == "__main__":
    main()
```

## 5. Solutions attendues pour vérification

### Solution correcte pour Hello World en C
```c
#include <stdio.h>

int main() {
    printf("Hello, World!\n");
    return 0;
}
```

### Solution incorrecte pour Hello World en C (espacement différent)
```c
#include <stdio.h>

int main() {
    printf("Hello,World!\n");
    return 0;
}
```

### Solution presque correcte pour Moyenne en Python (arrondi manquant)
```python
#!/usr/bin/env python3

import sys

def main():
    if len(sys.argv) != 3:
        print(f"Usage: {sys.argv[0]} <nombre1> <nombre2>")
        sys.exit(1)
    
    try:
        num1 = float(sys.argv[1])
        num2 = float(sys.argv[2])
        average = (num1 + num2) / 2
        
        # Manque l'arrondi à 1 décimale
        print(f"{average}")
    except ValueError:
        print("Erreur: les arguments doivent être des nombres")
        sys.exit(1)

if __name__ == "__main__":
    main()
```
