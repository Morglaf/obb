#!/bin/bash

# Script pour corriger les permissions du dossier workspace
# À exécuter après chaque déploiement

echo "🔧 Correction des permissions du dossier workspace..."

# Vérifier si le dossier existe
if [ ! -d "workspace" ]; then
    echo "❌ Le dossier workspace n'existe pas !"
    exit 1
fi

# Attendre que Docker soit prêt
echo "⏳ Attente que Docker soit prêt..."
sleep 5

# Vérifier que le conteneur processor fonctionne
if ! docker ps | grep -q "onlinebookbrew-processor-1"; then
    echo "❌ Le conteneur processor n'est pas en cours d'exécution !"
    echo "🔄 Redémarrage du conteneur processor..."
    docker restart onlinebookbrew-processor-1
    sleep 10
fi

# Corriger les permissions de tout le dossier workspace
echo "📁 Correction de workspace..."
sudo chown -R morglaf:morglaf workspace
sudo chmod -R 777 workspace

# Créer le dossier commands s'il n'existe pas
if [ ! -d "workspace/commands" ]; then
    echo "📁 Création du dossier commands..."
    mkdir -p workspace/commands
    sudo chown morglaf:morglaf workspace/commands
    sudo chmod 777 workspace/commands
fi

# Nettoyer les fichiers de commande qui traînent
sudo rm -f workspace/commands/*.cmd
sudo rm -f workspace/commands/*.result

# Vérifier que le conteneur processor traite les commandes
echo "🔍 Vérification du conteneur processor..."
if docker logs onlinebookbrew-processor-1 --tail 5 | grep -q "surveillance des commandes"; then
    echo "✅ Le conteneur processor fonctionne correctement"
else
    echo "⚠️  Le conteneur processor pourrait avoir des problèmes"
fi

echo "✅ Permissions corrigées !"
echo "📁 Dossier workspace: $(ls -ld workspace/)"
echo "📁 Dossier commands: $(ls -ld workspace/commands/)"
echo "🗂️  Contenu commands: $(ls -la workspace/commands/)"
echo ""
echo "🎯 Le système est maintenant prêt pour la compilation !"
