<?php
/**
 * Online Book Brew - Contrôleur de conversion
 * 
 * Contrôleur pour gérer les routes de conversion de documents
 */

namespace App\Controllers;

use App\Services\ConversionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConversionController
{
    /**
     * Service de conversion
     */
    private $conversionService;
    
    /**
     * URL de l'API
     */
    private $apiUrl;
    
    /**
     * Constructeur
     * 
     * @param ConversionService $conversionService Service de conversion
     * @param string $apiUrl URL de l'API
     */
    public function __construct(ConversionService $conversionService, $apiUrl)
    {
        $this->conversionService = $conversionService;
        $this->apiUrl = $apiUrl;
    }
    
    /**
     * Route pour convertir un document Markdown en PDF
     * POST /api/convert
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @return Response Réponse avec les résultats de la conversion
     */
    public function convertDocument(Request $request, Response $response)
    {
        try {
            error_log('=== DÉBUT CONVERT DOCUMENT ===');
            error_log('Content-Type: ' . $request->getHeaderLine('Content-Type'));
            
            // Lire le body une seule fois
            $body = $request->getBody()->getContents();
            error_log('Body brut: ' . $body);
            
            // Essayer de parser le JSON manuellement si getParsedBody() ne fonctionne pas
            $data = $request->getParsedBody();
            if (empty($data) || !is_array($data)) {
                error_log('getParsedBody() retourne vide, parsing manuel du JSON');
                $data = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('Erreur JSON: ' . json_last_error_msg());
                }
            }
            
            // Récupérer et valider les données
            error_log('Données parsées: ' . print_r($data, true));
            
            if (!is_array($data) || !isset($data['content'])) {
                throw new \Exception('Format de données invalide');
            }

            $content = $data['content'];
            if (empty($content)) {
                throw new \Exception('Le contenu est vide');
            }

            // Vérifier si nous avons un template
            $template = isset($data['template']) && is_array($data['template']) ? $data['template'] : null;
            
            // Récupérer la méthode de conversion (par défaut: pandoc_direct)
            $conversionMethod = isset($data['conversionMethod']) ? $data['conversionMethod'] : 'pandoc_direct';
            error_log('Méthode de conversion: ' . $conversionMethod);
            
            // Convertir le document
            $result = $this->conversionService->convertDocument($content, $template, $conversionMethod);
            
            $response->getBody()->write(json_encode($result));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        } catch (\Exception $e) {
            error_log("Erreur lors de la conversion: " . $e->getMessage());
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
     * Route pour compiler uniquement la couverture
     * POST /api/compile-cover
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @return Response Réponse avec les résultats de la compilation
     */
    public function compileCover(Request $request, Response $response)
    {
        try {
            $data = $request->getParsedBody();
            
            // Récupérer et valider les données
            error_log('Données reçues pour compilation de couverture: ' . print_r($data, true));
            
            if (!is_array($data) || !isset($data['template'])) {
                throw new \Exception('Format de données invalide');
            }

            // Récupérer la méthode de conversion (par défaut: pandoc_direct)
            $conversionMethod = isset($data['conversionMethod']) ? $data['conversionMethod'] : 'pandoc_direct';
            error_log('Méthode de conversion pour couverture: ' . $conversionMethod);

            // Compiler la couverture
            $result = $this->conversionService->compileCover($data['template'], $conversionMethod);
            
            $response->getBody()->write(json_encode($result));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        } catch (\Exception $e) {
            error_log("Erreur lors de la compilation de la couverture: " . $e->getMessage());
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
     * Route pour imposer un document
     * POST /api/impose
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @return Response Réponse avec les résultats de l'imposition
     */
    public function imposeDocument(Request $request, Response $response)
    {
        try {
            error_log('=== DÉBUT IMPOSITION ===');
            error_log('Content-Type: ' . $request->getHeaderLine('Content-Type'));
            
            // Lire le body une seule fois
            $body = $request->getBody()->getContents();
            error_log('Body brut: ' . $body);
            error_log('Longueur du body: ' . strlen($body));
            error_log('Body est vide: ' . (empty($body) ? 'OUI' : 'NON'));
            
            // Forcer l'affichage des logs dans un fichier
            file_put_contents('/tmp/debug.log', "=== DÉBUT IMPOSITION ===\n", FILE_APPEND);
            file_put_contents('/tmp/debug.log', "Content-Type: " . $request->getHeaderLine('Content-Type') . "\n", FILE_APPEND);
            file_put_contents('/tmp/debug.log', "Body brut: " . $body . "\n", FILE_APPEND);
            file_put_contents('/tmp/debug.log', "Longueur du body: " . strlen($body) . "\n", FILE_APPEND);
            
            // Essayer de parser le JSON manuellement si getParsedBody() ne fonctionne pas
            $data = $request->getParsedBody();
            file_put_contents('/tmp/debug.log', "getParsedBody() retourne: " . print_r($data, true) . "\n", FILE_APPEND);
            if (empty($data) || !is_array($data)) {
                error_log('getParsedBody() retourne vide, parsing manuel du JSON');
                file_put_contents('/tmp/debug.log', "getParsedBody() retourne vide, parsing manuel du JSON\n", FILE_APPEND);
                $data = json_decode($body, true);
                file_put_contents('/tmp/debug.log', "json_decode() retourne: " . print_r($data, true) . "\n", FILE_APPEND);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('Erreur JSON: ' . json_last_error_msg());
                    file_put_contents('/tmp/debug.log', "Erreur JSON: " . json_last_error_msg() . "\n", FILE_APPEND);
                }
            }
            
            // Récupérer et valider les données
            error_log('=== DEBUG IMPOSITION ===');
            error_log('getParsedBody() retourne: ' . print_r($data, true));
            error_log('Type de data: ' . gettype($data));
            error_log('Data est vide: ' . (empty($data) ? 'OUI' : 'NON'));
            error_log('Data est array: ' . (is_array($data) ? 'OUI' : 'NON'));
            error_log('Data contient content: ' . (isset($data['content']) ? 'OUI' : 'NON'));
            error_log('Data contient template: ' . (isset($data['template']) ? 'OUI' : 'NON'));
            
            if (!is_array($data) || !isset($data['content']) || !isset($data['template'])) {
                error_log('ERREUR: Format de données invalide détecté');
                file_put_contents('/tmp/debug.log', "ERREUR: Format de données invalide détecté\n", FILE_APPEND);
                throw new \Exception('Format de données invalide');
            }

            $content = $data['content'];
            error_log('Contenu reçu: "' . substr($content, 0, 100) . '" (longueur: ' . strlen($content) . ')');
            file_put_contents('/tmp/debug.log', "Contenu reçu: " . substr($content, 0, 100) . " (longueur: " . strlen($content) . ")\n", FILE_APPEND);
            if (empty($content)) {
                error_log('ERREUR: Le contenu est vide');
                file_put_contents('/tmp/debug.log', "ERREUR: Le contenu est vide\n", FILE_APPEND);
                throw new \Exception('Le contenu est vide');
            }

            // Vérifier que le template contient une imposition
            $templateOptions = $data['template'];
            file_put_contents('/tmp/debug.log', "Template options: " . print_r($templateOptions, true) . "\n", FILE_APPEND);
            if (!isset($templateOptions['impose']) || empty($templateOptions['impose'])) {
                file_put_contents('/tmp/debug.log', "ERREUR: Aucune imposition sélectionnée\n", FILE_APPEND);
                throw new \Exception('Aucune imposition sélectionnée');
            }
            
            // Vérifier que le layout est défini
            if (!isset($templateOptions['layout']) || empty($templateOptions['layout'])) {
                file_put_contents('/tmp/debug.log', "ERREUR: Une mise en page est nécessaire pour l'imposition\n", FILE_APPEND);
                throw new \Exception('Une mise en page est nécessaire pour l\'imposition');
            }

            // Récupérer la méthode de conversion (par défaut: pandoc_direct)
            $conversionMethod = isset($data['conversionMethod']) ? $data['conversionMethod'] : 'pandoc_direct';
            error_log('Méthode de conversion pour imposition: ' . $conversionMethod);

            // Imposer le document
            file_put_contents('/tmp/debug.log', "Appel du service d'imposition...\n", FILE_APPEND);
            
            try {
                $result = $this->conversionService->imposeDocument($content, $templateOptions, $conversionMethod);
                file_put_contents('/tmp/debug.log', "Résultat de l'imposition: " . print_r($result, true) . "\n", FILE_APPEND);
            } catch (\Throwable $e) {
                file_put_contents('/tmp/debug.log', "ERREUR FATALE dans imposeDocument: " . $e->getMessage() . "\n", FILE_APPEND);
                file_put_contents('/tmp/debug.log', "Fichier: " . $e->getFile() . " ligne: " . $e->getLine() . "\n", FILE_APPEND);
                file_put_contents('/tmp/debug.log', "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
                throw $e;
            }
            
            $response->getBody()->write(json_encode($result));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        } catch (\Exception $e) {
            error_log("Erreur lors de l'imposition: " . $e->getMessage());
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
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
     * Route pour servir directement les fichiers PDF
     * GET /api/pdf/{id}
     * 
     * @param Request $request Requête HTTP
     * @param Response $response Réponse HTTP
     * @param array $args Arguments de la route
     * @return Response Réponse avec le fichier PDF
     */
    public function getPdf(Request $request, Response $response, array $args)
    {
        $docId = $args['id'];
        
        // Chemin du fichier PDF
        $pdfPath = $GLOBALS['config']['paths']['files'] . DIRECTORY_SEPARATOR . $docId . '.pdf';
        
        if (!file_exists($pdfPath)) {
            return $response
                ->withStatus(404)
                ->withJson(['error' => 'PDF non trouvé'])
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        }
        
        // Servir le fichier PDF
        $response = $response->withHeader('Content-Type', 'application/pdf');
        $response = $response->withHeader('Content-Disposition', 'inline; filename="' . basename($pdfPath) . '"');
        $response = $response->withHeader('Content-Length', filesize($pdfPath));
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        $response = $response->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->getBody()->write(file_get_contents($pdfPath));
        
        return $response;
    }
} 