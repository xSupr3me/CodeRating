<?php
/**
 * Script pour vérifier et mettre à jour les tests de référence
 */

// Charger les configurations
require_once '/var/www/coursero/config.php';

echo "=== Vérification des tests de référence ===\n";

// Se connecter à la base de données
$db = db_connect();

// Vérifier la table des tests de référence
$stmt = $db->query("
    SELECT rt.id, rt.exercise_id, rt.language_id, rt.test_number, 
           rt.arguments, rt.expected_output,
           e.title as exercise_title, 
           l.name as language_name
    FROM reference_tests rt
    JOIN exercises e ON rt.exercise_id = e.id
    JOIN languages l ON rt.language_id = l.id
    ORDER BY e.title, l.name, rt.test_number
");

$tests = $stmt->fetchAll();

if (empty($tests)) {
    echo "Aucun test de référence trouvé dans la base de données.\n";
    echo "Ajout de tests de référence de base...\n";
    
    // Identifier les exercices et langages existants
    $stmt = $db->query("SELECT id, title FROM exercises");
    $exercises = $stmt->fetchAll();
    
    $stmt = $db->query("SELECT id, name FROM languages");
    $languages = $stmt->fetchAll();
    
    // Créer des tests de base
    foreach ($exercises as $exercise) {
        foreach ($languages as $language) {
            // Adapter selon l'exercice
            if (stripos($exercise['title'], 'Hello World') !== false) {
                $db->prepare("
                    INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output)
                    VALUES (?, ?, 1, '', 'Hello, World!')
                ")->execute([$exercise['id'], $language['id']]);
                
                echo "Test ajouté: {$exercise['title']} - {$language['name']} - Test #1\n";
            }
            elseif (stripos($exercise['title'], 'moyenne') !== false) {
                $db->prepare("
                    INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output)
                    VALUES (?, ?, 1, '10 20', '15.0')
                ")->execute([$exercise['id'], $language['id']]);
                
                $db->prepare("
                    INSERT INTO reference_tests (exercise_id, language_id, test_number, arguments, expected_output)
                    VALUES (?, ?, 2, '5 7', '6.0')
                ")->execute([$exercise['id'], $language['id']]);
                
                echo "Tests ajoutés: {$exercise['title']} - {$language['name']} - Tests #1 et #2\n";
            }
        }
    }
} else {
    echo "Tests de référence existants:\n";
    echo "--------------------------------\n";
    echo "ID | Exercice | Langage | Test # | Arguments | Sortie attendue\n";
    echo "--------------------------------\n";
    
    foreach ($tests as $test) {
        echo "{$test['id']} | {$test['exercise_title']} | {$test['language_name']} | ";
        echo "#{$test['test_number']} | {$test['arguments']} | {$test['expected_output']}\n";
    }
}

echo "=== Vérification terminée ===\n";
