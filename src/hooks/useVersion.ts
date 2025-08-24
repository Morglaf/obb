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
        setLoading(true);
        const response = await fetch('/api/version');
        
        if (!response.ok) {
          throw new Error(`Erreur HTTP: ${response.status}`);
        }
        
        const data: VersionResponse = await response.json();
        
        if (data.status === 'success') {
          setVersion(data.data);
        } else {
          throw new Error(data.message || 'Erreur lors de la récupération de la version');
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Erreur inconnue');
        console.error('Erreur lors de la récupération de la version:', err);
      } finally {
        setLoading(false);
      }
    };

    fetchVersion();
  }, []);

  return { version, loading, error };
};
