<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use App\Utils\Database;

class AdminMiddleware
{
    /**
     * Middleware pour vérifier si l'utilisateur est admin (id=1)
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Récupérer le token depuis l'en-tête Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (empty($token)) {
            $responseData = [
                'status' => 'error',
                'message' => 'Accès non autorisé - token manquant'
            ];
            
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        try {
            // Vérifier le token
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT_SECRET non défini dans les variables d\'environnement');
            }
            $secretKey = $_ENV['JWT_SECRET'];
            
            // Décoder le token
            $decoded = JWT::decode($token, new \Firebase\JWT\Key($secretKey, 'HS256'));
            
            // Vérifier si l'utilisateur est admin (id=1)
            if (!isset($decoded->userId) || $decoded->userId != 1) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Accès non autorisé - droits administrateur requis'
                ];
                
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
            }
            
            // L'utilisateur est admin, continuer la requête
            return $handler->handle($request);
            
        } catch (\Exception $e) {
            error_log('Erreur AdminMiddleware: ' . $e->getMessage());
            
            $responseData = [
                'status' => 'error',
                'message' => 'Accès non autorisé - token invalide'
            ];
            
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
} 