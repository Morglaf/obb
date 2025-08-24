<?php
/**
 * Online Book Brew - Routes API
 * 
 * Ce fichier définit toutes les routes de l'API
 */

use Slim\App;
use App\Controllers\ConversionController;
use App\Controllers\TemplateController;
use App\Controllers\MediaController;
use App\Controllers\AuthController;
use App\Controllers\UserTemplateController;
use App\Controllers\UserFontController;
use App\Controllers\AdminController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

/**
 * Configure toutes les routes de l'API
 * 
 * @param App $app Instance de l'application Slim
 * @param AuthMiddleware $authMiddleware Middleware d'authentification (optionnel)
 * @return void
 */
return function (App $app, ?AuthMiddleware $authMiddleware = null) {
    // Créer une instance du middleware d'administration
    $adminMiddleware = new AdminMiddleware();

    // Route de test/ping
    $app->get('/test', function ($request, $response) {
        $data = [
            'status' => 'success',
            'message' => 'API is working!',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Route de test des extensions PHP
    $app->get('/debug/php-extensions', function ($request, $response) {
        $extensions = get_loaded_extensions();
        $pdoDrivers = PDO::getAvailableDrivers();
        
        $data = [
            'status' => 'success',
            'php_version' => PHP_VERSION,
            'loaded_extensions' => $extensions,
            'pdo_drivers' => $pdoDrivers,
            'has_pdo_pgsql' => in_array('pdo_pgsql', $extensions),
            'has_pgsql' => in_array('pgsql', $extensions),
            'environment_vars' => [
                'DATABASE_URL' => !empty($_ENV['DATABASE_URL']) ? 'Défini' : 'Non défini',
                'DB_HOST' => $_ENV['DB_HOST'] ?? 'Non défini',
                'DB_PORT' => $_ENV['DB_PORT'] ?? 'Non défini'
            ]
        ];
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Route de test création table simple
    $app->get('/debug/create-test', function ($request, $response) {
        try {
            $db = new \App\Utils\Database();
            
            $tests = [];
            
            // Test 1: Table temporaire
            try {
                $db->exec("CREATE TEMP TABLE test_temp (id INTEGER)");
                $tests['temp_table'] = 'SUCCESS';
                $db->exec("DROP TABLE test_temp");
            } catch (\Exception $e) {
                $tests['temp_table'] = 'FAILED: ' . $e->getMessage();
            }
            
            // Test 2: Table normale dans schéma public
            try {
                $db->exec("CREATE TABLE test_public (id INTEGER)");
                $tests['public_table'] = 'SUCCESS';
                $db->exec("DROP TABLE test_public");
            } catch (\Exception $e) {
                $tests['public_table'] = 'FAILED: ' . $e->getMessage();
            }
            
            // Test 3: Table dans schéma utilisateur si il existe
            try {
                $db->exec("CREATE TABLE db.test_user (id INTEGER)");
                $tests['user_schema_table'] = 'SUCCESS';
                $db->exec("DROP TABLE db.test_user");
            } catch (\Exception $e) {
                $tests['user_schema_table'] = 'FAILED: ' . $e->getMessage();
            }
            
            // Test 4: Créer schéma utilisateur
            try {
                $db->exec("CREATE SCHEMA IF NOT EXISTS db");
                $tests['create_schema'] = 'SUCCESS';
            } catch (\Exception $e) {
                $tests['create_schema'] = 'FAILED: ' . $e->getMessage();
            }
            
            $data = [
                'status' => 'success',
                'tests' => $tests,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de test des tables de l'application
    $app->get('/debug/tables', function ($request, $response) {
        try {
            $db = new \App\Utils\Database();
            
            $tables = [];
            $requiredTables = ['users', 'user_templates', 'user_fonts', 'comments'];
            
            foreach ($requiredTables as $tableName) {
                try {
                    $stmt = $db->query("SELECT COUNT(*) FROM $tableName");
                    $count = $stmt->fetchColumn();
                    $tables[$tableName] = [
                        'exists' => true,
                        'record_count' => $count
                    ];
                } catch (\Exception $e) {
                    $tables[$tableName] = [
                        'exists' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $data = [
                'status' => 'success',
                'tables' => $tables,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de diagnostic des permissions PostgreSQL
    $app->get('/debug/permissions', function ($request, $response) {
        try {
            $db = new \App\Utils\Database();
            
            // Informations sur l'utilisateur et la base
            $user = $db->query("SELECT current_user")->fetchColumn();
            $database = $db->query("SELECT current_database()")->fetchColumn();
            $schema = $db->query("SELECT current_schema()")->fetchColumn();
            $searchPath = $db->query("SHOW search_path")->fetchColumn();
            
            // Lister tous les schémas disponibles
            $schemas = $db->query("SELECT schema_name FROM information_schema.schemata ORDER BY schema_name")->fetchAll(\PDO::FETCH_COLUMN);
            
            // Vérifier les permissions sur différents schémas
            $permissions = [];
            foreach ($schemas as $schemaName) {
                $hasUsage = false;
                $hasCreate = false;
                
                try {
                    // Test permission USAGE
                    $result = $db->query("SELECT has_schema_privilege('$schemaName', 'USAGE')")->fetchColumn();
                    $hasUsage = $result === 't';
                } catch (\Exception $e) {
                    $hasUsage = false;
                }
                
                try {
                    // Test permission CREATE
                    $result = $db->query("SELECT has_schema_privilege('$schemaName', 'CREATE')")->fetchColumn();
                    $hasCreate = $result === 't';
                } catch (\Exception $e) {
                    $hasCreate = false;
                }
                
                $permissions[$schemaName] = [
                    'usage' => $hasUsage,
                    'create' => $hasCreate
                ];
            }
            
            // Tester création d'une table temporaire
            $canCreateTemp = false;
            try {
                $db->exec("CREATE TEMP TABLE test_temp (id INTEGER)");
                $canCreateTemp = true;
                $db->exec("DROP TABLE test_temp");
            } catch (\Exception $e) {
                $canCreateTemp = false;
            }
            
            $data = [
                'status' => 'success',
                'user' => $user,
                'database' => $database,
                'current_schema' => $schema,
                'search_path' => $searchPath,
                'available_schemas' => $schemas,
                'schema_permissions' => $permissions,
                'can_create_temp_table' => $canCreateTemp,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de debug pour afficher le contenu du fichier index.php
    $app->get('/debug/index', function ($request, $response) {
        try {
            $indexFile = __DIR__ . '/../public/index.php';
            $content = file_get_contents($indexFile);
            
            $debug = [
                'status' => 'success',
                'file' => $indexFile,
                'content' => $content,
                'lines' => explode("\n", $content)
            ];
            
            $response->getBody()->write(json_encode($debug, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de debug pour vérifier les outils LaTeX installés
    $app->get('/debug/tools', function ($request, $response) {
        try {
            $tools = ['pandoc', 'xelatex', 'pdflatex', 'pdftk'];
            $debug = [
                'status' => 'success',
                'timestamp' => date('Y-m-d H:i:s'),
                'tools' => [],
                'environment' => [
                    'PATH' => $_ENV['PATH'] ?? 'Not set',
                    'USER' => $_ENV['USER'] ?? 'Not set',
                    'PWD' => getcwd()
                ]
            ];
            
            foreach ($tools as $tool) {
                $output = [];
                $returnVar = 0;
                
                // Vérifier si l'outil est disponible
                exec("which $tool 2>&1", $output, $returnVar);
                $debug['tools'][$tool] = [
                    'available' => $returnVar === 0,
                    'path' => $returnVar === 0 ? implode("\n", $output) : 'Not found',
                    'version_check' => []
                ];
                
                // Si disponible, vérifier la version
                if ($returnVar === 0) {
                    $versionOutput = [];
                    $versionReturn = 0;
                    exec("$tool --version 2>&1", $versionOutput, $versionReturn);
                    $debug['tools'][$tool]['version_check'] = [
                        'return_code' => $versionReturn,
                        'output' => implode("\n", $versionOutput)
                    ];
                }
            }
            
            $response->getBody()->write(json_encode($debug, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de debug pour tester la connexion PostgreSQL
    $app->get('/debug/database', function ($request, $response) {
        try {
            $db = new \App\Utils\Database();
            
            // Test de connexion
            $version = $db->query('SELECT version()')->fetchColumn();
            
            // Lister les tables existantes
            $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll(\PDO::FETCH_COLUMN);
            
            // Vérifier les variables d'environnement
            $envVars = [
                'DATABASE_URL' => $_ENV['DATABASE_URL'] ?? 'Non défini',
                'DB_HOST' => $_ENV['DB_HOST'] ?? 'Non défini',
                'DB_PORT' => $_ENV['DB_PORT'] ?? 'Non défini',
                'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? 'Non défini',
                'DB_USER' => $_ENV['DB_USER'] ?? 'Non défini',
                'JWT_SECRET' => !empty($_ENV['JWT_SECRET']) ? 'Défini (' . strlen($_ENV['JWT_SECRET']) . ' caractères)' : 'Non défini'
            ];
            
            $debug = [
                'status' => 'success',
                'message' => 'Connexion PostgreSQL réussie',
                'postgresql_version' => $version,
                'existing_tables' => $tables,
                'environment_variables' => $envVars,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $response->getBody()->write(json_encode($debug, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage(),
                'environment_check' => [
                    'DATABASE_URL' => !empty($_ENV['DATABASE_URL']) ? 'Défini' : 'Non défini',
                    'DB_HOST' => $_ENV['DB_HOST'] ?? 'Non défini',
                    'DB_PORT' => $_ENV['DB_PORT'] ?? 'Non défini',
                    'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? 'Non défini',
                    'DB_USER' => $_ENV['DB_USER'] ?? 'Non défini'
                ],
                'trace' => $e->getTraceAsString()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de debug temporaire pour vérifier les fichiers
    $app->get('/debug/files', function ($request, $response) {
        try {
            $config = require __DIR__ . '/../config/app.php';
            $paths = $config['paths'];
            
            $debug = [
                'status' => 'success',
                'timestamp' => date('Y-m-d H:i:s'),
                'paths' => [],
                'directories' => [],
                'files' => []
            ];
            
            // Vérifier chaque chemin configuré
            foreach ($paths as $name => $path) {
                $debug['paths'][$name] = [
                    'path' => $path,
                    'exists' => file_exists($path),
                    'is_dir' => is_dir($path),
                    'is_readable' => is_readable($path)
                ];
            }
            
            // Vérifier spécifiquement le répertoire typeset
            $typesetDir = $paths['typeset'];
            if (is_dir($typesetDir)) {
                $subdirs = ['layout', 'cover', 'impose', 'fonts'];
                foreach ($subdirs as $subdir) {
                    $fullPath = $typesetDir . DIRECTORY_SEPARATOR . $subdir;
                    $debug['directories'][$subdir] = [
                        'path' => $fullPath,
                        'exists' => file_exists($fullPath),
                        'is_dir' => is_dir($fullPath),
                        'files' => is_dir($fullPath) ? scandir($fullPath) : []
                    ];
                }
            }
            
            $response->getBody()->write(json_encode($debug, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Routes pour la conversion de documents
    $app->post('/convert', [ConversionController::class, 'convertDocument']);
    $app->post('/compile-cover', [ConversionController::class, 'compileCover']);
    $app->post('/impose', [ConversionController::class, 'imposeDocument']);
    $app->get('/pdf/{id}', [ConversionController::class, 'getPdf']);
    
    // Routes pour les templates
    $app->get('/layouts', [TemplateController::class, 'getTemplates']);
    $app->get('/cover-variables/{cover}', [TemplateController::class, 'getCoverVariables']);
    $app->get('/preview', [TemplateController::class, 'previewTemplate']);
    
    // Routes pour les médias
    $app->post('/upload/image', [MediaController::class, 'uploadImage']);
    $app->get('/uploads/{filename}', [MediaController::class, 'getUploadedImage']);
    $app->delete('/uploads/{filename}', [MediaController::class, 'deleteImage']);
    
    // Route de migration de base de données (accessible via GET pour faciliter l'exécution)
    $app->get('/migrate', function ($request, $response) {
        try {
            // Capturer la sortie de la migration
            ob_start();
            \App\Utils\Database::migrate();
            $migrationOutput = ob_get_clean();
            
            $responseData = [
                'status' => 'success',
                'message' => 'Migration de la base de données réussie. Toutes les tables PostgreSQL ont été créées.',
                'output' => $migrationOutput,
                'tables' => ['users', 'user_templates', 'user_fonts', 'comments']
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la migration: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la migration: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Route de migration de base de données (POST également supporté)
    $app->post('/migrate', function ($request, $response) {
        try {
            // Capturer la sortie de la migration
            ob_start();
            \App\Utils\Database::migrate();
            $migrationOutput = ob_get_clean();
            
            $responseData = [
                'status' => 'success',
                'message' => 'Migration de la base de données réussie. Toutes les tables PostgreSQL ont été créées.',
                'output' => $migrationOutput,
                'tables' => ['users', 'user_templates', 'user_fonts', 'comments']
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la migration: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la migration: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Routes d'authentification
    $app->post('/auth/register', [AuthController::class, 'register']);
    $app->post('/auth/login', [AuthController::class, 'login']);
    $app->get('/auth/me', [AuthController::class, 'me']);
    
    // Routes pour les templates utilisateur
    $userTemplatesRoute = $app->get('/templates/user', [UserTemplateController::class, 'getUserTemplates']);
    $uploadTemplateRoute = $app->post('/templates/upload', [UserTemplateController::class, 'uploadTemplate']);
    $updateTemplateRoute = $app->put('/templates/{id}', [UserTemplateController::class, 'updateTemplate']);
    $deleteTemplateRoute = $app->delete('/templates/{id}', [UserTemplateController::class, 'deleteTemplate']);
    $cleanupTemplatesRoute = $app->post('/templates/cleanup', [UserTemplateController::class, 'cleanupCorruptedEntries']);
    $updateTableStructureRoute = $app->post('/templates/update-structure', [UserTemplateController::class, 'updateTableStructure']);
    $getTemplateContentRoute = $app->get('/templates/content/{id}', [UserTemplateController::class, 'getTemplateContent']);
    
    // Routes pour les polices utilisateur
    $userFontsRoute = $app->get('/fonts/user', [UserFontController::class, 'getUserFonts']);
    $uploadFontRoute = $app->post('/fonts/upload', [UserFontController::class, 'uploadFont']);
    $deleteFontRoute = $app->delete('/fonts/{filename}', [UserFontController::class, 'deleteFont']);
    $cleanupFontsRoute = $app->post('/fonts/cleanup', [UserFontController::class, 'cleanupCorruptedFonts']);
    
    // Appliquer le middleware d'authentification si fourni
    if ($authMiddleware !== null) {
        $userTemplatesRoute->add($authMiddleware);
        $uploadTemplateRoute->add($authMiddleware);
        $updateTemplateRoute->add($authMiddleware);
        $deleteTemplateRoute->add($authMiddleware);
        $cleanupTemplatesRoute->add($authMiddleware);
        $updateTableStructureRoute->add($authMiddleware);
        $getTemplateContentRoute->add($authMiddleware);
        
        $userFontsRoute->add($authMiddleware);
        $uploadFontRoute->add($authMiddleware);
        $deleteFontRoute->add($authMiddleware);
        $cleanupFontsRoute->add($authMiddleware);
    }
    
    // Route OPTIONS spécifique pour les requêtes aux uploads
    $app->options('/uploads/{filename}', function ($request, $response) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Max-Age', '86400'); // 24 heures
    });
    
    $app->get('/workspace/{path:.*}', [MediaController::class, 'getWorkspaceFile']);

    // Route pour les commentaires (accessible à tous les utilisateurs)
    $app->post('/comments', function ($request, $response) use ($authMiddleware) {
        // Vérifie si l'utilisateur est connecté
        $userId = null;
        $authHeader = $request->getHeaderLine('Authorization');
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (!empty($token)) {
            try {
                if (!isset($_ENV['JWT_SECRET'])) {
                    throw new \Exception('JWT_SECRET non défini');
                }
                $secretKey = $_ENV['JWT_SECRET'];
                $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secretKey, 'HS256'));
                $userId = $decoded->userId;
            } catch (\Exception $e) {
                // Token invalide, continuer en tant qu'anonyme
                $userId = null;
            }
        }
        
        // Si l'utilisateur n'est pas connecté, on ne peut pas enregistrer le commentaire
        if ($userId === null) {
            $responseData = [
                'status' => 'error',
                'message' => 'Vous devez être connecté pour envoyer un commentaire'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        // Récupérer les données du commentaire
        $data = $request->getParsedBody();
        
        if (!isset($data['content']) || empty(trim($data['content']))) {
            $responseData = [
                'status' => 'error',
                'message' => 'Le contenu du commentaire est requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $content = htmlspecialchars(strip_tags($data['content']));
        
        try {
            $db = new \App\Utils\Database();
            $stmt = $db->prepare('INSERT INTO comments (user_id, content, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$userId, $content]);
            
            $commentId = $db->lastInsertId();
            
            $responseData = [
                'status' => 'success',
                'message' => 'Commentaire enregistré avec succès',
                'comment' => [
                    'id' => $commentId,
                    'content' => $content,
                    'createdAt' => date('Y-m-d H:i:s')
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de l\'enregistrement du commentaire: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de l\'enregistrement du commentaire: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Routes d'administration (nécessitent d'être admin)
    $app->get('/admin/users', [AdminController::class, 'getUsers'])->add($adminMiddleware);
    $app->delete('/admin/users/{id}', [AdminController::class, 'deleteUser'])->add($adminMiddleware);
    $app->get('/admin/logs', [AdminController::class, 'getLogs'])->add($adminMiddleware);
    $app->post('/admin/clear-cache', [AdminController::class, 'clearCache'])->add($adminMiddleware);
    $app->get('/admin/comments', [AdminController::class, 'getComments'])->add($adminMiddleware);
    $app->delete('/admin/comments/{id}', [AdminController::class, 'deleteComment'])->add($adminMiddleware);
    $app->post('/admin/upload-template', [AdminController::class, 'uploadTemplate'])->add($adminMiddleware);
    
    // Nouvelles routes pour la gestion des caches et des logs
    $app->get('/admin/cache-files', [AdminController::class, 'getCacheFiles'])->add($adminMiddleware);
    $app->post('/admin/cache-files/delete', [AdminController::class, 'deleteCacheFile'])->add($adminMiddleware);
    $app->post('/admin/clear-logs', [AdminController::class, 'clearLogs'])->add($adminMiddleware);
    
    // Nouvelles routes pour la gestion des templates et polices système
    $app->get('/admin/system-templates', [AdminController::class, 'getSystemTemplates'])->add($adminMiddleware);
    $app->get('/admin/system-fonts', [AdminController::class, 'getSystemFonts'])->add($adminMiddleware);
    $app->delete('/admin/system-templates/{type}/{name}', [AdminController::class, 'deleteSystemTemplate'])->add($adminMiddleware);
    $app->delete('/admin/system-fonts/{name}', [AdminController::class, 'deleteSystemFont'])->add($adminMiddleware);
    $app->post('/admin/system-fonts/upload', [AdminController::class, 'uploadSystemFont'])->add($adminMiddleware);

    // Route pour forcer la création des tables (solution de contournement pour Dev Database)
    $app->post('/force-create-tables', function ($request, $response) {
        try {
            $pdo = Database::getConnection();
            
            // Essayer de créer les tables une par une avec gestion d'erreurs détaillée
            $tables = [
                'users' => "CREATE TABLE IF NOT EXISTS users (
                    id SERIAL PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    username VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )",
                'user_templates' => "CREATE TABLE IF NOT EXISTS user_templates (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    preview_path VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE (name, type, user_id)
                )",
                'user_fonts' => "CREATE TABLE IF NOT EXISTS user_fonts (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE (filename, user_id)
                )",
                'comments' => "CREATE TABLE IF NOT EXISTS comments (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL,
                    content TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )"
            ];
            
            $results = [];
            $errors = [];
            
            foreach ($tables as $tableName => $sql) {
                try {
                    // Essayer différentes approches
                    $approaches = [
                        "default" => $sql,
                        "temp_then_rename" => str_replace("CREATE TABLE IF NOT EXISTS $tableName", "CREATE TEMP TABLE temp_$tableName", $sql),
                        "without_constraints" => preg_replace('/,\s*FOREIGN KEY.*?CASCADE/', '', $sql)
                    ];
                    
                    $created = false;
                    foreach ($approaches as $approach => $modifiedSql) {
                        try {
                            $stmt = $pdo->prepare($modifiedSql);
                            $stmt->execute();
                            $results[$tableName] = "✅ Créée avec approche: $approach";
                            $created = true;
                            break;
                        } catch (Exception $e) {
                            $errors[$tableName][$approach] = $e->getMessage();
                        }
                    }
                    
                    if (!$created) {
                        $results[$tableName] = "❌ Échec toutes approches";
                    }
                    
                } catch (Exception $e) {
                    $errors[$tableName]['general'] = $e->getMessage();
                }
            }
            
            // Vérifier les tables existantes
            $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $response->withJson([
                'status' => 'completed',
                'results' => $results,
                'errors' => $errors,
                'existing_tables' => $existingTables,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            return $response->withJson([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ], 500);
        }
    });
}; 
