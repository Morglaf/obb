<?php
/**
 * Online Book Brew - Configuration
 * 
 * Fichier de configuration centralisé pour l'application
 */

return [
    // Paramètres de base
    'app' => [
        'name' => 'Online Book Brew',
        'version' => '1.0.0',
        'debug' => true,
        'display_errors' => true,
        'log_errors' => true,
        'error_log' => __DIR__ . '/../error.log',
        'max_execution_time' => 300, // 5 minutes
    ],
    
    // Chemins de l'application
    'paths' => [
        'workspace' => realpath(__DIR__ . '/../../workspace') ?: '/app/workspace',
        'typeset' => realpath(__DIR__ . '/../typeset') ?: '/app/typeset',
        'user_templates' => '/app/user_templates',
        'process_script' => realpath(__DIR__ . '/../../process-commands.sh') ?: '/process-commands.sh',
        'public' => realpath(__DIR__ . '/../public') ?: __DIR__ . '/../public',
        'uploads' => realpath(__DIR__ . '/../public/uploads') ?: __DIR__ . '/../public/uploads',
        'files' => realpath(__DIR__ . '/../public/files') ?: __DIR__ . '/../public/files',
        'fonts' => realpath(__DIR__ . '/../typeset/fonts') ?: '/app/typeset/fonts',
    ],
    
    // URL de l'API
    'api' => [
        'url' => $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://obb.morglaf.com',
    ],
    
    // Paramètres CORS
    'cors' => [
        'allow_origin' => '*',
        'allow_headers' => 'X-Requested-With, Content-Type, Accept, Origin, Authorization',
        'allow_methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
    ],
    
    // Paramètres d'upload
    'upload' => [
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg+xml'
        ],
        'max_size' => 5242880, // 5 MB
    ],
    
    // Paramètres Docker
    'docker' => [
        'processor_image' => 'onlinebookbrew-processor',
    ],
]; 