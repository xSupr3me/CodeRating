<?php
require_once 'config.php';

// Redirection vers la page de connexion si l'utilisateur n'est pas connecté
if (!is_logged_in()) {
    redirect('/auth/login.php');
}

$error = '';
$success = '';
$db = db_connect();

// Récupération de l'ID du cours s'il est spécifié
$course_id = $_POST['course_id'] ?? $_GET['course_id'] ?? null;

// Si le cours est spécifié, récupérer les exercices associés
$exercises = [];
if ($course_id) {
    $stmt = $db->prepare('SELECT id, exercise_number, title FROM exercises WHERE course_id = ? ORDER BY exercise_number');
    $stmt->execute([$course_id]);
    $exercises = $stmt->fetchAll();
}

// Récupérer les langages disponibles
$stmt = $db->query('SELECT id, name FROM languages ORDER BY name');
$languages = $stmt->fetchAll();

// Traitement du formulaire de soumission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit']) && !empty($_FILES['code_file']['name'])) {
    $exercise_id = $_POST['exercise_id'] ?? null;
    $language_id = $_POST['language_id'] ?? null;
    
    // Vérification des champs
    if (!$exercise_id || !$language_id) {
        $error = 'Veuillez sélectionner un exercice et un langage.';
    } else {
        // Vérification du fichier
        $file = $_FILES['code_file'];
        
        // Vérifier l'extension du fichier
        $language_stmt = $db->prepare('SELECT extension FROM languages WHERE id = ?');
        $language_stmt->execute([$language_id]);
        $language = $language_stmt->fetch();
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension !== $language['extension']) {
            $error = 'Le fichier doit avoir l\'extension .' . $language['extension'] . ' pour le langage sélectionné.';
        } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
            $error = 'Le fichier est trop volumineux. Taille maximale: ' . (MAX_UPLOAD_SIZE / 1024) . ' Ko.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erreur lors du téléchargement du fichier.';
        } else {
            // Générer un nom de fichier unique avec timestamp
            $timestamp = time();
            $filename = $timestamp . '_' . $_SESSION['user_id'] . '_' . basename($file['name']);
            $filepath = UPLOAD_DIR . $filename;
            
            // Déplacer le fichier téléchargé
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Enregistrer la soumission dans la base de données
                $stmt = $db->prepare('
                    INSERT INTO submissions (user_id, exercise_id, language_id, file_path, status)
                    VALUES (?, ?, ?, ?, "pending")
                ');
                
                if ($stmt->execute([$_SESSION['user_id'], $exercise_id, $language_id, $filepath])) {
                    $submission_id = $db->lastInsertId();
                    $success = 'Code soumis avec succès! Votre soumission est en attente d\'évaluation.';
                    
                    // Redirection vers la page d'accueil après 2 secondes
                    header('Refresh: 2; URL=' . APP_URL);
                } else {
                    $error = 'Erreur lors de l\'enregistrement de la soumission.';
                    // Supprimer le fichier en cas d'erreur
                    unlink($filepath);
                }
            } else {
                $error = 'Erreur lors du déplacement du fichier.';
            }
        }
    }
}

// Récupérer tous les cours pour le sélecteur
$stmt = $db->query('SELECT id, name FROM courses ORDER BY name');
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Soumettre un code</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1><?= APP_NAME ?></h1>
            <nav>
                <ul>
                    <li><a href="index.php">Tableau de bord</a></li>
                    <li><a href="submit.php" class="active">Soumettre un code</a></li>
                    <li><a href="auth/logout.php">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <section>
            <h2>Soumettre un code pour évaluation</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="course">Cours:</label>
                    <select name="course_id" id="course" required onchange="this.form.submit()">
                        <option value="">Sélectionnez un cours</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $course_id == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($course_id && !empty($exercises)): ?>
                    <div class="form-group">
                        <label for="exercise">Exercice:</label>
                        <select name="exercise_id" id="exercise" required>
                            <option value="">Sélectionnez un exercice</option>
                            <?php foreach ($exercises as $exercise): ?>
                                <option value="<?= $exercise['id'] ?>">
                                    Ex <?= $exercise['exercise_number'] ?> - <?= htmlspecialchars($exercise['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="language">Langage de programmation:</label>
                        <select name="language_id" id="language" required>
                            <option value="">Sélectionnez un langage</option>
                            <?php foreach ($languages as $language): ?>
                                <option value="<?= $language['id'] ?>">
                                    <?= htmlspecialchars($language['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="code_file">Fichier de code:</label>
                        <input type="file" name="code_file" id="code_file" required>
                        <small>Taille maximale: <?= MAX_UPLOAD_SIZE / 1024 ?> Ko</small>
                    </div>
                    
                    <button type="submit" name="submit" class="btn">Soumettre</button>
                <?php elseif ($course_id): ?>
                    <div class="alert alert-error">Aucun exercice n'est disponible pour ce cours.</div>
                <?php endif; ?>
            </form>
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
