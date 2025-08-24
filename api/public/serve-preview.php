<?php
/**
 * Service de fichiers pour l'application
 * Permet de servir des fichiers depuis le workspace
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Si c'est une requête OPTIONS, on s'arrête là
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Vérifier si un chemin de fichier est fourni
if (!isset($_GET['path'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Path parameter is required']);
    exit;
}

// Récupérer et sécuriser le chemin
$relativePath = $_GET['path'];

// Vérifier si la requête concerne du contenu brut
$isRawRequest = isset($_GET['raw']) && $_GET['raw'] === '1';

// Assurer que le chemin ne sort pas du répertoire workspace
if (strpos($relativePath, '..') !== false) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Invalid path']);
    exit;
}

// Construire le chemin complet - correction pour Docker
// Dans le conteneur, les volumes sont montés directement dans /app
$basePath = '/app/';
$fullPath = $basePath . $relativePath;

// Debug: afficher le chemin pour diagnostiquer
error_log("serve-preview.php: relativePath = $relativePath, fullPath = $fullPath");

// Vérifier si le fichier existe
if (!file_exists($fullPath)) {
    error_log("serve-preview.php: File not found: $fullPath");
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'File not found', 'path' => $fullPath]);
    exit;
}

// Si on veut le contenu brut du fichier
if ($isRawRequest) {
    // Vérifier que le fichier est un .tex ou un fichier texte
    $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
    if ($extension === 'tex' || $extension === 'txt' || $extension === 'md') {
        header('Content-Type: text/plain');
        echo file_get_contents($fullPath);
        exit;
    }
    
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Raw content is only available for text files']);
    exit;
}

// Pour les fichiers image, les servir normalement
$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$contentTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'pdf' => 'application/pdf'
];

// Vérifier si l'extension est supportée
if (!isset($contentTypes[$extension])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Unsupported file type']);
    exit;
}

// Servir le fichier
header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit; 