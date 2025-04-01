<?php
// Script pour traiter les soumissions en attente avec évaluation réelle
require_once '../../web/config.php';
require_once dirname(__DIR__) . '/correction/corrector.php';

echo "== Traitement manuel des soumissions ==\n";

try {
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
    
    foreach ($submissions as $submission) {
        echo "Traitement de la soumission #{$submission['id']}...\n";
        
        try {
            // Mettre à jour le statut en 'processing'
            $update = $db->prepare("UPDATE submissions SET status = 'processing' WHERE id = ?");
            $update->execute([$submission['id']]);
            
            // Utiliser le correcteur pour évaluer la soumission
            $corrector = new Corrector($submission);
            $result = $corrector->run();
            
            // Mettre à jour avec le résultat réel
            $update = $db->prepare("
                UPDATE submissions 
                SET status = ?, score = ?, processed_at = NOW()
                WHERE id = ?
            ");
            
            $status = $result['success'] ? 'completed' : 'failed';
            $update->execute([$status, $result['score'], $submission['id']]);
            
            echo "Soumission #{$submission['id']} traitée avec succès. Score: {$result['score']}%\n";
            
            // Afficher le détail des tests
            if (isset($result['details']) && !empty($result['details'])) {
                echo "Détails des tests:\n";
                foreach ($result['details'] as $test_num => $test_result) {
                    $status = $test_result['passed'] ? "RÉUSSI" : "ÉCHOUÉ";
                    echo " - Test #$test_num: $status\n";
                    
                    if (!$test_result['passed']) {
                        echo "   Attendu: " . str_replace("\n", "\\n", $test_result['expected']) . "\n";
                        echo "   Obtenu : " . str_replace("\n", "\\n", $test_result['output'] ?? "Erreur") . "\n";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "ERREUR lors du traitement de la soumission #{$submission['id']}: " . $e->getMessage() . "\n";
            
            // Mettre à jour le statut en 'failed' en cas d'erreur
            $update = $db->prepare("
                UPDATE submissions 
                SET status = 'failed', processed_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$submission['id']]);
        }
    }
    
    echo "Traitement terminé!\n";
    
} catch (Exception $e) {
    echo "ERREUR GÉNÉRALE: " . $e->getMessage() . "\n";
    exit(1);
}
