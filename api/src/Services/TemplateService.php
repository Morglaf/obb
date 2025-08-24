<?php
/**
 * Online Book Brew - Service de templates
 * 
 * Service pour gérer les templates (layouts, covers, imposes)
 */

namespace App\Services;

use App\Utils\TemplateUtils;

class TemplateService
{
    /**
     * Chemin du répertoire des templates
     */
    private $typesetDir;
    
    /**
     * Constructeur
     * 
     * @param string $typesetDir Chemin du répertoire des templates
     */
    public function __construct($typesetDir)
    {
        $this->typesetDir = $typesetDir;
    }
    
    /**
     * Récupère tous les templates disponibles
     * 
     * @return array Les templates disponibles (layouts, covers, imposes)
     */
    public function getAllTemplates()
    {
        // Créer les répertoires s'ils n'existent pas
        if (!is_dir($this->typesetDir)) {
            error_log("Répertoire typeset manquant, création: " . $this->typesetDir);
            mkdir($this->typesetDir, 0755, true);
        }
        
        $layoutsDir = $this->typesetDir . DIRECTORY_SEPARATOR . 'layout';
        $coversDir = $this->typesetDir . DIRECTORY_SEPARATOR . 'cover';
        $imposesDir = $this->typesetDir . DIRECTORY_SEPARATOR . 'impose';
        
        // Créer les sous-répertoires s'ils n'existent pas
        foreach ([$layoutsDir, $coversDir, $imposesDir] as $dir) {
            if (!is_dir($dir)) {
                error_log("Répertoire manquant, création: " . $dir);
                mkdir($dir, 0755, true);
            }
        }
        
        // Vérifier si le répertoire typeset existe
        if (!is_dir($this->typesetDir)) {
            error_log("Répertoire typeset introuvable: " . $this->typesetDir);
            return [
                'layouts' => [],
                'covers' => [],
                'imposes' => [],
                'invalidFiles' => [
                    'layouts' => [['file' => 'typeset', 'error' => 'Répertoire typeset introuvable: ' . $this->typesetDir]],
                    'covers' => [],
                    'imposes' => []
                ]
            ];
        }
        
        // Récupérer les layouts
        $layouts = [];
        $layoutFiles = is_dir($layoutsDir) ? glob($layoutsDir . '/*.tex') : false;
        $invalidLayouts = [];
        
        if ($layoutFiles === false) {
            error_log("Erreur lors de la lecture du dossier des layouts: " . $layoutsDir);
        } else {
            foreach ($layoutFiles as $file) {
                try {
                    $layouts[] = TemplateUtils::parseLayoutFile($file);
                } catch (\Exception $e) {
                    error_log("Erreur lors du parsing du fichier $file: " . $e->getMessage());
                    $invalidLayouts[] = [
                        'file' => basename($file),
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        // Récupérer les couvertures avec métadonnées
        $coverFiles = glob($coversDir . '/*.tex');
        $covers = [];
        $invalidCovers = [];
        
        if ($coverFiles !== false) {
            foreach ($coverFiles as $file) {
                try {
                    $metadata = TemplateUtils::extractFileMetadata($file);
                    if ($metadata) {
                        // Associer les métadonnées directement pour la cohérence avec le format côté client
                        $metadata['name'] = $metadata['filename']; // Ajouter name pour la rétrocompatibilité
                        $covers[] = $metadata;
                    }
                } catch (\Exception $e) {
                    error_log("Erreur lors du parsing du fichier $file: " . $e->getMessage());
                    $invalidCovers[] = [
                        'file' => basename($file),
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        // Récupérer les impositions avec métadonnées
        $imposeFiles = glob($imposesDir . '/*.tex');
        $imposes = [];
        $invalidImposes = [];
        
        if ($imposeFiles !== false) {
            foreach ($imposeFiles as $file) {
                try {
                    $metadata = TemplateUtils::extractFileMetadata($file);
                    if ($metadata) {
                        // Associer les métadonnées directement pour la cohérence avec le format côté client
                        $metadata['name'] = $metadata['filename']; // Ajouter name pour la rétrocompatibilité
                        $imposes[] = $metadata;
                    }
                } catch (\Exception $e) {
                    error_log("Erreur lors du parsing du fichier $file: " . $e->getMessage());
                    $invalidImposes[] = [
                        'file' => basename($file),
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        return [
            'layouts' => $layouts,
            'covers' => $covers,
            'imposes' => $imposes,
            'invalidFiles' => [
                'layouts' => $invalidLayouts,
                'covers' => $invalidCovers,
                'imposes' => $invalidImposes
            ]
        ];
    }
    
    /**
     * Récupère les variables d'un fichier de couverture
     * 
     * @param string $coverName Nom du fichier de couverture
     * @return array Les variables trouvées
     */
    public function getCoverVariables($coverName)
    {
        // Nettoyer le nom pour éviter les attaques par traversée de répertoire
        $coverName = basename($coverName);
        
        // Construire le chemin du fichier dans le répertoire système
        $coverFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'cover' . DIRECTORY_SEPARATOR . $coverName . '.tex';
        
        // Vérifier si le fichier existe dans le système
        if (!file_exists($coverFile)) {
            // Si non, chercher dans les templates utilisateur
            $config = require __DIR__ . '/../../config/app.php';
            $userTemplatesDir = $config['paths']['user_templates'];
            
            if (is_dir($userTemplatesDir)) {
                $found = false;
                error_log("Recherche de la couverture utilisateur: $coverName dans $userTemplatesDir");
                
                // Chercher dans tous les répertoires utilisateur
                $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                foreach ($userDirs as $userDir) {
                    error_log("Vérification du répertoire utilisateur: $userDir");
                    $coverDir = $userDir . DIRECTORY_SEPARATOR . 'cover';
                    
                    if (!is_dir($coverDir)) {
                        error_log("Répertoire de couverture inexistant: $coverDir");
                        continue;
                    }
                    
                    // Première tentative: recherche directe
                    $directFile = $coverDir . DIRECTORY_SEPARATOR . $coverName . '.tex';
                    if (file_exists($directFile)) {
                        $coverFile = $directFile;
                        $found = true;
                        error_log("Fichier trouvé directement: $coverFile");
                        break;
                    }
                    
                    // Deuxième tentative: recherche avec le motif *-cover-*
                    // Si le nom ne contient pas déjà 'cover', essayer les formats alternatifs
                    if (strpos($coverName, '-cover-') === false) {
                        // Essai 1: [baseName]-cover-*.tex
                        $pattern = $coverDir . DIRECTORY_SEPARATOR . $coverName . '-cover-*.tex';
                        error_log("Recherche avec motif 1: $pattern");
                        $matchingFiles = glob($pattern);
                        
                        if (!empty($matchingFiles)) {
                            $coverFile = $matchingFiles[0];
                            $found = true;
                            error_log("Fichier trouvé avec motif 1: $coverFile");
                            break;
                        }
                        
                        // Essai 2: recherche de toutes les couvertures qui contiennent le nom de base
                        $pattern = $coverDir . DIRECTORY_SEPARATOR . '*' . $coverName . '*.tex';
                        error_log("Recherche avec motif 2: $pattern");
                        $matchingFiles = glob($pattern);
                        
                        if (!empty($matchingFiles)) {
                            $coverFile = $matchingFiles[0];
                            $found = true;
                            error_log("Fichier trouvé avec motif 2: $coverFile");
                            break;
                        }
                    }
                    
                    // Essai 3: Rechercher simplement tous les fichiers .tex dans le répertoire
                    $pattern = $coverDir . DIRECTORY_SEPARATOR . '*.tex';
                    error_log("Recherche de tous les fichiers .tex: $pattern");
                    $matchingFiles = glob($pattern);
                    
                    foreach ($matchingFiles as $file) {
                        error_log("Fichier .tex trouvé: " . basename($file));
                    }
                }
                
                if (!$found) {
                    throw new \Exception("Fichier de couverture introuvable pour: $coverName");
                }
            } else {
                throw new \Exception("Fichier de couverture introuvable: $coverName");
            }
        }
        
        error_log("Extraction des variables depuis le fichier de couverture: $coverFile");
        
        // Extraire les variables
        $variables = TemplateUtils::extractVariablesFromFile($coverFile);
        
        // Créer des objets pour chaque variable
        $variableOptions = [];
        foreach ($variables as $var) {
            $variableOptions[] = [
                'name' => $var,
                'type' => stripos($var, 'image') !== false ? 'image' : 'text',
                'description' => "Variable pour la couverture"
            ];
        }
        
        return $variableOptions;
    }
} 