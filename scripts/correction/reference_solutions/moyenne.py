#!/usr/bin/env python3

"""
Programme qui calcule la moyenne de deux nombres
Solution de référence pour l'exercice 2
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
