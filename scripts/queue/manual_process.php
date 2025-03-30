<?php
// Script simple pour traiter les soumissions en attente manuellement
require_once '../../web/config.php';

echo "== Traitement manuel des soumissions ==\n";

try {
    $db = db_connect();
    
    // Récupérer les soumissions en attente
    $stmt = $db->query("
        SELECT id, user_id, exercise_id, language_id, file_path, submitted_at
        FROM submissions 
        WHERE status = 'pending'
        ORDER BY submitted_at ASC
    ");
    
    $submissions = $stmt->fetchAll();
    
    if (empty($submissions)) {
        echo "Aucune soumission en attente.\n";
        exit(0);
    }
    
    echo "Nombre de soumissions en attente: " . count($submissions) . "\n";
    
    foreach ($submissions as $submission) {
        echo "Traitement de la soumission #{$submission['id']}...\n";
        
        // Simuler un traitement pour le moment
        // Dans un environnement de production, vous appelleriez ici votre logique de correction
        
        // Mettre à jour le statut de la soumission
        $update = $db->prepare("
            UPDATE submissions 
            SET status = 'completed', score = ?, processed_at = NOW()
            WHERE id = ?
        ");
        
        // Générer un score aléatoire pour la démonstration
        $score = rand(0, 100);
        $update->execute([$score, $submission['id']]);
        
        echo "Soumission #{$submission['id']} traitée avec succès. Score: $score%\n";
    }
    
    echo "Traitement terminé!\n";
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
