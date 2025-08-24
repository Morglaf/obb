<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Fonction pour extraire les métadonnées des commentaires en début de fichier
function extractMetadataFromComments($content) {
    $metadata = [
        'title' => '',
        'description' => '',
        'version' => ''
    ];
    
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '%') !== 0) {
            break; // Sortir de la boucle quand on ne trouve plus de commentaires
        }
        
        $line = trim(substr($line, 1)); // Supprimer le caractère %
        if (strpos($line, 'title:') === 0) {
            $metadata['title'] = trim(substr($line, 6));
        } elseif (strpos($line, 'description:') === 0) {
            $metadata['description'] = trim(substr($line, 12));
        } elseif (strpos($line, 'version:') === 0) {
            $metadata['version'] = trim(substr($line, 8));
        }
    }
    
    return $metadata;
}

function getOptionDescription($content, $optionName) {
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, $optionName) !== false) {
            // Remonter pour trouver le commentaire le plus proche
            for ($j = $i - 1; $j >= 0 && $j >= $i - 3; $j--) {
                $line = trim($lines[$j]);
                if (strpos($line, '%') === 0 && strpos($line, '%%') !== 0) {
                    return trim(substr($line, 1));
                }
            }
            break;
        }
    }
    return '';
}

// Fonction pour valider et parser un nom de fichier layout
function parseLayoutFilename($filename) {
    // Regex pour valider le format [nom]-[format]-layout.tex
    if (!preg_match('/^([^-]+)-([^-]+)-layout\.tex$/', $filename, $matches)) {
        return null;
    }
    
    return [
        'style' => $matches[1],
        'format' => $matches[2]
    ];
}

// Fonction pour valider et parser un nom de fichier cover
function parseCoverFilename($filename) {
    // Regex pour valider le format [nom]-[format]-cover-[format_papier].tex
    if (!preg_match('/^([^-]+)-([^-]+)-cover-([^-]+)\.tex$/', $filename, $matches)) {
        return null;
    }
    
    return [
        'style' => $matches[1],
        'format' => $matches[2],
        'paper_format' => $matches[3]
    ];
}

// Fonction pour valider et parser un nom de fichier impose
function parseImposeFilename($filename) {
    // Regex pour valider le format [format]-[format_papier]-[nb][type].tex
    if (!preg_match('/^([^-]+)-([^-]+)-(\d+)(signature|spread)\.tex$/', $filename, $matches)) {
        return null;
    }
    
    return [
        'format' => $matches[1],
        'paper_format' => $matches[2],
        'page_count' => $matches[3],
        'type' => $matches[4]
    ];
}

function parseLayoutFile($filePath) {
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Impossible de lire le fichier: $filePath");
    }

    $filename = basename($filePath);
    $parsedFilename = parseLayoutFilename($filename);
    if (!$parsedFilename) {
        throw new Exception("Format de nom de fichier layout invalide: $filename");
    }
    
    $metadata = extractMetadataFromComments($content);
    $options = [
        'booleans' => [],
        'variables' => []
    ];
    
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
                'default' => isset($match[3]) && $match[3] === 'true',
                'description' => getOptionDescription($content, $optionName)
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
                'type' => stripos($var, 'image') !== false ? 'image' : 'text',
                'description' => getOptionDescription($content, $var)
            ];
        }
    }
    $options['variables'] = array_values($variableOptions);
    
    // Vérifier s'il existe une image de prévisualisation
    $previewPath = substr($filePath, 0, -4) . '.png'; // Remplacer .tex par .png
    $hasPreview = file_exists($previewPath);
    $previewUrl = $hasPreview ? str_replace(__DIR__ . '/../', '/', $previewPath) : null;
    
    return [
        'name' => basename($filePath, '.tex'),
        'path' => $filePath,
        'style' => $parsedFilename['style'],
        'format' => $parsedFilename['format'],
        'title' => $metadata['title'] ?: basename($filePath, '.tex'),
        'description' => $metadata['description'],
        'version' => $metadata['version'],
        'preview_url' => $previewUrl,
        'options' => $options
    ];
}

function processCoverFile($filePath) {
    $filename = basename($filePath);
    $parsedFilename = parseCoverFilename($filename);
    if (!$parsedFilename) {
        return null;
    }
    
    $content = file_get_contents($filePath);
    $metadata = extractMetadataFromComments($content);
    
    // Vérifier s'il existe une image de prévisualisation
    $previewPath = substr($filePath, 0, -4) . '.png'; // Remplacer .tex par .png
    $hasPreview = file_exists($previewPath);
    $previewUrl = $hasPreview ? str_replace(__DIR__ . '/../', '/', $previewPath) : null;
    
    return [
        'name' => basename($filePath, '.tex'),
        'path' => $filePath,
        'style' => $parsedFilename['style'],
        'format' => $parsedFilename['format'],
        'paper_format' => $parsedFilename['paper_format'],
        'title' => $metadata['title'] ?: basename($filePath, '.tex'),
        'description' => $metadata['description'],
        'version' => $metadata['version'],
        'preview_url' => $previewUrl
    ];
}

function processImposeFile($filePath) {
    $filename = basename($filePath);
    $parsedFilename = parseImposeFilename($filename);
    if (!$parsedFilename) {
        return null;
    }
    
    $content = file_get_contents($filePath);
    $metadata = extractMetadataFromComments($content);
    
    // Vérifier s'il existe une image de prévisualisation
    $previewPath = substr($filePath, 0, -4) . '.png'; // Remplacer .tex par .png
    $hasPreview = file_exists($previewPath);
    $previewUrl = $hasPreview ? str_replace(__DIR__ . '/../', '/', $previewPath) : null;
    
    // Log pour déboguer l'extraction du format
    error_log("Traitement d'imposition: $filename, format extrait: " . $parsedFilename['format']);
    
    return [
        'name' => basename($filePath, '.tex'),
        'path' => $filePath,
        'format' => $parsedFilename['format'], // Le format est la première partie du nom
        'paper_format' => $parsedFilename['paper_format'],
        'page_count' => $parsedFilename['page_count'],
        'type' => $parsedFilename['type'],
        'title' => $metadata['title'] ?: basename($filePath, '.tex'),
        'description' => $metadata['description'],
        'version' => $metadata['version'],
        'preview_url' => $previewUrl
    ];
}

try {
    // Dans le conteneur Docker, le volume est monté sur /app/typeset
    $layoutsDir = '/app/typeset/layout';
    $coversDir = '/app/typeset/cover';
    $imposesDir = '/app/typeset/impose';
    
    // Variables pour suivre les erreurs
    $invalidFiles = [
        'layouts' => [],
        'covers' => [],
        'imposes' => []
    ];
    
    // Récupérer les layouts
    $layouts = [];
    $layoutFiles = glob($layoutsDir . '/*.tex');
    if ($layoutFiles === false) {
        throw new Exception("Erreur lors de la lecture du dossier des layouts");
    }
    
    foreach ($layoutFiles as $file) {
        try {
            $layout = parseLayoutFile($file);
            $layouts[] = $layout;
        } catch (Exception $e) {
            error_log("Erreur lors du parsing du fichier $file: " . $e->getMessage());
            $invalidFiles['layouts'][] = [
                'file' => basename($file),
                'error' => $e->getMessage()
            ];
            // Continue avec le fichier suivant
            continue;
        }
    }
    
    // Récupérer les couvertures
    $covers = [];
    $coverFiles = glob($coversDir . '/*.tex');
    if ($coverFiles !== false) {
        foreach ($coverFiles as $file) {
            $cover = processCoverFile($file);
            if ($cover) {
                $covers[] = $cover;
            } else {
                $invalidFiles['covers'][] = [
                    'file' => basename($file),
                    'error' => 'Format de nom de fichier cover invalide'
                ];
            }
        }
    }
    
    // Récupérer les impositions
    $imposes = [];
    $imposeFiles = glob($imposesDir . '/*.tex');
    if ($imposeFiles !== false) {
        foreach ($imposeFiles as $file) {
            $impose = processImposeFile($file);
            if ($impose) {
                $imposes[] = $impose;
            } else {
                $invalidFiles['imposes'][] = [
                    'file' => basename($file),
                    'error' => 'Format de nom de fichier impose invalide'
                ];
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'layouts' => $layouts,
        'covers' => $covers,
        'imposes' => $imposes,
        'invalidFiles' => $invalidFiles
    ]);
} catch (Exception $e) {
    error_log("Erreur dans layouts.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 