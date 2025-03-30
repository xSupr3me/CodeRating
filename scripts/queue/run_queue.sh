#!/bin/bash

# Script pour lancer le processeur de file d'attente en tant que démon

ROOT_DIR=$(dirname $(dirname $(dirname $(realpath $0))))
QUEUE_DIR="$ROOT_DIR/scripts/queue"
LOG_DIR="$ROOT_DIR/logs"
PID_FILE="$ROOT_DIR/tmp/queue_processor.pid"

# Créer les dossiers s'ils n'existent pas
mkdir -p "$ROOT_DIR/tmp"
mkdir -p "$LOG_DIR"

# Fonction d'aide
usage() {
    echo "Usage: $0 {start|stop|restart|status}"
    exit 1
}

# Démarrer le processeur de file d'attente
start() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo "Le processeur de file d'attente est déjà en cours d'exécution (PID: $PID)"
            return
        else
            echo "Le fichier PID existe mais le processus n'est pas en cours d'exécution. Suppression du fichier PID."
            rm -f "$PID_FILE"
        fi
    fi
    
    echo "Démarrage du processeur de file d'attente..."
    nohup php "$QUEUE_DIR/queue_processor.php" > "$LOG_DIR/queue.log" 2>&1 &
    PID=$!
    echo $PID > "$PID_FILE"
    echo "Processeur de file d'attente démarré avec PID: $PID"
}

# Arrêter le processeur de file d'attente
stop() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo "Arrêt du processeur de file d'attente (PID: $PID)..."
            kill $PID
            sleep 2
            
            # Vérifier si le processus est toujours en cours d'exécution
            if ps -p $PID > /dev/null 2>&1; then
                echo "Le processus ne répond pas, force l'arrêt..."
                kill -9 $PID
            fi
            
            rm -f "$PID_FILE"
            echo "Processeur de file d'attente arrêté."
        else
            echo "Le processeur de file d'attente n'est pas en cours d'exécution."
            rm -f "$PID_FILE"
        fi
    else
        echo "Le processeur de file d'attente n'est pas en cours d'exécution."
    fi
}

# Vérifier l'état du processeur de file d'attente
status() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo "Le processeur de file d'attente est en cours d'exécution (PID: $PID)"
        else
            echo "Le processeur de file d'attente n'est pas en cours d'exécution (fichier PID obsolète)"
        fi
    else
        echo "Le processeur de file d'attente n'est pas en cours d'exécution."
    fi
}

# Traitement des arguments
case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        start
        ;;
    status)
        status
        ;;
    *)
        usage
        ;;
esac

exit 0
