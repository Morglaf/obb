<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\Auth;
use App\Utils\Database;

class UserFontController
{
    /**
     * Récupère les polices d'un utilisateur
     */
    public function getUserFonts(Request $request, Response $response): Response
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
            
            // Créer le dossier des polices pour l'utilisateur s'il n'existe pas
            $userFontDir = '/app/user_templates/' . $userId . '/fonts';
            if (!file_exists($userFontDir)) {
                mkdir($userFontDir, 0777, true);
            }
            
            // Récupérer la liste des polices de l'utilisateur
            $fonts = [];
            if (is_dir($userFontDir)) {
                $files = scandir($userFontDir);
                foreach ($files as $file) {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    if (in_array($ext, ['ttf', 'otf']) && $file != '.' && $file != '..') {
                        $fonts[] = $file;
                    }
                }
            }
            
            // Renvoyer la liste des polices
            $responseData = [
                'status' => 'success',
                'fonts' => $fonts
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur getUserFonts: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des polices: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Téléverse une nouvelle police pour l'utilisateur
     */
    public function uploadFont(Request $request, Response $response): Response
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
            
            // Récupérer le fichier téléversé
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
            $filename = $fontFile->getClientFilename();
            
            // Vérifier l'extension du fichier
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!in_array(strtolower($ext), ['ttf', 'otf'])) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Format de police non supporté. Utilisez .ttf ou .otf'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Créer le répertoire de l'utilisateur s'il n'existe pas
            $userFontDir = '/app/user_templates/' . $userId . '/fonts';
            if (!file_exists($userFontDir)) {
                mkdir($userFontDir, 0777, true);
            }
            
            // Vérifier si le fichier existe déjà
            $fontPath = $userFontDir . '/' . $filename;
            if (file_exists($fontPath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Une police avec ce nom existe déjà'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // Sauvegarder le fichier
            $fontFile->moveTo($fontPath);
            
            // Créer également un lien symbolique dans le dossier de polices global
            $globalFontDir = '/app/typeset/fonts';
            if (!file_exists($globalFontDir)) {
                mkdir($globalFontDir, 0777, true);
            }
            
            // Copier le fichier dans le dossier global avec un préfixe utilisateur pour éviter les conflits
            $globalFilename = 'user_' . $userId . '_' . $filename;
            copy($fontPath, $globalFontDir . '/' . $globalFilename);
            
            // Renvoyer la réponse
            $responseData = [
                'status' => 'success',
                'message' => 'Police téléversée avec succès',
                'font' => [
                    'name' => $filename,
                    'path' => 'user_templates/' . $userId . '/fonts/' . $filename
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur uploadFont: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors du téléversement de la police: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
    
    /**
     * Supprime une police utilisateur
     */
    public function deleteFont(Request $request, Response $response, array $args): Response
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
            
            // Récupérer le nom du fichier
            $filename = urldecode($args['filename']);
            
            // Vérifier si le fichier existe
            $userFontDir = '/app/user_templates/' . $userId . '/fonts';
            $fontPath = $userFontDir . '/' . $filename;
            
            if (!file_exists($fontPath)) {
                $responseData = [
                    'status' => 'error',
                    'message' => 'Police non trouvée'
                ];
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
            
            // Supprimer le fichier
            unlink($fontPath);
            
            // Supprimer également la version dans le dossier global
            $globalFontDir = '/app/typeset/fonts';
            $globalFilename = 'user_' . $userId . '_' . $filename;
            $globalFontPath = $globalFontDir . '/' . $globalFilename;
            
            if (file_exists($globalFontPath)) {
                unlink($globalFontPath);
            }
            
            // Renvoyer la réponse
            $responseData = [
                'status' => 'success',
                'message' => 'Police supprimée avec succès'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            error_log('Erreur deleteFont: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de la police: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Nettoie les polices corrompues pour un utilisateur
     */
    public function cleanupCorruptedFonts(Request $request, Response $response): Response
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
            
            $db = new Database();
            $deletedCount = 0;
            $cleanedFiles = 0;
            $debugInfo = [];
            
            // Récupérer le chemin du dossier des polices de l'utilisateur
            $userFontsDir = '/app/user_templates/' . $userId . '/fonts';
            
            // 1. Vérifier si le dossier existe, sinon le créer
            if (!file_exists($userFontsDir)) {
                mkdir($userFontsDir, 0777, true);
            }
            
            // 2. Nettoyer les entrées de la base de données dont les fichiers n'existent plus
            $stmt = $db->prepare('SELECT id, filename FROM user_fonts WHERE user_id = ?');
            $stmt->execute([$userId]);
            $dbFonts = $stmt->fetchAll();
            
            $corruptedFontIds = [];
            $dbFilenames = []; // Pour vérifier les fichiers orphelins plus tard
            
            // Vérifier chaque police dans la base de données
            foreach ($dbFonts as $font) {
                $filePath = $userFontsDir . '/' . $font['filename'];
                
                // Vérifier si le fichier existe réellement
                if (!file_exists($filePath) || !is_file($filePath)) {
                    $corruptedFontIds[] = $font['id'];
                    $deletedCount++;
                    
                    // Ajouter des informations de débogage
                    $debugInfo[] = [
                        'id' => $font['id'],
                        'filename' => $font['filename'],
                        'path' => $filePath,
                        'reason' => 'Fichier manquant'
                    ];
                    
                    continue;
                }
                
                // Si le fichier existe, l'ajouter à la liste pour vérification ultérieure
                $dbFilenames[] = $font['filename'];
            }
            
            // Supprimer les entrées corrompues
            if (count($corruptedFontIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($corruptedFontIds), '?'));
                $stmt = $db->prepare("DELETE FROM user_fonts WHERE id IN ($placeholders)");
                $stmt->execute($corruptedFontIds);
            }
            
            // 3. Vérifier les fichiers orphelins (présents dans le dossier mais pas dans la base de données)
            if (file_exists($userFontsDir) && is_dir($userFontsDir)) {
                $filesInDir = scandir($userFontsDir);
                foreach ($filesInDir as $file) {
                    // Ignorer . et ..
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    
                    // Si le fichier n'est pas dans la base de données, le supprimer
                    if (!in_array($file, $dbFilenames)) {
                        $orphanFilePath = $userFontsDir . '/' . $file;
                        if (is_file($orphanFilePath)) {
                            unlink($orphanFilePath);
                            $cleanedFiles++;
                            
                            // Supprimer également la version dans le dossier global
                            $globalFontDir = '/app/typeset/fonts';
                            $globalFilename = 'user_' . $userId . '_' . $file;
                            $globalFontPath = $globalFontDir . '/' . $globalFilename;
                            
                            if (file_exists($globalFontPath)) {
                                unlink($globalFontPath);
                            }
                            
                            // Ajouter des informations de débogage
                            $debugInfo[] = [
                                'filename' => $file,
                                'path' => $orphanFilePath,
                                'reason' => 'Fichier orphelin'
                            ];
                        }
                    }
                }
            }
            
            // 4. Vérifier les entrées dupliquées (même nom de fichier pour un utilisateur)
            $stmt = $db->prepare('
                SELECT filename, COUNT(*) as count, MIN(id) as keep_id
                FROM user_fonts 
                WHERE user_id = ?
                GROUP BY filename
                HAVING COUNT(*) > 1
            ');
            $stmt->execute([$userId]);
            $duplicates = $stmt->fetchAll();
            
            // Supprimer les entrées dupliquées en gardant la plus ancienne
            foreach ($duplicates as $duplicate) {
                $stmt = $db->prepare('
                    DELETE FROM user_fonts 
                    WHERE user_id = ? AND filename = ? AND id != ?
                ');
                $stmt->execute([
                    $userId, 
                    $duplicate['filename'], 
                    $duplicate['keep_id']
                ]);
                
                $deletedRows = $stmt->rowCount();
                $deletedCount += $deletedRows;
                
                // Ajouter des informations de débogage
                $debugInfo[] = [
                    'filename' => $duplicate['filename'],
                    'count' => $duplicate['count'],
                    'keep_id' => $duplicate['keep_id'],
                    'deleted' => $deletedRows,
                    'reason' => 'Entrées dupliquées'
                ];
            }
            
            // 5. Détecter et nettoyer les entrées problématiques dans la base de données
            // Vérifier à nouveau toutes les entrées dans la base de données
            $stmt = $db->prepare('SELECT id, filename FROM user_fonts WHERE user_id = ?');
            $stmt->execute([$userId]);
            $allFonts = $stmt->fetchAll();
            
            $problemFontIds = [];
            
            foreach ($allFonts as $font) {
                // Vérifier si le fichier existe réellement
                $filePath = $userFontsDir . '/' . $font['filename'];
                
                // Si le fichier n'existe pas et l'ID n'est pas déjà dans la liste des corrompus
                if ((!file_exists($filePath) || !is_file($filePath)) && !in_array($font['id'], $corruptedFontIds)) {
                    $problemFontIds[] = $font['id'];
                    
                    // Ajouter des informations de débogage
                    $debugInfo[] = [
                        'id' => $font['id'],
                        'filename' => $font['filename'],
                        'path' => $filePath,
                        'reason' => 'Entrée problématique détectée'
                    ];
                }
            }
            
            // Supprimer les entrées problématiques
            if (count($problemFontIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($problemFontIds), '?'));
                $stmt = $db->prepare("DELETE FROM user_fonts WHERE id IN ($placeholders)");
                $stmt->execute($problemFontIds);
                $deletedCount += count($problemFontIds);
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
            error_log('Erreur cleanupCorruptedFonts: ' . $e->getMessage());
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors du nettoyage des polices corrompues: ' . $e->getMessage()
            ];
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
} 