<?php
/**
 * Online Book Brew - Contrôleur média
 * 
 * Contrôleur pour gérer les uploads de médias et leur accès
 */

namespace App\Controllers;

use App\Utils\FileUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MediaController
{
    /**
     * Chemin du dossier d'uploads
     */
    private $uploadsDir;
    
    /**
     * Types MIME autorisés
     */
    private $allowedMimeTypes;
    
    /**
     * Taille maximale des fichiers
     */
    private $maxFileSize;
    
    /**
     * Constructeur
     * 
     * @param string $uploadsDir Chemin du dossier d'uploads
     * @param array $allowedMimeTypes Types MIME autorisés
     * @param int $maxFileSize Taille maximale des fichiers en octets
     */
    public function __construct($uploadsDir, $allowedMimeTypes, $maxFileSize)
    {
        $this->uploadsDir = $uploadsDir;
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->maxFileSize = $maxFileSize;
    }
    
    /**
     * Route pour uploader des images
     * POST /api/upload/image
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @return Response Réponse avec les résultats de l'upload
     */
    public function uploadImage(Request $request, Response $response)
    {
        // Ajouter les en-têtes CORS immédiatement
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

        try {
            // Démarrer la session si ce n'est pas déjà fait
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Obtenir ou générer un ID de session
            $sessionId = session_id();
            if (empty($sessionId)) {
                $sessionId = uniqid('sess_', true);
                session_id($sessionId);
            }
            
            // Nettoyer l'ID de session pour l'utilisation en tant que préfixe de fichier
            $safeSessionId = preg_replace('/[^a-z0-9_]/i', '', $sessionId);
            $shortSessionId = substr($safeSessionId, 0, 10); // Limiter la longueur
            
            // Vérifier si des fichiers ont été uploadés
            $uploadedFiles = $request->getUploadedFiles();
            
            if (empty($uploadedFiles['image'])) {
                throw new \Exception('Aucune image n\'a été envoyée');
            }
            
            $uploadedFile = $uploadedFiles['image'];
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                throw new \Exception('Erreur lors de l\'upload: ' . $uploadedFile->getError());
            }
            
            // Vérifier le type MIME
            $fileMimeType = $uploadedFile->getClientMediaType();
            
            if (!in_array($fileMimeType, $this->allowedMimeTypes)) {
                throw new \Exception('Type de fichier non autorisé. Types acceptés: JPG, PNG, GIF, SVG');
            }
            
            // Vérifier la taille du fichier
            if ($uploadedFile->getSize() > $this->maxFileSize) {
                throw new \Exception(sprintf(
                    'Fichier trop volumineux. Taille maximale: %s MB',
                    $this->maxFileSize / (1024 * 1024)
                ));
            }
            
            // Créer un nom de fichier unique avec l'extension originale et l'ID de session
            $filename = $shortSessionId . '_' . uniqid() . '_' . $uploadedFile->getClientFilename();
            
            // Créer le dossier d'upload s'il n'existe pas
            if (!FileUtils::ensureDirectoryExists($this->uploadsDir)) {
                throw new \Exception("Impossible de créer le dossier d'upload");
            }
            
            // Sauvegarder l'association entre le nom d'origine et le nom unique dans la session
            if (!isset($_SESSION['uploaded_files'])) {
                $_SESSION['uploaded_files'] = [];
            }
            
            $_SESSION['uploaded_files'][$uploadedFile->getClientFilename()] = $filename;
            
            // Sauvegarder le fichier
            $uploadedFile->moveTo($this->uploadsDir . DIRECTORY_SEPARATOR . $filename);
            
            // Préparer la réponse
            $fileUrl = '/api/uploads/' . urlencode($filename);
            
            $responseData = [
                'status' => 'success',
                'message' => 'Image téléversée avec succès',
                'filename' => $filename,
                'original_filename' => $uploadedFile->getClientFilename(),
                'url' => $fileUrl,
                'session_id' => $shortSessionId
            ];
            
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($responseData));
            
            return $response;
        } catch (\Exception $e) {
            $responseData = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            
            $response = $response->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($responseData));
            
            return $response;
        }
    }
    
    /**
     * Route pour servir les images uploadées
     * GET /api/uploads/{filename}
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @param array $args Arguments de la route
     * @return Response Réponse avec l'image
     */
    public function getUploadedImage(Request $request, Response $response, array $args)
    {
        // Ajouter les en-têtes CORS immédiatement
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            
        $filename = $args['filename'];
        
        // Décoder le nom de fichier si nécessaire
        $filename = urldecode($filename);
        
        // Nettoyer le nom de fichier
        $filename = basename($filename);
        
        // Chemin du fichier image
        $filePath = $this->uploadsDir . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($filePath)) {
            $response = $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*');
            $response->getBody()->write(json_encode(['error' => 'Image non trouvée']));
            return $response;
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
        
        // Servir le fichier image
        $response = $response->withHeader('Content-Type', $mimeType);
        $response = $response->withHeader('Content-Disposition', 'inline; filename="' . basename($filePath) . '"');
        $response = $response->withHeader('Content-Length', filesize($filePath));
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        $response->getBody()->write(file_get_contents($filePath));
        
        return $response;
    }
    
    /**
     * Route pour servir les fichiers statiques du dossier workspace
     * GET /workspace/{path:.*}
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @param array $args Arguments de la route
     * @return Response Réponse avec le fichier statique
     */
    public function getWorkspaceFile(Request $request, Response $response, array $args)
    {
        // Ajouter les en-têtes CORS immédiatement
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            
        $path = $args['path'];
        $workspaceDir = $GLOBALS['config']['paths']['workspace'];
        $filePath = realpath($workspaceDir . DIRECTORY_SEPARATOR . $path);
        
        // Vérifier que le chemin est bien dans le dossier workspace (sécurité)
        if (!$filePath || strpos($filePath, $workspaceDir) !== 0 || !file_exists($filePath)) {
            return $response->withStatus(404);
        }
        
        $response = $response->withHeader('Content-Type', mime_content_type($filePath));
        $response = $response->withHeader('Content-Disposition', 'inline; filename="' . basename($filePath) . '"');
        $response->getBody()->write(file_get_contents($filePath));
        
        return $response;
    }
    
    /**
     * Route pour supprimer une image téléversée
     * DELETE /api/uploads/{filename}
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @param array $args Arguments de la route
     * @return Response Réponse de suppression
     */
    public function deleteImage(Request $request, Response $response, array $args)
    {
        // Ajouter les en-têtes CORS immédiatement pour toutes les réponses
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            
        // Requête OPTIONS préliminaire
        if ($request->getMethod() === 'OPTIONS') {
            return $response;
        }
        
        // Démarrer la session si ce n'est pas déjà fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $filename = $args['filename'];
        
        // Décoder le nom de fichier
        $filename = urldecode($filename);
        
        // Nettoyer le nom de fichier
        $filename = basename($filename);
        
        // Chemin du fichier image
        $filePath = $this->uploadsDir . DIRECTORY_SEPARATOR . $filename;
        
        // Vérifier que le fichier existe
        if (!file_exists($filePath)) {
            $responseData = [
                'status' => 'error',
                'message' => 'Image non trouvée'
            ];
            
            $response = $response->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
            
            $response->getBody()->write(json_encode($responseData));
            return $response;
        }
        
        // Tenter de supprimer le fichier
        $deleted = unlink($filePath);
        
        if ($deleted) {
            // Si le fichier a été supprimé avec succès
            
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
            
            $responseData = [
                'status' => 'success',
                'message' => 'Image supprimée avec succès'
            ];
            
            $response = $response
                ->withHeader('Content-Type', 'application/json');
            
            $response->getBody()->write(json_encode($responseData));
            return $response;
        } else {
            // Si la suppression a échoué
            $responseData = [
                'status' => 'error',
                'message' => 'Impossible de supprimer l\'image'
            ];
            
            $response = $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
            
            $response->getBody()->write(json_encode($responseData));
            return $response;
        }
    }
} 