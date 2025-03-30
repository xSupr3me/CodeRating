#!/bin/bash

# Script d'initialisation de la base de données

# Variables de configuration (à modifier selon votre environnement)
DB_USER="root"
DB_PASSWORD=""
DB_NAME="coursero"

# Création de la base de données
echo "Création de la base de données $DB_NAME..."
mysql -u $DB_USER -p$DB_PASSWORD -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Importation du schéma
echo "Importation du schéma de la base de données..."
mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME < schema.sql

# Ajout de données de test (optionnel)
echo "Ajout de données de test..."
mysql -u $DB_USER -p$DB_PASSWORD $DB_NAME < test_data.sql

echo "Base de données initialisée avec succès!"
