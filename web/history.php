<?php
require_once 'config.php';

// Redirection vers la page de connexion si l'utilisateur n'est pas connecté
if (!is_logged_in()) {
    redirect('/auth/login.php');
}

$db = db_connect();
$user_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Récupérer le nombre total de soumissions pour la pagination
$count_stmt = $db->prepare('SELECT COUNT(*) as total FROM submissions WHERE user_id = ?');
$count_stmt->execute([$user_id]);
$total_submissions = $count_stmt->fetch()['total'];
$total_pages = ceil($total_submissions / $per_page);

// Récupérer les soumissions pour cette page
$stmt = $db->prepare('
    SELECT s.id, c.name as course_name, e.exercise_number, e.title, 
           l.name as language, s.status, s.score, s.submitted_at,
           s.processed_at
    FROM submissions s
    JOIN exercises e ON s.exercise_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN languages l ON s.language_id = l.id
    WHERE s.user_id = ?
    ORDER BY s.submitted_at DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$user_id, $per_page, $offset]);
$submissions = $stmt->fetchAll();

// Obtenir la soumission avec le score le plus élevé pour chaque exercice
$best_stmt = $db->prepare('
    SELECT e.id as exercise_id, MAX(s.score) as best_score
    FROM submissions s
    JOIN exercises e ON s.exercise_id = e.id
    WHERE s.user_id = ? AND s.status = "completed"
    GROUP BY e.id
');
$best_stmt->execute([$user_id]);
$best_scores = [];
while ($row = $best_stmt->fetch()) {
    $best_scores[$row['exercise_id']] = $row['best_score'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Historique des soumissions</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= APP_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="index.php">Tableau de bord</a></li>
                    <li><a href="submit.php">Soumettre un code</a></li>
                    <li><a href="history.php" class="active">Historique</a></li>
                    <li><a href="auth/logout.php">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section>
            <h2>Historique des soumissions</h2>
            
            <?php if (empty($submissions)): ?>
                <p>Vous n'avez encore soumis aucun code.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cours</th>
                            <th>Exercice</th>
                            <th>Langage</th>
                            <th>Statut</th>
                            <th>Score</th>
                            <th>Meilleur score</th>
                            <th>Date de soumission</th>
                            <th>Date de traitement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <?php 
                            $exercise_id = null;
                            $stmt = $db->prepare('SELECT id FROM exercises WHERE exercise_number = ? AND title = ?');
                            $stmt->execute([$sub['exercise_number'], $sub['title']]);
                            $ex = $stmt->fetch();
                            if ($ex) {
                                $exercise_id = $ex['id'];
                            }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['course_name']) ?></td>
                                <td>Ex <?= $sub['exercise_number'] ?> - <?= htmlspecialchars($sub['title']) ?></td>
                                <td><?= htmlspecialchars($sub['language']) ?></td>
                                <td class="status-<?= $sub['status'] ?>"><?= ucfirst($sub['status']) ?></td>
                                <td><?= isset($sub['score']) ? $sub['score'] . '%' : '-' ?></td>
                                <td>
                                    <?php if ($exercise_id && isset($best_scores[$exercise_id])): ?>
                                        <?= $best_scores[$exercise_id] ?>%
                                        <?php if ($sub['score'] == $best_scores[$exercise_id]): ?>
                                            <span class="badge-best">Meilleur</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($sub['submitted_at'])) ?></td>
                                <td>
                                    <?= $sub['processed_at'] ? date('d/m/Y H:i', strtotime($sub['processed_at'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <span>Page <?= $page ?> sur <?= $total_pages ?></span>
                    <div class="pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="btn btn-small">Précédent</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="btn btn-small btn-current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" class="btn btn-small"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="btn btn-small">Suivant</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Coursero</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>
