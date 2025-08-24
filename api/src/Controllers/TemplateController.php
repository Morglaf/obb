<?php
/**
 * Online Book Brew - Contrôleur des templates
 * 
 * Gère les routes relatives aux templates
 */

namespace App\Controllers;

use App\Services\TemplateService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TemplateController
{
    /**
     * Service de templates
     */
    private $templateService;
    
    /**
     * Constructeur
     * 
     * @param TemplateService $templateService Service de templates
     */
    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }
    
    /**
     * Route pour obtenir tous les templates disponibles
     * GET /api/layouts
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @return Response Réponse avec les templates
     */
    public function getTemplates(Request $request, Response $response)
    {
        try {
            $templates = $this->templateService->getAllTemplates();
            
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'layouts' => $templates['layouts'],
                'covers' => $templates['covers'],
                'imposes' => $templates['imposes'],
                'invalidFiles' => $templates['invalidFiles']
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withStatus(200);
        } catch (\Exception $e) {
            error_log("Erreur dans /api/layouts: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withStatus(500);
        }
    }
    
    /**
     * Route pour obtenir les variables d'un fichier de couverture
     * GET /api/cover-variables/{cover}
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @param array $args Arguments de la route
     * @return Response Réponse avec les variables
     */
    public function getCoverVariables(Request $request, Response $response, array $args)
    {
        try {
            $coverName = $args['cover'];
            $variables = $this->templateService->getCoverVariables($coverName);
            
            $response->getBody()->write(json_encode([
                'status' => 'success',
                'variables' => $variables
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        }
    }
    
    /**
     * Route pour prévisualiser un template
     * GET /preview
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @return Response Réponse avec l'image de prévisualisation
     */
    public function previewTemplate(Request $request, Response $response)
    {
        // Récupérer les paramètres de la requête
        $queryParams = $request->getQueryParams();
        $filename = $queryParams['image'] ?? null;
        $type = $queryParams['type'] ?? null;
        $name = $queryParams['name'] ?? null;
        
        // Si le format ancien est utilisé (type + name)
        if ($type && $name) {
            // Vérifier si le type est autorisé
            $allowedTypes = ['layout', 'cover', 'impose'];
            if (!in_array($type, $allowedTypes)) {
                $response->getBody()->write(json_encode(['error' => 'Type non autorisé']));
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('Access-Control-Allow-Origin', '*')
                    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            }
            
            // Nettoyer le nom de fichier pour éviter les attaques par traversée de répertoire
            $name = basename($name);
            
            // Chemin de base pour les prévisualisations
            $basePath = $GLOBALS['config']['paths']['typeset'];
            
            // Construire le chemin complet vers l'image
            $imagePath = "{$basePath}/{$type}/{$name}.png";
            
            // Vérifier si le fichier existe
            if (!file_exists($imagePath)) {
                // Si le fichier .png n'existe pas, essayer avec .jpg
                $imagePath = "{$basePath}/{$type}/{$name}.jpg";
                
                if (!file_exists($imagePath)) {
                    // Si aucun fichier n'existe, retourner une erreur 404
                    $response->getBody()->write(json_encode(['error' => 'Image non trouvée']));
                    return $response
                        ->withStatus(404)
                        ->withHeader('Content-Type', 'application/json')
                        ->withHeader('Access-Control-Allow-Origin', '*')
                        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
                }
            }
        }
        // Sinon, utiliser le format nouveau (image)
        else if ($filename) {
            // Chemin du fichier image
            $uploadDir = $GLOBALS['config']['paths']['uploads'];
            $imagePath = $uploadDir . '/' . $filename;
            
            if (!file_exists($imagePath)) {
                $response->getBody()->write(json_encode(['error' => 'Image non trouvée']));
                return $response
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('Access-Control-Allow-Origin', '*')
                    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            }
        }
        // Si aucun paramètre valide n'est fourni
        else {
            $response->getBody()->write(json_encode(['error' => 'Nom de fichier manquant']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        }
        
        // Déterminer le type MIME par l'extension
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
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
        $response = $response->withHeader('Content-Disposition', 'inline; filename="' . basename($imagePath) . '"');
        $response = $response->withHeader('Content-Length', filesize($imagePath));
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        $response = $response->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->getBody()->write(file_get_contents($imagePath));
        
        return $response;
    }
} 