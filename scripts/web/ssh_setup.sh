#!/bin/bash
# Script pour configurer l'authentification SSH entre les serveurs web

# Vérifier si le script est exécuté en tant que root
if [ "$EUID" -ne 0 ]; then
  echo "Ce script doit être exécuté en tant que root"
  exit 1
fi

echo "=== Configuration de l'authentification SSH sans mot de passe ==="

# Générer une paire de clés SSH si elle n'existe pas
if [ ! -f /root/.ssh/id_rsa ]; then
  echo "Génération d'une nouvelle paire de clés SSH..."
  ssh-keygen -t rsa -b 4096 -f /root/.ssh/id_rsa -N ""
else
  echo "Paire de clés SSH existante trouvée."
fi

# Fonction pour copier la clé SSH vers un autre serveur en utilisant un mot de passe fourni
copy_key_to_server() {
  local server=$1
  local password=$2
  
  echo "Configuration de l'accès SSH vers $server..."
  
  # S'assurer que le répertoire .ssh existe sur le serveur distant
  sshpass -p "$password" ssh -o StrictHostKeyChecking=no root@$server "mkdir -p ~/.ssh && chmod 700 ~/.ssh"
  
  # Copier la clé publique
  sshpass -p "$password" ssh-copy-id -o StrictHostKeyChecking=no root@$server
  
  # Tester la connexion
  ssh -o BatchMode=yes -o ConnectTimeout=5 root@$server echo "Connexion SSH vers $server établie avec succès."
  if [ $? -eq 0 ]; then
    echo "Authentification par clé configurée vers $server."
  else
    echo "Échec de la configuration SSH vers $server."
  fi
}

# Demander le mot de passe root pour les serveurs
echo "Veuillez entrer le mot de passe root pour les serveurs web:"
read -s ROOT_PASSWORD

# Configurer SSH pour les deux serveurs web
if [ "$(hostname)" == "web01" ]; then
  copy_key_to_server "web02" "$ROOT_PASSWORD"
elif [ "$(hostname)" == "web02" ]; then
  copy_key_to_server "web01" "$ROOT_PASSWORD"
else
  echo "Ce script doit être exécuté sur web01 ou web02"
  exit 1
fi

echo "=== Configuration SSH terminée ==="
