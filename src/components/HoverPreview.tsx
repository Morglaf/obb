import React from 'react';
import Image from 'next/image';

interface HoverPreviewProps {
  imageUrl: string;
  templateName: string;
  position: { x: number, y: number } | null;
}

const HoverPreview: React.FC<HoverPreviewProps> = ({ imageUrl, templateName, position }) => {
  if (!position) return null;
  
  return (
    <div 
      className="fixed z-50 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
      style={{ 
        left: `${position.x}px`, 
        top: `${position.y}px`,
        maxWidth: '300px',
        maxHeight: '300px',
        pointerEvents: 'none' // Pour éviter que le hover preview interfère avec les autres éléments
      }}
    >
      <div className="p-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
        {templateName}
      </div>
      <div className="p-2">
        <img 
          src={imageUrl} 
          alt={`Aperçu de ${templateName}`} 
          className="w-full h-auto max-h-[250px] object-contain"
        />
      </div>
    </div>
  );
};

export default HoverPreview; 