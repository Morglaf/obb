<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class VersionController
{
    /**
     * Retourne les informations de version de l'application
     */
    public function getVersion(Request $request, Response $response): Response
    {
        try {
            $versionFile = __DIR__ . '/../../version.json';
            
            if (file_exists($versionFile)) {
                $versionData = json_decode(file_get_contents($versionFile), true);
                
                if ($versionData) {
                    $responseData = [
                        'status' => 'success',
                        'data' => $versionData
                    ];
                } else {
                    throw new \Exception('Erreur lors du parsing du fichier de version');
                }
            } else {
                // Fallback si le fichier n'existe pas
                $responseData = [
                    'status' => 'success',
                    'data' => [
                        'version' => '0.1.0',
                        'fullVersion' => '0.1.0.0',
                        'commitHash' => 'unknown',
                        'commitDate' => date('Y-m-d'),
                        'commitCount' => '0',
                        'branch' => 'unknown',
                        'buildDate' => date('c'),
                        'buildDateFormatted' => date('d F Y à H:i')
                    ]
                ];
            }
            
            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
                
        } catch (\Exception $e) {
            error_log('Erreur lors de la récupération de la version: ' . $e->getMessage());
            
            $responseData = [
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de la version',
                'error' => $e->getMessage()
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
                ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->withStatus(500);
        }
    }
}
