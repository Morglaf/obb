import React, { useEffect, useState } from 'react';
import { FaEye, FaUser } from 'react-icons/fa';
import { useTranslation } from 'next-i18next';
import { LayoutMetadata } from '../types/templates';
import { getFriendlyName, parseFormatFromName, fetchUserTemplateMetadata, getPreviewUrl, isUserTemplate as isUserTemplateUtil } from '../utils/formatUtils';
import HoverPreview from './HoverPreview';

// Utiliser le proxy Next.js au lieu d'une URL absolue
const API_URL = '';

interface LayoutSelectorProps {
  layouts: LayoutMetadata[];
  selectedLayout: string;
  onSelectLayout: (layoutName: string) => void;
  onPreviewLayout: (layoutName: string) => void;
}

/**
 * Vérifie si un template est défini par l'utilisateur
 */
const isUserTemplate = (template: LayoutMetadata): boolean => {
  if (template.isUserTemplate === true) return true;
  return isUserTemplateUtil(template.name);
};

/**
 * Composant pour la sélection de layouts
 */
const LayoutSelector: React.FC<LayoutSelectorProps> = ({
  layouts,
  selectedLayout,
  onSelectLayout,
  onPreviewLayout
}) => {
  const { t } = useTranslation('translation');
  const [userMetadata, setUserMetadata] = useState<Record<string, { title?: string, description?: string, version?: string }>>({});
  const [hoverPreview, setHoverPreview] = useState<{ name: string, position: { x: number, y: number }, isUserTemplate: boolean, userId?: number } | null>(null);

  // Charger les métadonnées des templates personnalisés
  useEffect(() => {
    const loadUserMetadata = async () => {
      const userTemplates = layouts.filter(isUserTemplate);
      const metadataPromises = userTemplates.map(async (template) => {
        // Utiliser l'ID utilisateur du template s'il est disponible
        const userId = (template as any).userId;
        const metadata = await fetchUserTemplateMetadata('layout', template.name, userId);
        if (metadata) {
          return { name: template.name, metadata, isUserTemplate: true };
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

    if (layouts.some(isUserTemplate)) {
      loadUserMetadata();
    }
  }, [layouts]);

  // Afficher l'aperçu au survol
  const handleTemplateHover = (event: React.MouseEvent, layoutName: string, isUserTemplate: boolean) => {
    if (layoutName) {
      const x = event.clientX + 10;
      const y = event.clientY + 10;
      // Récupérer le userId du template
      const layout = layouts.find(l => l.name === layoutName);
      const userId = layout ? (layout as any).userId : undefined;
      setHoverPreview({ name: layoutName, position: { x, y }, isUserTemplate, userId });
    } else {
      setHoverPreview(null);
    }
  };

  return (
    <div className="flex flex-col h-full">
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
        {t('layout')}
      </label>
      <div className="grid grid-cols-1 gap-4 flex-grow">
        {layouts.length > 0 ? (
          layouts.map((layout) => {
            const isCustom = isUserTemplate(layout);
            // Extraire le format du nom si non défini dans les métadonnées
            const format = layout.format || parseFormatFromName(layout.name);
            
            // Récupérer les métadonnées du fichier .tex
            const customMetadata = isCustom ? userMetadata[layout.name] : null;
            const layoutTitle = customMetadata?.title || layout.metadata?.title || layout.title || getFriendlyName(layout.name);
            const layoutDescription = customMetadata?.description || layout.metadata?.description || layout.description || '';
            const layoutVersion = customMetadata?.version || layout.metadata?.version || layout.version || '';
            const layoutAuthor = layout.metadata?.author || layout.metadata?.font || '';
            
            return (
              <div
                key={`layout-${layout.name}`}
                className={`border rounded-lg p-3 cursor-pointer transition-all ${
                  selectedLayout === layout.name
                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900'
                    : isCustom
                      ? 'border-purple-200 dark:border-purple-800 hover:border-purple-300 dark:hover:border-purple-700'
                      : 'border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-700'
                }`}
                onClick={() => onSelectLayout(layout.name)}
              >
                <div className="flex items-center justify-between mb-2">
                  <div className="flex items-center">
                    <div className={`w-4 h-4 rounded-full mr-2 ${
                      selectedLayout === layout.name
                        ? 'bg-indigo-500'
                        : isCustom
                          ? 'bg-purple-500'
                          : 'bg-gray-300 dark:bg-gray-600'
                    }`}></div>
                    <h3 className="font-medium text-gray-800 dark:text-gray-200">
                      {layoutTitle}
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
                        e.stopPropagation(); // Empêcher la sélection du layout
                        onPreviewLayout(layout.name);
                      }}
                      onMouseEnter={(e) => handleTemplateHover(e, layout.name, isUserTemplate(layout))}
                      onMouseLeave={(e) => handleTemplateHover(e, '', false)}
                      title={t('preview')}
                    >
                      <FaEye />
                    </button>
                  </div>
                </div>
                
                {layoutDescription && (
                  <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                    {layoutDescription}
                  </p>
                )}
                
                <div className="flex flex-wrap gap-2 mt-2">
                  {layout.style && (
                    <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                      {layout.style}
                    </span>
                  )}
                  {format && (
                    <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                      {format}
                    </span>
                  )}
                  {layoutVersion && (
                    <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                      v{layoutVersion}
                    </span>
                  )}
                  {layoutAuthor && (
                    <span className="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                      {layoutAuthor}
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
            <p className="text-gray-500 dark:text-gray-400">{t('no_layouts_available')}</p>
          </div>
        )}
      </div>
      
      {/* Aperçu au survol */}
      {hoverPreview && (
        <HoverPreview 
          imageUrl={getPreviewUrl('', 'layout', hoverPreview.name, hoverPreview.isUserTemplate, hoverPreview.userId)} 
          templateName={hoverPreview.name}
          position={hoverPreview.position}
        />
      )}
    </div>
  );
};

export default LayoutSelector; 