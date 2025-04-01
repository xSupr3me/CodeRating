#!/bin/bash

# Script d'initialisation de la base de données

# Variables de configuration (à modifier selon votre environnement)
DB_USER="root"
DB_PASSWORD=""
DB_NAME="coursero"

# Création de la base de données
echo "Création de la base de données $DB_NAME..."
mysql -u $DB_USER -p$DB_PASSWORD -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importation du schéma
echo "Importation du schéma de la base de données..."
mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME < schema.sql

# Ajout de données de base
echo "Ajout de données de base..."
mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME << EOF
# Créer un utilisateur administrateur
INSERT INTO users (email, password) 
VALUES ('admin@coursero.local', '\$2y\$10\$XQHgtV.vVEPN8GH7bHOA2.6o0.O4x1Hd5iJ9KsC5EdWXpKHOOO/62');

# Créer quelques cours et exercices de base
INSERT INTO courses (name, description) 
VALUES ('Introduction à la programmation', 'Cours de base pour apprendre la programmation');

INSERT INTO exercises (course_id, exercise_number, title, description) 
VALUES (1, 1, 'Hello World', 'Afficher "Hello, World!" à l''écran'),
       (1, 2, 'Calcul de moyenne', 'Calculer la moyenne de deux nombres');

INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output) 
VALUES (1, 1, 1, '', 'Hello, World!'),
       (1, 2, 1, '', 'Hello, World!'),
       (2, 1, 1, '10 20', '15.0'),
       (2, 1, 2, '5 7', '6.0'),
       (2, 2, 1, '10 20', '15.0'),
       (2, 2, 2, '5 7', '6.0');
EOF

echo "Base de données initialisée avec succès!"
