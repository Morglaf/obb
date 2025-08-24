import React, { useState, useEffect } from 'react';
import { useTranslation } from 'next-i18next';

interface VersionInfo {
  version: string;
  fullVersion: string;
  commitHash: string;
  commitDate: string;
  commitCount: string;
  branch: string;
  buildDate: string;
  buildDateFormatted: string;
}

const VersionInfo: React.FC = () => {
  const { t } = useTranslation('translation');
  const [versionInfo, setVersionInfo] = useState<VersionInfo | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Charger les informations de version
    const loadVersionInfo = async () => {
      try {
        console.log('🔄 Chargement des informations de version...');
        
        // Essayer d'abord le fichier local (plus fiable)
        const localResponse = await fetch('/version.json');
        console.log('📁 Réponse fichier local:', localResponse.status, localResponse.ok);
        
        if (localResponse.ok) {
          const localData = await localResponse.json();
          console.log('✅ Données locales chargées:', localData);
          setVersionInfo(localData);
        } else {
          console.log('❌ Fichier local non trouvé, essai API...');
          // Fallback : essayer l'API
          const response = await fetch('/api/version');
          console.log('🌐 Réponse API:', response.status, response.ok);
          
          if (response.ok) {
            const data = await response.json();
            console.log('✅ Données API chargées:', data);
            setVersionInfo(data.data); // L'API retourne {status: 'success', data: {...}}
          }
        }
      } catch (error) {
        console.error('❌ Erreur lors du chargement de la version:', error);
      } finally {
        setLoading(false);
      }
    };

    loadVersionInfo();
  }, []);

  if (loading) {
    return (
      <div className="text-xs text-gray-400 dark:text-gray-500">
        {t('loading')}...
      </div>
    );
  }

  if (!versionInfo) {
    return (
      <div className="text-xs text-gray-400 dark:text-gray-500">
        <span>v0.1.0</span>
        <span> • </span>
        <span>Version non disponible</span>
      </div>
    );
  }

  // Construire l'URL Git
  const gitUrl = `https://github.com/Morglaf/obb/commit/${versionInfo.commitHash}`;
  
  return (
    <div className="flex flex-col items-center space-y-1 text-xs text-gray-500 dark:text-gray-400">
      <div className="flex items-center space-x-2">
        <span>v{versionInfo.fullVersion}</span>
        <span>•</span>
        <a
          href={gitUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="hover:text-blue-500 dark:hover:text-blue-400 transition-colors"
          title={`Commit ${versionInfo.commitHash} sur ${versionInfo.branch}`}
        >
          {versionInfo.commitHash}
        </a>
      </div>
      <div className="text-center">
        <span>{versionInfo.buildDateFormatted}</span>
        {versionInfo.branch !== 'master' && (
          <>
            <span> • </span>
            <span className="text-orange-500">{versionInfo.branch}</span>
          </>
        )}
      </div>
    </div>
  );
};

export default VersionInfo;
