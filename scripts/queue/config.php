<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'coursero_user');
define('DB_PASS', 'root'); // Utilisez le mot de passe que vous avez défini
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

// Connexion à la base de données avec gestion de reconnexion
// Définir la fonction seulement si elle n'existe pas déjà
if (!function_exists('db_connect')) {
    function db_connect() {
        $max_retries = 3;
        $retry_count = 0;
        
        while ($retry_count < $max_retries) {
            try {
                $pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 5, // 5 secondes pour une connexion
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=600" // 10 minutes
                    ]
                );
                return $pdo;
            } catch (PDOException $e) {
                $retry_count++;
                log_message('WARNING', "Tentative de connexion à la base de données échouée ($retry_count/$max_retries): " . $e->getMessage());
                
                if ($retry_count >= $max_retries) {
                    throw $e;
                }
                
                // Attendre avant de réessayer
                sleep(2);
            }
        }
    }
}

// Fonction de journalisation (vérifie également si elle existe déjà)
if (!function_exists('log_message')) {
    function log_message($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        echo $log_entry;
        
        // S'assurer que le dossier de logs existe
        $log_dir = ROOT_DIR . '/logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        error_log($log_entry, 3, $log_dir . '/queue.log');
    }
}

// Création des dossiers temporaires s'ils n'existent pas
if (!file_exists(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}
if (!file_exists(ROOT_DIR . '/logs')) {
    mkdir(ROOT_DIR . '/logs', 0755, true);
}
?>
