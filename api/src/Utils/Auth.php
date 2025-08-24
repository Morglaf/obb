<?php

namespace App\Utils;

use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    /**
     * Authentifie un utilisateur à partir du token JWT
     * 
     * @param Request $request Requête HTTP
     * @return int|null Identifiant de l'utilisateur ou null si non authentifié
     */
    public function authenticate(Request $request): ?int
    {
        try {
            // Récupérer le token du header Authorization
            $authHeader = $request->getHeaderLine('Authorization');
            error_log('Auth - Header Authorization: ' . $authHeader);
            
            $token = str_replace('Bearer ', '', $authHeader);
            error_log('Auth - Token extrait: ' . substr($token, 0, 20) . '...');
            
            if (empty($token)) {
                error_log('Auth - Token vide');
                return null;
            }
            
            // Vérifier le token
            if (!isset($_ENV['JWT_SECRET'])) {
                error_log('Erreur: JWT_SECRET non défini dans les variables d\'environnement');
                throw new \Exception('Configuration de sécurité manquante');
            }
            $secretKey = $_ENV['JWT_SECRET'];
            error_log('Auth - JWT_SECRET défini: ' . (strlen($secretKey) > 0 ? 'oui' : 'non'));
            
            $payload = JWT::decode($token, new Key($secretKey, 'HS256'));
            error_log('Auth - Token décodé avec succès, userId: ' . ($payload->userId ?? 'null'));
            
            // Vérifier si le token est expiré
            if (isset($payload->exp) && $payload->exp < time()) {
                error_log('Auth - Token expiré');
                return null;
            }
            
            // Retourner l'ID de l'utilisateur
            $userId = (int) $payload->userId;
            error_log('Auth - Retourne userId: ' . $userId);
            return $userId;
        } catch (\Exception $e) {
            error_log('Erreur d\'authentification: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Génère un nouveau token JWT
     * 
     * @param int $userId Identifiant de l'utilisateur
     * @param string $email Email de l'utilisateur
     * @param string $username Nom d'utilisateur
     * @return string Token JWT
     */
    public function generateToken(int $userId, string $email, string $username): string
    {
        if (!isset($_ENV['JWT_SECRET'])) {
            error_log('Erreur: JWT_SECRET non défini dans les variables d\'environnement');
            throw new \Exception('Configuration de sécurité manquante');
        }
        $secretKey = $_ENV['JWT_SECRET'];
        $issuedAt = time();
        $expirationTime = $issuedAt + 60 * 60 * 24; // 24 heures
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'userId' => $userId,
            'email' => $email,
            'username' => $username
        ];
        
        return JWT::encode($payload, $secretKey, 'HS256');
    }
} 