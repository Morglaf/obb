<?php
/**
 * Router pour serveur Morglaf
 * Gestion des routes avec nginx proxy
 */

// Routeur simple pour le serveur de développement PHP
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);

// Enlever le préfixe /api s'il existe (géré par nginx)
if (strpos($path, '/api') === 0) {
    $path = substr($path, 4);
}

// Déléguer les scripts PHP publics spéciaux directement, sans passer par Slim
if (preg_match('#^/serve-image\.php(?:/.*)?$#', $path)) {
    require_once __DIR__ . '/public/serve-image.php';
    exit; // éviter de repasser par Slim
}

if (preg_match('#^/serve-preview\.php(?:/.*)?$#', $path)) {
    require_once __DIR__ . '/public/serve-preview.php';
    exit; // éviter de repasser par Slim
}

if (preg_match('#^/delete-image\.php(?:/.*)?$#', $path)) {
    require_once __DIR__ . '/public/delete-image.php';
    exit; // éviter de repasser par Slim
}

// Si c'est une ressource statique, la servir directement
if (preg_match('/\.(js|css|png|jpg|jpeg|gif|ico|svg)$/', $path)) {
    return false; // Laisser le serveur PHP gérer les fichiers statiques
}

// Rediriger vers index.php pour toutes les autres requêtes
require_once __DIR__ . '/public/index.php'; 