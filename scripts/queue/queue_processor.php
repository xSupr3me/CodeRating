<?php
/**
 * Script de traitement de la file d'attente des soumissions
 * 
 * Ce script surveille les soumissions en attente et les transmet au correcteur
 */

require_once dirname(__DIR__) . '/correction/corrector.php';
require_once 'config.php';

// Vérifier si le script est déjà en cours d'exécution
$lock_file = TEMP_DIR . 'queue_processor.lock';
if (file_exists($lock_file)) {
    $pid = file_get_contents($lock_file);
    // Sur Linux, nous pouvons vérifier si le processus existe encore
    if (function_exists('posix_kill') && posix_kill($pid, 0)) {
        echo "Le processeur de file d'attente est déjà en cours d'exécution (PID: $pid)\n";
        exit;
    }
    echo "Le fichier de verrouillage existe, mais le processus ne semble pas être en cours d'exécution. Suppression du verrou.\n";
    unlink($lock_file);
}

// Créer le fichier de verrouillage
file_put_contents($lock_file, getmypid());

try {
    echo "Démarrage du processeur de file d'attente (PID: " . getmypid() . ")\n";
    echo "Appuyez sur Ctrl+C pour arrêter\n";
    
    // Intercepter le signal de terminaison pour nettoyer proprement
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($lock_file) {
            echo "Arrêt du processeur de file d'attente suite à un signal SIGTERM\n";
            if (file_exists($lock_file)) {
                unlink($lock_file);
            }
            exit;
        });
    }

    // Processus principal de surveillance de la file d'attente
    while (true) {
        // Récupérer et traiter les soumissions en attente
        process_pending_submissions();
        
        // Attendre avant la prochaine vérification (important de ne pas boucler à vide!)
        echo "En attente de nouvelles soumissions...\n";
        sleep(POLLING_INTERVAL);
        
        // Traiter les signaux si disponible
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }
} catch (Exception $e) {
    echo "Erreur dans le processeur de file d'attente: " . $e->getMessage() . "\n";
} finally {
    // Supprimer le fichier de verrouillage
    if (file_exists($lock_file)) {
        unlink($lock_file);
    }
    echo "Arrêt du processeur de file d'attente\n";
}

/**
 * Récupérer et traiter les soumissions en attente
 */
function process_pending_submissions() {
    $db = db_connect();
    
    // Obtenir le nombre actuel de soumissions en cours de traitement
    $stmt = $db->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'processing'");
    $processing_count = $stmt->fetch()['count'];
    $available_slots = PARALLEL_JOBS - $processing_count;
    
    if ($available_slots <= 0) {
        log_message('INFO', "Nombre maximum de traitements parallèles atteint ($processing_count/$parallel_jobs)");
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
        // Marquer comme en cours de traitement
        $update_stmt = $db->prepare("UPDATE submissions SET status = 'processing' WHERE id = ?");
        if (!$update_stmt->execute([$submission['id']])) {
            log_message('ERROR', "Impossible de mettre à jour le statut de la soumission {$submission['id']}");
            continue;
        }
        
        // Lancer le correcteur dans un processus séparé pour permettre le parallélisme
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            // Erreur lors de la création du processus
            log_message('ERROR', "Erreur lors de la création du processus pour la soumission {$submission['id']}");
            $update_stmt = $db->prepare("UPDATE submissions SET status = 'failed' WHERE id = ?");
            $update_stmt->execute([$submission['id']]);
        } 
        elseif ($pid == 0) {
            // Processus enfant
            try {
                // Exécuter la correction
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
                log_message('ERROR', "Erreur lors du traitement de la soumission {$submission['id']}: " . $e->getMessage());
                $update_stmt = $db->prepare("UPDATE submissions SET status = 'failed' WHERE id = ?");
                $update_stmt->execute([$submission['id']]);
            }
            
            // Terminer le processus enfant
            exit(0);
        }
    }
    
    // Attendre la fin de tous les processus enfants sans bloquer
    pcntl_wait($status, WNOHANG);
}
?>
