<?php
require_once 'config.php';

// Redirection vers la page de connexion si l'utilisateur n'est pas connecté
if (!is_logged_in()) {
    redirect('/auth/login.php');
}

// Obtenir les cours disponibles
$db = db_connect();
$stmt = $db->query('SELECT id, name FROM courses ORDER BY name');
$courses = $stmt->fetchAll();

// Charger le statut des soumissions de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare('
    SELECT s.id, c.name as course_name, e.exercise_number, e.title, 
           l.name as language, s.status, s.score, s.submitted_at
    FROM submissions s
    JOIN exercises e ON s.exercise_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN languages l ON s.language_id = l.id
    WHERE s.user_id = ?
    ORDER BY s.submitted_at DESC
    LIMIT 10
');
$stmt->execute([$user_id]);
$submissions = $stmt->fetchAll();

// Vérifier si l'utilisateur est administrateur (ID 1 par défaut)
$is_admin = ($user_id == 1);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Tableau de bord</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= APP_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Tableau de bord</a></li>
                    <li><a href="submit.php">Soumettre un code</a></li>
                    <?php if ($is_admin): ?>
                    <li><a href="admin/process.php" class="admin-link">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="auth/logout.php">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="welcome">
            <h2>Bienvenue sur Coursero</h2>
            <p>Plateforme d'évaluation automatique de code pour vos exercices de programmation.</p>
        </section>

        <section class="quick-submit">
            <h2>Soumettre un code</h2>
            <form action="submit.php" method="post">
                <div class="form-group">
                    <label for="course">Cours:</label>
                    <select name="course_id" id="course" required>
                        <option value="">Sélectionnez un cours</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Continuer</button>
            </form>
        </section>

        <section class="recent-submissions">
            <h2>Soumissions récentes</h2>
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
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['course_name']) ?></td>
                                <td>Ex <?= $sub['exercise_number'] ?> - <?= htmlspecialchars($sub['title']) ?></td>
                                <td><?= htmlspecialchars($sub['language']) ?></td>
                                <td class="status-<?= $sub['status'] ?>"><?= ucfirst($sub['status']) ?></td>
                                <td><?= isset($sub['score']) ? $sub['score'] . '%' : '-' ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($sub['submitted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if ($is_admin): ?>
        <section class="admin-panel">
            <h2>Administration</h2>
            <div class="admin-actions">
                <?php
                // Obtenir le nombre de soumissions en attente
                $admin_db = db_connect();
                $admin_stmt = $admin_db->query("SELECT COUNT(*) as count FROM submissions WHERE status = 'pending'");
                $pending_count = $admin_stmt->fetch()['count'];
                ?>
                <p>Il y a actuellement <strong><?= $pending_count ?></strong> soumission(s) en attente de traitement.</p>
                <a href="admin/process.php" class="btn btn-primary">Accéder au panneau d'administration</a>
                <?php if ($pending_count > 0): ?>
                    <p class="note">Note: Le traitement des soumissions nécessite une intervention manuelle dans le panneau d'administration.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?= date('Y') ?> Coursero</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>
