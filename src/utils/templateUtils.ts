/**
 * Utilitaires pour la gestion des templates
 */

import { LayoutOption } from '../types/templates';

/**
 * Extrait les métadonnées d'un template à partir de son contenu
 * @param content Contenu du fichier template
 * @returns Objet contenant les métadonnées extraites
 */
export const extractTemplateMetadata = (content: string) => {
  const metadata: Record<string, any> = {};
  const options: {
    booleans: LayoutOption[];
    variables: LayoutOption[];
  } = {
    booleans: [],
    variables: []
  };
  
  // Rechercher les lignes de métadonnées commençant par %% META:
  const metaRegex = /%% META:\s*(.*?)$/gm;
  let match;
  
  while ((match = metaRegex.exec(content)) !== null) {
    const metaLine = match[1].trim();
    
    // Analyser la ligne de métadonnées (format: clé=valeur, clé=valeur, ...)
    const keyValuePairs = metaLine.split(',').map(pair => pair.trim());
    
    keyValuePairs.forEach(pair => {
      const [key, value] = pair.split('=').map(part => part.trim());
      
      if (key && value) {
        // Traiter les valeurs spéciales
        if (key === 'booleans') {
          // Format attendu: booleans=option1:true,option2:false
          const booleanOptions = value.split(',');
          booleanOptions.forEach(opt => {
            const [optName, optDefault] = opt.split(':').map(p => p.trim());
            options.booleans.push({
              name: optName,
              type: 'boolean',
              default: optDefault === 'true'
            });
          });
        } else if (key === 'variables') {
          // Format attendu: variables=title,author,publisher
          const variableNames = value.split(',');
          variableNames.forEach(varName => {
            options.variables.push({
              name: varName.trim(),
              type: 'text',
              default: ''
            });
          });
        } else {
          // Autres métadonnées standards
          metadata[key] = value;
        }
      }
    });
  }
  
  // Détecter les variables utilisées dans le template (format: %VARIABLE%)
  const variableRegex = /%([A-Z0-9_]+)%/g;
  let varMatch;
  
  while ((varMatch = variableRegex.exec(content)) !== null) {
    const varName = varMatch[1].toLowerCase();
    
    // Vérifier si cette variable existe déjà
    const existingVar = options.variables.find(v => v.name.toLowerCase() === varName);
    
    if (!existingVar) {
      options.variables.push({
        name: varName,
        type: 'text',
        default: ''
      });
    }
  }
  
  return {
    metadata,
    options
  };
};

/**
 * Enrichit un template utilisateur avec des métadonnées extraites du contenu
 * @param template Template à enrichir
 * @param content Contenu du fichier template
 * @returns Template enrichi avec les métadonnées
 */
export const enrichUserTemplate = (template: any, content?: string) => {
  // Si pas de contenu, retourner le template tel quel avec des options par défaut
  if (!content) {
    return {
      ...template,
      options: template.options || {
        booleans: [],
        variables: []
      }
    };
  }
  
  // Extraire les métadonnées du contenu
  const { metadata, options } = extractTemplateMetadata(content);
  
  // Fusionner les métadonnées extraites avec le template
  return {
    ...template,
    ...metadata,
    options: {
      booleans: [...(template.options?.booleans || []), ...options.booleans],
      variables: [...(template.options?.variables || []), ...options.variables]
    },
    metadata: {
      ...(template.metadata || {}),
      ...metadata
    }
  };
}; 