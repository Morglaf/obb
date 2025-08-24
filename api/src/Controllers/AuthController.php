<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use App\Utils\Database;

class AuthController
{
    /**
     * Gère l'inscription d'un nouvel utilisateur
     */
    public function register(Request $request, Response $response): Response
    {
        // Récupérer les données du formulaire
        $data = $request->getParsedBody();
        
        // Valider les données
        if (!isset($data['email']) || !isset($data['username']) || !isset($data['password']) || !isset($data['passwordConfirmation'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Tous les champs sont requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $username = htmlspecialchars(strip_tags($data['username']));
        $password = $data['password'];
        $passwordConfirmation = $data['passwordConfirmation'];
        
        // Vérifier que les mots de passe correspondent
        if ($password !== $passwordConfirmation) {
            $responseData = [
                'status' => 'error',
                'message' => 'Les mots de passe ne correspondent pas'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Vérifier la longueur du mot de passe
        if (strlen($password) < 6) {
            $responseData = [
                'status' => 'error',
                'message' => 'Le mot de passe doit contenir au moins 6 caractères'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $db = new Database();
            
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn()) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Cet email est déjà utilisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Vérifier si le nom d'utilisateur existe déjà
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            
            if ($stmt->fetchColumn()) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Ce nom d\'utilisateur est déjà utilisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Crypter le mot de passe
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insérer l'utilisateur dans la base de données
            $stmt = $db->prepare('INSERT INTO users (email, username, password, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$email, $username, $passwordHash]);
            
            $userId = $db->lastInsertId();
            
            // Vérifier si c'est le premier utilisateur (admin)
            $isAdmin = ($userId == 1);
            
            // Créer un token JWT
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT_SECRET non défini dans les variables d\'environnement');
            }
            $secretKey = $_ENV['JWT_SECRET'];
            $issuedAt = time();
            $expirationTime = $issuedAt + 60 * 60 * 24; // 24 heures
            
            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'userId' => $userId,
                'email' => $email,
                'username' => $username,
                'isAdmin' => $isAdmin
            ];
            
            $token = JWT::encode($payload, $secretKey, 'HS256');
            
            // Créer le répertoire des templates pour l'utilisateur
            $config = require __DIR__ . '/../../config/app.php';
            $userTemplateDir = $config['paths']['user_templates'] . '/' . $userId;
            if (!file_exists($userTemplateDir)) {
                mkdir($userTemplateDir, 0777, true);
            }
            
            // Renvoyer la réponse avec le token
            $responseData = [
                'status' => 'success',
                'message' => 'Inscription réussie',
                'token' => $token,
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'username' => $username,
                    'createdAt' => date('Y-m-d H:i:s'),
                    'isAdmin' => $isAdmin
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de l\'inscription: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Gère la connexion d'un utilisateur
     */
    public function login(Request $request, Response $response): Response
    {
        // Récupérer les données du formulaire
        $data = $request->getParsedBody();
        
        // Valider les données
        if (!isset($data['email']) || !isset($data['password'])) {
            $responseData = [
                'status' => 'error',
                'message' => 'Email et mot de passe requis'
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];
        
        try {
            $db = new Database();
            
            // Vérifier si l'utilisateur existe
            $stmt = $db->prepare('SELECT id, username, password, created_at FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($password, $user['password'])) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Email ou mot de passe incorrect'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Vérifier si c'est l'administrateur (id = 1)
            $isAdmin = ($user['id'] == 1);
            
            // Créer un token JWT
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT_SECRET non défini dans les variables d\'environnement');
            }
            $secretKey = $_ENV['JWT_SECRET'];
            $issuedAt = time();
            $expirationTime = $issuedAt + 60 * 60 * 24; // 24 heures
            
            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'userId' => $user['id'],
                'email' => $email,
                'username' => $user['username'],
                'isAdmin' => $isAdmin
            ];
            
            $token = JWT::encode($payload, $secretKey, 'HS256');
            
            // Renvoyer la réponse avec le token
            $responseData = [
                'status' => 'success',
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $email,
                    'username' => $user['username'],
                    'createdAt' => $user['created_at'],
                    'isAdmin' => $isAdmin
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur lors de la connexion: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la connexion: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Récupère les informations de l'utilisateur connecté
     */
    public function me(Request $request, Response $response): Response
    {
        try {
            // Récupérer le token du header Authorization
            $authHeader = $request->getHeaderLine('Authorization');
            $token = str_replace('Bearer ', '', $authHeader);
            
            if (empty($token)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Token manquant'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Vérifier le token avec la nouvelle syntaxe de firebase/jwt
            if (!isset($_ENV['JWT_SECRET'])) {
                throw new \Exception('JWT_SECRET non défini dans les variables d\'environnement');
            }
            $secretKey = $_ENV['JWT_SECRET'];
            
            try {
                $decoded = JWT::decode($token, new \Firebase\JWT\Key($secretKey, 'HS256'));
                
                // Vérifier si l'utilisateur existe toujours dans la base de données
                $db = new Database();
                $stmt = $db->prepare('SELECT id, email, username, created_at FROM users WHERE id = ?');
                $stmt->execute([$decoded->userId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $responseData = [
                        'status' => 'error',
                        'message' => 'Utilisateur non trouvé'
                    ];
                    $response->getBody()->write(json_encode($responseData));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
                }
                
                // Vérifier si c'est l'administrateur (id = 1)
                $isAdmin = ($user['id'] == 1);
                
                $responseData = [
                    'status' => 'success',
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'username' => $user['username'],
                        'createdAt' => $user['created_at'],
                        'isAdmin' => $isAdmin
                    ]
                ];
                
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json');
                
            } catch (\Exception $e) {
                error_log('Erreur /auth/me: ' . $e->getMessage());
                $responseData = [
                    'status' => 'error',
                    'message' => 'Token invalide ou expiré: ' . $e->getMessage()
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
        } catch (\Exception $e) {
            error_log('Erreur /auth/me: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Token invalide ou expiré: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
} 