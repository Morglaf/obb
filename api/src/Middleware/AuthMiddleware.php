<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;
use App\Utils\Auth;

class AuthMiddleware
{
    /**
     * Middleware d'authentification pour les routes protégées
     * 
     * @param Request $request Requête HTTP
     * @param RequestHandler $handler Gestionnaire de requête
     * @return Response Réponse HTTP
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $auth = new Auth();
        $userId = $auth->authenticate($request);
        
        if (!$userId) {
            $response = new Response();
            $responseData = [
                'status' => 'error',
                'message' => 'Non autorisé'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }
        
        // Ajouter l'ID de l'utilisateur à la requête
        $request = $request->withAttribute('userId', $userId);
        
        return $handler->handle($request);
    }
} 