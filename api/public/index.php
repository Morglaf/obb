<?php
/**
 * Online Book Brew - Point d'entrée API
 * 
 * Ce fichier est le point d'entrée de l'API qui gère la conversion de documents
 * Markdown en PDF via LaTeX.
 */

require __DIR__ . '/../vendor/autoload.php';



// Gestionnaire d'erreurs global
set_error_handler(function($severity, $message, $file, $line) {
    error_log("ERREUR PHP: $message dans $file ligne $line");
    return true;
});

set_exception_handler(function($exception) {
    error_log("EXCEPTION: " . $exception->getMessage());
    error_log("Stack trace: " . $exception->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur interne du serveur',
        'debug' => [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]
    ]);
});

// Charger les variables d'environnement depuis le fichier .env si il existe
// En production (serveur Morglaf), les variables sont définies via l'environnement système
$envPath = __DIR__ . '/..';
$envFile = $envPath . '/.env';

// Détection de l'environnement de production (serveur Morglaf)
$isProduction = !empty($_ENV['DATABASE_URL']) || !empty(getenv('DATABASE_URL'));

if (!$isProduction && file_exists($envFile) && is_readable($envFile)) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable($envPath);
        $dotenv->load();
        error_log('Info: Loaded .env file for local development');
    } catch (Exception $e) {
        error_log('Warning: Could not load .env file: ' . $e->getMessage());
    }
} else {
    // En production, les variables d'environnement sont déjà définies par le serveur
    error_log('Info: Production environment detected, using system environment variables - v3');
}

use DI\Container;
use Slim\Factory\AppFactory;
use App\Services\ConversionService;
use App\Services\TemplateService;
use App\Controllers\ConversionController;
use App\Controllers\TemplateController;
use App\Controllers\MediaController;
use App\Controllers\AuthController;
use App\Controllers\UserTemplateController;
use App\Middleware\AuthMiddleware;

// Charger la configuration
$config = require __DIR__ . '/../config/app.php';

// Variable globale de configuration pour utilisation facile dans les contrôleurs
$GLOBALS['config'] = $config;

// Activer l'affichage des erreurs selon la configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// Augmenter la limite de temps d'exécution pour les opérations PDF lourdes
ini_set('max_execution_time', $config['app']['max_execution_time']);

// Créer le conteneur DI
$container = new Container();

// Définir les services dans le conteneur
$container->set(ConversionService::class, function () use ($config) {
    return new ConversionService($config);
});

$container->set(TemplateService::class, function () use ($config) {
    return new TemplateService($config['paths']['typeset']);
});

$container->set(ConversionController::class, function () use ($container, $config) {
    return new ConversionController(
        $container->get(ConversionService::class),
        $config['api']['url']
    );
});

$container->set(TemplateController::class, function () use ($container) {
    return new TemplateController(
        $container->get(TemplateService::class)
    );
});

$container->set(MediaController::class, function () use ($config) {
    return new MediaController(
        $config['paths']['uploads'],
        $config['upload']['allowed_mime_types'],
        $config['upload']['max_size']
    );
});

$container->set(AuthController::class, function () {
    return new AuthController();
});

$container->set(UserTemplateController::class, function () {
    return new UserTemplateController();
});

// Créer l'application Slim avec le conteneur
AppFactory::setContainer($container);
$app = AppFactory::create();

// Ajouter le middleware CORS
$app->add(function ($request, $handler) {
    $response = $request->getMethod() === 'OPTIONS'
        ? new \Slim\Psr7\Response()
        : $handler->handle($request);
    
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Max-Age', '86400'); // 24 heures
});



// Middleware pour parser le JSON
$app->addBodyParsingMiddleware();

// Ajouter explicitement le middleware de routing
$app->addRoutingMiddleware();

// Créer l'instance du middleware d'authentification
$authMiddleware = new AuthMiddleware();

// Route de test directe pour debug (SANS préfixe /api)
$app->get('/debug-test', function ($request, $response) {
    $data = [
        'status' => 'success',
        'message' => 'Routes fonctionnent sans préfixe /api!',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Route de test AVEC préfixe /api
$app->get('/api/debug-test', function ($request, $response) {
    $data = [
        'status' => 'success',
        'message' => 'Routes fonctionnent AVEC préfixe /api!',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Route pour explorer la structure des répertoires
$app->get('/explore/{path:.*}', function ($request, $response, $args) {
    $path = '/' . $args['path'];
    $data = [
        'requested_path' => $path,
        'exists' => file_exists($path),
        'is_dir' => is_dir($path),
        'contents' => []
    ];
    
    if (is_dir($path)) {
        $contents = scandir($path);
        foreach ($contents as $item) {
            if ($item !== '.' && $item !== '..') {
                $fullPath = $path . '/' . $item;
                $data['contents'][$item] = [
                    'is_dir' => is_dir($fullPath),
                    'size' => is_file($fullPath) ? filesize($fullPath) : null
                ];
            }
        }
    }
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Ajouter les routes définies dans le fichier de routes
// et appliquer le middleware d'authentification dans ce fichier
try {
    $routes = require __DIR__ . '/../routes/api.php';
    $routes($app, $authMiddleware); // Passer le middleware au fichier de routes
    error_log('Info: Routes loaded successfully');
} catch (\Exception $e) {
    error_log('Error loading routes: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
}

// Exécuter l'application
$app->run();

 