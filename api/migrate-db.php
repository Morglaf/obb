<?php
/**
 * Script de migration pour créer les tables de la base de données
 * Peut être exécuté manuellement pour s'assurer que les tables existent
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Utils\Database;

echo "=== Migration de la base de données OnlineBookBrew ===\n";

try {
    // Tester la connexion
    echo "Test de connexion à la base de données...\n";
    $db = new Database();
    echo "✅ Connexion réussie\n";
    
    // Créer les tables
    echo "\nCréation des tables...\n";
    
    // Table users
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        username VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Table 'users' créée/vérifiée\n";
    
    // Table user_templates
    $db->exec("CREATE TABLE IF NOT EXISTS user_templates (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        name VARCHAR(255) NOT NULL,
        type VARCHAR(50) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        preview_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (name, type, user_id)
    )");
    echo "✅ Table 'user_templates' créée/vérifiée\n";
    
    // Table user_fonts
    $db->exec("CREATE TABLE IF NOT EXISTS user_fonts (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        filename VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (filename, user_id)
    )");
    echo "✅ Table 'user_fonts' créée/vérifiée\n";
    
    // Table comments
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "✅ Table 'comments' créée/vérifiée\n";
    
    // Créer un utilisateur de test si la table est vide
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        echo "\nCréation d'un utilisateur de test...\n";
        $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (email, username, password) VALUES ('admin@onlinebookbrew.com', 'admin', '$hashedPassword')");
        echo "✅ Utilisateur de test créé (email: admin@onlinebookbrew.com, password: password)\n";
    } else {
        echo "\n✅ Utilisateurs existants trouvés ($userCount utilisateur(s))\n";
    }
    
    // Vérifier les tables créées
    echo "\nVérification des tables...\n";
    $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "✅ Table '$table' existe\n";
    }
    
    echo "\n🎉 Migration terminée avec succès !\n";
    echo "La base de données est prête à être utilisée.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la migration: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
} 