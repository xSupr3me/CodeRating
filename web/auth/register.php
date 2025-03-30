<?php
require_once '../config.php';

$error = '';
$success = '';

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (is_logged_in()) {
    redirect('/');
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation basique
    if (empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        // Vérifier si l'email existe déjà
        $db = db_connect();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Cette adresse email est déjà utilisée.';
        } else {
            // Hachage du mot de passe et création de l'utilisateur
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare('INSERT INTO users (email, password) VALUES (?, ?)');
            if ($stmt->execute([$email, $hashed_password])) {
                $success = 'Compte créé avec succès! Vous pouvez maintenant vous connecter.';
            } else {
                $error = 'Une erreur est survenue lors de la création du compte.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Inscription</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1><?= APP_NAME ?></h1>
            <p>Créez un compte pour accéder à la plateforme</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
            <p class="text-center">
                <a href="login.php" class="btn btn-primary">Se connecter</a>
            </p>
        <?php else: ?>
            <form method="post" class="auth-form">
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" name="password" id="password" required minlength="8">
                    <small>Minimum 8 caractères</small>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" name="password_confirm" id="password_confirm" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary btn-block">S'inscrire</button>
            </form>
        <?php endif; ?>

        <div class="auth-footer">
            <p>Déjà un compte? <a href="login.php">Se connecter</a></p>
        </div>
    </div>
</body>
</html>
