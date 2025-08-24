import React from 'react';
import { useTranslation } from 'next-i18next';

interface ImagePreviewProps {
  previewData: {
    type: string;
    url: string;
    name: string;
  } | null;
  onClose: () => void;
}

/**
 * Composant de prévisualisation d'image en modal
 * 
 * Affiche une image en plein écran avec possibilité de fermer la modal
 */
const ImagePreview: React.FC<ImagePreviewProps> = ({ previewData, onClose }) => {
  const { t } = useTranslation('translation');

  if (!previewData) return null;

  return (
    <div 
      className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75"
      onClick={onClose}
    >
      <div className="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-xl max-w-4xl max-h-screen overflow-auto" onClick={e => e.stopPropagation()}>
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-lg font-semibold">{t('preview_of')} {previewData.name}</h3>
          <button 
            className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            onClick={onClose}
          >
            ×
          </button>
        </div>
        <img 
          src={previewData.url} 
          alt={`Aperçu de ${previewData.name}`} 
          className="max-w-full max-h-[70vh]"
          onError={(e) => {
            // Masquer l'image si elle n'existe pas et afficher un message
            (e.target as HTMLImageElement).style.display = 'none';
            const parent = (e.target as HTMLImageElement).parentElement;
            if (parent) {
              const errorMsg = document.createElement('p');
              errorMsg.textContent = t('preview_not_available');
              errorMsg.className = 'text-red-500 dark:text-red-400 mt-2';
              parent.appendChild(errorMsg);
            }
          }}
        />
      </div>
    </div>
  );
};

export default ImagePreview; 