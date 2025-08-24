<?php
/**
 * Online Book Brew - Service de conversion
 * 
 * Service pour gérer la conversion des documents Markdown en PDF
 */

namespace App\Services;

use App\Utils\FileUtils;
use App\Utils\TemplateUtils;

class ConversionService
{
    /**
     * Configuration de l'application
     */
    private $config;
    
    /**
     * Chemins des répertoires
     */
    private $workspaceDir;
    private $typesetDir;
    private $userTemplatesDir;
    private $processScript;
    private $publicDir;
    private $fontsDir;
    
    /**
     * Images Docker
     */
    private $processorImage;
    
    /**
     * Service de conversion Markdown
     */
    private $mdConversionService;
    
    /**
     * Constructeur
     * 
     * @param array $config Configuration de l'application
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->workspaceDir = $config['paths']['workspace'];
        $this->typesetDir = $config['paths']['typeset'];
        $this->userTemplatesDir = $config['paths']['user_templates'];
        $this->processScript = $config['paths']['process_script'];
        $this->publicDir = $config['paths']['files'];
        $this->fontsDir = $config['paths']['fonts'];
        
        $this->processorImage = $config['docker']['processor_image'];
        
        // Initialiser le service de conversion Markdown
        $this->mdConversionService = new \App\Services\MdConversionService($config);
    }
    
    /**
     * Convertit un document Markdown en PDF
     * 
     * @param string $content Contenu Markdown
     * @param array $template Options de template
     * @param string $conversionMethod Méthode de conversion ('pandoc_direct' ou 'obsidian_export')
     * @return array Résultat de la conversion
     */
    public function convertDocument($content, $template, $conversionMethod = 'pandoc_direct')
    {
        // Activer l'affichage des erreurs pour le debug
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        
        error_log("=== DÉBUT CONVERSION DOCUMENT ===");
        error_log("Content length: " . strlen($content));
        error_log("Template: " . ($template ? json_encode($template) : 'null'));
        
        if (empty($content)) {
            error_log("ERREUR: Le contenu est vide");
            throw new \Exception('Le contenu est vide');
        }

        // Vérifier si nous avons un template
        $isTemplateMode = !empty($template) && !empty($template['layout']);
        error_log("Mode template: " . ($isTemplateMode ? 'OUI' : 'NON'));
        
        if ($isTemplateMode && empty($template['layout'])) {
            throw new \Exception('Une mise en page est requise pour le mode template');
        }
        
        // Créer un ID unique pour ce document
        $docId = uniqid();
        $workDir = $this->workspaceDir . DIRECTORY_SEPARATOR . $docId;
        
        // Créer le dossier de travail s'il n'existe pas
        if (!FileUtils::ensureDirectoryExists($this->workspaceDir)) {
            throw new \Exception("Impossible de créer le dossier workspace");
        }
        
        if (!FileUtils::ensureDirectoryExists($workDir)) {
            throw new \Exception("Impossible de créer le dossier de travail");
        }

        // Normaliser le contenu Markdown avant écriture (wikilinks Obsidian, chemins d'images)
        $normalizedContent = $this->normalizeMarkdownForImages($content);

        // Extraire les métadonnées du contenu Markdown (fallback uniquement)
        $documentMetadata = \App\Utils\TemplateUtils::extractMarkdownMetadata($normalizedContent);
        error_log("Métadonnées extraites du document (fallback): " . print_r($documentMetadata, true));

        // Sauvegarder le contenu Markdown
        $mdFile = $workDir . DIRECTORY_SEPARATOR . 'content.md';
        if (file_put_contents($mdFile, $normalizedContent) === false) {
            throw new \Exception("Impossible d'écrire le fichier Markdown");
        }

        // Créer le dossier pour les images si nécessaire
        $imagesDir = $workDir . DIRECTORY_SEPARATOR . 'images';
        if (!FileUtils::ensureDirectoryExists($imagesDir)) {
            throw new \Exception("Impossible de créer le dossier images");
        }
        
        // Extraire les références d'images du contenu Markdown et les copier
        $this->extractAndCopyImagesFromMarkdown($normalizedContent, $imagesDir);
        
        // Si mode template, gérer les options de template
        if ($isTemplateMode) {
            // Nettoyer les options
            $layout = basename($template['layout']);
            $cover = !empty($template['cover']) ? basename($template['cover']) : '';
            $impose = !empty($template['impose']) ? basename($template['impose']) : '';
            
            // Vérifier si ce sont des templates utilisateur
            $isUserLayout = isset($template['isUserTemplate']) && $template['isUserTemplate'] === true;
            $isUserCover = isset($template['coverIsUserTemplate']) && $template['coverIsUserTemplate'] === true;
            $isUserImpose = isset($template['imposeIsUserTemplate']) && $template['imposeIsUserTemplate'] === true;
            
            // Assurer que userId est défini si on utilise un template utilisateur
            $userId = isset($template['userId']) ? $template['userId'] : null;
            
            // Si on a un template utilisateur mais pas d'ID utilisateur, c'est une erreur
            if (($isUserLayout || $isUserCover || $isUserImpose) && $userId === null) {
                throw new \Exception("Template utilisateur détecté sans userId. L'ID utilisateur est obligatoire pour utiliser un template personnalisé.");
            }
            
            // Valider et nettoyer les métadonnées
            $metadata = isset($template['metadata']) && is_array($template['metadata'])
                ? TemplateUtils::validateAndCleanMetadata($template['metadata'])
                : TemplateUtils::validateAndCleanMetadata([]);
    
            error_log("Métadonnées: " . print_r($metadata, true));
            
            // Déterminer le chemin du fichier de layout en fonction du type (système ou utilisateur)
            $layoutFile = null;
            if ($isUserLayout) {
                if ($userId) {
                    // Chemin direct si userId est connu
                    $layoutDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'layout';
                    $layoutFile = $this->findTemplateFileWithVariants($layoutDir, $layout, 'layout');
                    
                    if (!$layoutFile) {
                        throw new \Exception("Fichier de layout utilisateur introuvable pour: " . $layout);
                    }
                } else {
                    // Chercher dans tous les dossiers utilisateurs
                    error_log("Recherche du fichier de layout dans tous les dossiers utilisateurs...");
                    $userTemplatesDir = $this->userTemplatesDir;
                    if (is_dir($userTemplatesDir)) {
                        $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                        foreach ($userDirs as $userDir) {
                            $layoutDir = $userDir . DIRECTORY_SEPARATOR . 'layout';
                            $possibleFile = $this->findTemplateFileWithVariants($layoutDir, $layout, 'layout');
                            
                            if ($possibleFile) {
                                $layoutFile = $possibleFile;
                                error_log("Fichier de layout trouvé: " . $layoutFile);
                                break;
                            }
                        }
                    }
                    
                    if (!$layoutFile) {
                        // Si toujours pas trouvé, utiliser le chemin système par défaut
                        error_log("Impossible de trouver le fichier layout dans les dossiers utilisateurs, tentative avec le système");
                        $layoutFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . $layout . '.tex';
                    }
                }
            } else {
                // Chemin système standard
                $layoutFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . $layout . '.tex';
            }
            
            $targetLayoutFile = $workDir . DIRECTORY_SEPARATOR . 'layout.tex';
            
            error_log("Utilisation du fichier de layout: " . $layoutFile);
            
            if (!file_exists($layoutFile)) {
                throw new \Exception("Le fichier de mise en page n'existe pas: " . $layoutFile);
            }
            
            if (!copy($layoutFile, $targetLayoutFile)) {
                throw new \Exception("Impossible de copier le fichier de mise en page");
            }
            
            // Corriger les chemins de polices dans le fichier layout
            $this->fixFontPaths($targetLayoutFile);
            
            // Copier la police si elle existe
            $fontDir = $this->typesetDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'fonts';
            $targetFontDir = $workDir . DIRECTORY_SEPARATOR . 'fonts';
            
            if (is_dir($fontDir)) {
                if (!FileUtils::ensureDirectoryExists($targetFontDir)) {
                    throw new \Exception("Impossible de créer le dossier fonts");
                }
                
                FileUtils::copyDirectory($fontDir, $targetFontDir);
            }
            
            // Si c'est un template utilisateur, copier aussi les polices utilisateur
            if ($isUserLayout && $userId) {
                $userFontDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'fonts';
                if (is_dir($userFontDir)) {
                    error_log("Copie des polices utilisateur depuis: " . $userFontDir);
                    if (!FileUtils::ensureDirectoryExists($targetFontDir)) {
                        FileUtils::ensureDirectoryExists($targetFontDir);
                    }
                    
                    // Copier toutes les polices du dossier utilisateur
                    $fontFiles = glob($userFontDir . DIRECTORY_SEPARATOR . '*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
                    foreach ($fontFiles as $fontFile) {
                        $targetFontFile = $targetFontDir . DIRECTORY_SEPARATOR . basename($fontFile);
                        error_log("Copie de la police utilisateur: " . $fontFile . " vers " . $targetFontFile);
                        copy($fontFile, $targetFontFile);
                    }
                }
            }
            
            // Si une couverture est spécifiée, la copier également
            if (!empty($cover)) {
                $coverFile = null;
                // Définir le chemin cible AVANT de l'utiliser
                $targetCoverFile = $workDir . DIRECTORY_SEPARATOR . 'cover.tex';
                
                if ($isUserCover && $userId) {
                    $coverDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'cover';
                    $coverFile = $this->findTemplateFileWithVariants($coverDir, $cover, 'cover');
                    
                    if (!$coverFile) {
                        throw new \Exception("Fichier de couverture utilisateur introuvable pour: " . $cover);
                    }
                    
                    if (!copy($coverFile, $targetCoverFile)) {
                        throw new \Exception("Impossible de copier le fichier de couverture utilisateur");
                    }
                    
                    // Corriger les chemins de polices pour les templates utilisateur
                    $this->fixFontPaths($targetCoverFile);
                    
                    // Créer un dossier fonts si nécessaire et copier les polices utilisateur
                    $targetFontDir = $workDir . DIRECTORY_SEPARATOR . 'fonts';
                    if (!FileUtils::ensureDirectoryExists($targetFontDir)) {
                        FileUtils::ensureDirectoryExists($targetFontDir);
                    }
                    
                    // Copier les polices utilisateur si disponibles
                    $userFontDir = $this->workspaceDir . DIRECTORY_SEPARATOR . 'user_fonts' . DIRECTORY_SEPARATOR . $userId;
                    if (is_dir($userFontDir)) {
                        $fontFiles = glob($userFontDir . DIRECTORY_SEPARATOR . '*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
                        foreach ($fontFiles as $fontFile) {
                            $targetFontFile = $targetFontDir . DIRECTORY_SEPARATOR . basename($fontFile);
                            error_log("Copie de la police utilisateur: " . $fontFile . " vers " . $targetFontFile);
                            copy($fontFile, $targetFontFile);
                        }
                    }
                } else if ($isUserCover) {
                    // Chercher dans tous les dossiers utilisateurs
                    error_log("Recherche du fichier de couverture dans tous les dossiers utilisateurs...");
                    $userTemplatesDir = $this->userTemplatesDir;
                    $found = false;
                    
                    if (is_dir($userTemplatesDir)) {
                        $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                        foreach ($userDirs as $userDir) {
                            $coverDir = $userDir . DIRECTORY_SEPARATOR . 'cover';
                            $possibleFile = $this->findTemplateFileWithVariants($coverDir, $cover, 'cover');
                            
                            if ($possibleFile) {
                                $coverFile = $possibleFile;
                                error_log("Fichier de couverture trouvé: " . $coverFile);
                                if (copy($coverFile, $targetCoverFile)) {
                                    // Corriger les chemins de polices pour les templates utilisateur
                                    $this->fixFontPaths($targetCoverFile);
                                    $found = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    if (!$found) {
                        if (!FileUtils::copyTemplateFile($this->typesetDir, 'cover', $cover, $targetCoverFile)) {
                            throw new \Exception("Fichier de couverture introuvable: $cover");
                        }
                    }
                } else {
                    // Chemin système standard
                    $coverFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'cover' . DIRECTORY_SEPARATOR . $cover . '.tex';
                }
                
                // Supprimer cette ligne redondante qui redéfinit $targetCoverFile après son utilisation
                // $targetCoverFile = $workDir . DIRECTORY_SEPARATOR . 'cover.tex';
                
                if (!file_exists($coverFile)) {
                    throw new \Exception("Le fichier de couverture n'existe pas: " . $coverFile);
                }
                
                if (!copy($coverFile, $targetCoverFile)) {
                    throw new \Exception("Impossible de copier le fichier de couverture");
                }
                
                // Copier les polices de la couverture si elles existent
                $coverFontDir = $this->typesetDir . DIRECTORY_SEPARATOR . 'cover' . DIRECTORY_SEPARATOR . 'fonts';
                
                if (is_dir($coverFontDir)) {
                    if (!FileUtils::ensureDirectoryExists($targetFontDir)) {
                        FileUtils::ensureDirectoryExists($targetFontDir);
                    }
                    
                    FileUtils::copyDirectory($coverFontDir, $targetFontDir);
                }
            }
            
            // Si une imposition est spécifiée, la copier également
            if (!empty($impose)) {
                $targetImposeFile = $workDir . DIRECTORY_SEPARATOR . 'impose.tex';
                
                $imposeFile = null;
                if ($isUserImpose) {
                    if ($userId) {
                        // Chemin direct si userId est connu
                        $imposeDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'impose';
                        $imposeFile = $this->findTemplateFileWithVariants($imposeDir, $impose, 'impose');
                        
                        if (!$imposeFile) {
                            throw new \Exception("Fichier d'imposition utilisateur introuvable pour: " . $impose);
                        }
                    } else {
                        // Chercher dans tous les dossiers utilisateurs
                        error_log("Recherche du fichier d'imposition dans tous les dossiers utilisateurs...");
                        $userTemplatesDir = $this->userTemplatesDir;
                        $found = false;
                        
                        if (is_dir($userTemplatesDir)) {
                            $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                            foreach ($userDirs as $userDir) {
                                $imposeDir = $userDir . DIRECTORY_SEPARATOR . 'impose';
                                $possibleFile = $this->findTemplateFileWithVariants($imposeDir, $impose, 'impose');
                                
                                if ($possibleFile) {
                                    $imposeFile = $possibleFile;
                                    error_log("Fichier d'imposition trouvé: " . $imposeFile);
                                    if (copy($imposeFile, $targetImposeFile)) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if (!$found) {
                            // Essayer avec le fichier système
                            $imposeFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'impose' . DIRECTORY_SEPARATOR . $impose . '.tex';
                        }
                    }
                } else {
                    // Chemin système standard
                    $imposeFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'impose' . DIRECTORY_SEPARATOR . $impose . '.tex';
                }
                
                // Vérifier que le fichier existe
                if (!file_exists($imposeFile)) {
                    error_log("Fichier d'imposition introuvable: " . $imposeFile);
                    throw new \Exception("Fichier d'imposition introuvable: " . $impose);
                }
                
                // Copier le fichier
                if (!copy($imposeFile, $targetImposeFile)) {
                    throw new \Exception("Impossible de copier le fichier d'imposition");
                }
                
                error_log("Fichier d'imposition copié: " . $imposeFile . " vers " . $targetImposeFile);
            }
    
            // Créer un fichier JSON avec les métadonnées
            $metadataFile = $workDir . DIRECTORY_SEPARATOR . 'metadata.json';
            if (file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT)) === false) {
                throw new \Exception("Impossible d'écrire le fichier de métadonnées");
            }

            // Après la copie du fichier de layout, mettre à jour les variables et options
            TemplateUtils::updateLatexFile(
                $targetLayoutFile,
                $metadata,
                isset($template['booleanOptions']) ? $template['booleanOptions'] : []
            );
            
            // Si une couverture est spécifiée, mettre à jour ses variables aussi
            if (!empty($cover)) {
                TemplateUtils::updateLatexFile(
                    $targetCoverFile,
                    $metadata,
                    isset($template['booleanOptions']) ? $template['booleanOptions'] : []
                );
            }
            
            // Si une imposition est spécifiée, mettre à jour ses variables aussi
            if (!empty($impose)) {
                TemplateUtils::updateLatexFile(
                    $targetImposeFile,
                    $metadata,
                    isset($template['booleanOptions']) ? $template['booleanOptions'] : []
                );
            }
            
            // Copier les images téléversées dans le dossier images du workspace
            $this->copyUploadedImagesToWorkspace($metadata, $workDir);
        }

        // Log pour le débogage
        error_log("Fichier Markdown créé: " . realpath($mdFile));
        
        // Obtenir le chemin relatif du dossier de travail par rapport au dossier workspace
        $relativeWorkDir = basename($workDir);
        
        // Utiliser le service de conversion Markdown pour la conversion Markdown vers LaTeX
        $this->mdConversionService->setConversionMethod($conversionMethod);
        $this->mdConversionService->convertMarkdownToLatex($relativeWorkDir);
        
        // Étape intermédiaire: copier layout.tex vers main.tex pour le mode template
        if ($isTemplateMode) {
            // Copier layout.tex vers main.tex en utilisant une méthode adaptée au type de template
            if ($isUserLayout) {
                // Pour les templates utilisateur, créer un main.tex personnalisé
                error_log("Création d'un fichier main.tex personnalisé pour template utilisateur");
                if (!$this->createUserMainTex($workDir, $template)) {
                    throw new \Exception("Impossible de créer le fichier main.tex personnalisé");
                }
            } else {
                // Pour les templates système, copier layout.tex vers main.tex dans le processor
                // Attendre 200ms pour laisser le temps au FS de synchroniser
                usleep(200000);
                // Log du contenu du dossier de travail dans le processor avant le cp
                $lsCmd = 'ls -l';
                $this->executeInProcessor($relativeWorkDir, $lsCmd);
                $copyLayoutCmd = 'cp layout.tex main.tex';
                error_log("Commande copie layout (processor): " . $copyLayoutCmd);
                $cpLayoutResult = $this->executeInProcessor($relativeWorkDir, $copyLayoutCmd);
                if ($cpLayoutResult !== 0) {
                    throw new \Exception("Erreur lors de la préparation du template: copie layout.tex → main.tex a échoué");
                }
            }
        }
        
        // Créer un fichier main.tex corrigé pour s'assurer que les polices sont correctement référencées
        // Modifier pour ne l'appliquer que sur les templates utilisateur
        if ($isUserLayout) {
            $this->createCorrectedMainTex($workDir, $template);
        }
        
        // Installer les polices utilisateur si nécessaire
        if ($isUserLayout && $userId) {
            // Créer les liens symboliques
            $this->createUserFontsLink($workDir, $relativeWorkDir, $userId);
        }
        
        // Compiler avec XeLaTeX via le conteneur processor (DEUX PASSES pour la table des matières)
        $xelatexCmd = 'export TEXINPUTS=.:fonts:/typeset/fonts: && ' .
            'export OSFONTDIR=fonts:/typeset/fonts && ' .
            'xelatex -interaction=nonstopmode main.tex';
            
        error_log("Première passe xelatex via processor: " . $xelatexCmd);
        
        // Première passe : génère le fichier .toc et compile le document
        $processResult1 = $this->executeInProcessor($relativeWorkDir, $xelatexCmd);
        
        // Deuxième passe : lit le fichier .toc et génère la table des matières
        error_log("Deuxième passe xelatex pour la table des matières");
        $processResult2 = $this->executeInProcessor($relativeWorkDir, $xelatexCmd);
        
        // Utiliser le résultat de la deuxième passe pour la validation
        $processResult = $processResult2;
        
        // Vérifier si le PDF a été créé, même si le processus retourne une erreur
        // (car LaTeX peut générer des avertissements qui causent un code d'erreur mais le PDF est quand même valide)
        $pdfPath = $workDir . DIRECTORY_SEPARATOR . 'main.pdf';
        
        if ($processResult !== 0) {
            error_log("Sortie du processeur avec erreur, vérification du PDF...");
            
            // Vérifier si le PDF a été créé malgré l'erreur
            if (!FileUtils::validateFileOutput($pdfPath)) {
                throw new \Exception("Erreur de traitement via le conteneur processor");
            } else {
                // Log l'avertissement mais continuer le processus
                error_log("Avertissement: Le processus a retourné des erreurs, mais le PDF semble avoir été généré correctement");
            }
        }

        // Vérifier si le PDF existe et a une taille raisonnable
        if (!FileUtils::validateFileOutput($pdfPath)) {
            throw new \Exception('Le fichier PDF n\'a pas été généré ou est trop petit');
        }

        error_log("PDF généré avec succès: " . realpath($pdfPath));

        // Copier le PDF dans le dossier public
        if (!FileUtils::ensureDirectoryExists($this->publicDir)) {
            throw new \Exception("Impossible de créer le dossier public");
        }
        
        $publicPdfPath = $this->publicDir . DIRECTORY_SEPARATOR . $docId . '.pdf';
        if (!copy($pdfPath, $publicPdfPath)) {
            throw new \Exception("Impossible de copier le PDF dans le dossier public");
        }
        
        error_log("PDF copié vers: " . realpath($publicPdfPath));

        // Préparer la réponse
        $baseUrl = $this->config['api']['url'];
        $fileUrl = $baseUrl . '/pdf/' . $docId;
        
        // Utiliser UNIQUEMENT les métadonnées des variables de template (comme {{titre}}, {{auteur}})
        // qui sont saisies dans l'interface TemplateSelector
        $templateMetadata = [];
        if (isset($template['metadata']) && is_array($template['metadata'])) {
            $templateMetadata = $template['metadata'];
        }
        
        // Priorité absolue aux métadonnées de template, ignorer le contenu Markdown
        $finalMetadata = $templateMetadata;
        
        // Si pas de titre dans le template, utiliser "sans titre"
        if (empty($finalMetadata['titre']) && empty($finalMetadata['title'])) {
            $finalMetadata['titre'] = 'sans titre';
        }
        
        // Utiliser l'heure exacte de création du document
        $creationTime = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        
        // Générer le nom de fichier basé sur les métadonnées finales
        $filename = \App\Utils\TemplateUtils::generateDocumentFilename($finalMetadata, $docId, 'document');
        error_log("=== DEBUG NOM DE FICHIER ===");
        error_log("Métadonnées de template: " . print_r($templateMetadata, true));
        error_log("Métadonnées finales: " . print_r($finalMetadata, true));
        error_log("Nom de fichier généré: " . $filename);
        error_log("Document ID: " . $docId);
        error_log("Heure de création: " . $creationTime->format('Y-m-d H:i:s'));
        
        return [
            'status' => 'success',
            'message' => $isTemplateMode ? 'Conversion avec template réussie' : 'Conversion réussie',
            'pdf_url' => $baseUrl . '/api/pdf/' . $docId,
            'document_id' => $docId,
            'metadata' => $finalMetadata,
            'filename' => $filename,
            'creation_time' => $creationTime->format('Y-m-d H:i:s'),
            'template' => $isTemplateMode ? [
                'layout' => $layout,
                'cover' => $cover,
                'impose' => $impose,
            ] : null
        ];
    }
    
    /**
     * Compile uniquement la couverture d'un document
     * 
     * @param array $template Options de template
     * @param string $conversionMethod Méthode de conversion ('pandoc_direct' ou 'obsidian_export')
     * @return array Résultat de la compilation
     */
    public function compileCover($template, $conversionMethod = 'pandoc_direct')
    {
        if (!isset($template['cover']) || empty($template['cover'])) {
            throw new \Exception('Aucune couverture sélectionnée');
        }
        
        $cover = $template['cover'];
        $layout = isset($template['layout']) ? $template['layout'] : '';
        
        // Vérifier si ce sont des templates utilisateur
        $isUserCover = isset($template['coverIsUserTemplate']) && $template['coverIsUserTemplate'] === true;
        $isUserLayout = isset($template['isUserTemplate']) && $template['isUserTemplate'] === true;
        $userId = isset($template['userId']) ? $template['userId'] : null;
        
        // Créer un ID unique pour ce document
        $docId = uniqid();
        $workDir = $this->workspaceDir . DIRECTORY_SEPARATOR . $docId;

        // Créer le dossier de travail s'il n'existe pas
        if (!FileUtils::ensureDirectoryExists($this->workspaceDir)) {
            throw new \Exception("Impossible de créer le dossier workspace");
        }
        
        if (!FileUtils::ensureDirectoryExists($workDir)) {
            throw new \Exception("Impossible de créer le dossier de travail");
        }

        // Créer un contenu Markdown minimal pour la structure
        $content = "# Couverture\n\nCe fichier est utilisé uniquement pour la compilation de la couverture.";
        $mdFile = $workDir . DIRECTORY_SEPARATOR . 'content.md';
        if (file_put_contents($mdFile, $content) === false) {
            throw new \Exception("Impossible d'écrire le fichier Markdown");
        }

        // Vérifier que le fichier de couverture existe
        $targetCoverFile = $workDir . DIRECTORY_SEPARATOR . 'cover.tex';
        
        // Déterminer le chemin du fichier de couverture
        $coverFile = null;
        if ($isUserCover && $userId) {
            $coverDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'cover';
            $coverFile = $this->findTemplateFileWithVariants($coverDir, $cover, 'cover');
            
            if (!$coverFile) {
                throw new \Exception("Fichier de couverture utilisateur introuvable pour: " . $cover);
            }
            
            if (!copy($coverFile, $targetCoverFile)) {
                throw new \Exception("Impossible de copier le fichier de couverture utilisateur");
            }
            
            // Corriger les chemins de polices pour les templates utilisateur
            $this->fixFontPaths($targetCoverFile);
            
            // Créer un dossier fonts si nécessaire et copier les polices utilisateur
            $targetFontDir = $workDir . DIRECTORY_SEPARATOR . 'fonts';
            if (!FileUtils::ensureDirectoryExists($targetFontDir)) {
                FileUtils::ensureDirectoryExists($targetFontDir);
            }
            
            // Copier les polices utilisateur si disponibles
            $userFontDir = $this->workspaceDir . DIRECTORY_SEPARATOR . 'user_fonts' . DIRECTORY_SEPARATOR . $userId;
            if (is_dir($userFontDir)) {
                $fontFiles = glob($userFontDir . DIRECTORY_SEPARATOR . '*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
                foreach ($fontFiles as $fontFile) {
                    $targetFontFile = $targetFontDir . DIRECTORY_SEPARATOR . basename($fontFile);
                    error_log("Copie de la police utilisateur: " . $fontFile . " vers " . $targetFontFile);
                    copy($fontFile, $targetFontFile);
                }
            }
        } else if ($isUserCover) {
            // Chercher dans tous les dossiers utilisateurs
            error_log("Recherche du fichier de couverture dans tous les dossiers utilisateurs...");
            $userTemplatesDir = $this->userTemplatesDir;
            $found = false;
            
            if (is_dir($userTemplatesDir)) {
                $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                foreach ($userDirs as $userDir) {
                    $coverDir = $userDir . DIRECTORY_SEPARATOR . 'cover';
                    $possibleFile = $this->findTemplateFileWithVariants($coverDir, $cover, 'cover');
                    
                    if ($possibleFile) {
                        $coverFile = $possibleFile;
                        error_log("Fichier de couverture trouvé: " . $coverFile);
                        if (copy($coverFile, $targetCoverFile)) {
                            // Corriger les chemins de polices pour les templates utilisateur
                            $this->fixFontPaths($targetCoverFile);
                            $found = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$found) {
                if (!FileUtils::copyTemplateFile($this->typesetDir, 'cover', $cover, $targetCoverFile)) {
                    throw new \Exception("Fichier de couverture introuvable: $cover");
                }
            }
        } else {
            if (!FileUtils::copyTemplateFile($this->typesetDir, 'cover', $cover, $targetCoverFile)) {
                throw new \Exception("Fichier de couverture introuvable: $cover");
            }
        }
        
        // Si un layout est spécifié, le copier aussi pour les métadonnées communes
        $targetLayoutFile = null;
        if (!empty($layout)) {
            $targetLayoutFile = $workDir . DIRECTORY_SEPARATOR . 'layout.tex';
            
            if ($isUserLayout && $userId) {
                $layoutDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'layout';
                $layoutFile = $this->findTemplateFileWithVariants($layoutDir, $layout, 'layout');
                
                if (!$layoutFile) {
                    throw new \Exception("Fichier de layout utilisateur introuvable pour: " . $layout);
                }
                
                if (!copy($layoutFile, $targetLayoutFile)) {
                    throw new \Exception("Impossible de copier le fichier de layout utilisateur");
                }
                
                // Corriger les chemins de polices pour les templates utilisateur
                $this->fixFontPaths($targetLayoutFile);
            } else if ($isUserLayout) {
                // Chercher dans tous les dossiers utilisateurs
                error_log("Recherche du fichier de layout dans tous les dossiers utilisateurs...");
                $userTemplatesDir = $this->userTemplatesDir;
                $found = false;
                
                if (is_dir($userTemplatesDir)) {
                    $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                    foreach ($userDirs as $userDir) {
                        $layoutDir = $userDir . DIRECTORY_SEPARATOR . 'layout';
                        $possibleFile = $this->findTemplateFileWithVariants($layoutDir, $layout, 'layout');
                        
                        if ($possibleFile) {
                            $layoutFile = $possibleFile;
                            error_log("Fichier de layout trouvé: " . $layoutFile);
                            if (copy($layoutFile, $targetLayoutFile)) {
                                // Corriger les chemins de polices
                                $this->fixFontPaths($targetLayoutFile);
                                $found = true;
                                break;
                            }
                        }
                    }
                }
                
                if (!$found) {
                    throw new \Exception("Fichier de layout utilisateur introuvable pour: " . $layout);
                }
            } else {
                if (!FileUtils::copyTemplateFile($this->typesetDir, 'layout', $layout, $targetLayoutFile)) {
                    throw new \Exception("Fichier de layout introuvable: $layout");
                }
            }
        }

        // Vérification et fusion des métadonnées
        $metadata = [];
        if (isset($template['metadata'])) {
            $metadata = TemplateUtils::validateAndCleanMetadata($template['metadata']);
        }
        
        // Mise à jour du fichier LaTeX de couverture avec les métadonnées
        TemplateUtils::updateLatexFile(
            $targetCoverFile,
            $metadata,
            isset($template['booleanOptions']) ? $template['booleanOptions'] : []
        );
        
        // Copier les images téléversées dans le dossier images du workspace
        $this->copyUploadedImagesToWorkspace($metadata, $workDir);
        
        // Créer un fichier JSON avec les métadonnées
        $metadataFile = $workDir . DIRECTORY_SEPARATOR . 'metadata.json';
        if (file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT)) === false) {
            throw new \Exception("Impossible d'écrire le fichier de métadonnées");
        }

        // Obtenir le chemin relatif du dossier de travail par rapport au dossier workspace
        $relativeWorkDir = basename($workDir);
        
        // Pour les templates utilisateur, mettre à jour le cache des polices
        if (($isUserCover || $isUserLayout) && $userId) {
            // Créer les liens symboliques
            $this->createUserFontsLink($workDir, $relativeWorkDir, $userId);
        }
        
        // Vérification active de la présence du dossier de travail (max 1s)
        $maxTries = 10;
        $try = 0;
        while (!is_dir($workDir) && $try < $maxTries) {
            error_log("[compileCover] Dossier de travail $workDir non trouvé, tentative $try");
            usleep(100000); // 100ms
            clearstatcache();
            $try++;
        }
        if (!is_dir($workDir)) {
            throw new \Exception("[compileCover] Dossier de travail $workDir introuvable après attente active");
        }
        
        // Compiler la couverture avec XeLaTeX via le conteneur processor (DEUX PASSES pour la table des matières)
        $processCmd = 'export TEXINPUTS=.:fonts:/typeset/fonts: && ' .
            'export OSFONTDIR=fonts:/typeset/fonts && ' .
            'xelatex -interaction=nonstopmode cover.tex';
        
        error_log("Première passe xelatex pour la couverture via processor: " . $processCmd);
        
        // Première passe : génère le fichier .toc et compile le document
        $processResult1 = $this->executeInProcessor($relativeWorkDir, $processCmd);
        
        // Deuxième passe : lit le fichier .toc et génère la table des matières
        error_log("Deuxième passe xelatex pour la couverture");
        $processResult2 = $this->executeInProcessor($relativeWorkDir, $processCmd);
        
        // Utiliser le résultat de la deuxième passe pour la validation
        $processResult = $processResult2;
        
        // Vérifier si le PDF a été créé
        $pdfPath = $workDir . DIRECTORY_SEPARATOR . 'cover.pdf';
        
        if ($processResult !== 0) {
            error_log("Sortie du processeur (couverture) avec erreur, vérification du PDF...");
            
            // Vérifier si le PDF a été créé malgré l'erreur
            if (!FileUtils::validateFileOutput($pdfPath)) {
                throw new \Exception("Erreur de traitement de la couverture via le conteneur processor");
            } else {
                // Log l'avertissement mais continuer le processus
                error_log("Avertissement: Le processus a retourné des erreurs, mais le PDF de couverture semble avoir été généré correctement");
            }
        }

        // Vérifier si le PDF existe et a une taille raisonnable
        if (!FileUtils::validateFileOutput($pdfPath)) {
            throw new \Exception('Le fichier PDF de couverture n\'a pas été généré ou est trop petit');
        }

        error_log("PDF de couverture généré avec succès: " . realpath($pdfPath));

        // Copier le PDF dans le dossier public
        if (!FileUtils::ensureDirectoryExists($this->publicDir)) {
            throw new \Exception("Impossible de créer le dossier public");
        }
        
        $publicPdfPath = $this->publicDir . DIRECTORY_SEPARATOR . $docId . '-cover.pdf';
        if (!copy($pdfPath, $publicPdfPath)) {
            throw new \Exception("Impossible de copier le PDF de couverture dans le dossier public");
        }
        
        error_log("PDF de couverture copié vers: " . realpath($publicPdfPath));

        // Préparer la réponse
        $baseUrl = $this->config['api']['url'];
        $fileUrl = $baseUrl . '/api/pdf/' . $docId . '-cover';
        
        // Extraire les métadonnées du contenu (même si c'est minimal pour la couverture)
        $documentMetadata = \App\Utils\TemplateUtils::extractMarkdownMetadata($content);
        
        // Utiliser UNIQUEMENT les métadonnées des variables de template (comme {{titre}}, {{auteur}})
        // qui sont saisies dans l'interface TemplateSelector
        $templateMetadata = [];
        if (isset($template['metadata']) && is_array($template['metadata'])) {
            $templateMetadata = $template['metadata'];
        }
        
        // Priorité absolue aux métadonnées de template, ignorer le contenu Markdown
        $finalMetadata = $templateMetadata;
        
        // Si pas de titre dans le template, utiliser "sans titre"
        if (empty($finalMetadata['titre']) && empty($finalMetadata['title'])) {
            $finalMetadata['titre'] = 'sans titre';
        }
        
        // Utiliser l'heure exacte de création du document
        $creationTime = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        
        $filename = \App\Utils\TemplateUtils::generateDocumentFilename($finalMetadata, $docId, 'cover');
        
        return [
            'status' => 'success',
            'message' => 'Compilation de la couverture réussie',
            'pdf_url' => $fileUrl,
            'document_id' => $docId,
            'metadata' => $finalMetadata,
            'filename' => $filename,
            'creation_time' => $creationTime->format('Y-m-d H:i:s'),
            'template' => [
                'layout' => $layout,
                'cover' => $cover
            ]
        ];
    }

    /**
     * Impose un document
     * 
     * @param string $content Contenu Markdown
     * @param array $template Options de template
     * @param string $conversionMethod Méthode de conversion ('pandoc_direct' ou 'obsidian_export')
     * @return array Résultat de l'imposition
     */
    public function imposeDocument($content, $template, $conversionMethod = 'pandoc_direct')
    {
        error_log("=== DÉBUT IMPOSE DOCUMENT ===");
        error_log("=== TEST MODIFICATION LOG ===");
        error_log("Content length: " . strlen($content));
        error_log("Template: " . json_encode($template));
        
        // Forcer l'affichage des logs dans un fichier temporaire
        file_put_contents('/tmp/debug_service.log', "=== DÉBUT IMPOSE DOCUMENT ===\n", FILE_APPEND);
        file_put_contents('/tmp/debug_service.log', "Content length: " . strlen($content) . "\n", FILE_APPEND);
        file_put_contents('/tmp/debug_service.log', "Template: " . json_encode($template) . "\n", FILE_APPEND);
        
        if (empty($content)) {
            error_log("ERREUR: Le contenu est vide");
            file_put_contents('/tmp/debug_service.log', "ERREUR: Le contenu est vide\n", FILE_APPEND);
            throw new \Exception('Le contenu est vide');
        }

        // Vérifier que le template contient une imposition
        error_log("Vérification imposition: " . (isset($template['impose']) ? $template['impose'] : 'NON DÉFINI'));
        file_put_contents('/tmp/debug_service.log', "Vérification imposition: " . (isset($template['impose']) ? $template['impose'] : 'NON DÉFINI') . "\n", FILE_APPEND);
        if (!isset($template['impose']) || empty($template['impose'])) {
            error_log("ERREUR: Aucune imposition sélectionnée");
            file_put_contents('/tmp/debug_service.log', "ERREUR: Aucune imposition sélectionnée\n", FILE_APPEND);
            throw new \Exception('Aucune imposition sélectionnée');
        }
        
        // Vérifier que le layout est défini
        error_log("Vérification layout: " . (isset($template['layout']) ? $template['layout'] : 'NON DÉFINI'));
        file_put_contents('/tmp/debug_service.log', "Vérification layout: " . (isset($template['layout']) ? $template['layout'] : 'NON DÉFINI') . "\n", FILE_APPEND);
        if (!isset($template['layout']) || empty($template['layout'])) {
            error_log("ERREUR: Une mise en page est nécessaire pour l'imposition");
            file_put_contents('/tmp/debug_service.log', "ERREUR: Une mise en page est nécessaire pour l'imposition\n", FILE_APPEND);
            throw new \Exception('Une mise en page est nécessaire pour l\'imposition');
        }

        // Définir explicitement le mode template pour l'imposition
        $isTemplateMode = true;

        $impose = $template['impose'];
        $layout = $template['layout'];
        $cover = isset($template['cover']) ? $template['cover'] : '';
        
        file_put_contents('/tmp/debug_service.log', "Variables définies - impose: $impose, layout: $layout, cover: $cover\n", FILE_APPEND);
        
        // Vérifier si ce sont des templates utilisateur
        $isUserLayout = isset($template['isUserTemplate']) && $template['isUserTemplate'] === true;
        $isUserCover = isset($template['coverIsUserTemplate']) && $template['coverIsUserTemplate'] === true;
        $isUserImpose = isset($template['imposeIsUserTemplate']) && $template['imposeIsUserTemplate'] === true;
        
        // Assurer que userId est défini si on utilise un template utilisateur
        $userId = isset($template['userId']) ? $template['userId'] : null;
        
        // Si on a un template utilisateur mais pas d'ID utilisateur, c'est une erreur
        if (($isUserLayout || $isUserCover || $isUserImpose) && $userId === null) {
            throw new \Exception("Template utilisateur détecté sans userId. L'ID utilisateur est obligatoire pour utiliser un template personnalisé.");
        }
        
        // Récupérer l'épaisseur du papier si fournie
        $paperThickness = isset($template['paperThickness']) ? floatval($template['paperThickness']) : 0.1; // Valeur par défaut en mm
        
        // Créer un ID unique pour ce document
        $docId = 'impose_' . uniqid();
        $workDir = $this->workspaceDir . DIRECTORY_SEPARATOR . $docId;

        // Log des chemins pour le débogage
        error_log("Work dir: " . $workDir);
        error_log("Épaisseur du papier: " . $paperThickness . " mm");
        file_put_contents('/tmp/debug_service.log', "Work dir: $workDir\n", FILE_APPEND);
        file_put_contents('/tmp/debug_service.log', "Épaisseur du papier: $paperThickness mm\n", FILE_APPEND);
        
        // Créer le dossier de travail s'il n'existe pas
        file_put_contents('/tmp/debug_service.log', "Création du dossier workspace...\n", FILE_APPEND);
        if (!FileUtils::ensureDirectoryExists($this->workspaceDir)) {
            file_put_contents('/tmp/debug_service.log', "ERREUR: Impossible de créer le dossier workspace\n", FILE_APPEND);
            throw new \Exception("Impossible de créer le dossier workspace");
        }
        file_put_contents('/tmp/debug_service.log', "Dossier workspace créé avec succès\n", FILE_APPEND);
        
        file_put_contents('/tmp/debug_service.log', "Création du dossier de travail...\n", FILE_APPEND);
        if (!FileUtils::ensureDirectoryExists($workDir)) {
            file_put_contents('/tmp/debug_service.log', "ERREUR: Impossible de créer le dossier de travail\n", FILE_APPEND);
            throw new \Exception("Impossible de créer le dossier de travail");
        }
        file_put_contents('/tmp/debug_service.log', "Dossier de travail créé avec succès\n", FILE_APPEND);

        // Sauvegarder le contenu Markdown
        file_put_contents('/tmp/debug_service.log', "Sauvegarde du contenu Markdown...\n", FILE_APPEND);
        $mdFile = $workDir . DIRECTORY_SEPARATOR . 'content.md';
        if (file_put_contents($mdFile, $content) === false) {
            throw new \Exception("Impossible d'écrire le fichier Markdown");
        }
        file_put_contents('/tmp/debug_service.log', "Contenu Markdown sauvegardé avec succès\n", FILE_APPEND);

        // Copier le template d'imposition
        file_put_contents('/tmp/debug_service.log', "Début copie template imposition...\n", FILE_APPEND);
        $targetImposeFile = $workDir . DIRECTORY_SEPARATOR . 'impose.tex';
        
        $imposeFile = null;
        if ($isUserImpose) {
            if ($userId) {
                // Chemin direct si userId est connu
                $imposeDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'impose';
                $imposeFile = $this->findTemplateFileWithVariants($imposeDir, $impose, 'impose');
                
                if (!$imposeFile) {
                    throw new \Exception("Fichier d'imposition utilisateur introuvable pour: " . $impose);
                }
                
                if (!copy($imposeFile, $targetImposeFile)) {
                    throw new \Exception("Impossible de copier le fichier d'imposition utilisateur");
                }
            } else {
                // Chercher dans tous les dossiers utilisateurs
                error_log("Recherche du fichier d'imposition dans tous les dossiers utilisateurs...");
                $userTemplatesDir = $this->userTemplatesDir;
                $found = false;
                
                if (is_dir($userTemplatesDir)) {
                    $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                    foreach ($userDirs as $userDir) {
                        $imposeDir = $userDir . DIRECTORY_SEPARATOR . 'impose';
                        $possibleFile = $this->findTemplateFileWithVariants($imposeDir, $impose, 'impose');
                        
                        if ($possibleFile) {
                            $imposeFile = $possibleFile;
                            error_log("Fichier d'imposition trouvé: " . $imposeFile);
                            if (copy($imposeFile, $targetImposeFile)) {
                                $found = true;
                                break;
                            }
                        }
                    }
                }
                
                if (!$found) {
                    // Essayer avec le fichier système
                    $systemImposePath = $this->typesetDir . DIRECTORY_SEPARATOR . 'impose' . DIRECTORY_SEPARATOR . $impose . '.tex';
                    if (file_exists($systemImposePath)) {
                        if (!copy($systemImposePath, $targetImposeFile)) {
                            throw new \Exception("Impossible de copier le fichier d'imposition système");
                        }
                    } else {
                        throw new \Exception("Fichier d'imposition introuvable: $impose");
                    }
                }
            }
        } else {
            // Chemin système standard
            file_put_contents('/tmp/debug_service.log', "Template système - imposition: $impose\n", FILE_APPEND);
            $imposeFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'impose' . DIRECTORY_SEPARATOR . $impose . '.tex';
            file_put_contents('/tmp/debug_service.log', "Fichier imposition: $imposeFile\n", FILE_APPEND);
            if (!file_exists($imposeFile)) {
                file_put_contents('/tmp/debug_service.log', "ERREUR: Fichier imposition introuvable\n", FILE_APPEND);
                throw new \Exception("Fichier d'imposition système introuvable: " . $imposeFile);
            }
            file_put_contents('/tmp/debug_service.log', "Fichier imposition trouvé\n", FILE_APPEND);
            
            $targetImposeFile = $workDir . DIRECTORY_SEPARATOR . 'impose.tex';
            
            if (!copy($imposeFile, $targetImposeFile)) {
                file_put_contents('/tmp/debug_service.log', "ERREUR: Impossible de copier imposition\n", FILE_APPEND);
                throw new \Exception("Impossible de copier le fichier d'imposition");
            }
            file_put_contents('/tmp/debug_service.log', "Imposition copiée avec succès\n", FILE_APPEND);
        }
        
        // Copier le layout
        $targetLayoutFile = $workDir . DIRECTORY_SEPARATOR . 'layout.tex';
        
        $layoutFile = null;
        if ($isUserLayout) {
            if ($userId) {
                // Chemin direct si userId est connu
                $layoutFile = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . $layout . '.tex';
                if (!file_exists($layoutFile)) {
                    throw new \Exception("Fichier de layout utilisateur introuvable: " . $layoutFile);
                }
                if (!copy($layoutFile, $targetLayoutFile)) {
                    throw new \Exception("Impossible de copier le fichier de layout utilisateur");
                }
            } else {
                // Chercher dans tous les dossiers utilisateurs
                error_log("Recherche du fichier de layout dans tous les dossiers utilisateurs...");
                $userTemplatesDir = $this->userTemplatesDir;
                $found = false;
                
                if (is_dir($userTemplatesDir)) {
                    $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                    foreach ($userDirs as $userDir) {
                        $possibleFile = $userDir . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . $layout . '.tex';
                        if (file_exists($possibleFile)) {
                            $layoutFile = $possibleFile;
                            error_log("Fichier de layout trouvé: " . $layoutFile);
                            if (copy($layoutFile, $targetLayoutFile)) {
                                $found = true;
                                break;
                            }
                        }
                    }
                }
                
                if (!$found) {
                    if (!FileUtils::copyTemplateFile($this->typesetDir, 'layout', $layout, $targetLayoutFile)) {
                        throw new \Exception("Fichier de layout introuvable: $layout");
                    }
                }
            }
        } else {
            if (!FileUtils::copyTemplateFile($this->typesetDir, 'layout', $layout, $targetLayoutFile)) {
                throw new \Exception("Fichier de layout introuvable: $layout");
            }
        }
        
        // Si une couverture est spécifiée, la copier aussi
        $targetCoverFile = null;
        if (!empty($cover)) {
            $targetCoverFile = $workDir . DIRECTORY_SEPARATOR . 'cover.tex';
            
            $coverFile = null;
            if ($isUserCover) {
                if ($userId) {
                    // Chemin direct si userId est connu
                    $coverDir = $this->userTemplatesDir . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . 'cover';
                    $coverFile = $this->findTemplateFileWithVariants($coverDir, $cover, 'cover');
                    
                    if (!$coverFile) {
                        throw new \Exception("Fichier de couverture utilisateur introuvable pour: " . $cover);
                    }
                } else {
                    // Chercher dans tous les dossiers utilisateurs
                    error_log("Recherche du fichier de couverture dans tous les dossiers utilisateurs...");
                    $userTemplatesDir = $this->userTemplatesDir;
                    $found = false;
                    
                    if (is_dir($userTemplatesDir)) {
                        $userDirs = glob($userTemplatesDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
                        foreach ($userDirs as $userDir) {
                            $coverDir = $userDir . DIRECTORY_SEPARATOR . 'cover';
                            $possibleFile = $this->findTemplateFileWithVariants($coverDir, $cover, 'cover');
                            
                            if ($possibleFile) {
                                $coverFile = $possibleFile;
                                error_log("Fichier de couverture trouvé: " . $coverFile);
                                $found = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$found) {
                        // Si toujours pas trouvé, utiliser le chemin système par défaut
                        error_log("Impossible de trouver le fichier cover dans les dossiers utilisateurs, tentative avec le système");
                        $coverFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'cover' . DIRECTORY_SEPARATOR . $cover . '.tex';
                    }
                }
            } else {
                // Chemin système standard
                $coverFile = $this->typesetDir . DIRECTORY_SEPARATOR . 'cover' . DIRECTORY_SEPARATOR . $cover . '.tex';
            }
            
            // Vérifier que le fichier existe
            if (!file_exists($coverFile)) {
                error_log("Fichier template introuvable: " . $coverFile);
                throw new \Exception("Fichier de couverture introuvable: " . $cover);
            }
            
            if (!copy($coverFile, $targetCoverFile)) {
                throw new \Exception("Impossible de copier le fichier de couverture");
            }
            
            error_log("Fichier de couverture copié: " . $coverFile . " -> " . $targetCoverFile);
        }

        // Valider et traiter les métadonnées
        $metadata = [];
        if (isset($template['metadata'])) {
            $metadata = TemplateUtils::validateAndCleanMetadata($template['metadata']);
        }
        
        // Log des métadonnées
        error_log('Métadonnées après validation: ' . print_r($metadata, true));
        
        // Mettre à jour les fichiers LaTeX avec les métadonnées
        TemplateUtils::updateLatexFile(
            $targetLayoutFile,
            $metadata,
            isset($template['booleanOptions']) ? $template['booleanOptions'] : []
        );
        
        TemplateUtils::updateLatexFile(
            $targetImposeFile,
            $metadata,
            isset($template['booleanOptions']) ? $template['booleanOptions'] : []
        );
        
        if ($targetCoverFile) {
            TemplateUtils::updateLatexFile(
                $targetCoverFile,
                $metadata,
                isset($template['booleanOptions']) ? $template['booleanOptions'] : []
            );
        }
        
        // Copier les images téléversées dans le dossier images du workspace
        $this->copyUploadedImagesToWorkspace($metadata, $workDir);
        
        // Créer un fichier JSON avec les métadonnées
        $metadataFile = $workDir . DIRECTORY_SEPARATOR . 'metadata.json';
        if (file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT)) === false) {
            throw new \Exception("Impossible d'écrire le fichier de métadonnées");
        }

        // Log pour le débogage
        error_log("Fichiers préparés pour l'imposition: " . realpath($workDir));
        
        // Obtenir le chemin relatif du dossier de travail par rapport au dossier workspace
        $relativeWorkDir = basename($workDir);
        
        // Étape 1: Convertir Markdown en LaTeX via le service de conversion Markdown
        error_log("=== AVANT CONVERSION MARKDOWN ===");
        $this->mdConversionService->setConversionMethod($conversionMethod);
        $this->mdConversionService->convertMarkdownToLatex($relativeWorkDir);
        error_log("=== PANDOC RÉUSSI ===");
        error_log("=== PANDOC RÉUSSI, DÉBUT ÉTAPE 2 ===");
        
        try {
            // Étape 2: Copier layout.tex vers main.tex dans le processor (toujours)
            usleep(200000);
            error_log("=== EXÉCUTION LS -L ===");
            $lsCmd = 'ls -l';
            $this->executeInProcessor($relativeWorkDir, $lsCmd);
            error_log("=== EXÉCUTION CP LAYOUT ===");
            $copyLayoutCmd = 'cp layout.tex main.tex';
            error_log("Commande copie layout (processor): " . $copyLayoutCmd);
            $cpLayoutResult = $this->executeInProcessor($relativeWorkDir, $copyLayoutCmd);
            if ($cpLayoutResult !== 0) {
                throw new \Exception("Erreur lors de la préparation du template: copie layout.tex → main.tex a échoué");
            }
        } catch (\Exception $e) {
            error_log("=== EXCEPTION CAPTURÉE ===");
            error_log("Message: " . $e->getMessage());
            error_log("Fichier: " . $e->getFile() . ":" . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
            throw $e;
        }
        
        // Créer un fichier main.tex corrigé pour s'assurer que les polices sont correctement référencées
        // Modifier pour ne l'appliquer que sur les templates utilisateur
        if ($isUserLayout) {
            $this->createCorrectedMainTex($workDir, $template);
        }
        
        // Installer les polices utilisateur si nécessaire
        if ($isUserLayout && $userId) {
            // Mettre à jour le cache des polices
            $installFontsCmd = 'mkdir -p /usr/local/share/fonts/user && fc-cache -fv';
                
            error_log("Installation des polices: " . $installFontsCmd);
            exec($installFontsCmd, $output, $returnVar);
            error_log("Sortie installation polices: " . implode("\n", $output));
            
            // Créer les liens symboliques
            $this->createUserFontsLink($workDir, $relativeWorkDir, $userId);
        }
        
        // Compiler avec XeLaTeX via le conteneur processor (DEUX PASSES pour la table des matières)
        $xelatexCmd = 'export TEXINPUTS=.:fonts:/typeset/fonts: && ' .
            'export OSFONTDIR=fonts:/typeset/fonts && ' .
            'xelatex -interaction=nonstopmode main.tex';
        error_log("Première passe xelatex via processor: " . $xelatexCmd);
        
        // Première passe : génère le fichier .toc et compile le document
        $processResult1 = $this->executeInProcessor($relativeWorkDir, $xelatexCmd);
        
        // Deuxième passe : lit le fichier .toc et génère la table des matières
        error_log("Deuxième passe xelatex pour la table des matières");
        $processResult2 = $this->executeInProcessor($relativeWorkDir, $xelatexCmd);
        
        // Utiliser le résultat de la deuxième passe pour la validation
        $processResult = $processResult2;
        
        // Vérifier si le PDF a été créé, même si le processus retourne une erreur
        // (car LaTeX peut générer des avertissements qui causent un code d'erreur mais le PDF est quand même valide)
        $pdfPath = $workDir . DIRECTORY_SEPARATOR . 'main.pdf';
        
        if ($processResult !== 0) {
            error_log("Sortie du processeur avec erreur, vérification du PDF...");
            
            // Vérifier si le PDF a été créé malgré l'erreur
            if (!FileUtils::validateFileOutput($pdfPath)) {
                throw new \Exception("Erreur de traitement via le conteneur processor");
            } else {
                // Log l'avertissement mais continuer le processus
                error_log("Avertissement: Le processus a retourné des erreurs, mais le PDF semble avoir été généré correctement");
            }
        }

        // Vérifier si le PDF existe et a une taille raisonnable
        if (!FileUtils::validateFileOutput($pdfPath)) {
            throw new \Exception('Le fichier PDF n\'a pas été généré ou est trop petit');
        }

        error_log("PDF généré avec succès: " . realpath($pdfPath));

        // Étape 4: Copier main.pdf vers export.pdf DANS LE PROCESSOR
        $cpPdfCmd = 'cp main.pdf export.pdf';
        error_log("Commande copie main.pdf vers export.pdf (processor): " . $cpPdfCmd);
        $cpPdfResult = $this->executeInProcessor($relativeWorkDir, $cpPdfCmd);
        
        if ($cpPdfResult !== 0) {
            error_log("Erreur lors de la copie main.pdf vers export.pdf dans le processor");
            throw new \Exception("Erreur lors de la préparation du PDF pour imposition");
        }
        
        // Étape 5: Compiler impose.tex avec XeLaTeX DANS LE PROCESSOR
        $processCmd = 'mkdir -p ~/.fonts && cp -f fonts/*.ttf ~/.fonts/ 2>/dev/null || true && cp -f fonts/*.otf ~/.fonts/ 2>/dev/null || true && fc-cache -fv && TEXINPUTS=.:fonts:/usr/local/share/fonts/custom: OSFONTDIR=~/.fonts:fonts:/usr/local/share/fonts/custom xelatex -interaction=nonstopmode -no-shell-escape impose.tex';
        error_log("Commande de traitement imposition (processor): " . $processCmd);
        $processResult = $this->executeInProcessor($relativeWorkDir, $processCmd);
        
        // Vérifier si le PDF a été créé
        $pdfPath = $workDir . DIRECTORY_SEPARATOR . 'main.pdf';
        
        if ($processResult !== 0) {
            error_log("Sortie du processeur (traitement imposition) avec erreur, vérification du PDF...");
            
            // Vérifier si le PDF a été créé malgré l'erreur
            if (!FileUtils::validateFileOutput($pdfPath)) {
                throw new \Exception("Erreur de traitement imposition via le conteneur processor");
            } else {
                // Log l'avertissement mais continuer le processus
                error_log("Avertissement: Le processus d'imposition a retourné des erreurs, mais le PDF semble avoir été généré correctement");
            }
        }

        // Vérifier que le PDF existe et a une taille raisonnable
        if (!FileUtils::validateFileOutput($pdfPath)) {
            throw new \Exception('Le fichier PDF n\'a pas été généré');
        }
        
        // Étape 6: Copier le PDF généré vers source.pdf DANS LE PROCESSOR
        $copySourceCmd = 'cp main.pdf source.pdf';
        error_log("Commande copie main.pdf vers source.pdf (processor): " . $copySourceCmd);
        $copySourceResult = $this->executeInProcessor($relativeWorkDir, $copySourceCmd);
        
        if ($copySourceResult !== 0) {
            throw new \Exception("Impossible de créer le fichier source.pdf pour l'imposition");
        }
        error_log("main.pdf copié vers source.pdf pour l'imposition");
        
        // Étape 7: Déterminer le type d'imposition (signature ou spread)
        $imposeType = 'signature'; // Par défaut
        if (strpos($impose, 'spread') !== false) {
            $imposeType = 'spread';
        }
        
        // Étape 8: Extraire le nombre de pages par unité (4, 8, 16, etc.)
        preg_match('/(\d+)(signature|spread)/', $impose, $matches);
        $pagesPerUnit = isset($matches[1]) ? (int)$matches[1] : 4; // Par défaut à 4 si non trouvé
        
        // Étape 9: Calculer le nombre de pages du PDF d'entrée DANS LE PROCESSOR
        error_log("Vérification du nombre de pages du PDF dans le processor");
        
        // Utiliser pdftk pour obtenir le nombre de pages DANS LE PROCESSOR
        $pdftkCmd = 'pdftk source.pdf dump_data';
        error_log("Commande pdftk (processor): " . $pdftkCmd);
        
        // Créer un script temporaire pour capturer la sortie de pdftk
        $pdftkScript = "pdftk source.pdf dump_data > pdftk_output.txt 2>&1";
        $pdftkResult = $this->executeInProcessor($relativeWorkDir, $pdftkScript);
        
        if ($pdftkResult !== 0) {
            throw new \Exception("Impossible d'exécuter pdftk pour obtenir le nombre de pages");
        }
        
        // Lire le fichier de sortie de pdftk
        $pdftkOutputFile = $workDir . DIRECTORY_SEPARATOR . 'pdftk_output.txt';
        if (!file_exists($pdftkOutputFile)) {
            throw new \Exception("Fichier de sortie pdftk introuvable");
        }
        
        $pdftkOutput = file($pdftkOutputFile, FILE_IGNORE_NEW_LINES);
        $totalPages = 0;
        foreach ($pdftkOutput as $line) {
            if (preg_match('/NumberOfPages:\s*(\d+)/', $line, $matches)) {
                $totalPages = (int)$matches[1];
                error_log("Nombre de pages détecté avec pdftk: " . $totalPages);
                break;
            }
        }
        
        // Si échec, essayer avec d'autres méthodes
        if ($totalPages === 0) {
            throw new \Exception("Impossible de déterminer le nombre de pages du document source");
        }
        
        // Étape 10: Ajouter des pages blanches pour atteindre un multiple du nombre de pages par unité
        $targetPages = ceil($totalPages / $pagesPerUnit) * $pagesPerUnit;
        
        error_log("Pages totales: $totalPages, Pages cibles: $targetPages, Pages par unité: $pagesPerUnit");
        
        // Vérifier si le fichier source.pdf existe avant de continuer
        $sourcePdfPath = $workDir . DIRECTORY_SEPARATOR . 'source.pdf';
        if (!file_exists($sourcePdfPath)) {
            throw new \Exception("Erreur critique: Le fichier source.pdf n'existe pas: $sourcePdfPath");
        }
        
        // Étape 7: Traiter selon le type d'imposition
        $finalPdfPath = $this->processImposition(
            $workDir, 
            $sourcePdfPath, 
            $relativeWorkDir, 
            $imposeType, 
            $totalPages, 
            $targetPages, 
            $pagesPerUnit, 
            $paperThickness
        );
        
        // Étape 8: Copier le PDF final dans le dossier public
        if (!FileUtils::ensureDirectoryExists($this->publicDir)) {
            throw new \Exception("Impossible de créer le dossier public");
        }
        
        $publicPdfPath = $this->publicDir . DIRECTORY_SEPARATOR . $docId . '.pdf';
        if (!copy($finalPdfPath, $publicPdfPath)) {
            throw new \Exception("Impossible de copier le PDF d'imposition dans le dossier public");
        }
        
        error_log("PDF d'imposition final copié vers: " . realpath($publicPdfPath));

        // Préparer la réponse
        $baseUrl = $this->config['api']['url'];
        $fileUrl = $baseUrl . '/pdf/' . $docId;
        
        // Extraire les métadonnées du contenu Markdown
        $documentMetadata = \App\Utils\TemplateUtils::extractMarkdownMetadata($content);
        
        // Utiliser UNIQUEMENT les métadonnées des variables de template (comme {{titre}}, {{auteur}})
        // qui sont saisies dans l'interface TemplateSelector
        $templateMetadata = [];
        if (isset($template['metadata']) && is_array($template['metadata'])) {
            $templateMetadata = $template['metadata'];
        }
        
        // Priorité absolue aux métadonnées de template, ignorer le contenu Markdown
        $finalMetadata = $templateMetadata;
        
        // Si pas de titre dans le template, utiliser "sans titre"
        if (empty($finalMetadata['titre']) && empty($finalMetadata['title'])) {
            $finalMetadata['titre'] = 'sans titre';
        }
        
        // Utiliser l'heure exacte de création du document
        $creationTime = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        
        $filename = \App\Utils\TemplateUtils::generateDocumentFilename($finalMetadata, $docId, 'impose');
        
        return [
            'status' => 'success',
            'message' => 'Imposition réussie',
            'pdf_url' => $baseUrl . '/api/pdf/' . $docId,
            'document_id' => $docId,
            'metadata' => $finalMetadata,
            'filename' => $filename,
            'creation_time' => $creationTime->format('Y-m-d H:i:s'),
            'totalPages' => $totalPages,
            'targetPages' => $targetPages,
            'pagesPerUnit' => $pagesPerUnit,
            'paperThickness' => $paperThickness,
            'template' => [
                'layout' => $layout,
                'cover' => $cover,
                'impose' => $impose
            ]
        ];
    }

    /**
     * Traite l'imposition selon le type (signature ou spread)
     * 
     * @param string $workDir Dossier de travail
     * @param string $sourcePdfPath Chemin du PDF source
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @param string $imposeType Type d'imposition
     * @param int $totalPages Nombre total de pages
     * @param int $targetPages Nombre de pages cibles
     * @param int $pagesPerUnit Nombre de pages par unité
     * @param float $paperThickness Épaisseur du papier en mm
     * @return string Chemin du PDF final
     */
    private function processImposition($workDir, $sourcePdfPath, $relativeWorkDir, $imposeType, $totalPages, $targetPages, $pagesPerUnit, $paperThickness)
    {
        // Extraire les dimensions du PDF source avec pdftk
        $pdfDimensions = $this->getPdfDimensions($workDir, $sourcePdfPath, $relativeWorkDir);
        error_log("Dimensions du PDF source: " . print_r($pdfDimensions, true));
        
        // Créer une page blanche si nécessaire
        if ($targetPages > $totalPages) {
            error_log("Ajout de " . ($targetPages - $totalPages) . " pages blanches");
            
            // Créer une page blanche avec les mêmes dimensions que le document source
            $blankTexPath = $workDir . DIRECTORY_SEPARATOR . 'blank.tex';
            
            // Utiliser les dimensions du PDF source pour créer la page blanche
            $paperWidth = isset($pdfDimensions['width']) ? $pdfDimensions['width'] : '210';
            $paperHeight = isset($pdfDimensions['height']) ? $pdfDimensions['height'] : '297';
            
            $blankTexContent = "\\documentclass{article}
\\usepackage[utf8]{inputenc}
\\usepackage[T1]{fontenc}
\\usepackage{geometry}
\\geometry{paperwidth={$paperWidth}mm,paperheight={$paperHeight}mm,margin=0mm}
\\pagestyle{empty}
\\begin{document}
~
\\end{document}";
            
            file_put_contents($blankTexPath, $blankTexContent);
            
            // Compiler la page blanche DANS LE PROCESSOR
            $blankPdfCmd = 'pdflatex -interaction=nonstopmode blank.tex';
            $blankResult = $this->executeInProcessor($relativeWorkDir, $blankPdfCmd);
            
            $blankPdfPath = $workDir . DIRECTORY_SEPARATOR . 'blank.pdf';
            if (!file_exists($blankPdfPath)) {
                throw new \Exception("Impossible de créer la page blanche");
            }
            
            // Utiliser pdftk pour concaténer le document original avec les pages blanches nécessaires DANS LE PROCESSOR
            $concatCmd = 'pdftk source.pdf';
            for ($i = 0; $i < ($targetPages - $totalPages); $i++) {
                $concatCmd .= ' blank.pdf';
            }
            $concatCmd .= ' cat output padded.pdf';
            error_log("Commande de concaténation (processor): " . $concatCmd);
            $concatResult = $this->executeInProcessor($relativeWorkDir, $concatCmd);
            
            // Remplacer le fichier source.pdf par la version avec pages blanches
            $paddedPdfPath = $workDir . DIRECTORY_SEPARATOR . 'padded.pdf';
            if (file_exists($paddedPdfPath)) {
                unlink($sourcePdfPath);
                rename($paddedPdfPath, $sourcePdfPath);
                error_log("Fichier source.pdf remplacé par la version avec pages blanches");
            } else {
                throw new \Exception("Impossible de créer le fichier avec pages blanches");
            }
        }
        
        // Pour les spreads, réorganiser toutes les pages du document
        if ($imposeType === 'spread') {
            error_log("Réorganisation des pages pour le mode spread");
            
            // Réordonner le document entier avec le motif 1,n,2,n-1,3,n-2,...
            $reorderedDocPath = $workDir . DIRECTORY_SEPARATOR . 'reordered_source.pdf';
            
            // Construire la séquence de réordonnancement pour tout le document
            $reorderSequence = [];
            
            // Boucle pour construire la séquence 1,n,2,n-1,3,n-2,...
            for ($j = 1; $j <= $targetPages / 2; $j++) {
                $leftPage = $j;
                $reorderSequence[] = $leftPage;
                
                $rightPage = $targetPages - $j + 1;
                $reorderSequence[] = $rightPage;
            }
            
            // Construire la commande pdftk avec le format correct
            $reorderPages = "";
            foreach ($reorderSequence as $pageNum) {
                $reorderPages .= "$pageNum ";
            }
            
            // Supprimer l'espace final
            $reorderPages = rtrim($reorderPages);
            
            // Réordonner les pages avec la syntaxe correcte pour pdftk DANS LE PROCESSOR
            $reorderCmd = 'pdftk source.pdf cat ' . $reorderPages . ' output reordered_source.pdf';
            error_log("Réordonnancement (processor): $reorderCmd");
            $reorderResult = $this->executeInProcessor($relativeWorkDir, $reorderCmd);
        
            if ($reorderResult !== 0 || !file_exists($reorderedDocPath)) {
                throw new \Exception("Impossible de réordonner le document");
            }
            
            // Remplacer le fichier source.pdf par la version réordonnée
            unlink($sourcePdfPath);
            rename($reorderedDocPath, $sourcePdfPath);
            error_log("Document source remplacé par la version réordonnée");
        }
        
        // Étape: Diviser le PDF en paquets pour l'imposition
        $packageDir = $workDir . DIRECTORY_SEPARATOR . 'packages';
        if (!FileUtils::ensureDirectoryExists($packageDir)) {
            throw new \Exception("Impossible de créer le dossier pour les paquets");
        }
        
        // Créer le dossier imposed également
        $imposedDir = $workDir . DIRECTORY_SEPARATOR . 'imposed';
        if (!FileUtils::ensureDirectoryExists($imposedDir)) {
            throw new \Exception("Impossible de créer le dossier pour les paquets imposés");
        }
        
        // Nombre total de paquets
        $totalPackages = $targetPages / $pagesPerUnit;
        error_log("Nombre total de paquets: $totalPackages");
        
        // Si nous n'avons qu'un seul paquet, simplifier le processus
        if ($totalPackages == 1) {
            // Directement copier source.pdf vers package_001.pdf
            $packageName = 'package_001.pdf';
            $packagePath = $packageDir . DIRECTORY_SEPARATOR . $packageName;
            
            if (!copy($sourcePdfPath, $packagePath)) {
                throw new \Exception("Impossible de créer le paquet - la copie directe a échoué");
            }
        } else {
            // Traitement normal pour plusieurs paquets
            for ($i = 0; $i < $totalPackages; $i++) {
                $startPage = ($i * $pagesPerUnit) + 1;
                $endPage = ($i + 1) * $pagesPerUnit;
                
                $packageName = sprintf('package_%03d.pdf', $i + 1);
                $packagePath = $packageDir . DIRECTORY_SEPARATOR . $packageName;
                
                // Extraire les pages pour ce paquet DANS LE PROCESSOR
                $extractCmd = 'pdftk source.pdf cat ' . $startPage . '-' . $endPage . ' output packages/' . $packageName;
                error_log("Extraction du paquet $i (pages $startPage-$endPage) (processor): $extractCmd");
                $extractResult = $this->executeInProcessor($relativeWorkDir, $extractCmd);
                
                if ($extractResult !== 0 || !FileUtils::validateFileOutput($packagePath)) {
                    throw new \Exception("Impossible de créer le paquet $i");
                }
            }
        }
        
        // Pour les brochures à cheval (spread), les paquets les plus intérieurs sont les derniers
        // Imposer chaque paquet
        for ($i = 0; $i < $totalPackages; $i++) {
            $packageName = sprintf('package_%03d.pdf', $i + 1);
            $packagePath = $packageDir . DIRECTORY_SEPARATOR . $packageName;
            $imposedPath = $imposedDir . DIRECTORY_SEPARATOR . 'imposed_' . $packageName;
            
            // Vérifier que le paquet existe
            if (!file_exists($packagePath)) {
                throw new \Exception("Le paquet $packageName n'existe pas");
            }
        
            // Copier le paquet vers export.pdf (utilisé dans les templates d'imposition)
            $exportPath = $workDir . DIRECTORY_SEPARATOR . 'export.pdf';
            if (file_exists($exportPath)) {
                unlink($exportPath);
            }
            
            if (!copy($packagePath, $exportPath)) {
                throw new \Exception("Impossible de copier le paquet pour l'imposition");
            }
            
            // S'assurer que le fichier impose.tex existe
            $imposeTexPath = $workDir . DIRECTORY_SEPARATOR . 'impose.tex';
            if (!file_exists($imposeTexPath)) {
                throw new \Exception("Le fichier impose.tex n'existe pas");
            }
            
            // Calculer la compensation pour ce paquet si c'est une imposition de type spread
            if ($imposeType === 'spread' && $paperThickness > 0) {
                // Pour les brochures à cheval, dans la réalité physique du livre:
                // - Paquet 0 (premier): le plus extérieur → compensation plus grande
                // - Paquet N-1 (dernier): le plus intérieur → compensation standard (-1.10mm)
                
                // Calcul du unitIndex: une progression linéaire du plus extérieur au plus intérieur
                // Le paquet 0 (extérieur) aura le unitIndex maximum, le paquet N-1 (intérieur) aura unitIndex=0
                $unitIndex = $totalPackages - 1 - $i;
                
                // compensation = baseCompensation + (unitIndex × 2 × paperThickness)
                // On prend -1.10mm comme valeur de base (défini dans le modèle d'origine)
                $baseCompensation = -1.10; // mm, défini dans le template original
                $compensation = $baseCompensation + ($unitIndex * 2 * $paperThickness);
                
                error_log("Paquet $i: unitIndex = $unitIndex, compensation = $compensation mm");
                
                // Lire le contenu du fichier impose.tex
                $imposeContent = file_get_contents($imposeTexPath);
                
                // Remplacer la valeur de compensation dans le fichier
                $imposeContent = preg_replace(
                    '/\\\\newcommand{\\\\compensation}{([^}]+)}/', 
                    "\\newcommand{\\compensation}{" . $compensation . "mm}", 
                    $imposeContent
                );
                
                // Écrire le fichier modifié
                file_put_contents($imposeTexPath, $imposeContent);
                error_log("Compensation ajustée à $compensation mm pour le paquet $i");
            }
        
            // Compiler le template d'imposition DANS LE PROCESSOR
            $imposeCmd = 'mkdir -p ~/.fonts && cp -f fonts/*.ttf ~/.fonts/ 2>/dev/null || true && cp -f fonts/*.otf ~/.fonts/ 2>/dev/null || true && fc-cache -fv && TEXINPUTS=.:fonts:/usr/local/share/fonts/custom: OSFONTDIR=~/.fonts:fonts:/usr/local/share/fonts/custom xelatex -interaction=nonstopmode -no-shell-escape impose.tex';
            error_log("Imposition du paquet $i (processor): $imposeCmd");
            $imposeResult = $this->executeInProcessor($relativeWorkDir, $imposeCmd);
        
            // Vérifier le résultat
            $imposePdfPath = $workDir . DIRECTORY_SEPARATOR . 'impose.pdf';
            if (!FileUtils::validateFileOutput($imposePdfPath)) {
                // Tenter une seconde compilation
                error_log("Tentative de seconde compilation pour le paquet $i");
                $secondRunResult = $this->executeInProcessor($relativeWorkDir, $imposeCmd);
                
                if (!FileUtils::validateFileOutput($imposePdfPath)) {
                    throw new \Exception("Impossible d'imposer le paquet $i");
                }
            }
            
            // Déplacer le fichier imposé
            if (!rename($imposePdfPath, $imposedPath)) {
                // Si le rename échoue, essayer une copie suivie d'une suppression
                if (copy($imposePdfPath, $imposedPath)) {
                    unlink($imposePdfPath);
                } else {
                    throw new \Exception("Impossible de déplacer le paquet imposé $i");
                }
            }
        }
        
        // Fusionner tous les paquets imposés
        $imposedFiles = glob($imposedDir . DIRECTORY_SEPARATOR . 'imposed_*.pdf');
        if (empty($imposedFiles)) {
            throw new \Exception("Aucun fichier imposé n'a été généré");
        }
        
        // Trier les fichiers pour s'assurer qu'ils sont dans le bon ordre
        sort($imposedFiles);
        
        error_log("Fichiers imposés trouvés: " . count($imposedFiles));
        error_log("Ordre des fichiers: " . implode(", ", array_map('basename', $imposedFiles)));
        
        // Si un seul fichier, pas besoin de fusion
        $finalPdfPath = $workDir . DIRECTORY_SEPARATOR . 'final_imposed.pdf';
        if (count($imposedFiles) === 1) {
            if (!copy($imposedFiles[0], $finalPdfPath)) {
                throw new \Exception("Impossible de copier le fichier imposé unique vers le fichier final");
            }
        } else {
            // Vérifier que tous les fichiers existent avant la fusion
            foreach ($imposedFiles as $file) {
                if (!file_exists($file)) {
                    throw new \Exception("Fichier imposé manquant: " . basename($file));
                }
            }
            
            // Pour plusieurs fichiers, les fusionner DANS LE PROCESSOR
            $imposedDir = $workDir . DIRECTORY_SEPARATOR . 'imposed';
            // Construire la commande avec les noms de fichiers corrects
            $fileNames = array_map(function($file) { return basename($file); }, $imposedFiles);
            $mergeCmd = 'pdftk ' . implode(' ', array_map(function($name) { return '"' . $name . '"'; }, $fileNames)) . ' cat output "../final_imposed.pdf"';
            error_log("Fusion des paquets imposés (processor): $mergeCmd");
            error_log("Fichiers à fusionner: " . implode(', ', $fileNames));
            $mergeResult = $this->executeInProcessor($relativeWorkDir . '/imposed', $mergeCmd);
            
            if ($mergeResult !== 0 || !FileUtils::validateFileOutput($finalPdfPath)) {
                $errorDetails = "Code retour: $mergeResult";
                error_log("Erreur de fusion pdftk: " . $errorDetails);
                throw new \Exception("Impossible de fusionner les fichiers imposés. Détails: " . $errorDetails);
            }
        }
        
        return $finalPdfPath;
    }

    /**
     * Récupère les dimensions du PDF source en utilisant pdftk
     * 
     * @param string $workDir Dossier de travail
     * @param string $sourcePdfPath Chemin du PDF source
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @return array Tableau associatif contenant width et height en mm
     */
    private function getPdfDimensions($workDir, $sourcePdfPath, $relativeWorkDir)
    {
        $dimensions = ['width' => 210, 'height' => 297]; // Valeurs par défaut A4
        
        // Utiliser pdftk pour récupérer les informations sur le document
        $pdftkCmd = 'pdftk source.pdf dump_data';
        error_log("Commande pdftk pour les dimensions: " . $pdftkCmd);
        $pdftkOutput = [];
        $pdftkResult = 0;
        exec($pdftkCmd . ' 2>&1', $pdftkOutput, $pdftkResult);
        
        if ($pdftkResult !== 0) {
            error_log("Erreur lors de la récupération des dimensions: " . implode("\n", $pdftkOutput));
            return $dimensions;
        }
        
        // Chercher la ligne qui contient PageMediaDimensions
        foreach ($pdftkOutput as $line) {
            if (preg_match('/PageMediaDimensions:\s+([\d.]+)\s+([\d.]+)/', $line, $matches)) {
                // Les dimensions sont en points (72 points = 1 pouce = 25.4 mm)
                $widthPoints = floatval($matches[1]);
                $heightPoints = floatval($matches[2]);
                
                // Conversion en mm (1 point = 0.3528 mm)
                $dimensions['width'] = round($widthPoints * 0.3528, 2);
                $dimensions['height'] = round($heightPoints * 0.3528, 2);
                
                error_log("Dimensions du PDF détectées: " . $dimensions['width'] . " x " . $dimensions['height'] . " mm");
                break;
            }
        }
        
        return $dimensions;
    }

    private function copyUploadedImagesToWorkspace($metadata, $workDir)
    {
        $imagesDir = $workDir . DIRECTORY_SEPARATOR . 'images';
        
        // S'assurer que le dossier images existe
        if (!FileUtils::ensureDirectoryExists($imagesDir)) {
            throw new \Exception("Impossible de créer le dossier images");
        }
        
        // Parcourir les métadonnées et chercher les champs qui pourraient contenir des images
        foreach ($metadata as $key => $value) {
            // Si le champ contient 'image' ou 'couv' et n'est pas vide
            if ((stripos($key, 'image') !== false || stripos($key, 'couv') !== false) && !empty($value)) {
                // Corriger le problème d'échappement des caractères underscore
                $cleanValue = str_replace('\_', '_', $value);
                $uploadsDir = $GLOBALS['config']['paths']['uploads'];
                
                // Vérifier que le chemin uploads est bien formé
                if (!is_dir($uploadsDir)) {
                    error_log("Erreur: Le chemin uploads n'existe pas: {$uploadsDir}");
                    continue;
                }
                
                $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . basename($cleanValue);
                $targetFile = $imagesDir . DIRECTORY_SEPARATOR . basename($cleanValue);
                
                error_log("Tentative de copie d'image: {$sourceFile} -> {$targetFile}");
                
                // Vérifier si le fichier source existe
                if (file_exists($sourceFile)) {
                    // Copier l'image depuis le dossier uploads vers le dossier images du workspace
                    if (!copy($sourceFile, $targetFile)) {
                        error_log("Avertissement: Impossible de copier l'image {$sourceFile} vers {$targetFile}");
                    } else {
                        error_log("Image copiée avec succès: {$sourceFile} -> {$targetFile}");
                    }
                } else {
                    error_log("Fichier image introuvable: {$sourceFile}");
                    
                    // Essayer avec d'autres noms possibles (fichier sans caractères échappés, etc.)
                    $alternativeFile = $uploadsDir . DIRECTORY_SEPARATOR . str_replace(['\_', '\-'], ['_', '-'], basename($value));
                    if (file_exists($alternativeFile)) {
                        error_log("Trouvé une alternative: {$alternativeFile}");
                        if (copy($alternativeFile, $targetFile)) {
                            error_log("Image alternative copiée avec succès!");
                        }
                    }
                }
            }
        }
    }

    private function extractAndCopyImagesFromMarkdown($content, $imagesDir)
    {
        // Récupérer le répertoire des uploads
        $uploadsDir = $GLOBALS['config']['paths']['uploads'];
        
        // S'assurer que le dossier uploads existe
        if (!is_dir($uploadsDir)) {
            error_log("Avertissement: Le dossier uploads n'existe pas: " . $uploadsDir);
            return;
        }
        
        // Démarrer la session si ce n'est pas déjà fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Obtenir l'ID de session
        $sessionId = session_id();
        $safeSessionId = preg_replace('/[^a-z0-9_]/i', '', $sessionId);
        $shortSessionId = substr($safeSessionId, 0, 10);
        
        // Obtenir la table d'association des fichiers uploadés pour cet utilisateur
        $sessionUploadedFiles = isset($_SESSION['uploaded_files']) ? $_SESSION['uploaded_files'] : [];
        
        // Extraire toutes les références d'images au format ![[image.png]]
        $pattern = '/!\[\[([^\]]+)\]\]/';
        preg_match_all($pattern, $content, $matches);
        
        if (isset($matches[1]) && !empty($matches[1])) {
            // Récupérer tous les fichiers du dossier uploads pour la correspondance
            $uploadedFiles = glob($uploadsDir . DIRECTORY_SEPARATOR . '*');
            $uploadedFilesMap = [];
            $sessionFilesMap = [];
            
            // Construire une carte des fichiers uploadés pour recherche rapide
            foreach ($uploadedFiles as $file) {
                $fileName = basename($file);
                // Vérifier si ce fichier appartient à la session courante
                $isSessionFile = strpos($fileName, $shortSessionId . '_') === 0;
                
                // Stocker aussi une version "nettoyée" pour la correspondance simplifiée
                $baseFileName = preg_replace('/^[a-z0-9_]+_[a-z0-9]+_/', '', $fileName);
                
                // Séparer les fichiers de la session courante des autres
                if ($isSessionFile) {
                    $sessionFilesMap[$baseFileName] = $fileName;
                }
                
                $uploadedFilesMap[$baseFileName] = $fileName;
            }
            
            error_log("Session ID: " . $shortSessionId);
            error_log("Fichiers de session trouvés: " . count($sessionFilesMap));
            
            foreach ($matches[1] as $imageName) {
                // Nettoyer le nom de fichier
                $cleanImageName = str_replace(['\_', '\-'], ['_', '-'], trim($imageName));
                $baseFileName = basename($cleanImageName);
                $targetFile = $imagesDir . DIRECTORY_SEPARATOR . $baseFileName;
                
                // Stratégie 1: Vérifier d'abord si l'image est dans la table d'association de session
                if (isset($sessionUploadedFiles[$baseFileName])) {
                    $matchedFileName = $sessionUploadedFiles[$baseFileName];
                    $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $matchedFileName;
                    
                    if (file_exists($sourceFile)) {
                        if (copy($sourceFile, $targetFile)) {
                            error_log("Image trouvée dans la session et copiée: {$sourceFile} -> {$targetFile}");
                            continue;
                        }
                    }
                }
                
                // Stratégie 2: Chercher dans les fichiers de la session courante
                if (isset($sessionFilesMap[$baseFileName])) {
                    $matchedFileName = $sessionFilesMap[$baseFileName];
                    $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $matchedFileName;
                    
                    if (copy($sourceFile, $targetFile)) {
                        error_log("Image de la session courante copiée: {$sourceFile} -> {$targetFile}");
                        continue;
                    }
                }
                
                // Stratégie 3: Vérifier si le fichier existe directement
                $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $baseFileName;
                if (file_exists($sourceFile)) {
                    if (copy($sourceFile, $targetFile)) {
                        error_log("Image exacte copiée: {$sourceFile} -> {$targetFile}");
                        continue;
                    }
                }
                
                // Stratégie 4: Chercher dans tous les fichiers uploadés (dernière option)
                if (isset($uploadedFilesMap[$baseFileName])) {
                    $matchedFileName = $uploadedFilesMap[$baseFileName];
                    $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $matchedFileName;
                    
                    if (copy($sourceFile, $targetFile)) {
                        error_log("Image trouvée dans uploads et copiée: {$sourceFile} -> {$targetFile}");
                        continue;
                    }
                }
                
                // Stratégie 5: Recherche partielle (comme dernier recours)
                $found = false;
                
                // D'abord essayer avec les fichiers de session
                foreach ($sessionFilesMap as $uploadedBase => $actualFile) {
                    $baseNoExt = pathinfo($baseFileName, PATHINFO_FILENAME);
                    $uploadedNoExt = pathinfo($uploadedBase, PATHINFO_FILENAME);
                    
                    if (stripos($uploadedNoExt, $baseNoExt) !== false || stripos($baseNoExt, $uploadedNoExt) !== false) {
                        $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $actualFile;
                        
                        if (copy($sourceFile, $targetFile)) {
                            error_log("Image de session avec correspondance partielle copiée: {$sourceFile} -> {$targetFile}");
                            $found = true;
                            break;
                        }
                    }
                }
                
                // Si rien n'est trouvé, essayer avec tous les fichiers
                if (!$found) {
                    foreach ($uploadedFilesMap as $uploadedBase => $actualFile) {
                        $baseNoExt = pathinfo($baseFileName, PATHINFO_FILENAME);
                        $uploadedNoExt = pathinfo($uploadedBase, PATHINFO_FILENAME);
                        
                        if (stripos($uploadedNoExt, $baseNoExt) !== false || stripos($baseNoExt, $uploadedNoExt) !== false) {
                            $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $actualFile;
                            
                            if (copy($sourceFile, $targetFile)) {
                                error_log("Image avec correspondance partielle copiée: {$sourceFile} -> {$targetFile}");
                                $found = true;
                                break;
                            }
                        }
                    }
                }
                
                if (!$found) {
                    error_log("Avertissement: Aucune correspondance trouvée pour l'image: {$baseFileName}");
                }
            }
        }

        // 2) Détecter aussi les images Markdown standard vers images/<fichier>
        //    Exemple: ![](images/photo.png)
        $mdImagePattern = '/!\[[^\]]*\]\(\s*images\/([^\)\s]+)\s*\)/i';
        if (preg_match_all($mdImagePattern, $content, $mdMatches) && !empty($mdMatches[1])) {
            // Préparer des maps pour correspondance avec le répertoire uploads
            $uploadedFiles = glob($uploadsDir . DIRECTORY_SEPARATOR . '*');
            $uploadedFilesMap = [];
            $sessionFilesMap = [];
            $sessionUploadedFiles = isset($_SESSION['uploaded_files']) ? $_SESSION['uploaded_files'] : [];

            // ID de session raccourci
            $sessionId = session_id();
            $safeSessionId = preg_replace('/[^a-z0-9_]/i', '', $sessionId);
            $shortSessionId = substr($safeSessionId, 0, 10);

            foreach ($uploadedFiles as $file) {
                $fileName = basename($file);
                $isSessionFile = strpos($fileName, $shortSessionId . '_') === 0;
                $baseFileName = preg_replace('/^[a-z0-9_]+_[a-z0-9]+_/', '', $fileName);
                if ($isSessionFile) {
                    $sessionFilesMap[$baseFileName] = $fileName;
                }
                $uploadedFilesMap[$baseFileName] = $fileName;
            }

            foreach ($mdMatches[1] as $imageRel) {
                $baseFileName = basename(str_replace(['\\_', '\\-'], ['_', '-'], trim($imageRel)));
                $targetFile = $imagesDir . DIRECTORY_SEPARATOR . $baseFileName;

                // 2.1 via table de session
                if (isset($sessionUploadedFiles[$baseFileName])) {
                    $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $sessionUploadedFiles[$baseFileName];
                    if (file_exists($sourceFile) && copy($sourceFile, $targetFile)) {
                        error_log("[MD IMG] Image trouvée via sessionUploadedFiles: {$sourceFile} -> {$targetFile}");
                        continue;
                    }
                }

                // 2.2 via fichiers de la session courante
                if (isset($sessionFilesMap[$baseFileName])) {
                    $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $sessionFilesMap[$baseFileName];
                    if (file_exists($sourceFile) && copy($sourceFile, $targetFile)) {
                        error_log("[MD IMG] Image de la session copiée: {$sourceFile} -> {$targetFile}");
                        continue;
                    }
                }

                // 2.3 fichier direct dans uploads
                $directFile = $uploadsDir . DIRECTORY_SEPARATOR . $baseFileName;
                if (file_exists($directFile) && copy($directFile, $targetFile)) {
                    error_log("[MD IMG] Image exacte copiée: {$directFile} -> {$targetFile}");
                    continue;
                }

                // 2.4 via map globale
                if (isset($uploadedFilesMap[$baseFileName])) {
                    $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $uploadedFilesMap[$baseFileName];
                    if (file_exists($sourceFile) && copy($sourceFile, $targetFile)) {
                        error_log("[MD IMG] Image trouvée dans uploads et copiée: {$sourceFile} -> {$targetFile}");
                        continue;
                    }
                }

                // 2.5 match partiel
                $found = false;
                $baseNoExt = pathinfo($baseFileName, PATHINFO_FILENAME);
                foreach ($sessionFilesMap as $uploadedBase => $actualFile) {
                    $uploadedNoExt = pathinfo($uploadedBase, PATHINFO_FILENAME);
                    if (stripos($uploadedNoExt, $baseNoExt) !== false || stripos($baseNoExt, $uploadedNoExt) !== false) {
                        $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $actualFile;
                        if (file_exists($sourceFile) && copy($sourceFile, $targetFile)) {
                            error_log("[MD IMG] Image de session (match partiel) copiée: {$sourceFile} -> {$targetFile}");
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) {
                    foreach ($uploadedFilesMap as $uploadedBase => $actualFile) {
                        $uploadedNoExt = pathinfo($uploadedBase, PATHINFO_FILENAME);
                        if (stripos($uploadedNoExt, $baseNoExt) !== false || stripos($baseNoExt, $uploadedNoExt) !== false) {
                            $sourceFile = $uploadsDir . DIRECTORY_SEPARATOR . $actualFile;
                            if (file_exists($sourceFile) && copy($sourceFile, $targetFile)) {
                                error_log("[MD IMG] Image (match partiel) copiée: {$sourceFile} -> {$targetFile}");
                                $found = true;
                                break;
                            }
                        }
                    }
                }
                if (!$found) {
                    error_log("[MD IMG] Aucune correspondance trouvée pour l'image: {$baseFileName}");
                }
            }
        }
    }
    /**
     * Normalise les liens d'images dans le Markdown pour Pandoc/LaTeX
     * - Convertit ![[image.jpg]] -> ![](images/image.jpg)
     * - Convertit [[image.jpg]] en image si l'extension est une image
     * - Convertit ![](…/uploads/nom.png) -> ![](images/nom.png)
     */
    private function normalizeMarkdownForImages(string $content): string
    {
        // 1) ![[file|alt]] ou ![[file#anchor|alt]] -> ![alt](images/file)
        $content = preg_replace_callback(
            '/!\\\\?\[\[(?<target>[^\]|#]+)(?:#[^\]|]+)?(?:\|(?<alt>[^\]]+))?\]\]/',
            function ($m) {
                $file = basename(str_replace(['\\\_', '\\-'], ['_', '-'], trim($m['target'])));
                $alt = isset($m['alt']) ? trim($m['alt']) : '';
                return '![' . $alt . '](images/' . $file . ')';
            },
            $content
        );

        // 2) [[file]] sans ! — si extension image, le convertir en image
        $imageExts = '(?:png|jpg|jpeg|gif|svg)';
        $content = preg_replace_callback(
            '/(?<!\!)\[\[(?<target>[^\]|#]+)(?:#[^\]|]+)?\]\]/i',
            function ($m) use ($imageExts) {
                $target = trim($m['target']);
                $file = basename(str_replace(['\\\_', '\\-'], ['_', '-'], $target));
                if (preg_match('/\.' . $imageExts . '$/i', $file)) {
                    return '![](images/' . $file . ')';
                }
                // Sinon, laisser tel quel (lien interne non-image)
                return $m[0];
            },
            $content
        );

        // 3) Réécrire toute image pointant vers /uploads/… pour pointer vers images/nom
        //    Exemple: ![](http(s)://…/api/uploads/abc.png) -> ![](images/abc.png)
        $content = preg_replace_callback(
            '/!\[(?<alt>[^\]]*)\]\((?<url>[^)]+\/uploads\/[^)]+)\)/i',
            function ($m) {
                $alt = $m['alt'];
                $url = $m['url'];
                $file = basename(parse_url($url, PHP_URL_PATH));
                return '![' . $alt . '](images/' . $file . ')';
            },
            $content
        );

        // 4) Réécrire les liens via serve-image.php vers images/nom
        $content = preg_replace_callback(
            '/!\[(?<alt>[^\]]*)\]\((?<url>[^)]*serve-image\.php\/[\w%.-]+)\)/i',
            function ($m) {
                $alt = $m['alt'];
                $urlPath = parse_url($m['url'], PHP_URL_PATH);
                $file = basename($urlPath);
                $file = urldecode($file);
                return '![' . $alt . '](images/' . $file . ')';
            },
            $content
        );

        return $content;
    }

    // Copier les polices utilisateur vers le dossier de travail
    private function createUserFontsLink($workDir, $relativeWorkDir, $userId)
    {
        // Chemin vers les polices utilisateur
        $userFontDir = $this->workspaceDir . DIRECTORY_SEPARATOR . 'user_fonts' . DIRECTORY_SEPARATOR . $userId;
        $targetFontDir = $workDir . DIRECTORY_SEPARATOR . 'fonts';
        
        // S'assurer que le dossier cible existe
        if (!FileUtils::ensureDirectoryExists($targetFontDir)) {
            error_log("Impossible de créer le dossier des polices: " . $targetFontDir);
            return;
        }
        
        // Copier chaque police depuis le dossier utilisateur
        if (is_dir($userFontDir)) {
            error_log("Copie des polices depuis: " . $userFontDir . " vers " . $targetFontDir);
            
            // Lister toutes les polices
            $fontFiles = glob($userFontDir . DIRECTORY_SEPARATOR . '*.{ttf,otf,TTF,OTF}', GLOB_BRACE);
            
            foreach ($fontFiles as $fontFile) {
                $targetFile = $targetFontDir . DIRECTORY_SEPARATOR . basename($fontFile);
                error_log("Copie de la police: " . $fontFile . " -> " . $targetFile);
                
                if (!copy($fontFile, $targetFile)) {
                    error_log("Erreur lors de la copie de la police: " . $fontFile);
                }
            }
        } else {
            error_log("Dossier de polices utilisateur introuvable: " . $userFontDir);
        }
    }

    /**
     * Corrige les chemins de polices dans un fichier TeX
     * 
     * @param string $texFile Chemin du fichier TeX à modifier
     * @return bool True si la modification a réussi, false sinon
     */
    private function fixFontPaths($texFile)
    {
        if (!file_exists($texFile)) {
            error_log("Le fichier TeX à modifier n'existe pas: " . $texFile);
            return false;
        }
        
        // Lire le contenu du fichier
        $content = file_get_contents($texFile);
        if ($content === false) {
            error_log("Impossible de lire le fichier TeX: " . $texFile);
            return false;
        }
        
        // Remplacer les chemins dans les déclarations de police
        // Cas 1: Police principale (setmainfont) - pour toute police
        $content = preg_replace(
            '/\\\\setmainfont\{([^}]+)\}\s*\[\s*Path\s*=\s*[^,]+\s*,\s*Extension\s*=\s*\.ttf.*?\]/s',
            '\\setmainfont{$1}[Path=./fonts/, Extension=.ttf]',
            $content
        );
        
        // Cas 2: Police de titre (newfontfamily) - pour toute police
        // Attention à la syntaxe pour préserver les noms de commande (\titrefont, \headerfont, etc.)
        $content = preg_replace(
            '/\\\\newfontfamily\\\\([a-zA-Z]+)\{([^}]+)\}\s*\[\s*Path\s*=\s*[^,]+\s*,\s*Extension\s*=\s*\.ttf(.*?)\]/s',
            '\\newfontfamily\\\$1{$2}[Path=./fonts/, Extension=.ttf$3]',
            $content
        );
        
        // Cas 3: Police sans déclaration de chemin - ajout du chemin
        $content = preg_replace(
            '/\\\\(setmainfont|setsansfont|setmonofont)\{([^}]+)\}(?!\[)/s',
            '\\$1{$2}[Path=./fonts/, Extension=.ttf]',
            $content
        );
        
        // Cas 4: newfontfamily sans déclaration de chemin - ajout du chemin
        $content = preg_replace(
            '/\\\\newfontfamily\\\\([a-zA-Z]+)\{([^}]+)\}(?!\[)/s',
            '\\newfontfamily\\\$1{$2}[Path=./fonts/, Extension=.ttf]',
            $content
        );
        
        // Écrire le contenu modifié
        if (file_put_contents($texFile, $content) === false) {
            error_log("Impossible d'écrire le fichier TeX modifié: " . $texFile);
            return false;
        }
        
        error_log("Chemins de polices corrigés dans: " . $texFile);
        return true;
    }

    /**
     * Crée un fichier main.tex corrigé avec les bonnes références de polices
     * 
     * @param string $workDir Chemin du dossier de travail
     * @param array $template Options du template
     * @return bool Succès ou échec de l'opération
     */
    private function createCorrectedMainTex($workDir, $template)
    {
        // Chemin du fichier main.tex
        $mainTexFile = $workDir . DIRECTORY_SEPARATOR . 'main.tex';
        
        // Vérifier si main.tex existe déjà
        if (file_exists($mainTexFile)) {
            // Dans ce cas, on modifie simplement les chemins de polices
            error_log("Modification des chemins de polices dans main.tex existant");
            return $this->fixFontPaths($mainTexFile);
        }
        
        // Si on arrive ici, le fichier main.tex n'existe pas, ce qui est anormal
        error_log("Erreur: Le fichier main.tex n'existe pas pour la correction des chemins de polices");
        return false;
    }

    /**
     * Crée un nouveau fichier main.tex avec les chemins de polices corrects pour un template utilisateur
     * 
     * @param string $workDir Chemin du dossier de travail
     * @param array $template Options du template
     * @return bool Succès ou échec de l'opération
     */
    private function createUserMainTex($workDir, $template)
    {
        // Vérifier que le dossier de travail existe
        if (!is_dir($workDir)) {
            error_log("Le dossier de travail n'existe pas: " . $workDir);
            return false;
        }
        
        // Chemin du fichier main.tex
        $mainTexFile = $workDir . DIRECTORY_SEPARATOR . 'main.tex';
        
        // Vérifier si main.tex existe déjà (copié depuis layout.tex par exemple)
        if (file_exists($mainTexFile)) {
            // Dans ce cas, on modifie simplement les chemins de polices
            error_log("Modification des chemins de polices dans main.tex pour template utilisateur");
            return $this->fixFontPaths($mainTexFile);
        }
        
        // Si main.tex n'existe pas, on le crée à partir du layout.tex
        $layoutTexFile = $workDir . DIRECTORY_SEPARATOR . 'layout.tex';
        if (file_exists($layoutTexFile)) {
            // Copier layout.tex vers main.tex
            if (copy($layoutTexFile, $mainTexFile)) {
                // Puis corriger les chemins de polices
                error_log("Fichier main.tex créé à partir de layout.tex pour template utilisateur");
                return $this->fixFontPaths($mainTexFile);
            }
        }
        
        // Si on arrive ici, impossible de trouver un template valide
        error_log("Erreur: Impossible de créer main.tex - aucun template valide trouvé");
        return false;
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
        
        if (!FileUtils::ensureDirectoryExists($commandsDir)) {
            error_log("ERREUR: Impossible de créer le répertoire de commandes: " . $commandsDir);
            return 1;
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

    /**
     * Vérifie si une chaîne se termine par une sous-chaîne (compatibilité PHP < 8.0)
     * 
     * @param string $haystack La chaîne à vérifier
     * @param string $needle La sous-chaîne à rechercher à la fin
     * @return bool True si $haystack se termine par $needle
     */
    private function endsWith($haystack, $needle) {
        if (function_exists('str_ends_with')) {
            return str_ends_with($haystack, $needle);
        }
        
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        
        return (substr($haystack, -$length) === $needle);
    }

    /**
     * Tente de trouver un fichier template en essayant différentes variantes du nom
     * 
     * @param string $dir Répertoire où chercher
     * @param string $baseName Nom de base du fichier sans extension
     * @param string $type Type de template (layout, cover, impose)
     * @return string|null Chemin complet du fichier si trouvé, null sinon
     */
    private function findTemplateFileWithVariants($dir, $baseName, $type)
    {
        // S'assurer que le nom de base n'a pas d'extension
        $baseName = $this->endsWith(strtolower($baseName), '.tex') ? substr($baseName, 0, -4) : $baseName;
        
        // Vérifier que le répertoire existe
        if (!is_dir($dir)) {
            error_log("Le répertoire n'existe pas: " . $dir);
            return null;
        }
        
        error_log("Recherche de template $type pour $baseName dans le répertoire: $dir");
        
        // Vérifier d'abord si le fichier existe tel quel (avec l'extension .tex)
        // Cette vérification est valable pour tous les types de templates
        $directFile = $dir . DIRECTORY_SEPARATOR . $baseName . '.tex';
        if (file_exists($directFile)) {
            error_log("Fichier trouvé directement: " . $directFile);
            return $directFile;
        }
        
        // Si le fichier n'existe pas directement, essayer avec les variantes selon le type
        if ($type === 'cover') {
            // Rechercher tous les fichiers .tex dans le répertoire et faire une correspondance plus flexible
            $allTexFiles = glob($dir . DIRECTORY_SEPARATOR . '*.tex');
            error_log("Nombre de fichiers .tex trouvés: " . count($allTexFiles));
            
            foreach ($allTexFiles as $file) {
                $filename = basename($file);
                error_log("Vérification du fichier: " . $filename);
                
                // Vérifier si le nom du fichier contient le nom de base (insensible à la casse)
                if (stripos($filename, $baseName) !== false) {
                    error_log("Fichier correspondant trouvé: " . $file);
                    return $file;
                }
                
                // Si le nom de base contient des tirets, essayer de décomposer et vérifier chaque partie
                $baseNameParts = explode('-', $baseName);
                $matches = true;
                
                foreach ($baseNameParts as $part) {
                    if (stripos($filename, $part) === false) {
                        $matches = false;
                        break;
                    }
                }
                
                if ($matches) {
                    error_log("Fichier correspondant par parties trouvé: " . $file);
                    return $file;
                }
            }
            
            // Si le nom ne contient pas déjà le suffixe -cover-
            if (strpos($baseName, '-cover-') === false) {
                // Format officiel pour les couvertures: [nom]-[format]-cover-[format_papier].tex
                // Rechercher tous les fichiers qui correspondent au motif [baseName]-cover-*.tex
                $pattern = $dir . DIRECTORY_SEPARATOR . $baseName . '-cover-*.tex';
                $matchingFiles = glob($pattern);
                
                error_log("Recherche de fichiers correspondant au motif: " . $pattern);
                
                if (!empty($matchingFiles)) {
                    // Retourner le premier fichier correspondant
                    $coverFile = $matchingFiles[0];
                    error_log("Fichier trouvé: " . $coverFile);
                    return $coverFile;
                }
                
                // Essayer inversement avec *-[baseName].tex
                $pattern = $dir . DIRECTORY_SEPARATOR . '*' . $baseName . '.tex';
                $matchingFiles = glob($pattern);
                
                error_log("Recherche de fichiers correspondant au motif inverse: " . $pattern);
                
                if (!empty($matchingFiles)) {
                    $coverFile = $matchingFiles[0];
                    error_log("Fichier trouvé avec motif inverse: " . $coverFile);
                    return $coverFile;
                }
            }
        } else if ($type === 'layout') {
            // Si le nom ne contient pas déjà le suffixe -layout
            if (strpos($baseName, '-layout') === false) {
                // Format officiel pour les layouts: [nom]-[format]-layout.tex
                $layoutFile = $dir . DIRECTORY_SEPARATOR . $baseName . '-layout.tex';
                
                if (file_exists($layoutFile)) {
                    error_log("Fichier trouvé avec suffixe layout: " . $layoutFile);
                    return $layoutFile;
                }
            }
            
            // Rechercher tous les fichiers .tex dans le répertoire et faire une correspondance plus flexible
            $allTexFiles = glob($dir . DIRECTORY_SEPARATOR . '*.tex');
            
            foreach ($allTexFiles as $file) {
                $filename = basename($file);
                // Vérifier si le nom du fichier contient le nom de base (insensible à la casse)
                if (stripos($filename, $baseName) !== false) {
                    error_log("Fichier layout correspondant trouvé: " . $file);
                    return $file;
                }
            }
        } else if ($type === 'impose') {
            // Pas de suffixe spécifique pour impose, déjà vérifié avec le nom direct ci-dessus
            
            // Rechercher tous les fichiers .tex dans le répertoire et faire une correspondance plus flexible
            $allTexFiles = glob($dir . DIRECTORY_SEPARATOR . '*.tex');
            
            foreach ($allTexFiles as $file) {
                $filename = basename($file);
                // Vérifier si le nom du fichier contient le nom de base (insensible à la casse)
                if (stripos($filename, $baseName) !== false) {
                    error_log("Fichier impose correspondant trouvé: " . $file);
                    return $file;
                }
            }
        } else {
            // Pour les autres types de templates, essayer plusieurs motifs
            // Cette partie n'utilise pas de nomenclature officielle
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.tex');
            
            foreach ($files as $file) {
                $filename = basename($file);
                // Vérifier si le nom de base est présent dans le nom du fichier
                if (strpos($filename, $baseName) === 0) {
                    error_log("Fichier trouvé par correspondance partielle: " . $file);
                    return $file;
                }
            }
        }
        
        // Si on est ici, aucun fichier n'a été trouvé
        error_log("Aucun fichier trouvé pour $baseName dans $dir");
        return null;
    }
} 