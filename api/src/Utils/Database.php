<?php

namespace App\Utils;

/**
 * Classe Database pour gérer la connexion à la base de données PostgreSQL
 */
class Database extends \PDO 
{
    /**
     * Initialise la connexion à la base de données PostgreSQL
     */
    public function __construct()
    {
        try {
            // Configuration de la base de données depuis les variables d'environnement
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '5432';
            $dbname = $_ENV['DB_DATABASE'] ?? 'onlinebookbrew';
            $username = $_ENV['DB_USER'] ?? 'postgres';
            $password = $_ENV['DB_PASSWORD'] ?? 'password';
            
            // Utiliser DATABASE_URL si disponible (format PostgreSQL standard)
            if (isset($_ENV['DATABASE_URL']) && !empty($_ENV['DATABASE_URL'])) {
                // Parser l'URL PostgreSQL
                $url = parse_url($_ENV['DATABASE_URL']);
                $host = $url['host'];
                $port = $url['port'];
                $dbname = ltrim($url['path'], '/');
                $username = $url['user'];
                $password = $url['pass'];
                
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            } else {
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            }
            
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            // Connexion à PostgreSQL
            parent::__construct($dsn, $username, $password, $options);
            
        } catch (\PDOException $e) {
            error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
            throw new \Exception('Erreur de connexion à la base de données: ' . $e->getMessage());
        }
    }
    
    /**
     * Exécute le script de migration pour créer les tables nécessaires
     */
    public static function migrate(): void
    {
        try {
            $db = new self();
            
            // Vérifier le schéma par défaut et les permissions
            $currentSchema = $db->query("SELECT current_schema()")->fetchColumn();
            $searchPath = $db->query("SHOW search_path")->fetchColumn();
            $currentUser = $db->query("SELECT current_user")->fetchColumn();
            
            echo "Utilisateur: $currentUser, Schéma actuel: $currentSchema, Search path: $searchPath\n";
            
            // Laisser PostgreSQL utiliser le search_path pour trouver le bon schéma
            echo "Search path PostgreSQL: $searchPath\n";
            echo "PostgreSQL utilisera automatiquement le premier schéma avec permissions CREATE.\n";
            
            // Pas de préfixe - laisser PostgreSQL gérer via search_path
            $tablePrefix = '';
            
            // Créer les tables (PostgreSQL utilisera le search_path)
            $tables = [
                'users' => "
                    CREATE TABLE IF NOT EXISTS users (
                        id SERIAL PRIMARY KEY,
                        email VARCHAR(255) NOT NULL UNIQUE,
                        username VARCHAR(255) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );
                ",
                'user_templates' => "
                    CREATE TABLE IF NOT EXISTS user_templates (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        type VARCHAR(50) NOT NULL,
                        file_path VARCHAR(255) NOT NULL,
                        preview_path VARCHAR(255) DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE (name, type, user_id)
                    );
                ",
                'user_fonts' => "
                    CREATE TABLE IF NOT EXISTS user_fonts (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        filename VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE (filename, user_id)
                    );
                ",
                'comments' => "
                    CREATE TABLE IF NOT EXISTS comments (
                        id SERIAL PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        content TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    );
                "
            ];
            
            $createdTables = [];
            foreach ($tables as $tableName => $sql) {
                try {
                    $db->exec($sql);
                    $createdTables[] = $tableName;
                    echo "Table '$tableName' créée avec succès.\n";
                } catch (\PDOException $e) {
                    echo "Erreur lors de la création de la table '$tableName': " . $e->getMessage() . "\n";
                    throw $e;
                }
            }
            
            echo "Migration terminée. Tables créées: " . implode(', ', $createdTables) . "\n";
            echo "La base de données PostgreSQL est prête à être utilisée.\n";
            
        } catch (\PDOException $e) {
            echo "Erreur lors de la migration: " . $e->getMessage() . "\n";
            error_log('Erreur lors de la migration: ' . $e->getMessage());
            throw $e;
        }
    }
} 