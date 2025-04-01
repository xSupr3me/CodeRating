<?php
// Configuration commune en un seul fichier

// Activer les journaux d'erreurs détaillés pendant le développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'coursero_user');
define('DB_PASS', 'root');
define('DB_NAME', 'coursero');

// Configuration de l'application
define('APP_NAME', 'Coursero - Évaluation de Code');
define('APP_URL', 'https://coursero.local');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('TEMP_DIR', dirname(__DIR__) . '/tmp/');
define('LOG_DIR', dirname(__DIR__) . '/logs/');
define('MAX_UPLOAD_SIZE', 1024 * 1024); // 1MB

// Configuration de l'exécution des programmes
define('MAX_EXECUTION_TIME', 5); // secondes
define('MAX_MEMORY_USAGE', 128 * 1024 * 1024); // 128 MB
define('TIMEOUT_COMMAND', 'timeout'); // 'timeout' sur Linux

// Configuration des interpréteurs/compilateurs
define('PYTHON_PATH', 'python3');
define('GCC_PATH', 'gcc');
define('C_COMPILER_OPTIONS', '-Wall -O2');

// Configuration du processus de correction
define('PARALLEL_JOBS', 2);
define('POLLING_INTERVAL', 5);

// Configuration de la session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.save_handler', 'files');
ini_set('session.save_path', '/var/lib/php/sessions');

// Configuration de journalisation
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
define('ERROR_LOG', LOG_DIR . 'error.log');
define('ACCESS_LOG', LOG_DIR . 'access.log');

// Fonction de journalisation personnalisée
function log_message($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(ERROR_LOG, $log_entry, FILE_APPEND);
}

session_start();

// Connexion à la base de données
function db_connect($read_only = false) {
    global $db_config;
    
    // Choix du serveur en fonction du type d'opération
    $config = $read_only ? $db_config['slave'] : $db_config['master'];
    
    try {
        $pdo = new PDO(
            'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        log_message('ERROR', 'Erreur de connexion à la base de données: ' . $e->getMessage());
        
        // En cas d'erreur sur le serveur préféré, essayer l'autre serveur
        if ($read_only) {
            // Si l'esclave est indisponible, utiliser le maître
            return db_connect(false);
        } elseif ($config['host'] === $db_config['master']['host']) {
            // Si le maître est indisponible, essayer l'esclave (uniquement en lecture)
            log_message('WARNING', 'Tentative de connexion au serveur esclave');
            try {
                $config = $db_config['slave'];
                $pdo = new PDO(
                    'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
                    $config['user'],
                    $config['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                return $pdo;
            } catch (PDOException $e2) {
                log_message('ERROR', 'Erreur de connexion au serveur esclave: ' . $e2->getMessage());
                die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
            }
        } else {
            die('Erreur de connexion à la base de données: ' . $e->getMessage());
        }
    }
}

// Fonction pour vérifier si l'utilisateur est connecté
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Fonction de redirection
function redirect($path) {
    header('Location: ' . APP_URL . $path);
    exit;
}

// Création des dossiers nécessaires s'ils n'existent pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>
