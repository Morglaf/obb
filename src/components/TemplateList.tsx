import React, { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'next-i18next';
import { FaEdit, FaTrash, FaDownload, FaPlus, FaEye, FaRedo } from 'react-icons/fa';
import { useAuth } from '../contexts/AuthContext';

interface Template {
  id: number;
  name: string;
  type: 'layout' | 'cover' | 'impose';
  file_path: string;
  preview_path?: string;
  created_at: string;
}

interface TemplateListProps {
  onSelectTemplate: (template: Template) => void;
  onNewTemplate: () => void;
}

const TemplateList: React.FC<TemplateListProps> = ({ onSelectTemplate, onNewTemplate }) => {
  const { t } = useTranslation('translation');
  const { user } = useAuth();
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Fonction pour charger les templates
  const fetchTemplates = useCallback(async () => {
    if (!user) return;
    
    try {
      setLoading(true);
      setError(null);
      
      // Récupérer le token depuis localStorage
      const token = localStorage.getItem('auth_token');
      if (!token) {
        setError('Token d\'authentification manquant');
        return;
      }
      
      console.log('Tentative de récupération des templates avec token:', token.substring(0, 20) + '...');
      
      const response = await fetch(`/api/templates/user`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      console.log('Réponse API:', response.status, response.statusText);
      
      if (response.ok) {
        const data = await response.json();
        console.log('Templates reçus:', data);
        setTemplates(data.templates || []);
      } else {
        const errorData = await response.json().catch(() => ({}));
        console.error('Erreur API:', response.status, errorData);
        setError(`Erreur ${response.status}: ${errorData.message || 'Erreur lors du chargement des templates'}`);
      }
    } catch (err) {
      console.error('Erreur lors du chargement des templates:', err);
      setError('Erreur de connexion');
    } finally {
      setLoading(false);
    }
  }, [user]);

  // Charger les templates au montage et quand user change
  useEffect(() => {
    fetchTemplates();
  }, [fetchTemplates]);

  // Fonction de retry sécurisée
  const handleRetry = useCallback(() => {
    fetchTemplates();
  }, [fetchTemplates]);

  // Supprimer un template
  const handleDeleteTemplate = async (templateId: number) => {
    if (!confirm(t('confirm_delete_template'))) return;

    try {
      const token = localStorage.getItem('auth_token');
      if (!token) {
        alert('Token d\'authentification manquant');
        return;
      }
      
      const response = await fetch(`/api/templates/${templateId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        setTemplates(prev => prev.filter(t => t.id !== templateId));
      } else {
        alert(t('delete_error'));
      }
    } catch (err) {
      console.error('Erreur lors de la suppression:', err);
      alert(t('delete_error'));
    }
  };

  // Télécharger un template
  const handleDownloadTemplate = async (template: Template) => {
    try {
      const response = await fetch(`/api/templates/content/${template.id}`);
      
      if (response.ok) {
        const content = await response.text();
        const blob = new Blob([content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${template.name}.tex`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
      } else {
        alert('Erreur lors du téléchargement');
      }
    } catch (err) {
      console.error('Erreur lors du téléchargement:', err);
      alert('Erreur lors du téléchargement');
    }
  };

  // État de chargement
  if (loading) {
    return (
      <div className="text-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
        <p className="text-gray-500 dark:text-gray-400 mt-2">{t('loading')}</p>
      </div>
    );
  }

  // État d'erreur
  if (error) {
    return (
      <div className="text-center py-8">
        <div className="mb-4">
          <FaRedo className="mx-auto text-red-500 dark:text-red-400 text-4xl mb-2" />
          <p className="text-red-500 dark:text-red-400 font-medium">{error}</p>
        </div>
        <button 
          onClick={handleRetry}
          className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition-colors flex items-center mx-auto"
        >
          <FaRedo className="mr-2" />
          Réessayer
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* En-tête avec bouton nouveau template */}
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          {t('my_templates')}
        </h3>
        <button
          onClick={onNewTemplate}
          className="flex items-center px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-md transition-colors"
        >
          <FaPlus className="mr-2" size={12} />
          {t('new_template')}
        </button>
      </div>

      {/* Liste des templates */}
      {templates.length === 0 ? (
        <div className="text-center py-8 text-gray-500 dark:text-gray-400">
          <p>{t('no_templates')}</p>
        </div>
      ) : (
        <div className="space-y-3">
          {templates.map((template) => (
            <div
              key={template.id}
              className="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
            >
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center space-x-2">
                  <span className={`px-2 py-1 text-xs rounded-full ${
                    template.type === 'layout' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' :
                    template.type === 'cover' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                    'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                  }`}>
                    {template.type}
                  </span>
                  <h4 className="font-medium text-gray-900 dark:text-white">
                    {template.name}
                  </h4>
                </div>
              </div>

              <div className="text-xs text-gray-500 dark:text-gray-400 mb-3">
                {t('created_on')}: {new Date(template.created_at).toLocaleDateString()}
              </div>

              {/* Actions */}
              <div className="flex space-x-2">
                <button
                  onClick={() => onSelectTemplate(template)}
                  className="flex-1 flex items-center justify-center px-2 py-1 bg-blue-500 hover:bg-blue-600 text-white text-xs rounded transition-colors"
                  title={t('edit_template')}
                >
                  <FaEdit className="mr-1" size={10} />
                  {t('edit_template')}
                </button>
                
                <button
                  onClick={() => handleDownloadTemplate(template)}
                  className="px-2 py-1 bg-green-500 hover:bg-green-600 text-white text-xs rounded transition-colors"
                  title={t('download_template')}
                >
                  <FaDownload size={10} />
                </button>
                
                <button
                  onClick={() => handleDeleteTemplate(template.id)}
                  className="px-2 py-1 bg-red-500 hover:bg-red-600 text-white text-xs rounded transition-colors"
                  title={t('delete')}
                >
                  <FaTrash size={10} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default TemplateList;
