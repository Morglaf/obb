<?php
/**
 * Online Book Brew - Utilitaires de fichiers
 * 
 * Fonctions utilitaires pour la manipulation de fichiers
 */

namespace App\Utils;

class FileUtils
{
    /**
     * Copie un fichier de template dans le répertoire de travail
     * 
     * @param string $baseDir Répertoire de base des templates
     * @param string $type Type de template (layout, cover, impose)
     * @param string $name Nom du template
     * @param string $targetPath Chemin cible pour la copie
     * @return bool Succès de l'opération
     */
    public static function copyTemplateFile($baseDir, $type, $name, $targetPath)
    {
        // Nettoyer les noms pour éviter les attaques par traversée de répertoire
        $type = basename($type);
        $name = basename($name);
        
        // Construire le chemin du fichier source
        $sourceFile = $baseDir . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $name . '.tex';
        
        // Vérifier si le fichier existe
        if (!file_exists($sourceFile)) {
            error_log("Fichier template introuvable: $sourceFile");
            return false;
        }
        
        // Copier le fichier
        if (!copy($sourceFile, $targetPath)) {
            error_log("Impossible de copier le fichier: $sourceFile vers $targetPath");
            return false;
        }
        
        return true;
    }
    
    /**
     * Crée un répertoire s'il n'existe pas
     * 
     * @param string $dir Chemin du répertoire à créer
     * @param int $permissions Permissions du répertoire
     * @param bool $recursive Créer les répertoires parents si nécessaire
     * @return bool Succès de l'opération
     */
    public static function ensureDirectoryExists($dir, $permissions = 0777, $recursive = true)
    {
        if (is_dir($dir)) {
            return true;
        }
        
        if (!mkdir($dir, $permissions, $recursive)) {
            error_log("Impossible de créer le répertoire: $dir");
            return false;
        }
        
        return true;
    }
    
    /**
     * Copie un répertoire et son contenu de manière récursive
     * 
     * @param string $source Répertoire source
     * @param string $destination Répertoire de destination
     * @param string $pattern Motif de filtre pour les fichiers (glob)
     * @return bool Succès de l'opération
     */
    public static function copyDirectory($source, $destination, $pattern = '*.*')
    {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!self::ensureDirectoryExists($destination)) {
            return false;
        }
        
        $files = glob($source . DIRECTORY_SEPARATOR . $pattern);
        foreach ($files as $file) {
            $destFile = $destination . DIRECTORY_SEPARATOR . basename($file);
            if (!copy($file, $destFile)) {
                error_log("Impossible de copier le fichier: $file vers $destFile");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Vérifie si un fichier a une taille minimale et existe
     * 
     * @param string $filePath Chemin du fichier à vérifier
     * @param int $minSize Taille minimale en octets
     * @return bool Le fichier existe et a une taille suffisante
     */
    public static function validateFileOutput($filePath, $minSize = 1024)
    {
        if (!file_exists($filePath)) {
            error_log("Le fichier n'existe pas: $filePath");
            return false;
        }
        
        if (filesize($filePath) < $minSize) {
            error_log("Le fichier est trop petit: $filePath (" . filesize($filePath) . " octets)");
            return false;
        }
        
        return true;
    }
} 