<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use App\Utils\Database;
use App\Utils\Auth;

class UserTemplateController
{
    /**
     * Récupère les templates d'un utilisateur
     */
    public function getUserTemplates(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            if (!$userId) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Récupérer les templates de l'utilisateur
            $db = new Database();
            
            // Vérifier si la colonne preview_path existe (syntaxe PostgreSQL)
            $showColumnsResult = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'user_templates' AND column_name = 'preview_path'");
            $previewPathExists = $showColumnsResult->rowCount() > 0;
            
            if ($previewPathExists) {
                $stmt = $db->prepare('SELECT id, name, type, file_path, preview_path, created_at FROM user_templates WHERE user_id = ?');
            } else {
                $stmt = $db->prepare('SELECT id, name, type, file_path, created_at FROM user_templates WHERE user_id = ?');
            }
            
            $stmt->execute([$userId]);
            $templates = $stmt->fetchAll();
            
            // Transformer les données pour le format attendu par le frontend
            $transformedTemplates = array_map(function($template) use ($previewPathExists) {
                return [
                    'id' => $template['id'],
                    'name' => $template['name'],
                    'type' => $template['type'],
                    'path' => $template['file_path'],
                    'previewPath' => $previewPathExists ? $template['preview_path'] : null,
                    'userId' => $template['user_id'] ?? null,
                    'createdAt' => $template['created_at']
                ];
            }, $templates);
            
            // Renvoyer les templates
            $responseData = [
                'status' => 'success',
                'templates' => $transformedTemplates
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur getUserTemplates: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des templates: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Ajoute un nouveau template pour l'utilisateur
     */
    public function uploadTemplate(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            if (!$userId) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Récupérer les données du formulaire
            $uploadedFiles = $request->getUploadedFiles();
            $parsedBody = $request->getParsedBody();
            
            if (!isset($uploadedFiles['file']) || !isset($parsedBody['name']) || !isset($parsedBody['type'])) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Fichier, nom et type requis'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Vérifier si l'image de prévisualisation est fournie
            if (!isset($uploadedFiles['preview'])) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Image de prévisualisation requise'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $file = $uploadedFiles['file'];
            $previewFile = $uploadedFiles['preview'];
            
            // IMPORTANT: Le nom doit inclure les suffixes comme -layout, -cover-A4, etc.
            // Ces suffixes sont nécessaires pour que la détection du format fonctionne correctement.
            $name = htmlspecialchars(strip_tags($parsedBody['name']));
            $type = $parsedBody['type'];
            
            // Log pour débogage
            error_log("Upload de template - Nom reçu: " . $name . ", Type: " . $type);
            
            // Valider le type
            if (!in_array($type, ['layout', 'cover', 'impose'])) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Type invalide. Types valides: layout, cover, impose'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Vérifier si le template existe déjà pour cet utilisateur
            $db = new Database();
            
            $stmt = $db->prepare('SELECT id FROM user_templates WHERE user_id = ? AND name = ? AND type = ?');
            $stmt->execute([$userId, $name, $type]);
            
            if ($stmt->fetchColumn()) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Un template avec ce nom et ce type existe déjà'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Créer le répertoire de l'utilisateur s'il n'existe pas
            $userTemplateDir = '/app/user_templates/' . $userId;
            if (!file_exists($userTemplateDir)) {
                mkdir($userTemplateDir, 0777, true);
            }
            
            // Créer le répertoire du type s'il n'existe pas
            $typeDir = $userTemplateDir . '/' . $type;
            if (!file_exists($typeDir)) {
                mkdir($typeDir, 0777, true);
            }
            
            // Créer le répertoire des prévisualisations s'il n'existe pas
            $previewsDir = $userTemplateDir . '/previews';
            if (!file_exists($previewsDir)) {
                mkdir($previewsDir, 0777, true);
            }
            
            // Sauvegarder le fichier template
            $originalFilename = $file->getClientFilename();
            $filename = $originalFilename;
            
            // Si le fichier n'a pas d'extension .tex, ajouter l'extension
            if (function_exists('str_ends_with')) {
                if (!str_ends_with(strtolower($filename), '.tex')) {
                    $filename = $filename . '.tex';
                }
            } else {
                // Méthode de secours pour PHP < 8.0
                $length = strlen('.tex');
                if (strtolower(substr($filename, -$length)) !== '.tex') {
                    $filename = $filename . '.tex';
                }
            }
            
            $filePath = $typeDir . '/' . $filename;
            $file->moveTo($filePath);
            
            // Sauvegarder l'image de prévisualisation
            $previewFilename = $type . '-' . $name . '.png';
            $previewPath = $previewsDir . '/' . $previewFilename;
            $previewFile->moveTo($previewPath);
            
            // Enregistrer le template dans la base de données
            $relativePath = 'user_templates/' . $userId . '/' . $type . '/' . $filename;
            $relativePreviewPath = 'user_templates/' . $userId . '/previews/' . $previewFilename;
            
            $stmt = $db->prepare('INSERT INTO user_templates (user_id, name, type, file_path, preview_path, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$userId, $name, $type, $relativePath, $relativePreviewPath]);
            
            $templateId = $db->lastInsertId();
            
            // Renvoyer la réponse
            $responseData = [
                'status' => 'success',
                'message' => 'Template ajouté avec succès',
                'template' => [
                    'id' => $templateId,
                    'name' => $name,
                    'type' => $type,
                    'path' => $relativePath,
                    'previewPath' => $relativePreviewPath,
                    'userId' => $userId,
                    'createdAt' => date('Y-m-d H:i:s')
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur uploadTemplate: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de l\'upload du template: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Supprime un template utilisateur
     */
    public function deleteTemplate(Request $request, Response $response, array $args): Response
    {
        try {
            // Vérifier l'authentification
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            if (!$userId) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Vérifier si le template existe et appartient à l'utilisateur
            $templateId = (int) $args['id'];
            
            $db = new Database();
            
            $stmt = $db->prepare('SELECT file_path, preview_path FROM user_templates WHERE id = ? AND user_id = ?');
            $stmt->execute([$templateId, $userId]);
            $template = $stmt->fetch();
            
            if (!$template) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Template non trouvé ou non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Supprimer le fichier template
            $filePath = '/app/' . $template['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Supprimer l'image de prévisualisation si elle existe
            if (!empty($template['preview_path'])) {
                $previewPath = '/app/' . $template['preview_path'];
                if (file_exists($previewPath)) {
                    unlink($previewPath);
                }
            }
            
            // Supprimer le template de la base de données
            $stmt = $db->prepare('DELETE FROM user_templates WHERE id = ?');
            $stmt->execute([$templateId]);
            
            // Renvoyer la réponse
            $responseData = [
                'status' => 'success',
                'message' => 'Template supprimé avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur deleteTemplate: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la suppression du template: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Nettoie les entrées corrompues pour un utilisateur
     */
    public function cleanupCorruptedEntries(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            if (!$userId) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Récupérer tous les templates de l'utilisateur
            $db = new Database();
            $deletedCount = 0;
            $cleanedFiles = 0;
            $debugInfo = [];
            
            // 1. Nettoyer les templates dont les fichiers n'existent plus
            $stmt = $db->prepare('SELECT id, name, type, file_path FROM user_templates WHERE user_id = ?');
            $stmt->execute([$userId]);
            $templates = $stmt->fetchAll();
            
            $corruptedIds = [];
            $dbFilePaths = []; // Pour vérifier les fichiers orphelins plus tard
            $dbTypeFiles = []; // Pour organiser les fichiers par type
            
            // Vérifier chaque template
            foreach ($templates as $template) {
                // Construire le chemin complet du fichier
                $filePath = '/app/' . $template['file_path'];
                
                // Vérifier si le chemin est valide et si le fichier existe réellement
                if (!file_exists($filePath) || !is_file($filePath)) {
                    $corruptedIds[] = $template['id'];
                    $deletedCount++;
                    
                    // Ajouter des informations de débogage
                    $debugInfo[] = [
                        'id' => $template['id'],
                        'name' => $template['name'],
                        'type' => $template['type'],
                        'path' => $template['file_path'],
                        'fullPath' => $filePath,
                        'reason' => 'Fichier manquant'
                    ];
                    
                    continue;
                }
                
                // Si le fichier existe, l'ajouter aux listes pour vérification ultérieure
                $dbFilePaths[] = $filePath;
                
                // Organiser par type pour la vérification des orphelins
                if (!isset($dbTypeFiles[$template['type']])) {
                    $dbTypeFiles[$template['type']] = [];
                }
                $dbTypeFiles[$template['type']][] = basename($filePath);
            }
            
            // Supprimer les entrées corrompues (fichiers manquants)
            if (count($corruptedIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($corruptedIds), '?'));
                $stmt = $db->prepare("DELETE FROM user_templates WHERE id IN ($placeholders)");
                $stmt->execute($corruptedIds);
            }
            
            // 2. Vérifier les entrées dupliquées (même nom et type pour un utilisateur)
            $stmt = $db->prepare('
                SELECT name, type, COUNT(*) as count, MIN(id) as keep_id
                FROM user_templates 
                WHERE user_id = ?
                GROUP BY name, type
                HAVING COUNT(*) > 1
            ');
            $stmt->execute([$userId]);
            $duplicates = $stmt->fetchAll();
            
            // Supprimer les entrées dupliquées en gardant la plus ancienne
            foreach ($duplicates as $duplicate) {
                $stmt = $db->prepare('
                    DELETE FROM user_templates 
                    WHERE user_id = ? AND name = ? AND type = ? AND id != ?
                ');
                $stmt->execute([
                    $userId, 
                    $duplicate['name'], 
                    $duplicate['type'], 
                    $duplicate['keep_id']
                ]);
                
                $deletedRows = $stmt->rowCount();
                $deletedCount += $deletedRows;
                
                // Ajouter des informations de débogage
                $debugInfo[] = [
                    'name' => $duplicate['name'],
                    'type' => $duplicate['type'],
                    'count' => $duplicate['count'],
                    'keep_id' => $duplicate['keep_id'],
                    'deleted' => $deletedRows,
                    'reason' => 'Entrées dupliquées'
                ];
            }
            
            // 3. Vérifier les fichiers orphelins (présents dans le dossier mais pas dans la base de données)
            $userTemplateDir = '/app/user_templates/' . $userId;
            if (file_exists($userTemplateDir) && is_dir($userTemplateDir)) {
                // Parcourir les dossiers de types (layout, cover, impose)
                $typeDirs = scandir($userTemplateDir);
                foreach ($typeDirs as $typeDir) {
                    // Ignorer . et ..
                    if ($typeDir === '.' || $typeDir === '..') {
                        continue;
                    }
                    
                    $typePath = $userTemplateDir . '/' . $typeDir;
                    if (is_dir($typePath)) {
                        $typeFiles = scandir($typePath);
                        foreach ($typeFiles as $file) {
                            // Ignorer . et ..
                            if ($file === '.' || $file === '..') {
                                continue;
                            }
                            
                            // Vérifier si le fichier est orphelin (pas dans la base de données)
                            $isOrphan = true;
                            if (isset($dbTypeFiles[$typeDir]) && in_array($file, $dbTypeFiles[$typeDir])) {
                                $isOrphan = false;
                            }
                            
                            if ($isOrphan) {
                                $orphanFilePath = $typePath . '/' . $file;
                                if (is_file($orphanFilePath)) {
                                    unlink($orphanFilePath);
                                    $cleanedFiles++;
                                    
                                    // Ajouter des informations de débogage
                                    $debugInfo[] = [
                                        'path' => $orphanFilePath,
                                        'reason' => 'Fichier orphelin'
                                    ];
                                }
                            }
                        }
                    }
                }
            }
            
            // 4. Détecter et nettoyer les entrées problématiques dans la base de données
            // Vérifier s'il y a des entrées dans la base de données mais pas de fichier correspondant dans le système de fichiers
            $stmt = $db->prepare('
                SELECT ut.id, ut.name, ut.type, ut.file_path
                FROM user_templates ut
                WHERE ut.user_id = ?
            ');
            $stmt->execute([$userId]);
            $allTemplates = $stmt->fetchAll();
            
            $problemTemplateIds = [];
            
            foreach ($allTemplates as $template) {
                // Vérifier si le fichier existe réellement
                $filePath = __DIR__ . '/../../../' . $template['file_path'];
                
                // Si le fichier n'existe pas et l'ID n'est pas déjà dans la liste des corrompus
                if ((!file_exists($filePath) || !is_file($filePath)) && !in_array($template['id'], $corruptedIds)) {
                    $problemTemplateIds[] = $template['id'];
                    
                    // Ajouter des informations de débogage
                    $debugInfo[] = [
                        'id' => $template['id'],
                        'name' => $template['name'],
                        'type' => $template['type'],
                        'path' => $template['file_path'],
                        'reason' => 'Entrée problématique détectée'
                    ];
                }
            }
            
            // Supprimer les entrées problématiques
            if (count($problemTemplateIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($problemTemplateIds), '?'));
                $stmt = $db->prepare("DELETE FROM user_templates WHERE id IN ($placeholders)");
                $stmt->execute($problemTemplateIds);
                $deletedCount += count($problemTemplateIds);
            }
            
            // Renvoyer la réponse avec des informations détaillées
            $responseData = [
                'status' => 'success',
                'message' => "Nettoyage terminé. $deletedCount entrées corrompues ou dupliquées ont été supprimées et $cleanedFiles fichiers orphelins ont été nettoyés.",
                'deleted_count' => $deletedCount,
                'cleaned_files' => $cleanedFiles,
                'debug_info' => $debugInfo
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur cleanupCorruptedEntries: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors du nettoyage des entrées corrompues: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Met à jour la structure de la table user_templates si nécessaire
     */
    public function updateTableStructure(Request $request, Response $response): Response
    {
        try {
            // Vérifier l'authentification et les droits admin
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            if (!$userId) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // On pourrait ajouter une vérification des droits admin ici
            
            $db = new Database();
            $changes = [];
            
            // Vérifier si la colonne preview_path existe (PostgreSQL)
            $showColumnsResult = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'user_templates' AND column_name = 'preview_path'");
            if ($showColumnsResult->rowCount() == 0) {
                // La colonne n'existe pas, l'ajouter
                $db->exec("ALTER TABLE user_templates ADD COLUMN preview_path VARCHAR(255) DEFAULT NULL");
                $changes[] = "Colonne preview_path ajoutée à la table user_templates";
            }
            
            $responseData = [
                'status' => 'success',
                'message' => count($changes) > 0 
                    ? "Structure de la table mise à jour avec succès: " . implode(", ", $changes)
                    : "Aucune mise à jour nécessaire, la structure est déjà à jour.",
                'changes' => $changes
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur updateTableStructure: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la mise à jour de la structure de la table: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Récupère le contenu d'un template utilisateur
     */
    public function getTemplateContent(Request $request, Response $response, array $args): Response
    {
        try {
            // Logs de débogage
            error_log('getTemplateContent appelé avec args: ' . json_encode($args));
            
            // Vérifier l'authentification
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            error_log('getTemplateContent - userId authentifié: ' . ($userId ?? 'null'));
            
            if (!$userId) {
                error_log('getTemplateContent - Authentification échouée');
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Récupérer l'ID du template
            $templateId = (int) $args['id'];
            
            // Vérifier si le template existe et appartient à l'utilisateur
            $db = new Database();
            
            $stmt = $db->prepare('SELECT id, name, type, file_path FROM user_templates WHERE id = ? AND user_id = ?');
            $stmt->execute([$templateId, $userId]);
            $template = $stmt->fetch();
            
            if (!$template) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Template non trouvé ou non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Lire le contenu du fichier
            $filePath = '/app/' . $template['file_path'];
            
            if (!file_exists($filePath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Fichier template non trouvé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            $content = file_get_contents($filePath);
            
            if ($content === false) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Erreur lors de la lecture du fichier template'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // Extraire les métadonnées du contenu
            $metadata = $this->extractTemplateMetadata($content);
            
            // Renvoyer le contenu et les métadonnées
            $responseData = [
                'status' => 'success',
                'template' => [
                    'id' => $template['id'],
                    'name' => $template['name'],
                    'type' => $template['type'],
                    'content' => $content,
                    'metadata' => $metadata
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur getTemplateContent: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du contenu du template: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Extrait les métadonnées d'un template à partir de son contenu
     */
    private function extractTemplateMetadata(string $content): array
    {
        $metadata = [];
        $options = [
            'booleans' => [],
            'variables' => []
        ];
        
        // Rechercher les lignes de métadonnées commençant par %% META:
        preg_match_all('/%% META:\s*(.*?)$/m', $content, $matches);
        
        if (isset($matches[1]) && is_array($matches[1])) {
            foreach ($matches[1] as $metaLine) {
                $metaLine = trim($metaLine);
                
                // Analyser la ligne de métadonnées (format: clé=valeur, clé=valeur, ...)
                $keyValuePairs = array_map('trim', explode(',', $metaLine));
                
                foreach ($keyValuePairs as $pair) {
                    $parts = array_map('trim', explode('=', $pair, 2));
                    
                    if (count($parts) === 2) {
                        $key = $parts[0];
                        $value = $parts[1];
                        
                        // Traiter les valeurs spéciales
                        if ($key === 'booleans') {
                            // Format attendu: booleans=option1:true,option2:false
                            $booleanOptions = explode(',', $value);
                            foreach ($booleanOptions as $opt) {
                                $optParts = array_map('trim', explode(':', $opt, 2));
                                if (count($optParts) === 2) {
                                    $options['booleans'][] = [
                                        'name' => $optParts[0],
                                        'type' => 'boolean',
                                        'default' => $optParts[1] === 'true'
                                    ];
                                }
                            }
                        } elseif ($key === 'variables') {
                            // Format attendu: variables=title,author,publisher
                            $variableNames = array_map('trim', explode(',', $value));
                            foreach ($variableNames as $varName) {
                                $options['variables'][] = [
                                    'name' => $varName,
                                    'type' => 'text',
                                    'default' => ''
                                ];
                            }
                        } else {
                            // Autres métadonnées standards
                            $metadata[$key] = $value;
                        }
                    }
                }
            }
        }
        
        // Détecter les variables utilisées dans le template (format: %VARIABLE%)
        preg_match_all('/%([A-Z0-9_]+)%/', $content, $varMatches);
        
        if (isset($varMatches[1]) && is_array($varMatches[1])) {
            foreach ($varMatches[1] as $varName) {
                $varName = strtolower($varName);
                
                // Vérifier si cette variable existe déjà
                $exists = false;
                foreach ($options['variables'] as $var) {
                    if (strtolower($var['name']) === $varName) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $options['variables'][] = [
                        'name' => $varName,
                        'type' => 'text',
                        'default' => ''
                    ];
                }
            }
        }
        
        return [
            'metadata' => $metadata,
            'options' => $options
        ];
    }

    /**
     * Met à jour le contenu d'un template utilisateur existant
     */
    public function updateTemplate(Request $request, Response $response, array $args): Response
    {
        try {
            // Vérifier l'authentification
            $auth = new Auth();
            $userId = $auth->authenticate($request);
            
            if (!$userId) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            
            // Récupérer l'ID du template
            $templateId = (int) $args['id'];
            
            // Récupérer les données de la requête
            $parsedBody = $request->getParsedBody();
            
            if (!isset($parsedBody['content'])) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Contenu du template requis'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $content = $parsedBody['content'];
            
            // Vérifier si le template existe et appartient à l'utilisateur
            $db = new Database();
            
            $stmt = $db->prepare('SELECT file_path FROM user_templates WHERE id = ? AND user_id = ?');
            $stmt->execute([$templateId, $userId]);
            $template = $stmt->fetch();
            
            if (!$template) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Template non trouvé ou non autorisé'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Mettre à jour le fichier template
            $filePath = '/app/' . $template['file_path'];
            
            if (!file_exists($filePath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Fichier template introuvable sur le serveur'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // Écrire le nouveau contenu dans le fichier
            if (file_put_contents($filePath, $content) === false) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Erreur lors de l\'écriture du fichier'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            // Renvoyer la réponse
            $responseData = [
                'status' => 'success',
                'message' => 'Template mis à jour avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur updateTemplate: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la mise à jour du template: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
} 