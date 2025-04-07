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
$output_log = [];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_submissions'])) {
    try {
        // Exécuter le script de traitement
        $output = [];
        $return_var = 0;
        
        exec('php ' . escapeshellarg(dirname(dirname(__DIR__)) . '/scripts/queue/real_process.php') . ' 2>&1', $output, $return_var);
        $output_log = $output; // Sauvegarder la sortie complète
        
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

// Obtenir les soumissions récentes (en attente, complétées et échouées)
$stmt = $db->query("
    SELECT s.id, u.email as user_email, e.title as exercise_name, l.name as language_name, 
           s.status, s.score, s.submitted_at, s.processed_at
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN exercises e ON s.exercise_id = e.id
    JOIN languages l ON s.language_id = l.id
    WHERE s.status IN ('pending', 'completed', 'failed')
    ORDER BY s.submitted_at DESC
    LIMIT 20
");
$recent_submissions = $stmt->fetchAll();
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
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.85em;
        }
        .badge-pending {
            background-color: #f0ad4e;
            color: white;
        }
        .badge-completed {
            background-color: #5cb85c;
            color: white;
        }
        .badge-failed {
            background-color: #d9534f;
            color: white;
        }
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .stat-box {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            width: 30%;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        .refresh-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .refresh-btn:hover {
            background-color: #357ab8;
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
                <div class="stats-container">
                    <div class="stat-box">
                        <h3>En attente</h3>
                        <div class="stat-number"><?= $pending_count ?></div>
                        <p>soumission(s)</p>
                    </div>
                    
                    <?php if ($processed_count > 0): ?>
                    <div class="stat-box">
                        <h3>Traitées</h3>
                        <div class="stat-number"><?= $processed_count ?></div>
                        <p>dernière opération</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-box">
                        <h3>Traitement</h3>
                        <form method="post" class="admin-form">
                            <button type="submit" name="process_submissions" class="btn btn-primary" <?= $pending_count == 0 ? 'disabled' : '' ?>>
                                Traiter maintenant
                            </button>
                        </form>
                        <p>traitement manuel uniquement</p>
                    </div>
                </div>
                
                <h3>Soumissions récentes <a href="process.php" class="refresh-btn">Actualiser</a></h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Exercice</th>
                            <th>Langage</th>
                            <th>Statut</th>
                            <th>Score</th>
                            <th>Soumis le</th>
                            <th>Traité le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_submissions as $sub): ?>
                            <tr>
                                <td><?= $sub['id'] ?></td>
                                <td><?= htmlspecialchars($sub['user_email']) ?></td>
                                <td><?= htmlspecialchars($sub['exercise_name']) ?></td>
                                <td><?= htmlspecialchars($sub['language_name']) ?></td>
                                <td>
                                    <span class="status-badge badge-<?= $sub['status'] ?>">
                                        <?= ucfirst($sub['status']) ?>
                                    </span>
                                </td>
                                <td><?= isset($sub['score']) ? $sub['score'] . '%' : '-' ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($sub['submitted_at'])) ?></td>
                                <td><?= $sub['processed_at'] ? date('d/m/Y H:i', strtotime($sub['processed_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($output_log)): ?>
                <h3>Journal d'exécution</h3>
                <div class="output">
                    <?php foreach ($output_log as $line): ?>
                        <?= htmlspecialchars($line) ?><br>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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
