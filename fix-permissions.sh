#!/bin/bash

# Script pour corriger les permissions du dossier workspace/commands
# À exécuter après chaque déploiement

echo "🔧 Correction des permissions du dossier workspace/commands..."

# Vérifier si le dossier existe
if [ ! -d "workspace/commands" ]; then
    echo "❌ Le dossier workspace/commands n'existe pas !"
    exit 1
fi

# Corriger les permissions
sudo chown -R morglaf:morglaf workspace/commands
sudo chmod -R 755 workspace/commands

# Nettoyer les fichiers de commande qui traînent
sudo rm -f workspace/commands/*.cmd
sudo rm -f workspace/commands/*.result

echo "✅ Permissions corrigées !"
echo "📁 Dossier: $(ls -ld workspace/commands/)"
echo "🗂️  Contenu: $(ls -la workspace/commands/)"
