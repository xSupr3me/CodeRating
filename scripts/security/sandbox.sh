#!/bin/bash
# Script pour exécuter du code soumis avec des restrictions de sécurité

# Paramètres
TIMEOUT=5 # secondes
MAX_CPU=10 # % d'utilisation CPU max
MAX_MEM=128000 # 128 MB en KB
MAX_PROCESSES=10
MAX_FSIZE=1024 # Ko, taille max des fichiers
JAIL_DIR="/tmp/code_jail"

# Utilisation
usage() {
    echo "Usage: $0 [options] <command>"
    echo "Options:"
    echo "  -t <seconds>  Timeout (défaut: 5s)"
    echo "  -m <KB>       Limite de mémoire (défaut: 128000KB)"
    echo "  -c <percent>  Limite CPU (défaut: 10%)"
    echo "  -f <KB>       Taille max des fichiers (défaut: 1024KB)"
    echo "  -d <dir>      Répertoire d'exécution (défaut: /tmp/code_jail)"
    exit 1
}

# Traitement des arguments
while getopts "t:m:c:f:d:" opt; do
    case ${opt} in
        t) TIMEOUT=$OPTARG ;;
        m) MAX_MEM=$OPTARG ;;
        c) MAX_CPU=$OPTARG ;;
        f) MAX_FSIZE=$OPTARG ;;
        d) JAIL_DIR=$OPTARG ;;
        *) usage ;;
    esac
done
shift $((OPTIND -1))

if [ $# -eq 0 ]; then
    usage
fi

# Créer un répertoire d'exécution temporaire s'il n'existe pas
mkdir -p "$JAIL_DIR"
chmod 755 "$JAIL_DIR"

# Exécuter la commande avec des restrictions
timeout --kill-after=1 "$TIMEOUT" \
    nice -n 19 \
    /usr/bin/env \
        TMPDIR="$JAIL_DIR" \
        TMP="$JAIL_DIR" \
        TEMP="$JAIL_DIR" \
    /usr/bin/time -f "CPU: %P MEM: %M KB" \
    /bin/bash -c "
        ulimit -t $TIMEOUT    # CPU time in seconds
        ulimit -v $MAX_MEM    # Virtual memory
        ulimit -u $MAX_PROCESSES # Maximum user processes
        ulimit -f $MAX_FSIZE  # Maximum file size
        cd \"$JAIL_DIR\" && $*
    "

# Récupérer le code de sortie
EXIT_CODE=$?

# Interpréter le code de sortie
if [ $EXIT_CODE -eq 124 ] || [ $EXIT_CODE -eq 137 ]; then
    echo "TIMEOUT: Exécution interrompue après $TIMEOUT secondes"
    exit 124
elif [ $EXIT_CODE -ne 0 ]; then
    echo "ERREUR: Code de sortie $EXIT_CODE"
    exit $EXIT_CODE
fi

exit 0
