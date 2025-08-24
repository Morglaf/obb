import React, { useEffect, useState } from 'react';
import { FaEye, FaUser } from 'react-icons/fa';
import { useTranslation } from 'next-i18next';
import { CoverMetadata } from '../types/templates';
import { getFriendlyName, parseFormatFromName, parsePaperFormatFromName, fetchUserTemplateMetadata, getPreviewUrl, isUserTemplate as isUserTemplateUtil } from '../utils/formatUtils';
import { isStringArray } from '../utils/arrayUtils';
import HoverPreview from './HoverPreview';

// Utiliser le proxy Next.js au lieu d'une URL absolue
const API_URL = '';

interface CoverSelectorProps {
  covers: (string | CoverMetadata)[];
  filteredCovers: string[];
  selectedCover: string;
  hasLayout: boolean;
  onSelectCover: (coverName: string) => void;
  onPreviewCover: (coverName: string) => void;
}

/**
 * Vérifie si un template est défini par l'utilisateur
 */
const isUserTemplate = (cover: string, metadata?: CoverMetadata | null): boolean => {
  if (metadata?.isUserTemplate === true) return true;
  return isUserTemplateUtil(cover);
};

/**
 * Composant pour la sélection de couvertures
 */
const CoverSelector: React.FC<CoverSelectorProps> = ({
  covers,
  filteredCovers,
  selectedCover,
  hasLayout,
  onSelectCover,
  onPreviewCover
}) => {
  const { t } = useTranslation('translation');
  const [userMetadata, setUserMetadata] = useState<Record<string, { title?: string, description?: string, version?: string }>>({});
  const [hoverPreview, setHoverPreview] = useState<{ name: string, position: { x: number, y: number }, isUserTemplate: boolean, userId?: number } | null>(null);
  
  // Charger les métadonnées des templates personnalisés
  useEffect(() => {
    const loadUserMetadata = async () => {
      const userCovers = filteredCovers.filter(cover => {
        const metadata = !isStringArray(covers) 
          ? covers.find(c => typeof c === 'object' && (c.filename === cover || c.name === cover)) as CoverMetadata | undefined 
          : null;
        return isUserTemplate(cover, metadata || null);
      });
      
      const metadataPromises = userCovers.map(async (cover) => {
        // Trouver le template correspondant pour récupérer l'ID utilisateur
        const template = !isStringArray(covers) 
          ? covers.find(c => typeof c === 'object' && (c.filename === cover || c.name === cover)) as CoverMetadata | undefined 
          : null;
        const userId = template?.userId;
        const metadata = await fetchUserTemplateMetadata('cover', cover, userId);
        if (metadata) {
          return { name: cover, metadata };
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

    if (filteredCovers.length > 0) {
      loadUserMetadata();
    }
  }, [covers, filteredCovers]);

  // Fonction pour gérer le survol du bouton d'aperçu
  const handleTemplateHover = (event: React.MouseEvent, coverName: string, isUserTemplate: boolean) => {
    if (coverName) {
      const x = event.clientX + 10;
      const y = event.clientY + 10;
      // Récupérer le userId du template
      const cover = covers.find(c => c === coverName || (typeof c === 'object' && c.name === coverName));
      const userId = cover && typeof cover === 'object' ? (cover as any).userId : undefined;
      setHoverPreview({ name: coverName, position: { x, y }, isUserTemplate, userId });
    } else {
      setHoverPreview(null);
    }
  };

  return (
    <div className="flex flex-col h-full">
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
        {t('cover')}
      </label>
      <div className="grid grid-cols-1 gap-4 flex-grow">
        {hasLayout ? (
          filteredCovers.length > 0 ? (
            filteredCovers.map((cover, index) => {
              // Trouver les métadonnées de la couverture si disponibles
              const coverMetadata = isStringArray(covers) 
                ? null 
                : covers.find(c => 
                    typeof c === 'object' && 
                    (c.filename === cover || c.name === cover)
                  ) as CoverMetadata | undefined;
              
              const isCustom = isUserTemplate(cover, coverMetadata || null);
              // Extraire le format du nom
              const format = coverMetadata?.format || parseFormatFromName(cover);
              // Extraire le format papier
              const paperFormat = coverMetadata?.paperFormat || parsePaperFormatFromName(cover);
              
              // Récupérer les métadonnées du fichier .tex
              const customMetadata = isCustom ? userMetadata[cover] : null;
              const coverTitle = customMetadata?.title || coverMetadata?.title || getFriendlyName(cover);
              const coverDescription = customMetadata?.description || coverMetadata?.description || '';
              const coverVersion = customMetadata?.version || coverMetadata?.version || '';
              const coverAuthor = coverMetadata?.author || '';
              
              return (
                <div
                  key={`cover-${cover || index}`}
                  className={`border rounded-lg p-3 cursor-pointer transition-all ${
                    selectedCover === cover
                      ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900'
                      : isCustom
                        ? 'border-purple-200 dark:border-purple-800 hover:border-purple-300 dark:hover:border-purple-700'
                        : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700'
                  }`}
                  onClick={() => onSelectCover(cover)}
                >
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center">
                      <div className={`w-4 h-4 rounded-full mr-2 ${
                        selectedCover === cover
                          ? 'bg-indigo-500'
                          : isCustom
                            ? 'bg-purple-500'
                            : 'bg-gray-300 dark:bg-gray-600'
                      }`}></div>
                      <h3 className="font-medium text-gray-800 dark:text-gray-200">
                        {coverTitle}
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
                          e.stopPropagation(); // Empêcher la sélection de la couverture
                          onPreviewCover(cover);
                        }}
                        onMouseEnter={(e) => handleTemplateHover(e, cover, isUserTemplate(cover, coverMetadata || null))}
                        onMouseLeave={(e) => handleTemplateHover(e, '', false)}
                        title={t('preview')}
                      >
                        <FaEye />
                      </button>
                    </div>
                  </div>
                  
                  {coverDescription && (
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                      {coverDescription}
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
                    {coverVersion && (
                      <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        v{coverVersion}
                      </span>
                    )}
                    {coverAuthor && (
                      <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        {coverAuthor}
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
            })
          ) : (
            <div className="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800">
              <p className="text-gray-500 dark:text-gray-400">{t('no_compatible_covers')}</p>
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
          imageUrl={getPreviewUrl('', 'cover', hoverPreview.name, hoverPreview.isUserTemplate, hoverPreview.userId)} 
          templateName={hoverPreview.name}
          position={hoverPreview.position}
        />
      )}
    </div>
  );
};

export default CoverSelector; 