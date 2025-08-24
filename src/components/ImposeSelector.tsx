import React, { useEffect, useState } from 'react';
import { FaEye, FaUser } from 'react-icons/fa';
import { useTranslation } from 'next-i18next';
import { ImposeMetadata } from '../types/templates';
import { getFriendlyName, parseFormatFromName, parsePaperFormatFromName, fetchUserTemplateMetadata, getPreviewUrl, isUserTemplate as isUserTemplateUtil } from '../utils/formatUtils';
import { isStringArray } from '../utils/arrayUtils';
import HoverPreview from './HoverPreview';

// Utiliser le proxy Next.js au lieu d'une URL absolue
const API_URL = '';

interface ImposeSelectorProps {
  imposes: (string | ImposeMetadata)[];
  filteredImposes: string[];
  selectedImpose: string;
  hasLayout: boolean;
  onSelectImpose: (imposeName: string) => void;
  onPreviewImpose: (imposeName: string) => void;
  paperThickness?: number;
  onPaperThicknessChange?: (value: number) => void;
}

/**
 * Vérifie si un template est défini par l'utilisateur
 */
const isUserTemplate = (impose: string, metadata?: ImposeMetadata | null): boolean => {
  if (metadata?.isUserTemplate === true) return true;
  return isUserTemplateUtil(impose);
};

/**
 * Composant pour la sélection d'impositions
 */
const ImposeSelector: React.FC<ImposeSelectorProps> = ({
  imposes,
  filteredImposes,
  selectedImpose,
  hasLayout,
  onSelectImpose,
  onPreviewImpose,
  paperThickness = 0.1,
  onPaperThicknessChange
}) => {
  const { t } = useTranslation('translation');
  const [userMetadata, setUserMetadata] = useState<Record<string, { title?: string, description?: string, version?: string }>>({});
  const [hoverPreview, setHoverPreview] = useState<{ name: string, position: { x: number, y: number }, isUserTemplate: boolean, userId?: number } | null>(null);

  // Vérifier si l'imposition sélectionnée est de type "spread"
  const selectedImposeData = !isStringArray(imposes) 
    ? imposes.find(i => 
        typeof i === 'object' && 
        (i.filename === selectedImpose || i.name === selectedImpose)
      ) as ImposeMetadata | undefined
    : undefined;

  const isSpreadImposition = 
    selectedImpose && 
    (selectedImpose.includes('spread') || selectedImposeData?.isSpread);

  // Charger les métadonnées des templates personnalisés
  useEffect(() => {
    const loadUserMetadata = async () => {
      const userImposes = filteredImposes.filter(impose => {
        const metadata = !isStringArray(imposes) 
          ? imposes.find(i => typeof i === 'object' && (i.filename === impose || i.name === impose)) as ImposeMetadata | undefined 
          : null;
        return isUserTemplate(impose, metadata || null);
      });
      
      const metadataPromises = userImposes.map(async (impose) => {
        // Trouver le template correspondant pour récupérer l'ID utilisateur
        const template = !isStringArray(imposes) 
          ? imposes.find(i => typeof i === 'object' && (i.filename === impose || i.name === impose)) as ImposeMetadata | undefined 
          : null;
        const userId = template?.userId;
        const metadata = await fetchUserTemplateMetadata('impose', impose, userId);
        if (metadata) {
          return { name: impose, metadata };
        }
        return null;
      });

      const results = await Promise.all(metadataPromises);
      const metadataMap: Record<string, { title?: string, description?: string, version?: string }> = {};
      
      results.forEach(result => {
        if (result) {
          metadataMap[result.name] = result.metadata;
        }
      });
      
      setUserMetadata(metadataMap);
    };

    if (filteredImposes.length > 0) {
      loadUserMetadata();
    }
  }, [imposes, filteredImposes]);

  // Fonction pour gérer le survol du bouton d'aperçu
  const handleTemplateHover = (event: React.MouseEvent, imposeName: string, isUserTemplate: boolean) => {
    if (imposeName) {
      const x = event.clientX + 10;
      const y = event.clientY + 10;
      // Récupérer le userId du template
      const impose = imposes.find(i => i === imposeName || (typeof i === 'object' && i.name === imposeName));
      const userId = impose && typeof impose === 'object' ? (impose as any).userId : undefined;
      setHoverPreview({ name: imposeName, position: { x, y }, isUserTemplate, userId });
    } else {
      setHoverPreview(null);
    }
  };

  // Gestionnaire pour le changement d'épaisseur du papier
  const handlePaperThicknessChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = parseFloat(e.target.value);
    if (!isNaN(value) && onPaperThicknessChange) {
      onPaperThicknessChange(value);
    }
  };

  return (
    <div className="flex flex-col h-full">
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
        {t('impose')}
      </label>
      <div className="grid grid-cols-1 gap-4 flex-grow">
        {hasLayout ? (
          filteredImposes.length > 0 ? (
            <>
              {filteredImposes.map((impose, index) => {
                // Trouver les métadonnées de l'imposition si disponibles
                const imposeMetadata = isStringArray(imposes) 
                  ? null 
                  : imposes.find(i => 
                      typeof i === 'object' && 
                      (i.filename === impose || i.name === impose)
                    ) as ImposeMetadata | undefined;
                  
                // Vérifier si c'est une imposition de type spread
                const isSpread = impose.includes('spread') || imposeMetadata?.isSpread;
                const isCustom = isUserTemplate(impose, imposeMetadata || null);
                // Extraire le format du nom ou des métadonnées
                const format = imposeMetadata?.format || parseFormatFromName(impose);
                // Extraire le format papier du nom ou des métadonnées
                const paperFormat = imposeMetadata?.paperFormat || parsePaperFormatFromName(impose);
                
                // Récupérer les métadonnées du fichier .tex
                const customMetadata = isCustom ? userMetadata[impose] : null;
                
                const imposeTitle = customMetadata?.title || imposeMetadata?.title || getFriendlyName(impose);
                const imposeDescription = customMetadata?.description || imposeMetadata?.description || '';
                const imposeVersion = customMetadata?.version || imposeMetadata?.version || '';
                const imposeAuthor = imposeMetadata?.author || '';
                
                return (
                  <div
                    key={`impose-${impose || index}`}
                    className={`border rounded-lg p-3 cursor-pointer transition-all ${
                      selectedImpose === impose
                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900'
                        : isCustom
                          ? 'border-purple-200 dark:border-purple-800 hover:border-purple-300 dark:hover:border-purple-700'
                          : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700'
                    }`}
                    onClick={() => onSelectImpose(impose)}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center">
                        <div className={`w-4 h-4 rounded-full mr-2 ${
                          selectedImpose === impose
                            ? 'bg-indigo-500'
                            : isCustom
                              ? 'bg-purple-500'
                              : 'bg-gray-300 dark:bg-gray-600'
                        }`}></div>
                        <h3 className="font-medium text-gray-800 dark:text-gray-200">
                          {imposeTitle}
                        </h3>
                      </div>
                      <div className="flex items-center space-x-2">
                        {isCustom && (
                          <span className="inline-flex items-center p-1 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200" title={t('user_template')}>
                            <FaUser size={12} />
                          </span>
                        )}
                        <button 
                          className="p-1 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                          onClick={(e) => {
                            e.stopPropagation(); // Empêcher la sélection de l'imposition
                            onPreviewImpose(impose);
                          }}
                          onMouseEnter={(e) => handleTemplateHover(e, impose, isUserTemplate(impose, imposeMetadata || null))}
                          onMouseLeave={(e) => handleTemplateHover(e, '', false)}
                          title={t('preview')}
                        >
                          <FaEye />
                        </button>
                      </div>
                    </div>
                    
                    {imposeDescription && (
                      <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        {imposeDescription}
                      </p>
                    )}
                    
                    <div className="flex flex-wrap gap-2 mt-2">
                      {format && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                          {format}
                        </span>
                      )}
                      {paperFormat && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                          {paperFormat}
                        </span>
                      )}
                      {isSpread && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                          Spread
                        </span>
                      )}
                      {!isSpread && impose.includes('signature') && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                          Signature
                        </span>
                      )}
                      {imposeVersion && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                          v{imposeVersion}
                        </span>
                      )}
                      {imposeAuthor && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                          {imposeAuthor}
                        </span>
                      )}
                      {isCustom && (
                        <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200">
                          {t('custom')}
                        </span>
                      )}
                    </div>
                  </div>
                );
              })}

              {/* Champ pour l'épaisseur du papier si une imposition de type spread est sélectionnée */}
              {isSpreadImposition && (
                <div className="mt-4 p-4 border border-blue-200 dark:border-blue-800 rounded-lg bg-blue-50 dark:bg-blue-900/30">
                  <label htmlFor="paperThickness" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {t('paper_thickness')} (mm)
                    <span className="ml-2 text-xs text-gray-500 dark:text-gray-400 italic">
                      (Pour calculer la compensation)
                    </span>
                  </label>
                  <input
                    id="paperThickness"
                    type="number"
                    step="0.01"
                    min="0.01"
                    max="1"
                    value={paperThickness}
                    onChange={handlePaperThicknessChange}
                    className="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 py-2 px-3 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:bg-gray-800 dark:text-white"
                  />
                  <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    La compensation sera calculée en fonction de l'épaisseur du papier et de la position dans le livre.
                  </p>
                </div>
              )}
            </>
          ) : (
            <div className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800">
              <p className="text-gray-500 dark:text-gray-400">{t('no_compatible_imposes')}</p>
            </div>
          )
        ) : (
          <div className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800">
            <p className="text-gray-500 dark:text-gray-400">{t('select_layout_first')}</p>
          </div>
        )}
      </div>
      
      {/* Aperçu au survol */}
      {hoverPreview && (
        <HoverPreview 
          imageUrl={getPreviewUrl('', 'impose', hoverPreview.name, hoverPreview.isUserTemplate, hoverPreview.userId)} 
          templateName={hoverPreview.name}
          position={hoverPreview.position}
        />
      )}
    </div>
  );
};

export default ImposeSelector; 