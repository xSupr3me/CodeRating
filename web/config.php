<?php
// Configuration de la base de données avec support de haute disponibilité
$db_config = [
    'master' => [
        'host' => 'db-master',
        'user' => 'root',
        'pass' => '',
        'name' => 'coursero',
    ],
    'slave' => [
        'host' => 'db-slave',
        'user' => 'root',
        'pass' => '',
        'name' => 'coursero',
    ],
];

// Utilisation par défaut du serveur maître pour les opérations d'écriture
define('DB_HOST', $db_config['master']['host']);
define('DB_USER', $db_config['master']['user']);
define('DB_PASS', $db_config['master']['pass']);
define('DB_NAME', $db_config['master']['name']);

// Configuration de l'application
define('APP_NAME', 'Coursero - Évaluation de Code');
define('APP_URL', 'https://coursero.local');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 1024 * 1024); // 1MB

// Configuration de la session avec support pour le sticky session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

// Configuration de stockage de session pour la haute disponibilité
ini_set('session.save_handler', 'files');
ini_set('session.save_path', '/var/lib/php/sessions');

session_start();

// Connexion à la base de données avec support de haute disponibilité
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
        // En cas d'erreur sur le serveur préféré, essayer l'autre serveur
        if ($read_only) {
            // Si l'esclave est indisponible, utiliser le maître
            return db_connect(false);
        } elseif ($config['host'] === $db_config['master']['host']) {
            // Si le maître est indisponible, essayer l'esclave (uniquement en lecture)
            error_log('Erreur de connexion au serveur maître, tentative sur esclave: ' . $e->getMessage());
            try {
                $config = $db_config['slave'];
                $pdo = new PDO(
                    'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=utf8mb4',
                    $config['user'],
                    $config['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_READONLY => true
                    ]
                );
                return $pdo;
            } catch (PDOException $e2) {
                error_log('Erreur de connexion au serveur esclave: ' . $e2->getMessage());
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

// Création du dossier uploads s'il n'existe pas
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
?>
