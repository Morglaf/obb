<?php
/**
 * Script simple pour servir des images
 * Ce script est conçu pour éviter les problèmes CORS
 */

// Gestion stricte des erreurs pour renvoyer un JSON exploitable
set_error_handler(function($severity, $message, $file, $line) {
    error_log("[serve-image] PHP ERROR: $message in $file:$line");
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
});

set_exception_handler(function($e) {
    error_log('[serve-image] EXCEPTION: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type', 'application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
});

// Permettre les requêtes CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Gérer les requêtes OPTIONS préliminaires
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Charger la configuration
$config = require __DIR__ . '/../config/app.php';
$uploadsDir = $config['paths']['uploads'];

// Récupérer le nom du fichier à partir de l'URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
// Supporter /api/serve-image.php/<fichier> et /serve-image.php/<fichier>
if (strpos($path, '/api/serve-image.php/') === 0) {
    $path = substr($path, strlen('/api/serve-image.php/'));
} elseif (strpos($path, '/serve-image.php/') === 0) {
    $path = substr($path, strlen('/serve-image.php/'));
}
$filename = urldecode($path);

// Vérifier que le nom de fichier est sécurisé
$filename = basename($filename);

// Chemin complet du fichier
$filePath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
error_log('[serve-image] Trying to serve: ' . $filePath);

// Vérifier si le fichier existe
if (!file_exists($filePath)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Image non trouvée'
    ]);
    exit;
}

// Déterminer le type MIME par l'extension
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml'
];

$mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';

// Servir le fichier image avec les bons en-têtes
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath); 