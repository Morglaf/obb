import { useState, useEffect } from 'react';
import axios from 'axios';
import { LayoutMetadata, CoverMetadata, ImposeMetadata, TemplateOptions } from '../types/templates';
import { fetchUserTemplates, getTemplateContent } from '../services/templatesService';
import { useAuth } from '../contexts/AuthContext';
import { enrichUserTemplate } from '../utils/templateUtils';
import { parseFormatFromName } from '../utils/formatUtils';

interface TemplatesState {
  layouts: LayoutMetadata[];
  covers: CoverMetadata[];
  imposes: ImposeMetadata[];
  templateOptions: TemplateOptions | null;
  invalidFiles: {
    layouts: {file: string, error: string}[];
    covers: {file: string, error: string}[];
    imposes: {file: string, error: string}[];
  };
  isLoading: boolean;
  errorMessage: string;
}

interface TemplatesHook extends TemplatesState {
  loadTemplates: () => Promise<void>;
  setTemplateOptions: (options: TemplateOptions) => void;
}

// Utilisez l'URL relative au lieu d'une URL absolue pour éviter les problèmes CORS
// L'API est servie sur le même domaine (localhost) mais sur un port différent
const API_URL = '/api';

/**
 * Hook personnalisé pour gérer les templates et leurs options
 */
export const useTemplates = (): TemplatesHook => {
  const { isAuthenticated, user } = useAuth();
  const [layouts, setLayouts] = useState<LayoutMetadata[]>([]);
  const [covers, setCovers] = useState<CoverMetadata[]>([]);
  const [imposes, setImposes] = useState<ImposeMetadata[]>([]);
  const [templateOptions, setTemplateOptionsState] = useState<TemplateOptions | null>(null);
  const [invalidFiles, setInvalidFiles] = useState<{
    layouts: {file: string, error: string}[];
    covers: {file: string, error: string}[];
    imposes: {file: string, error: string}[];
  }>({
    layouts: [],
    covers: [],
    imposes: []
  });
  const [isLoading, setIsLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');

  /**
   * Charger tous les templates disponibles
   */
  const loadTemplates = async (): Promise<void> => {
    try {
      setIsLoading(true);
      setErrorMessage('');
      
      // Utilisez l'URL relative pour les requêtes API
      const response = await axios.get(`${API_URL}/layouts`);
      
      if (response.data.status === 'success') {
        // Transformer les données pour les rendre compatibles avec TemplateSelector
        const transformedLayouts = response.data.layouts.map((layout: any) => ({
          ...layout,
          // Ajouter les métadonnées du layout au niveau supérieur si elles existent
          ...(layout.metadata || {})
        }));
        
        // Transformer les métadonnées de cover en objets CoverMetadata
        const transformedCovers = response.data.covers.map((cover: any) => {
          if (!cover) return null;
          // Si c'est déjà un objet avec name, on le garde tel quel
          if (typeof cover === 'object' && cover.name) {
            return cover;
          }
          // Si c'est un objet avec filename, on l'adapte
          if (typeof cover === 'object' && cover.filename) {
            return cover;
          }
          // Si c'est une chaîne de caractères, on la transforme en objet
          if (typeof cover === 'string') {
            return { name: cover, filename: cover };
          }
          return null;
        }).filter(Boolean);
        
        // Transformer les métadonnées d'impose en objets ImposeMetadata
        const transformedImposes = response.data.imposes.map((impose: any) => {
          if (!impose) return null;
          // Si c'est déjà un objet avec name, on le garde tel quel
          if (typeof impose === 'object' && impose.name) {
            return impose;
          }
          // Si c'est un objet avec filename, on l'adapte
          if (typeof impose === 'object' && impose.filename) {
            return impose;
          }
          // Si c'est une chaîne de caractères, on la transforme en objet
          if (typeof impose === 'string') {
            return { name: impose, filename: impose };
          }
          return null;
        }).filter(Boolean);
        
        // Si l'utilisateur est authentifié, récupérer ses templates personnalisés
        let userLayouts: any[] = [];
        let userCovers: any[] = [];
        let userImposes: any[] = [];
        
        if (isAuthenticated && user) {
          try {
            const userTemplates = await fetchUserTemplates();
            
            // Transformer les templates utilisateur en objets compatibles
            for (const template of userTemplates) {
              // Extraire le format à partir du nom du template en utilisant la fonction universelle
              let format = parseFormatFromName(template.name);
              
              // Créer un objet avec les propriétés communes des templates système
              const templateObj = {
                name: template.name,
                filename: template.path,
                path: template.path,
                format: format,
                isUserTemplate: true,
                userId: user.id,
                // Structure minimale requise pour éviter les erreurs
                options: {
                  booleans: [],
                  variables: [
                    // Ajouter quelques variables de base selon le type
                    ...(template.type === 'layout' ? [
                      { name: 'title', type: 'text', default: '', description: 'Titre du document' },
                      { name: 'author', type: 'text', default: '', description: 'Auteur du document' }
                    ] : []),
                    ...(template.type === 'cover' ? [
                      { name: 'title', type: 'text', default: '', description: 'Titre du livre' },
                      { name: 'author', type: 'text', default: '', description: 'Auteur du livre' },
                      { name: 'publisher', type: 'text', default: '', description: 'Éditeur' }
                    ] : []),
                    ...(template.type === 'impose' ? [
                      { name: 'pages', type: 'text', default: '', description: 'Nombre de pages' }
                    ] : [])
                  ]
                },
                // Ajouter des métadonnées par défaut
                metadata: {
                  title: template.name,
                  description: `Template ${template.type} personnalisé`,
                  version: '1.0'
                },
                severity: 'normal' as const
              };
              
              // Essayer de récupérer le contenu du template pour enrichir les métadonnées
              try {
                if (template.id) {
                  const templateContent = await getTemplateContent(template.id);
                  if (templateContent && templateContent.content) {
                    // Enrichir le template avec les métadonnées extraites
                    const enrichedTemplate = enrichUserTemplate(templateObj, templateContent.content);
                    
                    // Utiliser les métadonnées extraites
                    if (templateContent.metadata) {
                      if (templateContent.metadata.metadata) {
                        Object.assign(enrichedTemplate.metadata, templateContent.metadata.metadata);
                      }
                      if (templateContent.metadata.options) {
                        if (templateContent.metadata.options.booleans) {
                          enrichedTemplate.options.booleans = [
                            ...enrichedTemplate.options.booleans,
                            ...templateContent.metadata.options.booleans
                          ];
                        }
                        if (templateContent.metadata.options.variables) {
                          enrichedTemplate.options.variables = [
                            ...enrichedTemplate.options.variables,
                            ...templateContent.metadata.options.variables
                          ];
                        }
                      }
                    }
                    
                    // Ajouter le template enrichi à la liste appropriée
                    if (template.type === 'layout') {
                      userLayouts.push(enrichedTemplate);
                    } else if (template.type === 'cover') {
                      userCovers.push(enrichedTemplate);
                    } else if (template.type === 'impose') {
                      userImposes.push(enrichedTemplate);
                    }
                    continue;
                  }
                }
              } catch (contentError) {
                console.error(`Erreur lors de la récupération du contenu du template ${template.id}:`, contentError);
                // En cas d'erreur, utiliser le template de base
              }
              
              // Si on n'a pas pu enrichir le template, utiliser la version de base
              if (template.type === 'layout') {
                userLayouts.push(templateObj);
              } else if (template.type === 'cover') {
                userCovers.push(templateObj);
              } else if (template.type === 'impose') {
                userImposes.push(templateObj);
              }
            }
          } catch (error) {
            console.error('Erreur lors du chargement des templates utilisateur:', error);
          }
        }
        
        // Combiner les templates système et utilisateur
        setLayouts([...transformedLayouts, ...userLayouts]);
        setCovers([...transformedCovers, ...userCovers]);
        setImposes([...transformedImposes, ...userImposes]);
        
        if (response.data.invalidFiles) {
          setInvalidFiles(response.data.invalidFiles);
        }
      } else {
        setErrorMessage('Erreur lors du chargement des templates.');
      }
    } catch (error) {
      console.error('Erreur lors du chargement des layouts:', error);
      setErrorMessage('Erreur lors du chargement des templates. Assurez-vous que le serveur API est en cours d\'exécution.');
      
      // Fallback: Définir des tableaux vides pour éviter les erreurs
      setLayouts([]);
      setCovers([]);
      setImposes([]);
    } finally {
      setIsLoading(false);
    }
  };

  // Charger les templates au montage du composant et quand l'état d'authentification change
  useEffect(() => {
    loadTemplates();
  }, [isAuthenticated, user]);

  /**
   * Mettre à jour les options de template
   */
  const setTemplateOptions = (options: TemplateOptions): void => {
    setTemplateOptionsState(options);
  };

  return {
    layouts,
    covers,
    imposes,
    templateOptions,
    invalidFiles,
    isLoading,
    errorMessage,
    loadTemplates,
    setTemplateOptions
  };
}; 