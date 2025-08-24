#!/bin/bash

# Script de démarrage pour l'API PHP
# Exécute la migration de la base de données puis démarre le serveur

echo "=== Démarrage de l'API OnlineBookBrew ==="

# Attendre que PostgreSQL soit prêt
echo "Attente de la base de données PostgreSQL..."
until php -r "
try {
    \$pdo = new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USER'), getenv('DB_PASSWORD'));
    echo 'Connexion à PostgreSQL réussie\n';
    exit(0);
} catch (Exception \$e) {
    echo 'En attente de PostgreSQL...\n';
    exit(1);
}
" 2>/dev/null; do
    sleep 2
done

echo "✅ PostgreSQL est prêt"

# Vérifier si les tables existent déjà
echo "Vérification de la structure de la base de données..."
php -r "
try {
    \$pdo = new PDO('pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USER'), getenv('DB_PASSWORD'));
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_name = \'users\'');
    \$tableExists = \$stmt->fetchColumn() > 0;
    
    if (\$tableExists) {
        echo '✅ Tables déjà existantes, pas besoin de migration\\n';
        exit(0);
    } else {
        echo '📋 Tables manquantes, exécution de la migration...\\n';
        exit(1);
    }
} catch (Exception \$e) {
    echo '❌ Erreur de vérification: ' . \$e->getMessage() . '\\n';
    exit(1);
}
"

if [ $? -eq 1 ]; then
    echo "Exécution de la migration de la base de données..."
    php migrate-db.php
    
    if [ $? -eq 0 ]; then
        echo "✅ Migration réussie"
    else
        echo "⚠️ Migration échouée, mais on continue..."
    fi
else
    echo "✅ Base de données déjà initialisée"
fi

# Démarrer le serveur PHP
echo "Démarrage du serveur PHP..."
exec php -S 0.0.0.0:8080 -t public router.php 