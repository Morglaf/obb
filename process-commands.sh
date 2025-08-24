#!/bin/bash

# Script de surveillance des commandes pour le conteneur processor
WORKSPACE_DIR="/workspace"
COMMAND_DIR="$WORKSPACE_DIR/commands"

echo "Démarrage du surveillance des commandes dans $COMMAND_DIR"

# Créer le répertoire de commandes s'il n'existe pas
if [[ ! -d "$COMMAND_DIR" ]]; then
    mkdir -p "$COMMAND_DIR"
    echo "Dossier commands créé: $COMMAND_DIR"
fi

# S'assurer que le dossier est accessible en écriture par l'utilisateur actuel
chmod 755 "$COMMAND_DIR"
echo "Permissions du dossier commands: $(ls -ld $COMMAND_DIR)"

while true; do
    # Chercher des fichiers de commandes
    for cmd_file in "$COMMAND_DIR"/*.cmd; do
        if [[ -f "$cmd_file" ]]; then
            echo "Traitement de la commande: $cmd_file"
            
            # Le fichier contient: work_dir|command
            content=$(cat "$cmd_file")
            work_dir=$(echo "$content" | cut -d'|' -f1)
            command=$(echo "$content" | cut -d'|' -f2-)
            
            # Si pas de répertoire spécifié, utiliser workspace
            if [[ -z "$work_dir" || "$work_dir" == "$command" ]]; then
                work_dir="$WORKSPACE_DIR"
                command="$content"
            else
                work_dir="$WORKSPACE_DIR/$work_dir"
            fi
            
            echo "Exécution dans $work_dir: $command"
            
            # Exécuter la commande dans le bon répertoire
            cd "$work_dir"
            
            # Exécuter et capturer le code de retour
            eval "$command"
            result_code=$?
            
            # Écrire le résultat
            echo "$result_code" > "${cmd_file}.result"
            
            # Supprimer le fichier de commande
            rm "$cmd_file"
            
            echo "Commande terminée avec le code: $result_code"
        fi
    done
    
    # Attendre 1 seconde avant de vérifier à nouveau
    sleep 1
done 