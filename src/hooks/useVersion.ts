import { useState, useEffect } from 'react';

interface VersionData {
  version: string;
  build: number;
  last_deploy: string;
  git_commit: string;
  deploy_date: string;
}

interface VersionResponse {
  status: string;
  data: VersionData;
  message?: string;
}

export const useVersion = () => {
  const [version, setVersion] = useState<VersionData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchVersion = async () => {
      try {
        console.log('🔄 Hook useVersion: Début de la récupération de la version...');
        setLoading(true);
        
        const response = await fetch('/api/version');
        console.log('📡 API Response:', response.status, response.statusText);
        
        if (!response.ok) {
          throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        const data: VersionResponse = await response.json();
        console.log('📋 Données reçues:', data);
        
        if (data.status === 'success') {
          console.log('✅ Version récupérée avec succès:', data.data);
          setVersion(data.data);
        } else {
          throw new Error(data.message || 'Erreur lors de la récupération de la version');
        }
      } catch (err) {
        console.error('❌ Erreur dans useVersion:', err);
        setError(err instanceof Error ? err.message : 'Erreur inconnue');
      } finally {
        setLoading(false);
        console.log('🏁 Hook useVersion: Terminé');
      }
    };

    fetchVersion();
  }, []);

  return { version, loading, error };
};
