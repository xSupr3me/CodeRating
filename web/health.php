<?php
/**
 * Point de vérification de l'état du serveur web pour HAProxy
 * 
 * Ce fichier est utilisé par HAProxy pour vérifier si le serveur est opérationnel.
 * Il effectue des vérifications de base sur les composants essentiels du système.
 */

// Désactiver l'affichage des erreurs pour ce fichier
ini_set('display_errors', 0);

// Vérifications à effectuer
$checks = [
    'php' => true,
    'db' => true,
    'disk' => true,
    'memory' => true,
];

// Tableau pour stocker les résultats des vérifications
$results = [];

// Vérification de la base de données
if ($checks['db']) {
    try {
        require_once 'config.php';
        $db = db_connect();
        $stmt = $db->query('SELECT 1');
        $results['db'] = true;
    } catch (Exception $e) {
        $results['db'] = false;
        $results['db_error'] = $e->getMessage();
    }
}

// Vérification de l'espace disque
if ($checks['disk']) {
    $free_space = disk_free_space('/');
    $results['disk'] = ($free_space > 100 * 1024 * 1024); // Au moins 100 MB disponibles
    $results['disk_free'] = round($free_space / (1024 * 1024 * 1024), 2) . ' GB';
}

// Vérification de la mémoire
if ($checks['memory']) {
    $memory_limit = ini_get('memory_limit');
    $results['memory'] = true;
    $results['memory_limit'] = $memory_limit;
}

// Vérification globale
$all_ok = !in_array(false, $results);

// Déterminer le statut HTTP
if ($all_ok) {
    http_response_code(200);
    $status = 'OK';
} else {
    http_response_code(503);
    $status = 'FAIL';
}

// Déterminer le format de réponse
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'plain';

switch ($format) {
    case 'json':
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $status,
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => gethostname(),
            'checks' => $results
        ]);
        break;
    
    default:
        header('Content-Type: text/plain');
        echo "Status: $status\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        echo "Server: " . gethostname() . "\n";
        foreach ($results as $check => $result) {
            if (is_bool($result)) {
                echo "$check: " . ($result ? 'OK' : 'FAIL') . "\n";
            } else if ($check !== 'db_error') {
                echo "$check: $result\n";
            }
        }
}
?>
