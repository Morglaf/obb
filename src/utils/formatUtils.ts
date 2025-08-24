/**
 * Utilitaires pour la gestion des formats et des noms de fichiers
 */

/**
 * Extrait le format à partir d'un nom de fichier
 * @param name Nom du fichier
 * @returns Le format extrait ou une chaîne vide
 */
export const parseFormatFromName = (name: string): string => {
  if (!name) return '';
  
  // Normaliser le nom (supprimer l'extension .tex si présente)
  const normalizedName = name.endsWith('.tex') ? name.slice(0, -4) : name;
  const parts = normalizedName.split('-');
  
  // Pour les layouts: [nom]-[format]-layout
  if (normalizedName.includes('-layout')) {
    return parts[1] || '';
  } 
  // Pour les couvertures: [nom]-[format]-cover-[format_papier]
  else if (normalizedName.includes('-cover-')) {
    return parts[1] || '';
  } 
  // Pour les impositions: [format]-[format_papier]-[nb][type]
  // Exemples: brsnoba5-A4-4spread
  else if (parts.length >= 2 && 
          (normalizedName.includes('signature') || normalizedName.includes('spread'))) {
    // Pour toutes les impositions, le format est toujours la première partie
    return parts[0] || '';
  }
  
  return '';
};

/**
 * Extrait le format papier à partir d'un nom de fichier couverture
 * @param name Nom du fichier
 * @returns Le format papier extrait ou une chaîne vide
 */
export const parsePaperFormatFromName = (name: string): string => {
  if (!name) return '';
  
  // Normaliser le nom (supprimer l'extension .tex si présente)
  const normalizedName = name.endsWith('.tex') ? name.slice(0, -4) : name;
  
  // Pour les couvertures: [nom]-[format]-cover-[format_papier]
  if (normalizedName.includes('-cover-')) {
    const parts = normalizedName.split('-cover-');
    if (parts.length > 1) {
      return parts[1] || '';
    }
  } 
  // Pour les impositions: [format]-[format_papier]-[nb][type]
  else if (normalizedName.includes('signature') || normalizedName.includes('spread')) {
    const parts = normalizedName.split('-');
    if (parts.length > 1) {
      return parts[1] || '';
    }
  }
  
  return '';
};

/**
 * Extrait un nom convivial à partir du nom technique
 * @param name Nom technique
 * @returns Nom convivial pour l'affichage
 */
export const getFriendlyName = (name: string): string => {
  const parts = name.split('-');
  if (name.includes('-layout')) {
    return `${parts[0]} (${parts[1]})`;
  } else if (name.includes('-cover-')) {
    return `${parts[0]} (${parts[2]})`;
  } else if (parts.length >= 3 && (name.includes('signature') || name.includes('spread'))) {
    const type = name.includes('signature') ? 'Signature' : 'Spread';
    return `${parts[0]} - ${parts[1]} (${parts[2].replace(/[^\d]/g, '')} ${type})`;
  }
  return name;
};

/**
 * Détermine si un template est un template utilisateur
 * Note: Cette fonction est principalement un indicateur de base.
 * L'identification principale des templates utilisateur doit être faite
 * via la propriété isUserTemplate dans les métadonnées API.
 * 
 * @param fileName Nom du fichier template
 * @returns true si c'est potentiellement un template utilisateur, false sinon
 */
export const isUserTemplate = (fileName: string): boolean => {
  if (!fileName) return false;
  
  // Marqueurs génériques pour templates utilisateur
  const userTemplateMarkers = [
    'user_',    // Préfixe user_
    'custom-',  // Préfixe custom-
    '-user-'    // Contient -user- dans le nom
  ];
  
  // Vérification des marqueurs
  for (const marker of userTemplateMarkers) {
    if (fileName.includes(marker)) {
      return true;
    }
  }
  
  // Par défaut, retourner false pour les templates systèmes
  return false;
};

/**
 * Construit l'URL de prévisualisation pour un fichier
 * @param baseUrl URL de base de l'API
 * @param fileType Type de fichier (layout, cover, impose)
 * @param fileName Nom du fichier
 * @param isUserTemplate Indique si c'est un template utilisateur
 * @param userId ID de l'utilisateur pour les templates utilisateur
 * @returns URL complète pour la prévisualisation
 */
export const getPreviewUrl = (baseUrl: string, fileType: 'layout' | 'cover' | 'impose', fileName: string, isUserTemplate: boolean = false, userId?: number): string => {
  if (isUserTemplate) {
    // Pour les templates utilisateur - utiliser le nouveau chemin
    const userDir = userId || 1;
    const userPreviewPath = `user_templates/${userDir}/previews/${fileType}-${fileName}.png`;
    return `${baseUrl}/serve-preview.php?path=${encodeURIComponent(userPreviewPath)}`;
  } else {
    // Pour les templates système
    const systemPreviewPath = `typeset/${fileType}/${fileName}.png`;
    return `${baseUrl}/serve-preview.php?path=${encodeURIComponent(systemPreviewPath)}`;
  }
};

/**
 * Extrait les métadonnées d'un fichier .tex pour les templates personnalisés
 * @param fileType Type de fichier (layout, cover, impose)
 * @param fileName Nom du fichier
 * @param userId ID de l'utilisateur pour les templates utilisateur
 * @returns Promise avec les métadonnées
 */
export const fetchUserTemplateMetadata = async (fileType: 'layout' | 'cover' | 'impose', fileName: string, userId?: number): Promise<{title?: string, description?: string, version?: string} | null> => {
  if (!fileName) return null;
  
  try {
    // Construire le chemin du fichier .tex avec l'ID utilisateur fourni ou par défaut 1
    const userDir = userId || 1;
    const texFilePath = `user_templates/${userDir}/${fileType}/${fileName}.tex`;
    
    // Utiliser serve-preview.php pour accéder au contenu du fichier
    const response = await fetch(`/serve-preview.php?path=${encodeURIComponent(texFilePath)}&raw=1`);
    
    if (!response.ok) {
      return null;
    }
    
    // Récupérer le contenu du fichier
    const content = await response.text();
    
    // Extraire les métadonnées des commentaires
    const titleMatch = content.match(/^%\s*title:\s*(.+)$/m);
    const descriptionMatch = content.match(/^%\s*description:\s*(.+)$/m);
    const versionMatch = content.match(/^%\s*version:\s*(.+)$/m);
    
    const metadata = {
      title: titleMatch?.[1] || '',
      description: descriptionMatch?.[1] || '',
      version: versionMatch?.[1] || ''
    };
    
    return metadata;
  } catch (error) {
    return null;
  }
}; 