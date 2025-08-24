<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\Database;
use Firebase\JWT\JWT;

class AdminController
{
    /**
     * Récupère tous les utilisateurs (admin seulement)
     */
    public function getUsers(Request $request, Response $response): Response
    {
        try {
            $db = new Database();
            $stmt = $db->prepare('SELECT id, username, email, created_at FROM users ORDER BY id ASC');
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            $responseData = [
                'status' => 'success',
                'users' => array_map(function($user) {
                    return [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'createdAt' => $user['created_at']
                    ];
                }, $users)
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des utilisateurs: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des utilisateurs: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Supprime un utilisateur (admin seulement)
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['id'];
        
        // Ne pas supprimer l'admin (id = 1)
        if ($userId === 1) {
            $responseData = [
                'status' => 'error',
                'message' => 'L\'utilisateur administrateur ne peut pas être supprimé'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        try {
            $db = new Database();
            
            // Vérifier si l'utilisateur existe
            $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Utilisateur non trouvé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Supprimer l'utilisateur
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            
            // Supprimer également les templates et polices associés
            // TODO: Implémenter la suppression des templates et polices de l'utilisateur
            
            $responseData = [
                'status' => 'success',
                'message' => 'Utilisateur supprimé avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de l\'utilisateur: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Récupère les logs du système (admin seulement)
     */
    public function getLogs(Request $request, Response $response): Response
    {
        try {
            // Chemins possibles du fichier de logs
            $candidates = [];
            // 1) Valeur configurée par PHP
            $iniLog = ini_get('error_log');
            if (!empty($iniLog)) {
                $candidates[] = $iniLog;
            }
            // 2) Log explicite configuré dans index.php
            $candidates[] = '/tmp/php_errors.log';
            // 3) Ancien fallback local
            $candidates[] = __DIR__ . '/../../error.log';

            $logPath = null;
            foreach ($candidates as $candidate) {
                if (is_string($candidate) && $candidate !== '' && file_exists($candidate) && is_readable($candidate)) {
                    $logPath = $candidate;
                    break;
                }
            }

            $logs = [];
            if ($logPath) {
                $content = @file_get_contents($logPath);
                if ($content !== false) {
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        if (trim($line) === '') { continue; }
                        
                        // Formats possibles: "[timestamp] message" ou plain text
                        if (preg_match('/\[(.*?)\]\s+(.*)/', $line, $m)) {
                            $timestamp = $m[1];
                            $message = $m[2];
                        } else {
                            $timestamp = date('Y-m-d H:i:s');
                            $message = $line;
                        }

                        $level = 'INFO';
                        if (stripos($message, 'fatal') !== false || stripos($message, 'error') !== false) {
                            $level = 'ERROR';
                        } elseif (stripos($message, 'warn') !== false) {
                            $level = 'WARNING';
                        }

                        $logs[] = [
                            'timestamp' => $timestamp,
                            'level' => $level,
                            'message' => $message,
                        ];
                    }
                }
            }

            // Inverser pour afficher les plus récents d'abord
            $logs = array_reverse($logs);

            $responseData = [
                'status' => 'success',
                'path' => $logPath,
                'logs' => $logs,
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des logs: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des logs: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Vide les caches (admin seulement)
     */
    public function clearCache(Request $request, Response $response): Response
    {
        try {
            // Répertoires à vider
            $cacheDirs = [
                __DIR__ . '/../../../workspace/cache/',
                __DIR__ . '/../../../.next/cache/'
            ];
            
            $deleted = 0;
            
            // Vider les répertoires de cache
            foreach ($cacheDirs as $cacheDir) {
                if (is_dir($cacheDir)) {
                    $deleted += $this->emptyDirectory($cacheDir);
                }
            }
            
            // Vider le répertoire des fichiers publics (PDFs générés)
            $filesDir = __DIR__ . '/../../public/files/';
            if (is_dir($filesDir)) {
                $deleted += $this->emptyDirectory($filesDir);
            }
            
            // Vider les fichiers temporaires dans le workspace
            $workspaceDir = __DIR__ . '/../../../workspace/';
            if (is_dir($workspaceDir)) {
                $oneHourAgo = time() - 3600; // Fichiers de plus d'une heure
                $deleted += $this->cleanTempFiles($workspaceDir, $oneHourAgo);
            }
            
            $responseData = [
                'status' => 'success',
                'message' => "Cache vidé avec succès. $deleted fichiers supprimés."
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors du vidage du cache: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors du vidage du cache: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Fonction utilitaire pour vider un répertoire (mais pas le supprimer)
     * @param string $dir Chemin du répertoire à vider
     * @return int Nombre de fichiers supprimés
     */
    private function emptyDirectory(string $dir): int
    {
        $deleted = 0;
        
        // Liste des dossiers à préserver (ne pas vider leur contenu)
        $preserveDirs = [
            'fonts', 'user_fonts', 'user_templates'
        ];
        
        // Vérifier si le dossier est dans la liste des dossiers à préserver
        $dirName = basename($dir);
        if (in_array($dirName, $preserveDirs)) {
            return 0;
        }
        
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                // Vérifier si le dossier parent est dans la liste des dossiers à préserver
                $parentDir = basename(dirname($file->getRealPath()));
                if (in_array($parentDir, $preserveDirs)) {
                    continue;
                }
                
                if ($file->isDir()) {
                    // Si c'est un dossier, vérifier s'il faut le préserver
                    if (!in_array($file->getFilename(), $preserveDirs)) {
                        rmdir($file->getRealPath());
                    }
                } else {
                    unlink($file->getRealPath());
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Nettoie les fichiers temporaires
     * @param string $dir Chemin du répertoire
     * @param int $olderThan Timestamp, supprime les fichiers plus anciens que cette date
     * @return int Nombre de fichiers supprimés
     */
    private function cleanTempFiles(string $dir, int $olderThan): int
    {
        $deleted = 0;
        
        // Liste des dossiers à préserver (ne pas supprimer leurs fichiers)
        $preserveDirs = [
            'fonts', 'user_fonts', 'user_templates', 'cache'
        ];
        
        // Extensions et préfixes à préserver
        $preserveExtensions = ['.tex', '.pdf', '.png', '.jpg', '.jpeg', '.svg', '.ttf', '.otf'];
        
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                // Ne pas traiter les dossiers
                if ($file->isDir()) {
                    continue;
                }
                
                $parentDir = basename(dirname($file->getRealPath()));
                $filename = $file->getFilename();
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                
                // Vérifier si le dossier parent est dans la liste des dossiers à préserver
                if (in_array($parentDir, $preserveDirs)) {
                    continue;
                }
                
                // Préserver les fichiers avec ces extensions
                if (in_array('.' . $extension, $preserveExtensions)) {
                    continue;
                }
                
                // Vérifier si c'est un fichier temporaire (impose_*, cover_*, etc.)
                $isTempFile = (
                    strpos($filename, 'impose_') === 0 || 
                    strpos($filename, 'cover_') === 0 || 
                    strpos($filename, 'temp_') === 0 || 
                    strpos($filename, 'md_') === 0
                );
                
                // Supprimer uniquement les fichiers temporaires plus anciens que $olderThan
                if ($isTempFile && filemtime($file->getRealPath()) < $olderThan) {
                    unlink($file->getRealPath());
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Liste les fichiers de cache
     */
    public function getCacheFiles(Request $request, Response $response): Response
    {
        try {
            $cacheFolders = [];
            $cacheFiles = [];
            
            // Dossiers à préserver (ne pas lister ni supprimer)
            $preserveDirs = ['fonts', 'user_fonts', 'user_templates', 'cache'];
            
            // Répertoires à scanner
            $workspaceDir = __DIR__ . '/../../../workspace/';
            $publicFilesDir = __DIR__ . '/../../public/files/';
            $nextCacheDir = __DIR__ . '/../../../.next/cache/';
            
            // Scanner le workspace pour les dossiers
            if (is_dir($workspaceDir)) {
                $folders = scandir($workspaceDir);
                
                foreach ($folders as $folder) {
                    // Ignorer . et .. et les dossiers à préserver
                    if ($folder === '.' || $folder === '..' || in_array($folder, $preserveDirs)) {
                        continue;
                    }
                    
                    $folderPath = $workspaceDir . $folder;
                    
                    if (is_dir($folderPath)) {
                        // C'est un dossier, ajouter à la liste des dossiers
                        $folderSize = $this->calculateDirectorySize($folderPath);
                        $cacheFolders[] = [
                            'type' => 'directory',
                            'name' => $folder,
                            'path' => $folderPath,
                            'relativePath' => '/workspace/' . $folder,
                            'size' => $folderSize,
                            'modified' => date('Y-m-d H:i:s', filemtime($folderPath)),
                            'age' => time() - filemtime($folderPath),
                            'fileCount' => $this->countFiles($folderPath)
                        ];
                    } else {
                        // C'est un fichier à la racine du workspace
                        $cacheFiles[] = [
                            'type' => 'file',
                            'name' => $folder,
                            'path' => $folderPath,
                            'relativePath' => '/workspace/' . $folder,
                            'size' => filesize($folderPath),
                            'modified' => date('Y-m-d H:i:s', filemtime($folderPath)),
                            'age' => time() - filemtime($folderPath)
                        ];
                    }
                }
            }
            
            // Scanner les fichiers publics
            if (is_dir($publicFilesDir)) {
                $files = scandir($publicFilesDir);
                
                foreach ($files as $file) {
                    // Ignorer . et ..
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    
                    $filePath = $publicFilesDir . $file;
                    
                    if (is_file($filePath)) {
                        // C'est un fichier, ajouter à la liste des fichiers
                        $cacheFiles[] = [
                            'type' => 'file',
                            'name' => $file,
                            'path' => $filePath,
                            'relativePath' => '/api/public/files/' . $file,
                            'size' => filesize($filePath),
                            'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                            'age' => time() - filemtime($filePath)
                        ];
                    }
                }
            }
            
            // Scanner le cache Next.js
            if (is_dir($nextCacheDir)) {
                $folderSize = $this->calculateDirectorySize($nextCacheDir);
                $cacheFolders[] = [
                    'type' => 'directory',
                    'name' => '.next cache',
                    'path' => $nextCacheDir,
                    'relativePath' => '/.next/cache',
                    'size' => $folderSize,
                    'modified' => date('Y-m-d H:i:s', filemtime($nextCacheDir)),
                    'age' => time() - filemtime($nextCacheDir),
                    'fileCount' => $this->countFiles($nextCacheDir)
                ];
            }
            
            // Fusionner et trier les résultats (dossiers d'abord, puis fichiers)
            $results = array_merge($cacheFolders, $cacheFiles);
            
            // Trier par date de modification (plus récent en premier)
            usort($results, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
            
            $responseData = [
                'status' => 'success',
                'cacheFiles' => $results
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des fichiers de cache: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des fichiers de cache: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Calcule la taille d'un répertoire
     * @param string $dir Chemin du répertoire
     * @return int Taille en octets
     */
    private function calculateDirectorySize(string $dir): int
    {
        $size = 0;
        
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        }
        
        return $size;
    }
    
    /**
     * Compte le nombre de fichiers dans un répertoire
     * @param string $dir Chemin du répertoire
     * @return int Nombre de fichiers
     */
    private function countFiles(string $dir): int
    {
        $count = 0;
        
        if (is_dir($dir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Supprimer un fichier ou dossier de cache spécifique
     */
    public function deleteCacheFile(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $path = $data['path'] ?? '';
        
        // Log pour le débogage
        error_log('Tentative de suppression du fichier: ' . $path);
        
        if (empty($path)) {
            $responseData = [
                'status' => 'error',
                'message' => 'Chemin du fichier requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Fichiers dans public/files
        if (strpos($path, '/api/public/files/') === 0) {
            $filename = basename($path);
            $fullPath = __DIR__ . '/../../public/files/' . $filename;
            
            error_log('Suppression fichier dans public/files: ' . $fullPath);
            
            if (!file_exists($fullPath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Fichier non trouvé: ' . $fullPath
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            if (!is_file($fullPath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Le chemin n\'est pas un fichier: ' . $fullPath
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            try {
                if (unlink($fullPath)) {
                    $responseData = [
                        'status' => 'success',
                        'message' => 'Fichier supprimé avec succès'
                    ];
                    $response->getBody()->write(json_encode($responseData));
                    return $response->withHeader('Content-Type', 'application/json');
                } else {
                    throw new \Exception("Impossible de supprimer le fichier");
                }
            } catch (\Exception $e) {
                error_log('Erreur lors de la suppression: ' . $e->getMessage());
                $responseData = [
                    'status' => 'error',
                    'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        }
        
        // Ignorer complètement les chemins .next pour l'instant
        if (strpos($path, '/.next/') !== false) {
            $responseData = [
                'status' => 'success',
                'message' => 'Opération ignorée pour les fichiers .next'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
        }
        
        // Fichiers dans workspace (sauf dossiers protégés)
        if (strpos($path, '/workspace/') === 0) {
            $fullPath = __DIR__ . '/../../../' . $path;
            
            error_log('Suppression fichier dans workspace: ' . $fullPath);
            
            // Dossiers à préserver
            $preserveDirs = ['fonts', 'user_fonts', 'user_templates', 'cache'];
            
            foreach ($preserveDirs as $preserveDir) {
                if (strpos($path, '/workspace/' . $preserveDir) === 0) {
                    $responseData = [
                        'status' => 'error',
                        'message' => 'Impossible de supprimer un dossier protégé: ' . $preserveDir
                    ];
                    $response->getBody()->write(json_encode($responseData));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
                }
            }
            
            if (!file_exists($fullPath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Fichier ou dossier non trouvé: ' . $fullPath
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            try {
                if (is_dir($fullPath)) {
                    $deleted = $this->deleteDirectory($fullPath);
                    if (!$deleted) {
                        throw new \Exception("Impossible de supprimer le dossier");
                    }
                } else {
                    if (!unlink($fullPath)) {
                        throw new \Exception("Impossible de supprimer le fichier");
                    }
                }
                
                $responseData = [
                    'status' => 'success',
                    'message' => 'Fichier ou dossier supprimé avec succès'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json');
            } catch (\Exception $e) {
                error_log('Erreur lors de la suppression: ' . $e->getMessage());
                $responseData = [
                    'status' => 'error',
                    'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
        }
        
        // Chemin non autorisé
        $responseData = [
            'status' => 'error',
            'message' => 'Chemin non autorisé: ' . $path
        ];
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }
    
    /**
     * Supprime récursivement un répertoire et son contenu
     * @param string $dir Chemin du répertoire à supprimer
     * @return bool Succès ou échec
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Vide le fichier de log
     */
    public function clearLogs(Request $request, Response $response): Response
    {
        try {
            $logFile = __DIR__ . '/../../error.log';
            
            if (file_exists($logFile)) {
                // Vider le contenu du fichier plutôt que de le supprimer
                file_put_contents($logFile, '');
            }
            
            $responseData = [
                'status' => 'success',
                'message' => 'Logs vidés avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors du vidage des logs: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors du vidage des logs: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Récupère les commentaires (admin seulement)
     */
    public function getComments(Request $request, Response $response): Response
    {
        try {
            $db = new Database();
            $stmt = $db->prepare('SELECT c.id, c.user_id, u.username, c.content, c.created_at 
                                  FROM comments c 
                                  JOIN users u ON c.user_id = u.id 
                                  ORDER BY c.created_at DESC');
            $stmt->execute();
            $comments = $stmt->fetchAll();
            
            $responseData = [
                'status' => 'success',
                'comments' => array_map(function($comment) {
                    return [
                        'id' => $comment['id'],
                        'userId' => $comment['user_id'],
                        'username' => $comment['username'],
                        'content' => $comment['content'],
                        'createdAt' => $comment['created_at']
                    ];
                }, $comments)
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des commentaires: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des commentaires: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Supprime un commentaire (admin seulement)
     */
    public function deleteComment(Request $request, Response $response, array $args): Response
    {
        $commentId = (int) $args['id'];
        
        try {
            $db = new Database();
            
            // Vérifier si le commentaire existe
            $stmt = $db->prepare('SELECT id FROM comments WHERE id = ?');
            $stmt->execute([$commentId]);
            if (!$stmt->fetch()) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Commentaire non trouvé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Supprimer le commentaire
            $stmt = $db->prepare('DELETE FROM comments WHERE id = ?');
            $stmt->execute([$commentId]);
            
            $responseData = [
                'status' => 'success',
                'message' => 'Commentaire supprimé avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la suppression du commentaire: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la suppression du commentaire: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Upload un nouveau template de base
     */
    public function uploadTemplate(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $data = $request->getParsedBody();
        
        if (!isset($uploadedFiles['file']) || !isset($uploadedFiles['preview'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Fichier template et prévisualisation requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        if (!isset($data['name']) || !isset($data['type'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Nom et type de template requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $templateFile = $uploadedFiles['file'];
        $previewFile = $uploadedFiles['preview'];
        $templateName = htmlspecialchars(strip_tags($data['name']));
        $templateType = $data['type'];
        
        // Vérifier le type de template
        if (!in_array($templateType, ['layout', 'cover', 'impose'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Type de template invalide'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Générer les noms de fichiers finaux
        $templateFilename = $templateName . '.tex';
        $previewFilename = $templateName . '.png';
        
        try {
            // Définir le répertoire de destination selon le type
            $typesetDir = __DIR__ . '/../../../typeset';
            $templateDir = '';
            
            switch ($templateType) {
                case 'layout':
                    $templateDir = $typesetDir . '/layout/';
                    break;
                case 'cover':
                    $templateDir = $typesetDir . '/cover/';
                    break;
                case 'impose':
                    $templateDir = $typesetDir . '/impose/';
                    break;
            }
            
            // Vérifier que les répertoires existent, sinon les créer
            if (!is_dir($templateDir)) {
                mkdir($templateDir, 0777, true);
            }
            
            // Déplacer les fichiers
            $templateFile->moveTo($templateDir . $templateFilename);
            $previewFile->moveTo($templateDir . $previewFilename);
            
            $responseData = [
                'status' => 'success',
                'message' => 'Template ajouté avec succès',
                'template' => [
                    'name' => $templateName,
                    'type' => $templateType,
                    'path' => $templateDir . $templateFilename,
                    'previewPath' => $templateDir . $previewFilename
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de l\'upload du template: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de l\'upload du template: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Liste tous les templates systèmes
     */
    public function getSystemTemplates(Request $request, Response $response): Response
    {
        try {
            $typesetDir = __DIR__ . '/../../../typeset';
            
            // Récupérer les templates de layout
            $layouts = $this->getFilesInDirectory($typesetDir . '/layout', '/\.(tex)$/i');
            
            // Récupérer les templates de couverture
            $covers = $this->getFilesInDirectory($typesetDir . '/cover', '/\.(tex)$/i');
            
            // Récupérer les templates d'imposition
            $imposes = $this->getFilesInDirectory($typesetDir . '/impose', '/\.(tex)$/i');
            
            $responseData = [
                'status' => 'success',
                'templates' => [
                    'layouts' => $layouts,
                    'covers' => $covers,
                    'imposes' => $imposes
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des templates: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des templates: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Liste toutes les polices système
     */
    public function getSystemFonts(Request $request, Response $response): Response
    {
        try {
            $fontsDir = __DIR__ . '/../typeset/fonts';
            
            // Récupérer les polices
            $fonts = $this->getFilesInDirectory($fontsDir, '/\.(ttf|otf)$/i');
            
            $responseData = [
                'status' => 'success',
                'fonts' => $fonts
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération des polices: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des polices: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Fonction utilitaire pour récupérer les fichiers d'un répertoire
     * @param string $dir Chemin du répertoire
     * @param string $pattern Regex pour filtrer les fichiers
     * @return array Liste des fichiers
     */
    private function getFilesInDirectory(string $dir, string $pattern): array
    {
        $files = [];
        
        if (!is_dir($dir)) {
            return $files;
        }
        
        $dirContents = scandir($dir);
        
        foreach ($dirContents as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $dir . '/' . $file;
            
            if (is_file($filePath) && preg_match($pattern, $file)) {
                $files[] = [
                    'name' => $file,
                    'path' => $filePath,
                    'size' => filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath))
                ];
            }
        }
        
        return $files;
    }
    
    /**
     * Supprime un template système
     */
    public function deleteSystemTemplate(Request $request, Response $response, array $args): Response
    {
        $templateType = $args['type'] ?? '';
        $templateName = $args['name'] ?? '';
        
        if (empty($templateType) || empty($templateName)) {
            $responseData = [
                'status' => 'error',
                'message' => 'Type et nom du template requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Vérifier le type de template
        if (!in_array($templateType, ['layout', 'cover', 'impose'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Type de template invalide'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $typesetDir = __DIR__ . '/../../../typeset';
            $templateDir = '';
            
            switch ($templateType) {
                case 'layout':
                    $templateDir = $typesetDir . '/layout/';
                    break;
                case 'cover':
                    $templateDir = $typesetDir . '/cover/';
                    break;
                case 'impose':
                    $templateDir = $typesetDir . '/impose/';
                    break;
            }
            
            $templateFile = $templateDir . $templateName;
            $previewFile = $templateDir . str_replace('.tex', '.png', $templateName);
            
            // Vérifier si le fichier existe
            if (!file_exists($templateFile)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Template non trouvé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Supprimer le fichier de template
            unlink($templateFile);
            
            // Supprimer le fichier de prévisualisation s'il existe
            if (file_exists($previewFile)) {
                unlink($previewFile);
            }
            
            $responseData = [
                'status' => 'success',
                'message' => 'Template supprimé avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la suppression du template: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la suppression du template: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Supprime une police système
     */
    public function deleteSystemFont(Request $request, Response $response, array $args): Response
    {
        $fontName = $args['name'] ?? '';
        
        if (empty($fontName)) {
            $responseData = [
                'status' => 'error',
                'message' => 'Nom de la police requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $fontsDir = __DIR__ . '/../typeset/fonts';
            $fontFile = $fontsDir . '/' . $fontName;
            
            // Vérifier si le fichier existe
            if (!file_exists($fontFile)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Police non trouvée'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Supprimer le fichier de police
            unlink($fontFile);
            
            $responseData = [
                'status' => 'success',
                'message' => 'Police supprimée avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la suppression de la police: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de la police: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Upload une nouvelle police système
     */
    public function uploadSystemFont(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles['font'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Fichier de police requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $fontFile = $uploadedFiles['font'];
        $fontName = $fontFile->getClientFilename();
        
        // Vérifier l'extension du fichier
        $extension = pathinfo($fontName, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), ['ttf', 'otf'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Format de police non supporté. Utilisez .ttf ou .otf'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $fontsDir = __DIR__ . '/../typeset/fonts';
            
            // Vérifier que le répertoire existe, sinon le créer
            if (!is_dir($fontsDir)) {
                mkdir($fontsDir, 0777, true);
            }
            
            // Déplacer le fichier
            $fontFile->moveTo($fontsDir . '/' . $fontName);
            
            $responseData = [
                'status' => 'success',
                'message' => 'Police ajoutée avec succès',
                'font' => [
                    'name' => $fontName,
                    'path' => $fontsDir . '/' . $fontName,
                    'size' => filesize($fontsDir . '/' . $fontName),
                    'modified' => date('Y-m-d H:i:s')
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de l\'upload de la police: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de l\'upload de la police: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
} 