#!/bin/bash

# Script à exécuter après chaque démarrage des conteneurs Docker
# Peut être ajouté dans docker-compose.yml avec depends_on et healthcheck

echo "🚀 Script post-démarrage Docker OnlineBookBrew"

# Attendre que tous les conteneurs soient prêts
echo "⏳ Attente que tous les conteneurs soient prêts..."
sleep 15

# Vérifier que tous les conteneurs sont en cours d'exécution
echo "🔍 Vérification des conteneurs..."
if ! docker ps | grep -q "onlinebookbrew-php-api-1"; then
    echo "❌ Conteneur PHP-API non prêt"
    exit 1
fi

if ! docker ps | grep -q "onlinebookbrew-processor-1"; then
    echo "❌ Conteneur processor non prêt"
    exit 1
fi

echo "✅ Tous les conteneurs sont prêts"

# Exécuter le script de correction des permissions
echo "🔧 Exécution de la correction des permissions..."
if [ -f "./fix-permissions.sh" ]; then
    chmod +x ./fix-permissions.sh
    ./fix-permissions.sh
else
    echo "⚠️  Script fix-permissions.sh non trouvé"
fi

echo "🎯 Système OnlineBookBrew prêt !"
