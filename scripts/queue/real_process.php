<?php
/**
 * Processeur de file d'attente qui utilise le correcteur réel pour évaluer les soumissions
 */

// Charger la configuration
require_once '/var/www/coursero/config.php';

// Charger le correcteur
require_once dirname(__DIR__) . '/correction/corrector.php';

echo "=== Traitement des soumissions en attente ===\n";

try {
    // Connexion à la base de données
    $db = db_connect();
    
    // Récupérer les soumissions en attente
    $stmt = $db->query("
        SELECT s.id, s.user_id, s.exercise_id, s.language_id, s.file_path, s.submitted_at,
               l.name as language_name, l.extension
        FROM submissions s
        JOIN languages l ON s.language_id = l.id
        WHERE s.status = 'pending'
        ORDER BY s.submitted_at ASC
    ");
    
    $submissions = $stmt->fetchAll();
    
    if (empty($submissions)) {
        echo "Aucune soumission en attente.\n";
        exit(0);
    }
    
    echo "Nombre de soumissions en attente: " . count($submissions) . "\n";
    
    // Traiter chaque soumission
    foreach ($submissions as $submission) {
        echo "\nTraitement de la soumission #{$submission['id']}...\n";
        
        try {
            // Mettre à jour le statut en 'processing'
            $update = $db->prepare("UPDATE submissions SET status = 'processing' WHERE id = ?");
            $update->execute([$submission['id']]);
            
            // Utiliser le correcteur réel
            $corrector = new Corrector($submission);
            $result = $corrector->run();
            
            // Mettre à jour le statut et le score
            $status = $result['success'] ? 'completed' : 'failed';
            $score = $result['score'] ?? 0;
            
            $update = $db->prepare("
                UPDATE submissions 
                SET status = ?, score = ?, processed_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$status, $score, $submission['id']]);
            
            echo "Soumission #{$submission['id']} traitée avec succès.\n";
            echo "Score: $score%\n";
            
            // Afficher les détails des tests
            if (isset($result['details']) && !empty($result['details'])) {
                echo "Détails des tests:\n";
                foreach ($result['details'] as $test_num => $test_result) {
                    $status_text = $test_result['passed'] ? "RÉUSSI" : "ÉCHOUÉ";
                    echo " - Test #$test_num: $status_text\n";
                    
                    if (!$test_result['passed']) {
                        if (isset($test_result['error'])) {
                            echo "   Erreur: {$test_result['error']}\n";
                        } else {
                            echo "   Attendu: " . str_replace("\n", "\\n", $test_result['expected']) . "\n";
                            echo "   Obtenu : " . str_replace("\n", "\\n", $test_result['output'] ?? '') . "\n";
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "ERREUR: {$e->getMessage()}\n";
            
            // Marquer la soumission comme échouée
            $update = $db->prepare("
                UPDATE submissions 
                SET status = 'failed', processed_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$submission['id']]);
        }
    }
    
    echo "\nTraitement terminé.\n";
    
} catch (Exception $e) {
    echo "ERREUR GLOBALE: " . $e->getMessage() . "\n";
    exit(1);
}
