<?php
/**
 * Processeur unifié de file d'attente
 * 
 * Ce fichier remplace les différentes versions (queue_processor.php, manual_process.php, real_process.php)
 * Usage: php processor.php [--daemon] [--once]
 *   --daemon: exécution continue en arrière-plan (par défaut)
 *   --once: traiter les soumissions en attente et quitter
 */

// Charger la configuration
require_once dirname(dirname(__DIR__)) . '/web/config.php';
require_once dirname(__DIR__) . '/correction/corrector.php';

// Analyser les arguments
$options = getopt('', ['daemon', 'once']);
$run_once = isset($options['once']);
$run_daemon = isset($options['daemon']) || (!$run_once);

// Fonction pour traiter les soumissions en attente
function process_pending_submissions() {
    try {
        $db = db_connect();
    
        // Obtenir le nombre actuel de soumissions en cours de traitement
        $stmt = $db->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'processing'");
        $processing_count = $stmt->fetch()['count'];
        $available_slots = PARALLEL_JOBS - $processing_count;
    
        if ($available_slots <= 0) {
            log_message('INFO', "Nombre maximum de traitements parallèles atteint ($processing_count/" . PARALLEL_JOBS . ")");
            return;
        }
    
        // Récupérer les soumissions en attente
        $stmt = $db->prepare("
            SELECT 
                s.id, s.user_id, s.exercise_id, s.language_id, s.file_path,
                l.name as language_name, l.extension
            FROM submissions s
            JOIN languages l ON s.language_id = l.id
            WHERE s.status = 'pending'
            ORDER BY s.submitted_at ASC
            LIMIT ?
        ");
        $stmt->execute([$available_slots]);
        $submissions = $stmt->fetchAll();
    
        if (empty($submissions)) {
            return;
        }
    
        log_message('INFO', "Récupération de " . count($submissions) . " soumission(s) en attente");
    
        // Traiter chaque soumission
        foreach ($submissions as $submission) {
            try {
                // Marquer comme en cours de traitement
                $update_stmt = $db->prepare("UPDATE submissions SET status = 'processing' WHERE id = ?");
                if (!$update_stmt->execute([$submission['id']])) {
                    log_message('ERROR', "Impossible de mettre à jour le statut de la soumission {$submission['id']}");
                    continue;
                }
                
                // Traiter la soumission
                try {
                    $corrector = new Corrector($submission);
                    $result = $corrector->run();
                    
                    // Mettre à jour la soumission avec le résultat
                    $update_stmt = $db->prepare("
                        UPDATE submissions 
                        SET status = ?, score = ?, processed_at = NOW() 
                        WHERE id = ?
                    ");
                    $update_stmt->execute([
                        $result['success'] ? 'completed' : 'failed',
                        $result['score'],
                        $submission['id']
                    ]);
                    
                    log_message('INFO', "Soumission {$submission['id']} traitée avec un score de {$result['score']}%");
                } catch (Exception $e) {
                    log_message('ERROR', "Erreur lors de la correction de la soumission {$submission['id']}: " . $e->getMessage());
                    
                    // Mettre à jour le statut en cas d'erreur
                    $update_stmt = $db->prepare("UPDATE submissions SET status = 'failed' WHERE id = ?");
                    $update_stmt->execute([$submission['id']]);
                }
            } catch (Exception $e) {
                log_message('ERROR', "Erreur lors du traitement de la soumission {$submission['id']}: " . $e->getMessage());
            }
        }
    } catch (PDOException $e) {
        throw $e; // Remonter pour être gérée par la boucle principale
    }
}

// Mode d'exécution
if ($run_once) {
    // Exécuter une seule fois et quitter
    echo "=== Traitement des soumissions en attente (mode unique) ===\n";
    process_pending_submissions();
    echo "=== Traitement terminé ===\n";
} else {
    // Mode démon continu
    echo "=== Démarrage du processeur de file d'attente (mode démon) ===\n";
    while (true) {
        process_pending_submissions();
        sleep(POLLING_INTERVAL);
    }
}
?>