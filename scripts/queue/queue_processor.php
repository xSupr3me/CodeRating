<?php
/**
 * Script de traitement de la file d'attente des soumissions
 */

require_once dirname(__DIR__) . '/correction/corrector.php';
require_once 'config.php';

// Configuration supplémentaire pour éviter les timeouts de base de données
ini_set('mysql.connect_timeout', 300);
ini_set('default_socket_timeout', 300);

// Définir la variable manquante PARALLEL_JOBS si elle n'est pas définie dans config.php
if (!defined('PARALLEL_JOBS')) {
    define('PARALLEL_JOBS', 2); // Valeur par défaut
}

// Définir le timeout pour le script entier
set_time_limit(0); // Pas de limite de temps pour le script

// Vérifier si le script est déjà en cours d'exécution
$lock_file = TEMP_DIR . 'queue_processor.lock';
if (file_exists($lock_file)) {
    $pid = file_get_contents($lock_file);
    // Vérifier si le processus existe encore
    if (function_exists('posix_kill') && posix_kill($pid, 0)) {
        log_message('WARNING', "Le processeur de file d'attente est déjà en cours d'exécution (PID: $pid)");
        exit;
    }
    log_message('WARNING', "Le fichier de verrouillage existe, mais le processus ($pid) ne semble pas être en cours d'exécution. Suppression du verrou.");
    unlink($lock_file);
}

// Créer le fichier de verrouillage
file_put_contents($lock_file, getmypid());

try {
    log_message('INFO', "Démarrage du processeur de file d'attente");
    
    // Boucle principale
    while (true) {
        try {
            // Créer une nouvelle connexion à chaque itération pour éviter les timeouts
            process_pending_submissions();
            
            // Attendre avant la prochaine vérification
            sleep(POLLING_INTERVAL);
        } catch (PDOException $e) {
            log_message('ERROR', "Erreur de base de données: " . $e->getMessage());
            // Attendre un peu avant de réessayer
            sleep(5);
        } catch (Exception $e) {
            log_message('ERROR', "Erreur dans le processeur de file d'attente: " . $e->getMessage());
            log_message('INFO', "Arrêt du processeur de file d'attente");
            break;
        }
    }
} finally {
    // Supprimer le fichier de verrouillage
    if (file_exists($lock_file)) {
        unlink($lock_file);
    }
}

/**
 * Récupérer et traiter les soumissions en attente
 */
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
?>
