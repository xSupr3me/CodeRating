<?php
// Script simplifié pour traiter les soumissions sans conflit de config

// Inclure UNIQUEMENT le fichier config principal
require_once '/var/www/coursero/config.php';

echo "== Traitement manuel des soumissions ==\n";

try {
    $db = db_connect();
    
    // Récupérer les soumissions en attente
    $stmt = $db->query("
        SELECT s.id, s.user_id, s.exercise_id, s.language_id, s.file_path 
        FROM submissions s
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
            
            // Générer un score aléatoire pour le test (entre 0 et 100)
            $score = mt_rand(0, 100);
            
            // Marquer comme terminé avec un score aléatoire
            $update = $db->prepare("
                UPDATE submissions 
                SET status = 'completed', score = ?, processed_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$score, $submission['id']]);
            
            echo "Soumission #{$submission['id']} traitée avec succès. Score: $score%\n";
            
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
