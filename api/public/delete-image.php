<?php
/**
 * Script simple pour supprimer une image
 * Ce script est conçu pour éviter les problèmes CORS
 */

// Durcir la gestion des erreurs pour éviter des 500 silencieux
set_error_handler(function($severity, $message, $file, $line) {
    error_log("[delete-image] PHP ERROR: $message in $file:$line");
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
});

set_exception_handler(function($e) {
    error_log('[delete-image] EXCEPTION: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
});

// Permettre les requêtes CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Gérer les requêtes OPTIONS préliminaires
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger la configuration
// Charger la configuration de façon résiliente
$config = require __DIR__ . '/../config/app.php';
$uploadsDir = isset($config['paths']['uploads']) ? $config['paths']['uploads'] : (__DIR__ . '/uploads');

// Récupérer le nom du fichier à partir de l'URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
// Supporte /api/delete-image.php/<fichier> et /delete-image.php/<fichier>
if (strpos($path, '/api/delete-image.php/') === 0) {
    $path = substr($path, strlen('/api/delete-image.php/'));
} elseif (strpos($path, '/delete-image.php/') === 0) {
    $path = substr($path, strlen('/delete-image.php/'));
}
$filename = urldecode($path);

// Vérifier que le nom de fichier est sécurisé
$filename = basename($filename);

// Chemin complet du fichier
$filePath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;

// Vérifier si le fichier existe
if (!file_exists($filePath)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Fichier non trouvé'
    ]);
    exit;
}

// Tenter de supprimer le fichier
if (unlink($filePath)) {
    // Démarrer la session pour mettre à jour les entrées de session si nécessaire
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Mettre à jour également la session
    if (isset($_SESSION['uploaded_files'])) {
        // Rechercher et supprimer l'entrée dans le tableau des fichiers uploadés
        foreach ($_SESSION['uploaded_files'] as $originalName => $storedName) {
            if ($storedName === $filename) {
                unset($_SESSION['uploaded_files'][$originalName]);
                break;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Image supprimée avec succès'
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Impossible de supprimer l\'image'
    ]);
} 