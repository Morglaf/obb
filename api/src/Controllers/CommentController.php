<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\Database;

class CommentController
{
    /**
     * Ajoute un commentaire utilisateur
     */
    public function addComment(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        
        // Récupérer les données du formulaire
        $data = $request->getParsedBody();
        
        // Validation
        if (!isset($data['message']) || empty($data['message'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Le message est requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $message = trim($data['message']);
        
        try {
            $db = new Database();
            $stmt = $db->prepare('INSERT INTO comments (user_id, content, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$userId, $message]);
            
            // Ajouter un log
            $logStmt = $db->prepare('INSERT INTO system_logs (log_type, message) VALUES (?, ?)');
            $logStmt->execute(['comment', 'Nouveau commentaire ajouté par l\'utilisateur ID: ' . $userId]);
            
            $responseData = [
                'status' => 'success',
                'message' => 'Commentaire envoyé avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de l\'ajout du commentaire: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de l\'ajout du commentaire: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Récupère les commentaires de l'utilisateur connecté
     */
    public function getUserComments(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        
        try {
            $db = new Database();
            $stmt = $db->prepare('SELECT * FROM comments WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId]);
            $comments = $stmt->fetchAll();
            
            $responseData = [
                'status' => 'success',
                'comments' => $comments
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
} 