<?php
/**
 * Online Book Brew - Service de Conversion Markdown
 * 
 * Service pour gérer la conversion des documents Markdown en LaTeX via différentes méthodes
 */

namespace App\Services;

class MdConversionService
{
    /**
     * Configuration de l'application
     */
    private $config;
    
    /**
     * Dossier workspace
     */
    private $workspaceDir;
    
    /**
     * Méthode de conversion actuelle
     */
    private $conversionMethod;
    
    /**
     * Service de pré-traitement Obsidian
     */
    private $obsidianPreProcessingService;
    
    /**
     * Constructeur
     * 
     * @param array $config Configuration de l'application
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->workspaceDir = $config['paths']['workspace'];
        $this->conversionMethod = 'pandoc_direct'; // Méthode par défaut
        
        // Initialiser le service de pré-traitement Obsidian
        $this->obsidianPreProcessingService = new \App\Services\ObsidianPreProcessingService($this->workspaceDir);
    }
    
    /**
     * Définit la méthode de conversion à utiliser
     * 
     * @param string $method Méthode de conversion ('pandoc_direct' ou 'obsidian_export')
     */
    public function setConversionMethod($method)
    {
        $this->conversionMethod = $method;
        error_log("Méthode de conversion définie: " . $method);
    }
    
    /**
     * Convertit un fichier Markdown en LaTeX selon la méthode sélectionnée
     * 
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @param string $inputFile Nom du fichier d'entrée Markdown (par défaut: content.md)
     * @param string $outputFile Nom du fichier de sortie LaTeX (par défaut: content.tex)
     * @return int Code de retour de la commande
     * @throws \Exception En cas d'erreur de conversion
     */
    public function convertMarkdownToLatex($relativeWorkDir, $inputFile = 'content.md', $outputFile = 'content.tex')
    {
        error_log("=== CONVERSION MARKDOWN VERS LATEX ===");
        error_log("Méthode: " . $this->conversionMethod);
        error_log("Dossier de travail: " . $relativeWorkDir);
        error_log("Fichier d'entrée: " . $inputFile);
        error_log("Fichier de sortie: " . $outputFile);
        
        switch ($this->conversionMethod) {
            case 'obsidian_export':
                return $this->convertWithObsidianExport($relativeWorkDir, $inputFile, $outputFile);
            case 'pandoc_direct':
            default:
                return $this->convertWithPandocDirect($relativeWorkDir, $inputFile, $outputFile);
        }
    }
    
    /**
     * Conversion simple avec Pandoc (sans post-traitement)
     * 
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @param string $inputFile Nom du fichier d'entrée Markdown
     * @param string $outputFile Nom du fichier de sortie LaTeX
     * @return int Code de retour
     */
    private function convertWithPandocDirect($relativeWorkDir, $inputFile, $outputFile)
    {
        error_log("=== CONVERSION PANDOC DIRECT ===");
        
        // Construire la commande pandoc simple
        $pandocCmd = "pandoc {$inputFile} -o {$outputFile}";
        error_log("Commande pandoc: " . $pandocCmd);
        
        // Exécuter la conversion via le conteneur processor
        $pandocResult = $this->executeInProcessor($relativeWorkDir, $pandocCmd);
        
        if ($pandocResult !== 0) {
            error_log("Erreur de pandoc dans le conteneur processor");
            throw new \Exception("Erreur lors de la conversion Markdown vers LaTeX");
        }
        
        error_log("Conversion Pandoc direct réussie");
        return $pandocResult;
    }
    
    /**
     * Conversion avec Obsidian-export + Pandoc + post-traitement léger
     * 
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @param string $inputFile Nom du fichier d'entrée Markdown
     * @param string $outputFile Nom du fichier de sortie LaTeX
     * @return int Code de retour
     */
    private function convertWithObsidianExport($relativeWorkDir, $inputFile, $outputFile)
    {
        error_log("=== CONVERSION OBSIDIAN-EXPORT + PANDOC ===");
        
        // Étape 1: Créer un dossier temporaire pour la conversion
        $tempDir = "temp_obsidian_" . uniqid();
        $tempInputDir = $tempDir . "/input";
        $tempOutputDir = $tempDir . "/output";
        
        // Préparer la structure des dossiers
        $prepareCmd = "mkdir -p {$tempInputDir} {$tempOutputDir} && cp {$inputFile} {$tempInputDir}/";
        error_log("Commande de préparation: " . $prepareCmd);
        
        $prepareResult = $this->executeInProcessor($relativeWorkDir, $prepareCmd);
        if ($prepareResult !== 0) {
            error_log("Erreur de préparation des dossiers");
            throw new \Exception("Erreur lors de la préparation des dossiers");
        }
        
        // Étape 2: Conversion Obsidian → Markdown standard
        $obsidianCmd = "obsidian-export {$tempInputDir} {$tempOutputDir}";
        error_log("Commande obsidian-export: " . $obsidianCmd);
        
        $obsidianResult = $this->executeInProcessor($relativeWorkDir, $obsidianCmd);
        if ($obsidianResult !== 0) {
            error_log("Erreur d'obsidian-export dans le conteneur processor");
            throw new \Exception("Erreur lors de la conversion Obsidian vers Markdown");
        }
        
        // NOUVEAU: Sauvegarder le fichier après obsidian-export pour debug
        error_log("=== SAUVEGARDE INTERMÉDIAIRE APRÈS OBSIDIAN-EXPORT ===");
        $outputDirPath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . $tempOutputDir;
        if (is_dir($outputDirPath)) {
            $outputFiles = scandir($outputDirPath);
            error_log("Fichiers dans le dossier de sortie: " . implode(', ', $outputFiles));
            
            // Chercher le fichier Markdown principal
            $markdownFile = null;
            foreach ($outputFiles as $file) {
                if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                    $markdownFile = $file;
                    break;
                }
            }
            
            if ($markdownFile) {
                error_log("Fichier Markdown trouvé: " . $markdownFile);
                
                // Sauvegarder le fichier intermédiaire dans le workspace principal
                $sourcePath = $outputDirPath . DIRECTORY_SEPARATOR . $markdownFile;
                $targetPath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . "content-obsidianexport.md";
                
                if (copy($sourcePath, $targetPath)) {
                    error_log("Fichier intermédiaire sauvegardé: content-obsidianexport.md");
                    
                    // Afficher le contenu pour debug
                    $content = file_get_contents($targetPath);
                    if ($content !== false) {
                        error_log("Taille du fichier: " . strlen($content) . " caractères");
                        error_log("Premiers 200 caractères: " . substr($content, 0, 200));
                        
                        // Vérifier si les notes de bas de page Obsidian sont présentes
                        if (preg_match('/\^\[/', $content)) {
                            error_log("Notes de bas de page Obsidian DÉTECTÉES dans le fichier intermédiaire");
                        } else {
                            error_log("AUCUNE note de bas de page Obsidian détectée dans le fichier intermédiaire");
                        }
                    }
                } else {
                    error_log("ERREUR: Impossible de sauvegarder le fichier intermédiaire");
                }
            } else {
                error_log("ATTENTION: Aucun fichier Markdown trouvé dans le dossier de sortie");
            }
        } else {
            error_log("ATTENTION: Dossier de sortie introuvable: " . $outputDirPath);
        }
        
        // Étape 2.5: Corriger les notes de bas de page échappées par obsidian-export
        if (!$markdownFile) {
            error_log("ERREUR: Impossible de trouver le fichier Markdown pour Pandoc");
            throw new \Exception("Fichier Markdown introuvable après obsidian-export");
        }
        
        // NOUVEAU: Corriger les notes de bas de page échappées par obsidian-export
        error_log("=== CORRECTION DES NOTES DE BAS DE PAGE ÉCHAPPÉES ===");
        $markdownPath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . $tempOutputDir . DIRECTORY_SEPARATOR . $markdownFile;
        
        if (file_exists($markdownPath)) {
            $content = file_get_contents($markdownPath);
            if ($content !== false) {
                error_log("Correction des notes de bas de page échappées...");
                
                // Remplacer ^\[contenu\] par ^[contenu] (dé-échapper)
                $originalContent = $content;
                error_log("Contenu original, taille: " . strlen($content) . " caractères");
                
                // Pattern plus simple et sûr pour dé-échapper
                $content = str_replace('^\\[', '^[', $content);
                $content = str_replace('\\]', ']', $content);
                
                $changes = (substr_count($originalContent, '^\\[') - substr_count($content, '^\\[')) + 
                           (substr_count($originalContent, '\\]') - substr_count($content, '\\]'));
                
                if ($changes > 0) {
                    error_log("Notes de bas de page corrigées: " . $changes);
                    error_log("Contenu après correction, taille: " . strlen($content) . " caractères");
                    
                    // Sauvegarder le fichier corrigé
                    if (file_put_contents($markdownPath, $content) !== false) {
                        error_log("Fichier Markdown corrigé sauvegardé");
                        
                        // Mettre à jour le fichier intermédiaire aussi
                        $intermediatePath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . "content-obsidianexport.md";
                        if (file_put_contents($intermediatePath, $content) !== false) {
                            error_log("Fichier intermédiaire mis à jour avec les corrections");
                        }
                    } else {
                        error_log("ERREUR: Impossible de sauvegarder le fichier corrigé");
                    }
                } else {
                    error_log("Aucune note de bas de page échappée trouvée");
                }
            } else {
                error_log("ERREUR: Impossible de lire le fichier Markdown pour correction");
            }
        } else {
            error_log("ATTENTION: Fichier Markdown introuvable pour correction: " . $markdownPath);
        }
        
        // Étape 3: Conversion Markdown → LaTeX avec Pandoc
        if ($markdownFile) {
            $pandocCmd = "pandoc {$tempOutputDir}/{$markdownFile} -o {$outputFile}";
        error_log("Commande pandoc: " . $pandocCmd);
        } else {
            error_log("ERREUR: Impossible de trouver le fichier Markdown pour Pandoc");
            throw new \Exception("Fichier Markdown introuvable après obsidian-export");
        }
        
        $pandocResult = $this->executeInProcessor($relativeWorkDir, $pandocCmd);
        if ($pandocResult !== 0) {
            error_log("Erreur de pandoc dans le conteneur processor");
            throw new \Exception("Erreur lors de la conversion Markdown vers LaTeX");
        }
        
        // Étape 4: Nettoyer les dossiers temporaires
        $cleanupCmd = "rm -rf {$tempDir}";
        $this->executeInProcessor($relativeWorkDir, $cleanupCmd);
        
        // Étape 5: Post-traitement léger pour les tableaux
        $this->lightPostProcessing($relativeWorkDir, $outputFile);
        
        error_log("Conversion Obsidian-export + Pandoc réussie");
        return $pandocResult;
    }
    
    /**
     * Post-traitement avancé pour corriger les tableaux et autres éléments
     * 
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @param string $outputFile Nom du fichier LaTeX à traiter
     */
    private function lightPostProcessing($relativeWorkDir, $outputFile)
    {
        error_log("=== POST-TRAITEMENT AVANCÉ ===");
        
        $fullPath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . $outputFile;
        
        if (!file_exists($fullPath)) {
            error_log("ERREUR: Fichier LaTeX introuvable: " . $fullPath);
            return;
        }
        
        $content = file_get_contents($fullPath);
        if ($content === false) {
            error_log("ERREUR: Impossible de lire le fichier LaTeX: " . $fullPath);
            return;
        }
        
        // Étape 1: Extraire l'en-tête du tableau depuis le Markdown original
        $markdownPath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . "content.md";
        $tableHeader = $this->extractTableHeaderFromMarkdown($markdownPath);
        
        // Étape 2: Convertir les longtable en tabular avec bordures
        $content = $this->convertLongtableToTabular($content, $tableHeader);
        
        // Étape 3: Corriger les sauts de ligne manquants pour les titres
        $content = $this->fixMissingLineBreaks($content);
        
        // Étape 4: Ajouter des espaces autour des tableaux
        $content = $this->addSpacingAroundTables($content);
        
        // Sauvegarder le contenu traité
        if (file_put_contents($fullPath, $content) === false) {
            error_log("ERREUR: Impossible de sauvegarder le fichier LaTeX traité: " . $fullPath);
            return;
        }
        
        error_log("Post-traitement avancé terminé");
    }
    
    /**
     * Extrait l'en-tête du tableau depuis le Markdown original
     * 
     * @param string $markdownPath Chemin vers le fichier Markdown
     * @return array Liste des en-têtes de colonnes
     */
    private function extractTableHeaderFromMarkdown($markdownPath)
    {
        error_log("=== EXTRACTION DE L'EN-TÊTE DEPUIS LE MARKDOWN ===");
        
        if (!file_exists($markdownPath)) {
            error_log("ERREUR: Fichier Markdown introuvable: " . $markdownPath);
            return [];
        }
        
        $content = file_get_contents($markdownPath);
        if ($content === false) {
            error_log("ERREUR: Impossible de lire le fichier Markdown: " . $markdownPath);
            return [];
        }
        
        $lines = explode("\n", $content);
        $headers = [];
        
        // Pattern simple pour détecter les tableaux Markdown
        // Cherche: | en-tête1 | en-tête2 | ... |
        $tablePattern = '/^\|(.+?)\|$/';
        
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (preg_match($tablePattern, $line)) {
                // C'est une ligne de tableau, extraire les cellules
                $cells = array_filter(array_map('trim', explode('|', $line)));
                if (count($cells) >= 2) { // Au moins 2 colonnes
                    $headers = array_values($cells);
                    error_log("En-tête trouvé à la ligne " . ($i + 1) . ": " . implode(', ', $headers));
                    break;
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * Convertit les environnements longtable en tabular avec bordures et \hline
     * 
     * @param string $content Contenu LaTeX à traiter
     * @param array $tableHeader En-tête extrait du Markdown
     * @return string Contenu LaTeX converti
     */
    private function convertLongtableToTabular($content, $tableHeader = [])
    {
        error_log("=== POST-TRAITEMENT: CONVERSION LONGTABLE -> TABULAR ===");
        
        // Nettoyer les artefacts LaTeX malformés avant de traiter les tableaux
        $content = $this->cleanLatexArtifacts($content);
        
        // Traiter les blocs longtable pour les convertir en tabular avec bordures
        $convertedContent = $this->processLongtableBlocks($content, $tableHeader);
        
        error_log("Conversion longtable -> tabular avec bordures terminée");
        return $convertedContent;
    }
    
    /**
     * Nettoie les artefacts LaTeX malformés générés par pandoc
     * 
     * @param string $content Contenu LaTeX à nettoyer
     * @return string Contenu LaTeX nettoyé
     */
    private function cleanLatexArtifacts($content)
    {
        error_log("=== NETTOYAGE DES ARTEFACTS LATEX ===");
        
        // Nettoyer UNIQUEMENT les minipage malformés qui cassent la structure des tableaux
        // Pattern: \end{minipage} suivi de \hline (dans un contexte de tableau)
        $content = preg_replace('/\\\\end\{minipage\}\s*\\\\hline/', '\\hline', $content);
        
        // Nettoyer les lignes vides multiples
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        
        // Nettoyer les espaces en fin de ligne
        $content = preg_replace('/[ \t]+\n/', "\n", $content);
        
        error_log("Nettoyage des artefacts LaTeX terminé");
        return $content;
    }
    
    /**
     * Traite les blocs longtable pour les convertir en tabular avec bordures
     * 
     * @param string $content Contenu LaTeX à traiter
     * @param array $tableHeader En-tête extrait du Markdown
     * @return string Contenu LaTeX converti
     */
    private function processLongtableBlocks($content, $tableHeader = [])
    {
        // Pattern pour détecter les blocs longtable
        $pattern = '/\\\\begin\{longtable\}\[\]\{([^}]*)\}(.*?)\\\\end\{longtable\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($tableHeader) {
            $colspec = $matches[1];
            $tableContent = $matches[2];
            
            // Compter le nombre de colonnes
            $numCols = $this->countColumnsFromSpec($colspec);
            
            // Créer la spécification tabular avec bordures
            $tabularSpec = $this->makeTabularSpec($numCols);
            
            // Traiter le contenu du tableau avec l'en-tête
            $processedContent = $this->processTableContent($tableContent, $tableHeader, $numCols);
            
            // Construire le nouveau tabular
            return "\\begin{tabular}{{$tabularSpec}}\n\\hline\n{$processedContent}\\end{tabular}";
        }, $content);
    }
    
    /**
     * Compte le nombre de colonnes à partir de la spécification longtable
     * 
     * @param string $spec Spécification de colonnes (ex: "lll", "c@{}c@{}c")
     * @return int Nombre de colonnes
     */
    private function countColumnsFromSpec($spec)
    {
        error_log("Analyse de la spécification: '{$spec}'");
        
        // Supprimer les spécifications @{...} qui sont des décorations
        $spec = preg_replace('/@\{[^}]*\}/', '', $spec);
        
        // Supprimer les spécifications p{...} et les convertir en 'p'
        $spec = preg_replace('/p\{[^}]*\}/', 'p', $spec);
        
        // Supprimer les spécifications >{...} qui sont des commandes de formatage
        $spec = preg_replace('/>\{[^}]*\}/', '', $spec);
        
        // Supprimer les spécifications \real{...} qui sont des calculs
        $spec = preg_replace('/\\\\real\{[^}]*\}/', '', $spec);
        
        // Supprimer les espaces et caractères non-alphabétiques
        $spec = preg_replace('/[^lcrp]/', '', $spec);
        
        $numCols = strlen($spec);
        error_log("Spécification nettoyée: '{$spec}' -> {$numCols} colonnes");
        
        // Fallback: si on ne peut pas déterminer, utiliser 2 colonnes (en-tête + données)
        if ($numCols == 0) {
            $numCols = 2;
            error_log("Impossible de déterminer le nombre de colonnes, fallback à {$numCols}");
        }
        
        return $numCols;
    }
    
    /**
     * Crée la spécification tabular avec bordures
     * 
     * @param int $numCols Nombre de colonnes
     * @return string Spécification tabular (ex: "|c|c|c|")
     */
    private function makeTabularSpec($numCols)
    {
        if ($numCols <= 0) {
            $numCols = 2;
        }
        
        return '|' . str_repeat('c|', $numCols);
    }
    
    /**
     * Traite le contenu du tableau pour ajouter \hline après chaque ligne
     * 
     * @param string $tableContent Contenu du tableau
     * @param array $tableHeader En-tête extrait du Markdown
     * @param int $numCols Nombre de colonnes
     * @return string Contenu traité avec \hline
     */
    private function processTableContent($tableContent, $tableHeader = [], $numCols = 2)
    {
        $lines = explode("\n", $tableContent);
        $processedLines = [];
        
        // Ajouter l'en-tête extrait du Markdown si disponible
        if (!empty($tableHeader) && count($tableHeader) >= $numCols) {
            $headerLine = implode(' & ', array_slice($tableHeader, 0, $numCols)) . ' \\\\';
            $processedLines[] = $headerLine;
            $processedLines[] = '\\hline';
            error_log("En-tête ajouté: {$headerLine}");
        } else {
            error_log("En-tête non ajouté: tableHeader=" . json_encode($tableHeader) . ", numCols={$numCols}");
        }
        
        foreach ($lines as $i => $line) {
            $line = trim($line);
            error_log("Traitement ligne {$i}: '{$line}'");
            
            // Ignorer les lignes de contrôle longtable
            if (in_array($line, ['\\endfirsthead', '\\endhead', '\\endfoot', '\\endlastfoot', 
                                 '\\toprule', '\\midrule', '\\bottomrule'])) {
                error_log("  -> Ignorée (contrôle longtable)");
                continue;
            }
            
            // Ignorer les légendes
            if (strpos($line, '\\caption') === 0) {
                error_log("  -> Ignorée (légende)");
                continue;
            }
            
            // Ignorer les lignes "Continued on next page"
            if (strpos($line, 'Continued on next page') !== false) {
                error_log("  -> Ignorée (continued)");
                continue;
            }
            
            // Ignorer les lignes multicolumn qui ne se terminent pas par \\
            if (strpos($line, '\\multicolumn') === 0 && !strpos($line, '\\\\')) {
                error_log("  -> Ignorée (multicolumn)");
                continue;
            }
            
            // Si c'est une ligne de données (contient des &)
            if (strpos($line, '&') !== false) {
                error_log("  -> Traitée (contient &)");
                // Nettoyer la ligne des artefacts minipage
                $cleanLine = $this->cleanTableLine($line);
                if (!empty($cleanLine)) {
                    $row = rtrim($cleanLine, '\\');
                    $processedLines[] = $row . ' \\\\';
                    $processedLines[] = '\\hline';
                    error_log("  -> Ajoutée: '{$row} \\\\'");
                } else {
                    error_log("  -> Ligne nettoyée vide, ignorée");
                }
            } else {
                error_log("  -> Ignorée (pas de &)");
            }
        }
        
        return implode("\n", $processedLines);
    }
    
    /**
     * Nettoie une ligne de tableau des artefacts minipage
     * 
     * @param string $line Ligne à nettoyer
     * @return string Ligne nettoyée
     */
    private function cleanTableLine($line)
    {
        // Supprimer les commandes minipage malformées
        $line = preg_replace('/\\\\end\{minipage\}/', '', $line);
        $line = preg_replace('/\\\\begin\{minipage\}\[b\]\{\\\\linewidth\}\\\\raggedright/', '', $line);
        
        // Nettoyer les espaces multiples
        $line = preg_replace('/\s+/', ' ', $line);
        
        // Nettoyer les espaces autour des &
        $line = preg_replace('/\s*&\s*/', ' & ', $line);
        
        // Supprimer les \\ en fin de ligne
        $line = rtrim($line, '\\');
        
        return trim($line);
    }
    
    /**
     * Corrige les sauts de ligne manquants qui empêchent les titres
     * 
     * @param string $content Contenu LaTeX à corriger
     * @return string Contenu LaTeX corrigé
     */
    private function fixMissingLineBreaks($content)
    {
        error_log("=== CORRECTION DES SAUTS DE LIGNE MANQUANTS ===");
        
        // Utiliser des remplacements simples et directs
        $content = str_replace('\\section{', "\n\n\\section{", $content);
        $content = str_replace('\\subsection{', "\n\n\\subsection{", $content);
        $content = str_replace('\\subsubsection{', "\n\n\\subsubsection{", $content);
        
        // Corriger les fins d'environnements collées
        $content = str_replace('\\end{tabular}', "\\end{tabular}\n\n", $content);
        $content = str_replace('\\end{itemize}', "\\end{itemize}\n\n", $content);
        $content = str_replace('\\end{longtable}', "\\end{longtable}\n\n", $content);
        
        // Corriger les images collées
        $content = str_replace('\\includegraphics{', "\n\n\\includegraphics{", $content);
        
        error_log("Correction des sauts de ligne terminée");
        return $content;
    }
    
    /**
     * Ajoute des espaces autour des tableaux pour améliorer la lisibilité
     * 
     * @param string $content Contenu LaTeX à traiter
     * @return string Contenu LaTeX avec espaces ajoutés
     */
    private function addSpacingAroundTables($content)
    {
        error_log("=== AJOUT D'ESPACES AUTOUR DES TABLEAUX ===");
        
        // Ajouter un espace avant \begin{tabular}
        $content = preg_replace('/([^\\s])\\\\begin\{tabular\}/', '$1\n\n\\begin{tabular}', $content);
        
        // Ajouter un espace après \end{tabular}
        $content = preg_replace('/\\\\end\{tabular\}([^\\s])/', "\\end{tabular}\n\n$1", $content);
        
        // Ajouter un espace avant \begin{longtable}
        $content = preg_replace('/([^\\s])\\\\begin\{longtable\}/', '$1\n\n\\begin{longtable}', $content);
        
        // Ajouter un espace après \end{longtable}
        $content = preg_replace('/\\\\end\{longtable\}([^\\s])/', "\\end{longtable}\n\n$1", $content);
        
        error_log("Ajout d'espaces autour des tableaux terminé");
        return $content;
    }
    
    /**
     * Exécute une commande dans le conteneur processor
     * 
     * @param string $workDir Répertoire de travail relatif
     * @param string $command Commande à exécuter
     * @return int Code de retour de la commande
     */
    private function executeInProcessor($workDir, $command)
    {
        error_log("=== EXÉCUTION DANS PROCESSOR ===");
        error_log("WorkDir: " . $workDir);
        error_log("Command: " . $command);
        
        // Créer le répertoire de commandes s'il n'existe pas
        $commandsDir = $this->workspaceDir . DIRECTORY_SEPARATOR . 'commands';
        error_log("Commands dir: " . $commandsDir);
        
        if (!is_dir($commandsDir)) {
            if (!mkdir($commandsDir, 0755, true)) {
                error_log("ERREUR: Impossible de créer le répertoire de commandes: " . $commandsDir);
                return 1;
            }
        }
        error_log("Répertoire de commandes OK");
        
        // Générer un ID unique pour cette commande
        $commandId = uniqid();
        $commandFile = $commandsDir . DIRECTORY_SEPARATOR . $commandId . '.cmd';
        $resultFile = $commandFile . '.result';
        
        // Créer le fichier de commande avec le format: work_dir|command
        $commandContent = $workDir . '|' . $command;
        error_log("Contenu du fichier de commande: " . $commandContent);
        error_log("Fichier de commande: " . $commandFile);
        
        if (file_put_contents($commandFile, $commandContent) === false) {
            error_log("ERREUR: Impossible de créer le fichier de commande: " . $commandFile);
            return 1;
        }
        
        error_log("Fichier de commande créé avec succès");
        error_log("Commande envoyée au processor: " . $command . " dans " . $workDir);
        
        // Attendre que la commande soit traitée (maximum 60 secondes)
        $timeout = 60;
        $startTime = time();
        error_log("Début de l'attente du résultat. Fichier attendu: " . $resultFile);
        
        $waitCount = 0;
        while (time() - $startTime < $timeout) {
            if (file_exists($resultFile)) {
                $resultCode = (int)file_get_contents($resultFile);
                unlink($resultFile); // Nettoyer le fichier de résultat
                
                error_log("Commande processor terminée avec le code: " . $resultCode . " après " . $waitCount . " itérations");
                return $resultCode;
            }
            
            // Log périodique pour suivre l'attente
            $waitCount++;
            if ($waitCount % 50 === 0) { // Log toutes les 5 secondes
                error_log("Attente en cours... " . $waitCount . " itérations (" . (time() - $startTime) . "s)");
            }
            
            // Attendre 100ms avant de vérifier à nouveau
            usleep(100000);
        }
        
        // Timeout atteint
        error_log("Timeout lors de l'exécution de la commande dans le processor");
        
        // Nettoyer le fichier de commande s'il existe encore
        if (file_exists($commandFile)) {
            unlink($commandFile);
        }
        
        return 1; // Code d'erreur
    }
}
