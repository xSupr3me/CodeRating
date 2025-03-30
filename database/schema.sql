-- Schéma de base de données pour le système d'évaluation de code

-- Table des utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Stocké avec hachage
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table des cours
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des exercices
CREATE TABLE exercises (
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
CREATE TABLE languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    extension VARCHAR(10) NOT NULL -- ex: 'py', 'c'
);

-- Table des tests de référence pour les exercices
CREATE TABLE reference_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    test_number INT NOT NULL,
    arguments TEXT, -- Arguments pour le test
    expected_output TEXT, -- Sortie attendue
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id),
    UNIQUE KEY unique_test (exercise_id, language_id, test_number)
);

-- Table des soumissions
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exercise_id INT NOT NULL,
    language_id INT NOT NULL,
    file_path VARCHAR(255), -- Chemin temporaire du fichier soumis
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    score DECIMAL(5,2), -- Pourcentage de réussite
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    FOREIGN KEY (language_id) REFERENCES languages(id)
);

-- Insertion des langages de base
INSERT INTO languages (name, extension) VALUES ('C', 'c');
INSERT INTO languages (name, extension) VALUES ('Python', 'py');
