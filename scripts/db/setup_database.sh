#!/bin/bash
# Script pour configurer correctement la base de données

# Vérifier si le script est exécuté en tant que root
if [ "$EUID" -ne 0 ]; then
  echo "Ce script doit être exécuté en tant que root"
  exit 1
fi

echo "=== Configuration de la base de données Coursero ==="

# Création du schéma de base
echo "Création du schéma de base de données..."
mysql -e "CREATE DATABASE IF NOT EXISTS coursero CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Création des tables nécessaires
echo "Création des tables..."
mysql coursero << 'EOF'
-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des cours
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des exercices
CREATE TABLE IF NOT EXISTS exercises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    exercise_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id),
    UNIQUE KEY unique_exercise (course_id, exercise_number)
);

-- Table des langages de programmation supportés
CREATE TABLE IF NOT EXISTS languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    extension VARCHAR(10) NOT NULL
);

-- Table des tests de référence pour les exercices
CREATE TABLE IF NOT EXISTS reference_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    test_number INT NOT NULL,
    arguments TEXT,
    expected_output TEXT,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id),
    UNIQUE KEY unique_test (exercise_id, language_id, test_number)
);

-- Table des soumissions
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    file_path VARCHAR(255),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    score DECIMAL(5,2),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id)
);

-- Table de test pour la réplication
CREATE TABLE IF NOT EXISTS replication_test (
    id INT,
    value VARCHAR(255)
);
EOF

# Insertion des données de base
echo "Insertion des données de base..."
mysql coursero << 'EOF'
-- Langages
INSERT IGNORE INTO languages (id, name, extension) VALUES (1, 'C', 'c');
INSERT IGNORE INTO languages (id, name, extension) VALUES (2, 'Python', 'py');

-- Utilisateur admin (password = 'password')
INSERT IGNORE INTO users (id, email, password) 
VALUES (1, 'admin@coursero.local', '$2y$10$XQHgtV.vVEPN8GH7bHOA2.6o0.O4x1Hd5iJ9KsC5EdWXpKHOOO/62');

-- Cours et exercices
INSERT IGNORE INTO courses (id, name, description) 
VALUES (1, 'Introduction à la programmation', 'Cours de base pour apprendre la programmation');

INSERT IGNORE INTO exercises (id, course_id, exercise_number, title, description) 
VALUES (1, 1, 1, 'Hello World', 'Afficher "Hello, World!" à l''écran'),
       (2, 1, 2, 'Calcul de moyenne', 'Calculer la moyenne de deux nombres');

-- Tests de référence
INSERT IGNORE INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output) 
VALUES (1, 1, 1, '', 'Hello, World!'),
       (1, 2, 1, '', 'Hello, World!'),
       (2, 1, 1, '10 20', '15.0'),
       (2, 1, 2, '5 7', '6.0'),
       (2, 2, 1, '10 20', '15.0'),
       (2, 2, 2, '5 7', '6.0');

-- Données de test pour la réplication
INSERT INTO replication_test (id, value) VALUES (1, 'Test de réplication initial');
EOF

# Création de l'utilisateur de réplication
echo "Configuration de l'utilisateur de réplication..."
mysql << 'EOF'
CREATE USER IF NOT EXISTS 'repl_user'@'%' IDENTIFIED BY 'repl_password';
GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'%';
FLUSH PRIVILEGES;
EOF

# Affichage des coordonnées de réplication
echo "Coordonnées de réplication pour le serveur esclave:"
mysql -e "SHOW MASTER STATUS\G"

echo "=== Configuration terminée avec succès ==="
