<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'coursero');

// Configuration des dossiers
define('ROOT_DIR', dirname(dirname(__DIR__)));
define('UPLOAD_DIR', ROOT_DIR . '/uploads/');
define('TEMP_DIR', ROOT_DIR . '/tmp/');

// Configuration de l'exécution des programmes
define('MAX_EXECUTION_TIME', 5); // secondes
define('MAX_MEMORY_USAGE', 128 * 1024 * 1024); // 128 MB
define('TIMEOUT_COMMAND', 'timeout'); // 'timeout' sur Linux, 'gtimeout' sur macOS

// Configuration des interpréteurs/compilateurs
define('PYTHON_PATH', 'python3'); // ou 'python' selon l'installation
define('GCC_PATH', 'gcc');
define('C_COMPILER_OPTIONS', '-Wall -O2');

// Configuration du processus de correction
define('PARALLEL_JOBS', 2); // Nombre de soumissions traitées en parallèle
define('MAX_QUEUE_SIZE', 100); // Taille maximale de la file d'attente
define('POLLING_INTERVAL', 5); // Intervalle de vérification de la file d'attente (secondes)

// Connexion à la base de données
function db_connect() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die('Erreur de connexion à la base de données: ' . $e->getMessage());
    }
}

// Fonction de journalisation
function log_message($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    echo $log_entry;
    error_log($log_entry, 3, ROOT_DIR . '/logs/queue.log');
}

// Création des dossiers temporaires s'ils n'existent pas
if (!file_exists(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}
if (!file_exists(ROOT_DIR . '/logs')) {
    mkdir(ROOT_DIR . '/logs', 0755, true);
}
?>
