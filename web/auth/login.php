<?php
require_once '../config.php';

$error = '';
$debug_info = '';

// Si l'utilisateur est déjà connecté, rediriger vers la page d'accueil
if (is_logged_in()) {
    redirect('/');
}

// Vérifier si un utilisateur admin existe, sinon le créer
function ensure_admin_exists() {
    $db = db_connect();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute(['admin@coursero.local']);
    
    if (!$stmt->fetch()) {
        // Créer l'utilisateur admin si inexistant
        $password_hash = password_hash('password', PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (email, password) VALUES (?, ?)');
        $stmt->execute(['admin@coursero.local', $password_hash]);
        return "Admin user created with password 'password'";
    }
    
    // Mettre à jour le mot de passe admin si nécessaire
    $password_hash = password_hash('password', PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE users SET password = ? WHERE email = ?');
    $stmt->execute([$password_hash, 'admin@coursero.local']);
    return "Admin password updated to 'password'";
}

// Exécuter la fonction de vérification d'admin
$debug_info = ensure_admin_exists();

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

        if ($user) {
            // Debug: vérifier le hash du mot de passe
            $debug_info .= "<br>Email: " . htmlspecialchars($email);
            $debug_info .= "<br>Hash stocké: " . htmlspecialchars($user['password']);
            $debug_info .= "<br>Vérification: " . (password_verify($password, $user['password']) ? 'OK' : 'Échec');
        }

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
            <?php if ($_GET['debug'] === 'true'): ?>
                <div class="debug-info" style="margin-top: 20px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; font-size: 12px; color: #666;">
                    <p><strong>Informations de débogage:</strong></p>
                    <p><?= $debug_info ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
