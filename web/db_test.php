<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure le fichier de configuration
require_once 'config.php';

echo "<h1>Test de connexion à la base de données</h1>";

try {
    // Utiliser la fonction de connexion définie dans config.php
    $db = db_connect();
    echo "<p style='color:green'>✅ Connexion à la base de données réussie!</p>";
    
    // Vérifier les tables disponibles
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables dans la base de données:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Vérifier les utilisateurs (pour confirmer que les requêtes fonctionnent)
    $stmt = $db->query("SELECT id, email FROM users");
    $users = $stmt->fetchAll();
    
    echo "<p>Utilisateurs enregistrés:</p>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>ID: {$user['id']} - Email: {$user['email']}</li>";
    }
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</p>";
    
    // Afficher des informations de diagnostic
    echo "<h2>Informations de diagnostic:</h2>";
    echo "<p>Host: " . DB_HOST . "</p>";
    echo "<p>User: " . DB_USER . "</p>";
    echo "<p>Database: " . DB_NAME . "</p>";
    
    // Vérifier si MariaDB est en cours d'exécution
    echo "<h2>Vérification de MariaDB:</h2>";
    echo "<pre>";
    system('systemctl status mariadb | head -n 3');
    echo "</pre>";
}
?>
