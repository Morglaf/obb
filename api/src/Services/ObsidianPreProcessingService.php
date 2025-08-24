<?php
/**
 * Online Book Brew - Service de pré-traitement Obsidian
 * 
 * Service pour convertir les éléments spécifiques Obsidian en syntaxe Markdown standard
 * AVANT la conversion Pandoc pour éviter la déformation
 */

namespace App\Services;

class ObsidianPreProcessingService
{
    /**
     * Dossier workspace
     */
    private $workspaceDir;
    
    /**
     * Définitions des notes de bas de page collectées
     */
    private $footnoteDefinitions = [];
    
    /**
     * Constructeur
     * 
     * @param string $workspaceDir Chemin du dossier workspace
     */
    public function __construct($workspaceDir)
    {
        $this->workspaceDir = $workspaceDir;
        $this->footnoteDefinitions = [];
    }
    
    /**
     * Applique le pré-traitement Obsidian sur un fichier Markdown
     * 
     * @param string $relativeWorkDir Chemin relatif du dossier de travail
     * @param string $inputFile Nom du fichier Markdown à traiter
     * @return bool Succès de l'opération
     */
    public function processFile($relativeWorkDir, $inputFile)
    {
        error_log("=== PRÉ-TRAITEMENT OBSIDIAN ===");
        error_log("WorkDir: " . $relativeWorkDir);
        error_log("Fichier: " . $inputFile);
        
        $fullPath = $this->workspaceDir . DIRECTORY_SEPARATOR . $relativeWorkDir . DIRECTORY_SEPARATOR . $inputFile;
        error_log("Chemin complet: " . $fullPath);
        
        if (!file_exists($fullPath)) {
            error_log("ERREUR: Fichier Markdown introuvable: " . $fullPath);
            error_log("Contenu du dossier " . dirname($fullPath) . ": " . implode(', ', scandir(dirname($fullPath))));
            return false;
        }
        
        error_log("Fichier trouvé, taille: " . filesize($fullPath) . " bytes");
        
        $content = file_get_contents($fullPath);
        if ($content === false) {
            error_log("ERREUR: Impossible de lire le fichier Markdown: " . $fullPath);
            return false;
        }
        
        error_log("Contenu lu, longueur: " . strlen($content) . " caractères");
        error_log("Premiers 200 caractères: " . substr($content, 0, 200));
        
        // Vérifier si le contenu contient des notes de bas de page Obsidian
        if (preg_match('/\^\[/', $content)) {
            error_log("Notes de bas de page Obsidian détectées dans le contenu");
        } else {
            error_log("AUCUNE note de bas de page Obsidian détectée dans le contenu");
        }
        
        // Réinitialiser les définitions pour ce fichier
        $this->footnoteDefinitions = [];
        
        // 1. Convertir les notes de bas de page inline Obsidian en syntaxe Pandoc
        $content = $this->convertObsidianFootnotes($content);
        
        // Sauvegarder le contenu pré-traité
        if (file_put_contents($fullPath, $content) === false) {
            error_log("ERREUR: Impossible de sauvegarder le fichier Markdown pré-traité: " . $fullPath);
            return false;
        }
        
        error_log("Pré-traitement Obsidian terminé avec succès");
        return true;
    }
    
    /**
     * Convertit les notes de bas de page inline Obsidian en syntaxe Pandoc
     * 
     * @param string $content Contenu Markdown à traiter
     * @return string Contenu Markdown avec notes de bas de page converties
     */
    private function convertObsidianFootnotes($content)
    {
        error_log("=== CONVERSION DES NOTES DE BAS DE PAGE OBSIDIAN ===");
        
        // Pattern pour capturer ^[contenu] et le convertir en [^1] contenu
        $pattern = '/\^\[([^\]]+)\]/';
        
        // Compter les notes existantes pour générer des références uniques
        $footnoteCount = 0;
        
        // Debug: afficher le pattern et le contenu
        error_log("Pattern de recherche: " . $pattern);
        error_log("Recherche de notes de bas de page dans le contenu...");
        
        $content = preg_replace_callback($pattern, function($matches) use (&$footnoteCount) {
            $footnoteCount++;
            $footnoteRef = "[^{$footnoteCount}]";
            $footnoteContent = $matches[1];
            
            // Stocker la définition de la note
            $this->footnoteDefinitions[$footnoteRef] = $footnoteContent;
            
            error_log("Note de bas de page convertie: {$footnoteRef} -> {$footnoteContent}");
            
            return $footnoteRef;
        }, $content);
        
        error_log("Conversion terminée. Notes trouvées: " . $footnoteCount);
        
        // Ajouter les définitions des notes en bas de fichier
        if (!empty($this->footnoteDefinitions)) {
            $content .= "\n\n";
            $content .= "<!-- Notes de bas de page -->\n";
            foreach ($this->footnoteDefinitions as $ref => $footnoteContent) {
                $content .= "{$ref}: {$footnoteContent}\n";
            }
            error_log("Définitions des notes ajoutées en bas de fichier");
        } else {
            error_log("Aucune note de bas de page à traiter");
        }
        
        error_log("Notes de bas de page converties: " . $footnoteCount);
        return $content;
    }
    
    /**
     * Obtient le nombre de notes de bas de page traitées
     * 
     * @return int Nombre de notes traitées
     */
    public function getProcessedFootnotesCount()
    {
        return count($this->footnoteDefinitions);
    }
    
    /**
     * Obtient les définitions des notes de bas de page
     * 
     * @return array Définitions des notes
     */
    public function getFootnoteDefinitions()
    {
        return $this->footnoteDefinitions;
    }
}
