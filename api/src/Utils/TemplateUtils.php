<?php
/**
 * Online Book Brew - Utilitaires pour les templates
 * 
 * Fonctions utilitaires pour la manipulation des templates LaTeX
 */

namespace App\Utils;

class TemplateUtils
{
    /**
     * Vérifie et nettoie les métadonnées pour les templates
     * 
     * @param array $metadata Les métadonnées fournies par l'utilisateur
     * @return array Les métadonnées nettoyées
     */
    public static function validateAndCleanMetadata($metadata)
    {
        $allowedFields = ['titre', 'auteur', 'edition', 'spineThickness', 'imagecouv'];
        $cleaned = [];
        
        foreach ($allowedFields as $field) {
            if (isset($metadata[$field])) {
                // Traitement spécial pour les noms de fichiers d'images
                if ($field === 'imagecouv' || stripos($field, 'image') !== false) {
                    // Pour les images, on garde le nom de fichier tel quel sans échapper les caractères spéciaux
                    $cleaned[$field] = $metadata[$field];
                } else {
                    // Nettoyer pour éviter les injections LaTeX
                    $cleaned[$field] = str_replace(
                        ['\\', '$', '%', '&', '_', '{', '}', '~', '^', '#'], 
                        ['\\textbackslash{}', '\\$', '\\%', '\\&', '\\_', '\\{', '\\}', '\\textasciitilde{}', '\\textasciicircum{}', '\\#'], 
                        $metadata[$field]
                    );
                }
            } else {
                // Valeurs par défaut
                switch ($field) {
                    case 'titre':
                        $cleaned[$field] = 'Document sans titre';
                        break;
                    case 'auteur':
                        $cleaned[$field] = 'Anonyme';
                        break;
                    case 'edition':
                        $cleaned[$field] = 'Edition OnlineBookBrew';
                        break;
                    case 'spineThickness':
                        $cleaned[$field] = '2mm';
                        break;
                    case 'imagecouv':
                        $cleaned[$field] = '';
                        break;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Remplace les variables et met à jour les options booléennes dans un fichier LaTeX
     * 
     * @param string $filePath Chemin du fichier LaTeX
     * @param array $metadata Métadonnées à remplacer
     * @param array $booleanOptions Options booléennes à mettre à jour
     * @return bool True si succès
     */
    public static function updateLatexFile($filePath, $metadata, $booleanOptions)
    {
        // Lire le contenu du fichier
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Impossible de lire le fichier LaTeX: $filePath");
        }
        
        // Remplacer les variables
        foreach ($metadata as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        // Mettre à jour les options booléennes
        foreach ($booleanOptions as $option => $value) {
            // Chercher la ligne qui définit l'option
            $pattern = '/\\\\' . $option . '(true|false)/';
            $replacement = '\\' . $option . ($value ? 'true' : 'false');
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        // Écrire le contenu mis à jour
        if (file_put_contents($filePath, $content) === false) {
            throw new \Exception("Impossible d'écrire le fichier LaTeX mis à jour");
        }
        
        return true;
    }
    
    /**
     * Parser un fichier de layout pour extraire les métadonnées et options
     * 
     * @param string $filePath Chemin du fichier de layout
     * @return array Les données parsées du layout
     */
    public static function parseLayoutFile($filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("Impossible de lire le fichier: $filePath");
        }
    
        $options = [
            'booleans' => [],
            'variables' => []
        ];
        
        // Extraire les métadonnées des commentaires au début du fichier
        $metadata = [
            'title' => '',
            'description' => '',
            'version' => '',
            'author' => ''
        ];
        
        // Recherche des métadonnées dans les commentaires
        preg_match_all('/^%\s*([a-zA-Z]+):\s*(.+)$/m', $content, $metaMatches, PREG_SET_ORDER);
        foreach ($metaMatches as $match) {
            $key = strtolower($match[1]);
            if (array_key_exists($key, $metadata)) {
                $metadata[$key] = trim($match[2]);
            }
        }
        
        // Recherche des options booléennes
        $pattern = '/\\\\newif\\\\if(\w+)\s*\\\\(\w+)(true|false)?/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        $booleanOptions = [];
        foreach ($matches as $match) {
            $optionName = $match[1];
            if (!isset($booleanOptions[$optionName])) {
                $booleanOptions[$optionName] = [
                    'name' => $optionName,
                    'type' => 'boolean',
                    'default' => isset($match[3]) && $match[3] === 'true'
                ];
            }
        }
        $options['booleans'] = array_values($booleanOptions);
        
        // Recherche des variables
        preg_match_all('/\{\{(\w+)\}\}/', $content, $matches);
        $variables = array_unique($matches[1]);
        $variableOptions = [];
        foreach ($variables as $var) {
            if (!isset($variableOptions[$var])) {
                $variableOptions[$var] = [
                    'name' => $var,
                    'type' => stripos($var, 'image') !== false ? 'image' : 'text'
                ];
            }
        }
        $options['variables'] = array_values($variableOptions);
        
        return [
            'name' => basename($filePath, '.tex'),
            'path' => $filePath,
            'options' => $options,
            'metadata' => $metadata
        ];
    }
    
    /**
     * Extraire les métadonnées d'un fichier LaTeX
     * 
     * @param string $filePath Chemin du fichier LaTeX
     * @return array|null Les métadonnées extraites ou null en cas d'erreur
     */
    public static function extractFileMetadata($filePath)
    {
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        
        $metadata = [
            'title' => '',
            'description' => '',
            'version' => '',
            'author' => ''
        ];
        
        // Recherche des métadonnées dans les commentaires
        preg_match_all('/^%\s*([a-zA-Z]+):\s*(.+)$/m', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = strtolower($match[1]);
            if (array_key_exists($key, $metadata)) {
                $metadata[$key] = trim($match[2]);
            }
        }
        
        $metadata['filename'] = basename($filePath, '.tex');
        
        return $metadata;
    }
    
    /**
     * Extraire les variables d'un fichier LaTeX
     * 
     * @param string $filePath Chemin du fichier LaTeX
     * @return array Les variables extraites
     */
    public static function extractVariablesFromFile($filePath)
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }
        
        $variables = [];
        preg_match_all('/\{\{(\w+)\}\}/', $content, $matches);
        
        if (isset($matches[1]) && is_array($matches[1])) {
            foreach ($matches[1] as $var) {
                if (!in_array($var, $variables)) {
                    $variables[] = $var;
                }
            }
        }
        
        return $variables;
    }
    
    /**
     * Extraire les métadonnées d'un document Markdown
     * 
     * @param string $content Contenu Markdown
     * @return array Les métadonnées extraites
     */
    public static function extractMarkdownMetadata($content)
    {
        $metadata = [
            'title' => '',
            'author' => '',
            'date' => '',
            'description' => '',
            'tags' => []
        ];
        
        // Essayer d'extraire le YAML frontmatter
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            $yamlContent = $matches[1];
            $lines = explode("\n", $yamlContent);
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, ':') === false) {
                    continue;
                }
                
                $colonIndex = strpos($line, ':');
                $key = strtolower(trim(substr($line, 0, $colonIndex)));
                $value = trim(substr($line, $colonIndex + 1));
                
                // Supprimer les guillemets si présents
                $value = trim($value, '"\'');
                
                switch ($key) {
                    case 'title':
                    case 'titre':
                        $metadata['title'] = $value;
                        break;
                    case 'author':
                    case 'auteur':
                        $metadata['author'] = $value;
                        break;
                    case 'date':
                        $metadata['date'] = $value;
                        break;
                    case 'description':
                    case 'desc':
                        $metadata['description'] = $value;
                        break;
                    case 'tags':
                        $metadata['tags'] = array_map('trim', explode(',', $value));
                        break;
                }
            }
        }
        
        // Si pas de YAML, essayer d'extraire le premier titre H1
        if (empty($metadata['title'])) {
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                $metadata['title'] = trim($matches[1]);
            }
        }
        
        // Si toujours pas de titre, essayer H2
        if (empty($metadata['title'])) {
            if (preg_match('/^##\s+(.+)$/m', $content, $matches)) {
                $metadata['title'] = trim($matches[1]);
            }
        }
        
        return $metadata;
    }
    
    /**
     * Générer un nom de fichier pour le document basé sur les métadonnées
     * 
     * @param array $metadata Métadonnées du document
     * @param string $documentId ID du document
     * @param string $type Type de document ('document', 'cover', 'impose')
     * @return string Nom de fichier généré
     */
    public static function generateDocumentFilename($metadata, $documentId, $type = 'document')
    {
        // Utiliser l'heure locale du serveur
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $dateStr = $now->format('dmY');
        $timeStr = $now->format('His');
        
        $filename = '';
        
        if (!empty($metadata['title'])) {
            // Nettoyer le titre pour en faire un nom de fichier valide
            $cleanTitle = preg_replace('/[<>:"\/\\|?*]/', '', $metadata['title']); // Caractères interdits
            $cleanTitle = preg_replace('/\s+/', '_', $cleanTitle); // Espaces en underscores
            $cleanTitle = substr($cleanTitle, 0, 30); // Limiter la longueur
            
            $filename = $cleanTitle;
        } else if (!empty($metadata['titre'])) {
            // Utiliser la variable 'titre' si 'title' n'existe pas
            $cleanTitle = preg_replace('/[<>:"\/\\|?*]/', '', $metadata['titre']); // Caractères interdits
            $cleanTitle = preg_replace('/\s+/', '_', $cleanTitle); // Espaces en underscores
            $cleanTitle = substr($cleanTitle, 0, 30); // Limiter la longueur
            
            $filename = $cleanTitle;
        } else {
            $filename = 'sans_titre';
        }
        
        // Ajouter le préfixe selon le type
        if ($type === 'cover') {
            $filename = 'cover_' . $filename;
        } else if ($type === 'impose') {
            $filename = 'impose_' . $filename;
        }
        
        // Format final : type_titre-date-heure
        return $filename . '-' . $dateStr . '-' . $timeStr;
    }
} 