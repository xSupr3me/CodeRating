<?php
/**
 * Processeur de file d'attente qui utilise le correcteur réel pour évaluer les soumissions
 */

// Déterminer le chemin de base du projet
$base_dir = dirname(dirname(__DIR__));

// Charger la configuration en utilisant un chemin relatif
if (file_exists($base_dir . '/web/config.php')) {
    require_once $base_dir . '/web/config.php';
} else {
    // Fallback pour les installations différentes
    require_once __DIR__ . '/../../web/config.php';
}

// Charger le correcteur réel avec un chemin relatif
if (file_exists($base_dir . '/scripts/correction/corrector.php')) {
    require_once $base_dir . '/scripts/correction/corrector.php';
} else {
    // Fallback pour les installations différentes
    require_once __DIR__ . '/../correction/corrector.php';
}

// Configuration de journalisation
if (!defined('LOG_DIR')) {
    define('LOG_DIR', $base_dir . '/logs/');
    // S'assurer que le répertoire existe
    if (!file_exists(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
}

$log_file = LOG_DIR . 'queue.log';
function queue_log($level, $message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

queue_log('INFO', "=== Démarrage du traitement des soumissions ===");

try {
    // Connexion à la base de données avec reconnexion automatique
    $max_attempts = 3;
    $attempt = 0;
    $db = null;
    
    while ($attempt < $max_attempts && $db === null) {
        try {
            $db = db_connect();
            queue_log('INFO', "Connexion à la base de données réussie");
        } catch (Exception $e) {
            $attempt++;
            queue_log('WARNING', "Tentative de connexion à la base de données échouée ($attempt/$max_attempts): " . $e->getMessage());
            
            if ($attempt >= $max_attempts) {
                throw $e;
            }
            
            sleep(2);
        }
    }
    
    // Récupérer les soumissions en attente
    $stmt = $db->query("
        SELECT s.id, s.user_id, s.exercise_id, s.language_id, s.file_path, s.submitted_at,
               l.name as language_name, l.extension
        FROM submissions s
        JOIN languages l ON s.language_id = l.id
        WHERE s.status = 'pending'
        ORDER BY s.submitted_at ASC
        LIMIT 10
    ");
    
    $submissions = $stmt->fetchAll();
    
    if (empty($submissions)) {
        queue_log('INFO', "Aucune soumission en attente.");
        exit(0);
    }
    
    queue_log('INFO', "Nombre de soumissions en attente: " . count($submissions));
    
    // Traiter chaque soumission
    foreach ($submissions as $submission) {
        queue_log('INFO', "Traitement de la soumission #{$submission['id']}...");
        
        try {
            // Vérifier si le fichier existe
            if (!file_exists($submission['file_path'])) {
                throw new Exception("Fichier non trouvé: {$submission['file_path']}");
            }
            
            // Mettre à jour le statut en 'processing'
            $update = $db->prepare("UPDATE submissions SET status = 'processing' WHERE id = ?");
            
            if (!$update->execute([$submission['id']])) {
                throw new Exception("Erreur SQL lors de la mise à jour du statut");
            }
            
            // TOUJOURS utiliser le correcteur réel, plus de condition
            try {
                $corrector = new Corrector($submission);
                $result = $corrector->run();
                $score = $result['score'];
                $status = $result['success'] ? 'completed' : 'failed';
                queue_log('INFO', "Correction: score = $score%");
            } catch (Exception $e) {
                queue_log('ERROR', "Erreur du correcteur: " . $e->getMessage());
                $score = 0;
                $status = 'failed';
            }
            
            // Mettre à jour le statut et le score
            $update = $db->prepare("
                UPDATE submissions 
                SET status = ?, score = ?, processed_at = NOW()
                WHERE id = ?
            ");
            
            if (!$update->execute([$status, $score, $submission['id']])) {
                throw new Exception("Erreur SQL lors de la mise à jour des résultats");
            }
            
            queue_log('INFO', "Soumission #{$submission['id']} traitée avec succès (Score: $score%)");
            
        } catch (Exception $e) {
            queue_log('ERROR', "ERREUR: {$e->getMessage()}");
            
            // Marquer la soumission comme échouée
            try {
                $update = $db->prepare("
                    UPDATE submissions 
                    SET status = 'failed', processed_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([$submission['id']]);
            } catch (Exception $inner_e) {
                queue_log('ERROR', "Impossible de marquer la soumission comme échouée: " . $inner_e->getMessage());
            }
        }
    }
    
    queue_log('INFO', "Traitement terminé.");
    
} catch (Exception $e) {
    queue_log('ERROR', "ERREUR GLOBALE: " . $e->getMessage());
    exit(1);
}
