#!/bin/bash
# Script d'automatisation des soumissions de test

# Variables
WEB_DIR="/var/www/coursero"
SAMPLES_DIR="/var/www/CodeRating/test_samples"
UPLOAD_DIR="/var/www/uploads"
DB_USER="coursero_user"
DB_PASS="root"
DB_NAME="coursero"

# Créer le répertoire d'upload s'il n'existe pas
mkdir -p "$UPLOAD_DIR"
chmod 777 "$UPLOAD_DIR"

# Récupérer les IDs des exercices et des langages
echo "Récupération des données de la base..."
HELLO_WORLD_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT id FROM exercises WHERE title LIKE '%Hello World%' LIMIT 1")
MOYENNE_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT id FROM exercises WHERE title LIKE '%moyenne%' OR title LIKE '%Moyenne%' LIMIT 1")
C_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT id FROM languages WHERE name = 'C' LIMIT 1")
PYTHON_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT id FROM languages WHERE name = 'Python' LIMIT 1")
USER_ID=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "SELECT id FROM users LIMIT 1")

echo "Hello World ID: $HELLO_WORLD_ID"
echo "Moyenne ID: $MOYENNE_ID"
echo "C ID: $C_ID"
echo "Python ID: $PYTHON_ID"
echo "User ID: $USER_ID"

if [ -z "$HELLO_WORLD_ID" ] || [ -z "$MOYENNE_ID" ] || [ -z "$C_ID" ] || [ -z "$PYTHON_ID" ] || [ -z "$USER_ID" ]; then
    echo "Erreur: Impossible de récupérer les IDs nécessaires."
    exit 1
fi

# Fonction pour soumettre un fichier
submit_file() {
    local file=$1
    local exercise_id=$2
    local language_id=$3
    local timestamp=$(date +%s)
    local dest_file="$UPLOAD_DIR/${timestamp}_${USER_ID}_$(basename $file)"
    
    # Copier le fichier
    cp "$file" "$dest_file"
    chmod 644 "$dest_file"
    
    # Insérer dans la base de données
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
        INSERT INTO submissions (user_id, exercise_id, language_id, file_path, status) 
        VALUES ($USER_ID, $exercise_id, $language_id, '$dest_file', 'pending')
    "
    
    echo "Fichier $(basename $file) soumis avec succès (exercise: $exercise_id, language: $language_id)."
}

# Soumettre les exemples C
echo "Soumission des exemples en C..."
if [ -f "$SAMPLES_DIR/hello_world_c_correct.c" ]; then
    submit_file "$SAMPLES_DIR/hello_world_c_correct.c" $HELLO_WORLD_ID $C_ID
fi

if [ -f "$SAMPLES_DIR/hello_world_c_wrong.c" ]; then
    submit_file "$SAMPLES_DIR/hello_world_c_wrong.c" $HELLO_WORLD_ID $C_ID
fi

if [ -f "$SAMPLES_DIR/moyenne_c_correct.c" ]; then
    submit_file "$SAMPLES_DIR/moyenne_c_correct.c" $MOYENNE_ID $C_ID
fi

if [ -f "$SAMPLES_DIR/timeout_c.c" ]; then
    submit_file "$SAMPLES_DIR/timeout_c.c" $HELLO_WORLD_ID $C_ID
fi

# Soumettre les exemples Python
echo "Soumission des exemples en Python..."
if [ -f "$SAMPLES_DIR/hello_world_py_correct.py" ]; then
    submit_file "$SAMPLES_DIR/hello_world_py_correct.py" $HELLO_WORLD_ID $PYTHON_ID
fi

if [ -f "$SAMPLES_DIR/moyenne_py_correct.py" ]; then
    submit_file "$SAMPLES_DIR/moyenne_py_correct.py" $MOYENNE_ID $PYTHON_ID
fi

if [ -f "$SAMPLES_DIR/memory_py.py" ]; then
    submit_file "$SAMPLES_DIR/memory_py.py" $HELLO_WORLD_ID $PYTHON_ID
fi

echo "Toutes les soumissions ont été effectuées."
echo "Vérifiez les résultats dans l'interface web ou exécutez manual_process.php."
