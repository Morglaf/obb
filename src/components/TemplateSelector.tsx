import { useState, useEffect, useRef, useCallback } from 'react';
import axios from 'axios';
import { useTranslation } from 'next-i18next';
import { parseFormatFromName, getPreviewUrl, isUserTemplate as isUserTemplateUtil } from '../utils/formatUtils';
import { getValidCovers, getValidImposes } from '../utils/arrayUtils';
import { 
  LayoutMetadata, 
  LayoutOption, 
  TemplateOptions, 
  TemplateSelectorProps,
  CoverMetadata,
  ImposeMetadata 
} from '../types/templates';
import ImagePreview from './ImagePreview';
import LayoutSelector from './LayoutSelector';
import CoverSelector from './CoverSelector';
import ImposeSelector from './ImposeSelector';
import { useAuth } from '../contexts/AuthContext';

// Utiliser le proxy Next.js au lieu d'une URL absolue
const API_URL = '';

// Créer une instance Axios avec une configuration par défaut
const axiosInstance = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: false, // Désactiver l'envoi automatique des cookies
  timeout: 10000 // Timeout de 10 secondes
});

// Ajouter un intercepteur pour les erreurs
axiosInstance.interceptors.response.use(
  response => response,
  error => {
    console.error('Erreur Axios:', error);
    return Promise.reject(error);
  }
);

/**
 * Vérifie si un template est défini par l'utilisateur
 * @param templateName Nom du template à vérifier
 * @param templates Liste des templates disponibles
 * @returns true si le template est défini par l'utilisateur, false sinon
 */
const isTemplateUserDefined = (templateName: string, templates: (string | LayoutMetadata | CoverMetadata | ImposeMetadata)[]) => {
  // Chercher le template dans la liste
  const template = templates.find(t => {
    if (typeof t === 'string') {
      return t === templateName;
    } else {
      return t.name === templateName;
    }
  });
  
  // Vérifier si le template existe et est un objet
  if (template && typeof template !== 'string') {
    // @ts-ignore - Certains types peuvent ne pas avoir isUserTemplate, mais on vérifie quand même
    return template.isUserTemplate === true;
  }
  
  // Vérifier par d'autres moyens si c'est un template utilisateur
  // Si le nom contient certains marqueurs spécifiques
  return isUserTemplateUtil(templateName);
};

export const TemplateSelector: React.FC<TemplateSelectorProps> = ({ 
  onChange,
  layouts,
  covers,
  imposes,
  invalidFiles
}) => {
  const [options, setOptions] = useState<TemplateOptions>({
    layout: '',
    cover: '',
    impose: '',
    booleanOptions: {},
    metadata: {},
    paperThickness: 0.1 // Valeur par défaut pour l'épaisseur du papier
  });

  const [currentLayout, setCurrentLayout] = useState<LayoutMetadata | null>(null);
  const [filteredCovers, setFilteredCovers] = useState<string[]>([]);
  const [filteredImposes, setFilteredImposes] = useState<string[]>([]);
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState('');
  const [showInvalidFiles, setShowInvalidFiles] = useState(false);
  const [previewImage, setPreviewImage] = useState<{type: string, url: string, name: string} | null>(null);
  const [coverVariables, setCoverVariables] = useState<LayoutOption[]>([]);
  const [uploadedContentImages, setUploadedContentImages] = useState<string[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { t } = useTranslation('translation');
  const { user } = useAuth();

  // Effet pour charger les images téléversées depuis le localStorage
  useEffect(() => {
    try {
      const savedImages = localStorage.getItem('uploadedContentImages');
      if (savedImages) {
        setUploadedContentImages(JSON.parse(savedImages));
      }
    } catch (error) {
      console.error('Erreur lors du chargement des images depuis localStorage:', error);
    }
  }, []);

  // Fonction pour sauvegarder les images téléversées dans localStorage
  const saveUploadedImages = useCallback((images: string[]) => {
    try {
      localStorage.setItem('uploadedContentImages', JSON.stringify(images));
    } catch (error) {
      console.error('Erreur lors de la sauvegarde des images dans localStorage:', error);
    }
  }, []);

  // Effet pour mettre à jour le layout courant quand l'option change
  useEffect(() => {
    const layout = layouts.find(l => l.name === options.layout);
    setCurrentLayout(layout || null);
    
    // Réinitialiser les sélections de cover et impose si le layout change
    if (options.layout !== layout?.name) {
      setOptions(prev => ({
        ...prev,
        cover: '',
        impose: ''
      }));
    }
    
    // Initialiser les options booléennes avec leurs valeurs par défaut
    if (layout) {
      // S'assurer que layout.options existe
      const layoutOptions = layout.options || { booleans: [], variables: [] };
      
      // Vérifier si les options booléennes existent
      const booleanOptions = Array.isArray(layoutOptions.booleans) ? layoutOptions.booleans : [];
      
      // Créer un objet avec les valeurs par défaut
      const defaultBooleans = booleanOptions.reduce((acc, opt) => ({
        ...acc,
        [opt.name]: opt.default || false
      }), {});
      
      setOptions(prev => ({
        ...prev,
        booleanOptions: defaultBooleans
      }));
      
      // Extraire le format depuis le nom du layout, e.g. "Garamond-brsnoba5-layout" -> "brsnoba5"
      let layoutName = layout.name || '';
      let layoutFormat = '';
      
      if (layoutName.includes('-')) {
        const parts = layoutName.split('-');
        if (parts.length >= 2 && layoutName.includes('-layout')) {
          layoutFormat = parts[1]; // Deuxième partie pour les layouts (e.g. "brsnoba5" dans "Garamond-brsnoba5-layout")
        }
      }
      
      // Si nous avons des métadonnées d'API plus récentes, utilisons le format qui y est défini
      if (layout.format) {
        layoutFormat = layout.format;
      }
      
      // Vérifier si covers est bien un tableau et filtrer les éléments undefined
      const validCovers = getValidCovers(covers);
      
      // Vérifier si imposes est bien un tableau et filtrer les éléments undefined
      const validImposes = getValidImposes(imposes);
      
      // Filtrer les couvertures compatibles selon le format du layout
      const coversCompatible = validCovers
        .filter(cover => {
          // Si c'est un objet avec un format, utiliser ce format
          if (typeof cover === 'object' && cover.format) {
            return cover.format === layoutFormat;
          }
          // Sinon, tenter de l'extraire du nom
          const coverName = typeof cover === 'string' ? cover : cover.name;
          const coverFormat = parseFormatFromName(coverName);
          return coverFormat === layoutFormat;
        })
        .map(cover => typeof cover === 'string' ? cover : cover.name);
      
      // Filtrer les impositions compatibles selon le format du layout
      const imposesCompatible = validImposes
        .filter(impose => {
          // Si c'est un objet avec un format, utiliser ce format
          if (typeof impose === 'object' && impose.format) {
            const isCompatible = impose.format === layoutFormat;
            return isCompatible;
          }
          
          // Si c'est un objet avec un nom, extraire le format du nom
          if (typeof impose === 'object' && impose.name) {
            const imposeFormat = parseFormatFromName(impose.name);
            const isCompatible = imposeFormat === layoutFormat;
            return isCompatible;
          }
          
          // Cas de base: vérifier si le nom de l'imposition contient le format du layout
          if (typeof impose === 'string') {
            return impose.includes(layoutFormat);
          }
          
          return false;
        })
        .map(impose => typeof impose === 'string' ? impose : impose.name);
      
      setFilteredCovers(coversCompatible);
      setFilteredImposes(imposesCompatible);
    }
  }, [layouts, options.layout, covers, imposes]);

  // Effet pour charger les variables de la couverture lorsque la couverture change
  useEffect(() => {
    if (options.cover && filteredCovers.includes(options.cover)) {
      // Réinitialiser les variables de couverture précédentes
      setCoverVariables([]);
      
      // Charger les variables de la couverture sélectionnée
      const fetchCoverVariables = async () => {
        try {
          const response = await axios.get(`/api/cover-variables/${options.cover}`);
          if (response.data.status === 'success') {
            setCoverVariables(response.data.variables);
          }
        } catch (error) {
          console.error('Erreur lors du chargement des variables de couverture:', error);
        }
      };
      
      fetchCoverVariables();
    } else {
      setCoverVariables([]);
    }
  }, [options.cover, filteredCovers]);

  // Effet pour notifier le parent quand les options changent
  useEffect(() => {
    onChange(options);
  }, [options, onChange]);

  // Gestionnaire pour les changements de sélection de template
  const handleTemplateChange = (
    type: 'layout' | 'cover' | 'impose',
    value: string
  ) => {
    // Vérifier si c'est un template utilisateur
    let isUserTemplate = false;
    
    if (type === 'layout') {
      isUserTemplate = isTemplateUserDefined(value, layouts);
    } else if (type === 'cover') {
      isUserTemplate = isTemplateUserDefined(value, covers);
    } else if (type === 'impose') {
      isUserTemplate = isTemplateUserDefined(value, imposes);
    }
    
    // Si c'est un template utilisateur, utiliser l'ID de l'utilisateur connecté
    const userId = isUserTemplate && user ? user.id : undefined;
    
    setOptions(prev => {
      // Construire le nouvel objet d'options
      let newOptions: TemplateOptions = {
      ...prev,
        [type]: value,
      };
      
      // Ajouter les informations sur le template utilisateur selon le type
      if (type === 'layout') {
        newOptions.isUserTemplate = isUserTemplate;
        if (isUserTemplate) newOptions.userId = userId;
      } else if (type === 'cover') {
        newOptions.coverIsUserTemplate = isUserTemplate;
        if (isUserTemplate) newOptions.userId = userId;
      } else if (type === 'impose') {
        newOptions.imposeIsUserTemplate = isUserTemplate;
        if (isUserTemplate) newOptions.userId = userId;
      }
      
      return newOptions;
    });
  };

  // Gestionnaire pour les changements de métadonnées
  const handleMetadataChange = (key: string, value: string) => {
    setOptions(prev => ({
      ...prev,
      metadata: {
        ...prev.metadata,
        [key]: value,
      },
    }));
  };

  // Gestionnaire pour les options booléennes
  const handleBooleanOptionChange = (optionName: string, value: boolean) => {
    setOptions(prev => ({
      ...prev,
      booleanOptions: {
        ...prev.booleanOptions,
        [optionName]: value
      }
    }));
  };

  // Gestionnaire pour l'upload d'image
  const handleImageUpload = async (event: React.ChangeEvent<HTMLInputElement>, fieldName: string) => {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    setUploading(true);
    setUploadError('');
    
    const formData = new FormData();
    formData.append('image', files[0]);
    
    try {
      const response = await axios.post(`/api/upload/image`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      
      if (response.data.status === 'success') {
        handleMetadataChange(fieldName, response.data.filename);
      } else {
        setUploadError('Erreur lors de l\'upload: ' + response.data.message);
      }
    } catch (error: any) {
      setUploadError('Erreur: ' + (error.response?.data?.message || error.message));
      console.error('Erreur détaillée:', error);
    } finally {
      setUploading(false);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  // Gestionnaire pour l'upload de plusieurs images
  const handleMultipleImagesUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = event.target.files;
    if (!files || files.length === 0) return;
    
    setUploading(true);
    setUploadError('');
    
    const uploadedImages: string[] = [];
    
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      const formData = new FormData();
      formData.append('image', file);
      
      try {
        const response = await axios.post(`/api/upload/image`, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        });
        
        if (response.data.status === 'success') {
          uploadedImages.push(response.data.filename);
          
          // Sauvegarder aussi le nom d'origine pour l'affichage
          if (response.data.original_filename) {
            console.log(`Image uploadée: ${response.data.original_filename} -> ${response.data.filename}`);
          }
        } else {
          setUploadError(`Erreur lors de l'upload de ${file.name}: ${response.data.message}`);
        }
      } catch (error: any) {
        setUploadError(`Erreur lors de l'upload de ${file.name}: ${error.response?.data?.message || error.message}`);
        console.error('Erreur détaillée:', error);
      }
    }
    
    setUploading(false);
    if (event.target.value) event.target.value = '';
    
    if (uploadedImages.length > 0) {
      const newUploadedImages = [...uploadedContentImages, ...uploadedImages];
      setUploadedContentImages(newUploadedImages);
      saveUploadedImages(newUploadedImages);
    }
  };

  // Toggles pour afficher/masquer l'information sur les fichiers invalides
  const toggleInvalidFiles = () => {
    setShowInvalidFiles(!showInvalidFiles);
  };

  // Gestionnaire pour afficher/masquer l'aperçu d'image
  const handleImagePreview = (type: 'layout' | 'cover' | 'impose', name: string) => {
    // Déterminer si c'est un template utilisateur et récupérer le userId
    let isUserTemplate = false;
    let userId: number | undefined;
    
    if (type === 'layout') {
      const layout = layouts.find(l => {
        if (typeof l === 'string') return l === name;
        return l.name === name;
      });
      if (layout && typeof layout !== 'string') {
        isUserTemplate = layout.isUserTemplate || false;
        userId = (layout as any).userId;
      }
    } else if (type === 'cover') {
      const cover = covers.find(c => {
        if (typeof c === 'string') return c === name;
        return c.name === name;
      });
      if (cover && typeof cover !== 'string') {
        isUserTemplate = cover.isUserTemplate || false;
        userId = (cover as any).userId;
      }
    } else if (type === 'impose') {
      const impose = imposes.find(i => {
        if (typeof i === 'string') return i === name;
        return i.name === name;
      });
      if (impose && typeof impose !== 'string') {
        isUserTemplate = impose.isUserTemplate || false;
        userId = (impose as any).userId;
      }
    }
    
    const previewUrl = getPreviewUrl('', type, name, isUserTemplate, userId);
    setPreviewImage({ type, url: previewUrl, name });
  };

  // Fermer la prévisualisation
  const closePreview = () => {
    setPreviewImage(null);
  };

  // Gestionnaire pour le changement d'épaisseur du papier
  const handlePaperThicknessChange = (value: number) => {
    setOptions(prev => ({
      ...prev,
      paperThickness: value
    }));
  };

  // Gestionnaires pour les sélecteurs
  const handleLayoutSelect = (layoutName: string) => {
    handleTemplateChange('layout', layoutName);
  };

  const handleCoverSelect = (coverName: string) => {
    handleTemplateChange('cover', coverName);
  };

  const handleImposeSelect = (imposeName: string) => {
    handleTemplateChange('impose', imposeName);
  };

  // Ajouter la fonction pour supprimer une image
  const handleDeleteImage = async (imageName: string) => {
    if (confirm(t('confirm_delete_image'))) {
      try {
        // Utiliser un script PHP dédié qui évite les problèmes CORS
        const encodedImageName = encodeURIComponent(imageName);
        const deleteUrl = `/delete-image.php/${encodedImageName}`;
        
        const response = await fetch(deleteUrl, {
          method: 'GET',
          headers: {
            'Accept': 'application/json'
          }
        });
        
        if (response.ok) {
          try {
            const data = await response.json();
            if (data.status === 'success') {
              // Mettre à jour la liste des images
              const updatedImages = uploadedContentImages.filter(img => img !== imageName);
              setUploadedContentImages(updatedImages);
              saveUploadedImages(updatedImages);
              
              // Afficher un message de confirmation
              alert(t('image_deleted_success'));
            } else {
              alert(t('image_deleted_error') + ': ' + (data.message || 'Erreur inconnue'));
            }
          } catch (e) {
            console.error('Erreur de parsing JSON:', e);
            alert(t('image_deleted_error') + ': Erreur de parsing JSON');
          }
        } else {
          console.error('Erreur HTTP:', response.status, response.statusText);
          alert(t('image_deleted_error') + ': ' + response.status + ' ' + response.statusText);
        }
      } catch (error: any) {
        console.error('Erreur lors de la suppression:', error);
        alert(t('image_deleted_error') + ': ' + (error.message || 'Erreur inconnue'));
      }
    }
  };

  return (
    <div className="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-md mb-4 border border-gray-200 dark:border-gray-700">
      <h2 className="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">{t('page_options')}</h2>
      
      {/* Utilisation du composant ImagePreview */}
      <ImagePreview previewData={previewImage} onClose={closePreview} />
      
      {/* Affichage des erreurs pour les fichiers invalides (admin only) */}
      {invalidFiles && (invalidFiles.layouts.length > 0 || invalidFiles.covers.length > 0 || invalidFiles.imposes.length > 0) && (
        <div className="mb-4">
          <button
            onClick={toggleInvalidFiles}
            className="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
          >
            {showInvalidFiles ? t('hide_invalid_files') : t('show_invalid_files')}
          </button>
          
          {showInvalidFiles && (
            <div className="mt-2 bg-red-50 dark:bg-red-900 p-3 rounded text-sm">
              <h3 className="font-medium text-red-800 dark:text-red-200 mb-1">{t('invalid_files')}</h3>
              <ul className="list-disc ml-5 text-red-700 dark:text-red-300">
                {invalidFiles.layouts.map((item, index) => (
                  <li key={`layout-error-${index}`}>Layout: {item.file} - {item.error}</li>
                ))}
                {invalidFiles.covers.map((item, index) => (
                  <li key={`cover-error-${index}`}>Cover: {item.file} - {item.error}</li>
                ))}
                {invalidFiles.imposes.map((item, index) => (
                  <li key={`impose-error-${index}`}>Impose: {item.file} - {item.error}</li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
      
      {/* Grille pour les sélections de template */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        {/* Utilisation du composant LayoutSelector */}
        <LayoutSelector 
          layouts={layouts}
          selectedLayout={options.layout}
          onSelectLayout={handleLayoutSelect}
          onPreviewLayout={(name) => handleImagePreview('layout', name)}
        />

        {/* Utilisation du composant CoverSelector */}
        <CoverSelector 
          covers={covers}
          filteredCovers={filteredCovers}
          selectedCover={options.cover}
          hasLayout={!!currentLayout}
          onSelectCover={handleCoverSelect}
          onPreviewCover={(name) => handleImagePreview('cover', name)}
        />

        {/* Utilisation du composant ImposeSelector */}
        <ImposeSelector 
          imposes={imposes}
          filteredImposes={filteredImposes}
          selectedImpose={options.impose}
          hasLayout={!!currentLayout}
          onSelectImpose={handleImposeSelect}
          onPreviewImpose={(name) => handleImagePreview('impose', name)}
          paperThickness={options.paperThickness}
          onPaperThicknessChange={handlePaperThicknessChange}
        />
      </div>

      {currentLayout && (
        <>
          {/* Options booléennes */}
          {currentLayout.options && 
           Array.isArray(currentLayout.options.booleans) && 
           currentLayout.options.booleans.length > 0 && (
            <div className="mb-6">
              <h3 className="text-md font-medium text-gray-700 dark:text-gray-200 mb-3">{t('options')}</h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {currentLayout.options.booleans.map((option) => (
                  <div key={`option-${option.name}`} className="flex items-center">
                    <button
                      type="button"
                      onClick={() => handleBooleanOptionChange(
                        option.name, 
                        !options.booleanOptions[option.name]
                      )}
                      className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 ${
                        options.booleanOptions[option.name]
                          ? 'bg-indigo-600'
                          : 'bg-gray-200 dark:bg-gray-700'
                      }`}
                    >
                      <span
                        className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                          options.booleanOptions[option.name]
                            ? 'translate-x-6'
                            : 'translate-x-1'
                        }`}
                      />
                    </button>
                    <label
                      htmlFor={option.name}
                      className="ml-3 text-sm text-gray-700 dark:text-gray-200 cursor-pointer"
                      title={option.description || ''}
                    >
                      {option.name}
                    </label>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Champs variables */}
          {((currentLayout.options && 
             Array.isArray(currentLayout.options.variables) && 
             currentLayout.options.variables.length > 0) || 
            coverVariables.length > 0) && (
            <div className="space-y-4">
              <h3 className="text-md font-medium text-gray-700 dark:text-gray-200 mb-3">{t('metadata')}</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Variables du layout */}
                {currentLayout.options && 
                 Array.isArray(currentLayout.options.variables) && 
                 currentLayout.options.variables.map((variable) => (
                  <div key={`variable-${variable.name}`} className="group">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                      {variable.name}
                      {variable.description && (
                        <span className="ml-1 text-xs text-gray-500 dark:text-gray-400 italic">
                          ({variable.description})
                        </span>
                      )}
                    </label>
                    {variable.type === 'image' ? (
                      <div>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={(e) => handleImageUpload(e, variable.name)}
                          className="block w-full text-sm text-gray-500 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-gray-700 file:text-indigo-700 dark:file:text-indigo-200 hover:file:bg-indigo-100 dark:hover:file:bg-gray-600"
                        />
                        {options.metadata[variable.name] && (
                          <p className="mt-1 text-sm text-gray-500 dark:text-gray-300">
                            Image sélectionnée : {options.metadata[variable.name]}
                          </p>
                        )}
                      </div>
                    ) : (
                      <input
                        type="text"
                        value={options.metadata[variable.name] || ''}
                        onChange={(e) => handleMetadataChange(variable.name, e.target.value)}
                        className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:bg-gray-800 dark:text-white"
                        placeholder={`Entrez ${variable.name}...`}
                      />
                    )}
                  </div>
                ))}
                
                {/* Variables de la couverture */}
                {coverVariables.map((variable) => {
                  // Vérifier si cette variable est déjà dans les variables du layout
                  const existsInLayout = currentLayout.options && 
                                         Array.isArray(currentLayout.options.variables) && 
                                         currentLayout.options.variables.some(v => v.name === variable.name);
                  if (existsInLayout) return null; // Ne pas afficher les doublons
                  
                  return (
                    <div key={`cover-variable-${variable.name}`} className="group">
                      <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1 group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors">
                        {variable.name}
                        <span className="ml-1 text-xs text-gray-500 dark:text-gray-400 italic">
                          (Variable de couverture)
                        </span>
                      </label>
                      {variable.type === 'image' ? (
                        <div>
                          <input
                            type="file"
                            accept="image/*"
                            onChange={(e) => handleImageUpload(e, variable.name)}
                            className="block w-full text-sm text-gray-500 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 dark:file:bg-green-900 file:text-green-700 dark:file:text-green-200 hover:file:bg-green-100 dark:hover:file:bg-green-800"
                          />
                          {options.metadata[variable.name] && (
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-300">
                              Image sélectionnée : {options.metadata[variable.name]}
                            </p>
                          )}
                        </div>
                      ) : (
                        <input
                          type="text"
                          value={options.metadata[variable.name] || ''}
                          onChange={(e) => handleMetadataChange(variable.name, e.target.value)}
                          className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 py-2 px-3 shadow-sm focus:border-green-500 focus:outline-none focus:ring-green-500 dark:bg-gray-800 dark:text-white"
                          placeholder={`Entrez ${variable.name}...`}
                        />
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </>
      )}

      {uploadError && (
        <div className="mt-4 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 p-3 rounded-md">
          {uploadError}
        </div>
      )}

      <div className="border-t border-gray-300 dark:border-gray-600 mt-4 pt-4">
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
          {t('document_images')}
        </h3>
        <p className="text-sm text-gray-500 dark:text-gray-400 mb-2">
          {t('document_images_help')} 
          <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">![[image.png]]</code>
        </p>
        <p className="text-sm text-green-600 dark:text-green-400 mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          {t('auto_match_images')}
        </p>
        <p className="text-sm text-blue-600 dark:text-blue-400 mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 inline-block mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          {t('session_images')}
        </p>
        <div className="mt-2">
          <input
            type="file"
            accept="image/*"
            multiple
            onChange={(e) => handleMultipleImagesUpload(e)}
            className="block w-full text-sm text-gray-500 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-gray-700 file:text-indigo-700 dark:file:text-indigo-200 hover:file:bg-indigo-100 dark:hover:file:bg-gray-600"
          />
        </div>
        {uploadedContentImages.length > 0 && (
          <div className="mt-2">
            <h4 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {t('uploaded_images')}:
            </h4>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
              {uploadedContentImages.map((img, index) => (
                <div key={index} className="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                  <div className="flex items-center">
                    <img 
                                              src={`/serve-image.php/${encodeURIComponent(img)}`} 
                      alt={img} 
                      className="h-10 w-10 object-cover mr-2"
                      onError={(e) => {
                        // Fallback en cas d'erreur de chargement de l'image
                        const target = e.target as HTMLImageElement;
                        target.src = '/images/image-placeholder.svg';
                        target.alt = 'Image non disponible';
                      }}
                    />
                    <code className="text-xs">![[{img}]]</code>
                  </div>
                  <div className="flex">
                    <button
                      onClick={() => {
                        navigator.clipboard.writeText(`![[${img}]]`);
                        alert(t('copied_to_clipboard'));
                      }}
                      className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 mr-2"
                      title={t('copy_to_clipboard')}
                    >
                      📋
                    </button>
                    <button
                      onClick={() => handleDeleteImage(img)}
                      className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                      title={t('delete_image')}
                    >
                      🗑️
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default TemplateSelector; 