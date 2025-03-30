<?php
require_once '../config.php';

$error = '';

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (is_logged_in()) {
    redirect('/');
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $db = db_connect();
        $stmt = $db->prepare('SELECT id, email, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            
            // Régénérer l'ID de session pour éviter les attaques de fixation de session
            session_regenerate_id(true);
            
            redirect('/');
        } else {
            $error = 'Adresse email ou mot de passe incorrect.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Connexion</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1><?= APP_NAME ?></h1>
            <p>Connectez-vous pour accéder à la plateforme</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
        </form>

        <div class="auth-footer">
            <p>Pas encore de compte? <a href="register.php">Créer un compte</a></p>
        </div>
    </div>
</body>
</html>
