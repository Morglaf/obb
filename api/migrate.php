<?php
/**
 * Script de migration pour créer les tables de la base de données
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Utils\Database;

// Exécuter la migration
echo "Exécution de la migration de la base de données...\n";

try {
    $db = new Database();
    
    // Créer la table users si elle n'existe pas
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
    
    // Créer la table user_templates si elle n'existe pas
    $db->exec('CREATE TABLE IF NOT EXISTS user_templates (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(20) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    
    // Créer la table comments si elle n'existe pas
    $db->exec('CREATE TABLE IF NOT EXISTS comments (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
    
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}