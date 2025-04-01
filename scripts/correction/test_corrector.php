<?php
/**
 * Script pour tester le correcteur manuellement avec un fichier spécifique
 * Usage: php test_corrector.php [chemin_du_fichier] [id_exercice] [id_langage]
 */

// Inclure les fichiers nécessaires
require_once '../../web/config.php';
require_once 'corrector.php';

// Vérifier les arguments
if ($argc < 4) {
    echo "Usage: php test_corrector.php [chemin_du_fichier] [id_exercice] [id_langage]\n";
    exit(1);
}

$file_path = $argv[1];
$exercise_id = (int)$argv[2];
$language_id = (int)$argv[3];

// Vérifier que le fichier existe
if (!file_exists($file_path)) {
    echo "Erreur: Fichier introuvable: $file_path\n";
    exit(1);
}

// Obtenir les informations sur le langage
$db = db_connect();
$stmt = $db->prepare("SELECT name, extension FROM languages WHERE id = ?");
$stmt->execute([$language_id]);
$language = $stmt->fetch();

if (!$language) {
    echo "Erreur: Langage non trouvé (ID: $language_id)\n";
    exit(1);
}

// Créer la soumission de test
$submission = [
    'id' => 0, // ID fictif
    'user_id' => 1, // ID utilisateur fictif
    'exercise_id' => $exercise_id,
    'language_id' => $language_id,
    'file_path' => $file_path,
    'language_name' => $language['name'],
    'extension' => $language['extension']
];

echo "=== Test du correcteur ===\n";
echo "Fichier: $file_path\n";
echo "Exercice ID: $exercise_id\n";
echo "Langage: {$language['name']} (ID: $language_id)\n";
echo "\n";

try {
    // Instancier le correcteur
    $corrector = new Corrector($submission);
    
    // Exécuter la correction
    $result = $corrector->run();
    
    // Afficher les résultats
    if ($result['success']) {
        echo "Correction réussie!\n";
        echo "Score: {$result['score']}%\n\n";
        
        if (isset($result['details'])) {
            echo "Détails des tests:\n";
            foreach ($result['details'] as $test_num => $test_result) {
                echo "- Test #$test_num: " . ($test_result['passed'] ? "RÉUSSI" : "ÉCHOUÉ") . "\n";
                
                if (!$test_result['passed']) {
                    echo "  Sortie attendue: " . str_replace("\n", "\\n", $test_result['expected']) . "\n";
                    echo "  Sortie obtenue : " . str_replace("\n", "\\n", $test_result['output'] ?? "N/A") . "\n";
                    
                    if (isset($test_result['error'])) {
                        echo "  Erreur: " . $test_result['error'] . "\n";
                    }
                }
            }
        }
    } else {
        echo "Échec de la correction: " . $result['error'] . "\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
