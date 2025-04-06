#!/bin/bash
# Script pour réinitialiser le mot de passe administrateur

# Vérifier que le script est exécuté en tant que root
if [ "$EUID" -ne 0 ]; then
  echo "Ce script doit être exécuté en tant que root"
  exit 1
fi

echo "=== Réinitialisation du mot de passe administrateur ==="

# Générer un hash bcrypt pour le mot de passe 'password'
# Note: Nous utilisons PHP pour générer un hash bcrypt compatible
PASSWORD_HASH=$(php -r 'echo password_hash("password", PASSWORD_BCRYPT);')

echo "Hash généré pour le mot de passe 'password': $PASSWORD_HASH"

# Mettre à jour le mot de passe dans la base de données
echo "Mise à jour de l'utilisateur admin dans la base de données..."
mysql coursero -e "
  -- Supprimer l'ancien utilisateur admin s'il existe
  DELETE FROM users WHERE email = 'admin@coursero.local';
  
  -- Créer un nouvel utilisateur admin
  INSERT INTO users (email, password) VALUES ('admin@coursero.local', '$PASSWORD_HASH');
"

# Vérifier que la mise à jour a été effectuée
ADMIN_EXISTS=$(mysql -N -e "SELECT COUNT(*) FROM coursero.users WHERE email = 'admin@coursero.local'")

if [ "$ADMIN_EXISTS" -eq "1" ]; then
  echo "✅ L'utilisateur admin a été réinitialisé avec succès."
  echo "Email: admin@coursero.local"
  echo "Mot de passe: password"
else
  echo "❌ Erreur lors de la réinitialisation de l'utilisateur admin."
fi

echo "=== Fin de la réinitialisation ==="
