#include <stdio.h>

/**
 * Programme avec une boucle infinie pour tester le timeout
 * Cas de test: Doit être interrompu par le timeout
 */
int main() {
    printf("Ce programme va entrer dans une boucle infinie...\n");
    
    // Boucle infinie pour tester le mécanisme de timeout
    while(1) {
        printf("Toujours en cours d'exécution...\n");
    }
    
    return 0;
}
