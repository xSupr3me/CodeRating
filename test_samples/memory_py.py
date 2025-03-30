#!/usr/bin/env python3

"""
Programme qui tente d'allouer beaucoup de mémoire
Cas de test: Doit être arrêté par la limitation de mémoire
"""

def main():
    print("Ce programme va tenter d'allouer trop de mémoire...")
    
    # Essayer d'allouer beaucoup de mémoire
    data = []
    for i in range(10000000):
        data.append("X" * 1000)  # Ajouter des chaînes de 1000 caractères
        
        # Afficher périodiquement pour montrer que le programme fonctionne
        if i % 100000 == 0:
            print(f"Allocation en cours... {i} éléments")
    
    print("Si vous voyez ce message, la limite de mémoire n'a pas fonctionné!")

if __name__ == "__main__":
    main()
