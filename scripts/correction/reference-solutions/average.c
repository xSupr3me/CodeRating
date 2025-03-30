#include <stdio.h>
#include <stdlib.h>

/**
 * Programme qui calcule la moyenne de deux nombres
 * Solution de référence pour l'exercice 2
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
