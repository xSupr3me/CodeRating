<?php
require_once '../config.php';

// Vérifier si l'utilisateur est connecté
if (!is_logged_in()) {
    redirect('/auth/login.php');
}

// Vérifier si l'utilisateur est administrateur (ID 1 par défaut)
$user_id = $_SESSION['user_id'];
if ($user_id != 1) {
    // Rediriger les non-administrateurs
    redirect('/');
}

$message = '';
$error = '';
$processed_count = 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_submissions'])) {
    try {
        // Exécuter le script de traitement
        $output = [];
        $return_var = 0;
        
        exec('php ' . escapeshellarg(dirname(dirname(__DIR__)) . '/scripts/queue/real_process.php') . ' 2>&1', $output, $return_var);
        
        if ($return_var === 0) {
            // Comptabiliser les soumissions traitées
            $db = db_connect();
            $stmt = $db->query("
                SELECT COUNT(*) as count FROM submissions 
                WHERE status IN ('completed', 'failed') 
                AND processed_at >= NOW() - INTERVAL 10 MINUTE
            ");
            $processed_count = $stmt->fetch()['count'];
            
            $message = "Traitement terminé avec succès. $processed_count soumission(s) traitée(s).";
        } else {
            $error = "Erreur lors du traitement: " . implode("\n", $output);
        }
    } catch (Exception $e) {
        $error = "Exception: " . $e->getMessage();
    }
}

// Obtenir le nombre de soumissions en attente
$db = db_connect();
$stmt = $db->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'");
$pending_count = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Administration</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-panel {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .output {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1><?= APP_NAME ?> - Administration</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Tableau de bord</a></li>
                    <li><a href="../submit.php">Soumettre un code</a></li>
                    <li><a href="process.php" class="active">Administration</a></li>
                    <li><a href="../auth/logout.php">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section>
            <h2>Traitement des soumissions</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            
            <div class="admin-panel">
                <h3>État actuel</h3>
                <p>Il y a actuellement <strong><?= $pending_count ?></strong> soumission(s) en attente de traitement.</p>
                
                <?php if ($processed_count > 0): ?>
                    <p><strong><?= $processed_count ?></strong> soumission(s) ont été traitées lors de la dernière opération.</p>
                <?php endif; ?>
                
                <form method="post" class="admin-form">
                    <button type="submit" name="process_submissions" class="btn btn-primary">
                        Traiter les soumissions en attente
                    </button>
                </form>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Coursero</p>
        </div>
    </footer>
</body>
</html>
