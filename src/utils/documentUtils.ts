/**
 * Utilitaires pour la gestion des documents Markdown
 */

export interface DocumentMetadata {
  title?: string;
  author?: string;
  date?: string;
  description?: string;
  tags?: string[];
}

/**
 * Extrait les métadonnées d'un document Markdown
 * Supporte les formats YAML frontmatter et les commentaires Markdown
 * NOTE: Cette fonction n'est plus utilisée pour la génération des noms
 */
export function extractMarkdownMetadata(content: string): DocumentMetadata {
  const metadata: DocumentMetadata = {};
  
  // Essayer d'extraire le YAML frontmatter
  const yamlMatch = content.match(/^---\s*\n([\s\S]*?)\n---\s*\n/);
  if (yamlMatch) {
    const yamlContent = yamlMatch[1];
    const lines = yamlContent.split('\n');
    
    for (const line of lines) {
      const colonIndex = line.indexOf(':');
      if (colonIndex > 0) {
        const key = line.substring(0, colonIndex).trim().toLowerCase();
        const value = line.substring(colonIndex + 1).trim();
        
        if (key === 'title' || key === 'titre') {
          metadata.title = value;
        } else if (key === 'author' || key === 'auteur') {
          metadata.author = value;
        } else if (key === 'date') {
          metadata.date = value;
        } else if (key === 'description' || key === 'desc') {
          metadata.description = value;
        } else if (key === 'tags') {
          metadata.tags = value.split(',').map(tag => tag.trim());
        }
      }
    }
  }
  
  // Si pas de YAML, essayer d'extraire le premier titre H1
  if (!metadata.title) {
    const h1Match = content.match(/^#\s+(.+)$/m);
    if (h1Match) {
      metadata.title = h1Match[1].trim();
    }
  }
  
  // Si toujours pas de titre, essayer H2
  if (!metadata.title) {
    const h2Match = content.match(/^##\s+(.+)$/m);
    if (h2Match) {
      metadata.title = h2Match[1].trim();
    }
  }
  
  return metadata;
}

/**
 * Génère un nom de fichier court et cohérent pour le document
 * Utilise UNIQUEMENT les métadonnées de template, pas le contenu Markdown
 */
export function generateDocumentFilename(metadata: DocumentMetadata, documentId: string, type: 'document' | 'cover' | 'impose' = 'document', creationTime?: Date): string {
  const now = creationTime || new Date();
  const dateStr = now.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  }).replace(/\//g, '');
  
  const timeStr = now.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  }).replace(/:/g, '').replace(/\./g, '');
  
  let filename = '';
  
  if (metadata.title && metadata.title.trim()) {
    // Nettoyer le titre pour en faire un nom de fichier valide
    const cleanTitle = metadata.title
      .replace(/[<>:"/\\|?*]/g, '') // Caractères interdits
      .replace(/\s+/g, '_') // Espaces en underscores
      .substring(0, 30); // Limiter la longueur
    
    filename = cleanTitle;
  } else {
    filename = 'sans_titre';
  }
  
  // Ajouter le préfixe selon le type
  if (type === 'cover') {
    filename = `cover_${filename}`;
  } else if (type === 'impose') {
    filename = `impose_${filename}`;
  }
  
  // Format final : type_titre-date-heure
  return `${filename}-${dateStr}-${timeStr}`;
}

/**
 * Formate l'affichage du nom du document dans la liste
 * Utilise UNIQUEMENT les métadonnées de template, pas le contenu Markdown
 */
export function formatDocumentDisplayName(metadata: DocumentMetadata, documentId: string, type: 'document' | 'cover' | 'impose' = 'document', creationTime?: Date): string {
  const now = creationTime || new Date();
  const dateStr = now.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  });
  
  const timeStr = now.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  
  let displayName = '';
  
  if (metadata.title && metadata.title.trim()) {
    displayName = metadata.title;
  } else {
    displayName = 'sans titre';
  }
  
  // Ajouter le préfixe selon le type
  if (type === 'cover') {
    displayName = `cover_${displayName}`;
  } else if (type === 'impose') {
    displayName = `impose_${displayName}`;
  }
  
  return `${displayName}-${dateStr}-${timeStr}`;
}
