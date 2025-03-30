-- Données de test pour le développement

-- Insertion de cours de test
INSERT INTO courses (name, description) VALUES 
('Introduction à la programmation', 'Cours de base pour apprendre la programmation'),
('Structures de données', 'Cours sur les structures de données fondamentales');

-- Insertion d'exercices de test
INSERT INTO exercises (course_id, exercise_number, title, description) VALUES
(1, 1, 'Hello World', 'Afficher "Hello, World!" à l''écran'),
(1, 2, 'Calcul de moyenne', 'Calculer la moyenne de deux nombres'),
(2, 1, 'Tri à bulles', 'Implémenter l''algorithme de tri à bulles');

-- Insertion de tests de référence
INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output) VALUES
-- Tests pour Hello World en C
(1, 1, 1, '', 'Hello, World!'),
-- Tests pour Hello World en Python
(1, 2, 1, '', 'Hello, World!'),
-- Tests pour Calcul de moyenne en C
(2, 1, 1, '10 20', '15.0'),
(2, 1, 2, '5 7', '6.0'),
-- Tests pour Calcul de moyenne en Python
(2, 2, 1, '10 20', '15.0'),
(2, 2, 2, '5 7', '6.0');

-- Création d'un utilisateur de test (mot de passe: 'password')
INSERT INTO users (email, password) VALUES 
('test@example.com', '$2y$10$XQHgtV.vVEPN8GH7bHOA2.6o0.O4x1Hd5iJ9KsC5EdWXpKHOOO/62');
