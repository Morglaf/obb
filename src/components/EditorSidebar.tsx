import React, { useState, useEffect } from 'react';
import { useTranslation } from 'next-i18next';
import { FaCode, FaFont, FaEye, FaEyeSlash, FaSearch, FaPlus } from 'react-icons/fa';
import { useAuth } from '../contexts/AuthContext';

interface Variable {
  name: string;
  type: 'text' | 'image' | 'number';
  description: string;
  example?: string;
}

interface Font {
  name: string;
  type: 'system' | 'user';
  format: string;
  preview?: string;
}

interface EditorSidebarProps {
  templateType: 'layout' | 'cover' | 'impose';
  isVisible: boolean;
  onToggleVisibility: () => void;
  onInsertVariable?: (variable: string) => void;
}

const EditorSidebar: React.FC<EditorSidebarProps> = ({ 
  templateType, 
  isVisible, 
  onToggleVisibility,
  onInsertVariable
}) => {
  const { t } = useTranslation('translation');
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<'variables' | 'fonts'>('variables');
  const [variables, setVariables] = useState<Variable[]>([]);
  const [fonts, setFonts] = useState<Font[]>([]);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredVariables, setFilteredVariables] = useState<Variable[]>([]);
  const [filteredFonts, setFilteredFonts] = useState<Font[]>([]);

  // Charger les variables selon le type de template
  useEffect(() => {
    const loadVariables = () => {
      const commonVariables: Variable[] = [
        { name: 'title', type: 'text', description: 'Titre principal du document', example: 'Mon Livre' },
        { name: 'subtitle', type: 'text', description: 'Sous-titre du document', example: 'Sous-titre' },
        { name: 'author', type: 'text', description: 'Nom de l\'auteur', example: 'Jean Dupont' },
        { name: 'date', type: 'text', description: 'Date de publication', example: '2024' },
        { name: 'publisher', type: 'text', description: 'Éditeur', example: 'Éditions XYZ' },
        { name: 'isbn', type: 'text', description: 'Numéro ISBN', example: '978-2-1234-5678-9' },
        { name: 'language', type: 'text', description: 'Langue du document', example: 'français' },
        { name: 'version', type: 'text', description: 'Version du document', example: '1.0' },
      ];

      // Variables spécifiques selon le type
      if (templateType === 'cover') {
        commonVariables.push(
          { name: 'cover_image', type: 'image', description: 'Image de couverture', example: 'cover.jpg' },
          { name: 'back_cover_text', type: 'text', description: 'Texte de la 4ème de couverture' },
          { name: 'cover_color', type: 'text', description: 'Couleur principale de la couverture' },
          { name: 'series_name', type: 'text', description: 'Nom de la série' }
        );
      } else if (templateType === 'layout') {
        commonVariables.push(
          { name: 'chapter_title', type: 'text', description: 'Titre du chapitre' },
          { name: 'section_title', type: 'text', description: 'Titre de la section' },
          { name: 'page_number', type: 'number', description: 'Numéro de page' },
          { name: 'total_pages', type: 'number', description: 'Nombre total de pages' },
          { name: 'chapter_number', type: 'number', description: 'Numéro du chapitre' },
          { name: 'section_number', type: 'number', description: 'Numéro de la section' }
        );
      } else if (templateType === 'impose') {
        commonVariables.push(
          { name: 'sheet_number', type: 'number', description: 'Numéro de feuille' },
          { name: 'total_sheets', type: 'number', description: 'Nombre total de feuilles' },
          { name: 'imposition_type', type: 'text', description: 'Type d\'imposition' }
        );
      }

      setVariables(commonVariables);
      setFilteredVariables(commonVariables);
    };

    loadVariables();
  }, [templateType]);

  // Charger les polices disponibles
  useEffect(() => {
    const loadFonts = async () => {
      if (!user) return;

      setLoading(true);
      try {
        // Polices système (build-in)
        const systemFonts: Font[] = [
          { name: 'Times New Roman', type: 'system', format: 'Serif' },
          { name: 'Arial', type: 'system', format: 'Sans-serif' },
          { name: 'Courier New', type: 'system', format: 'Monospace' },
          { name: 'Georgia', type: 'system', format: 'Serif' },
          { name: 'Verdana', type: 'system', format: 'Sans-serif' },
          { name: 'Palatino', type: 'system', format: 'Serif' },
          { name: 'Garamond', type: 'system', format: 'Serif' },
          { name: 'Bookman', type: 'system', format: 'Serif' },
        ];

        // Polices utilisateur (si disponibles)
        let userFonts: Font[] = [];
        try {
          const token = localStorage.getItem('auth_token');
          if (!token) {
            console.warn('Token d\'authentification manquant pour les polices');
            return;
          }
          
          const response = await fetch(`/api/fonts/user`, {
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json'
            }
          });
          if (response.ok) {
            const data = await response.json();
            userFonts = (data.fonts || []).map((font: any) => ({
              name: font.name,
              type: 'user' as const,
              format: font.format || 'Unknown'
            }));
          }
        } catch (err) {
          // Ignorer les erreurs pour les polices utilisateur
        }

        const allFonts = [...systemFonts, ...userFonts];
        setFonts(allFonts);
        setFilteredFonts(allFonts);
      } catch (err) {
        console.error('Erreur lors du chargement des polices:', err);
      } finally {
        setLoading(false);
      }
    };

    loadFonts();
  }, [user]);

  // Filtrer les variables et polices selon la recherche
  useEffect(() => {
    if (searchTerm.trim() === '') {
      setFilteredVariables(variables);
      setFilteredFonts(fonts);
    } else {
      const lowerSearch = searchTerm.toLowerCase();
      setFilteredVariables(
        variables.filter(v => 
          v.name.toLowerCase().includes(lowerSearch) ||
          v.description.toLowerCase().includes(lowerSearch)
        )
      );
      setFilteredFonts(
        fonts.filter(f => 
          f.name.toLowerCase().includes(lowerSearch) ||
          f.format.toLowerCase().includes(lowerSearch)
        )
      );
    }
  }, [searchTerm, variables, fonts]);

  // Insérer une variable dans l'éditeur
  const handleInsertVariable = (variableName: string) => {
    if (onInsertVariable) {
      onInsertVariable(`$${variableName}`);
    }
  };

  if (!isVisible) {
    return (
      <button
        onClick={onToggleVisibility}
        className="fixed left-4 top-1/2 transform -translate-y-1/2 bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-r-lg shadow-lg transition-all z-50"
        title={t('show_sidebar')}
      >
        <FaEye />
      </button>
    );
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 h-full overflow-hidden flex flex-col">
      {/* En-tête avec bouton de fermeture */}
      <div className="flex items-center justify-between mb-4 flex-shrink-0">
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          {t('available_variables')}
        </h3>
        <button
          onClick={onToggleVisibility}
          className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          title={t('hide_sidebar')}
        >
          <FaEyeSlash />
        </button>
      </div>

      {/* Barre de recherche */}
      <div className="relative mb-4 flex-shrink-0">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <FaSearch className="h-4 w-4 text-gray-400" />
        </div>
        <input
          type="text"
          placeholder={t('search_variables_fonts')}
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        />
      </div>

      {/* Onglets */}
      <div className="flex space-x-1 mb-4 flex-shrink-0">
        <button
          onClick={() => setActiveTab('variables')}
          className={`flex-1 px-3 py-2 text-sm rounded-md transition-colors ${
            activeTab === 'variables'
              ? 'bg-blue-500 text-white'
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          }`}
        >
          <FaCode className="inline mr-2" />
          {t('variables')} ({filteredVariables.length})
        </button>
        <button
          onClick={() => setActiveTab('fonts')}
          className={`flex-1 px-3 py-2 text-sm rounded-md transition-colors ${
            activeTab === 'fonts'
              ? 'bg-blue-500 text-white'
              : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
          }`}
        >
          <FaFont className="inline mr-2" />
          {t('fonts')} ({filteredFonts.length})
        </button>
      </div>

      {/* Contenu des onglets avec scroll */}
      <div className="flex-1 overflow-y-auto min-h-0">
        <div className="space-y-4">
          {activeTab === 'variables' ? (
            /* Onglet Variables */
            <div>
              <h4 className="font-medium text-gray-900 dark:text-white mb-3">
                {t('available_variables')} ({templateType})
              </h4>
              {filteredVariables.length === 0 ? (
                <div className="text-center py-4 text-gray-500 dark:text-gray-400">
                  <p>{t('no_variables_found')}</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {filteredVariables.map((variable) => (
                    <div
                      key={variable.name}
                      className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                    >
                      <div className="flex items-center justify-between mb-2">
                        <span className="font-mono text-sm text-blue-600 dark:text-blue-400">
                          \${variable.name}
                        </span>
                        <button
                          onClick={() => handleInsertVariable(variable.name)}
                          className="p-1 text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                          title={t('insert_variable')}
                        >
                          <FaPlus size={12} />
                        </button>
                      </div>
                      <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                        {variable.description}
                      </p>
                      <div className="flex items-center justify-between">
                        <span className={`px-2 py-1 text-xs rounded-full ${
                          variable.type === 'text' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                          variable.type === 'image' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' :
                          'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
                        }`}>
                          {variable.type}
                        </span>
                        {variable.example && (
                          <span className="text-xs text-gray-500 dark:text-gray-500 font-mono">
                            Ex: {variable.example}
                          </span>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ) : (
            /* Onglet Polices */
            <div>
              <h4 className="font-medium text-gray-900 dark:text-white mb-3">
                {t('available_fonts')}
              </h4>
              {loading ? (
                <div className="text-center py-4">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mx-auto"></div>
                </div>
              ) : filteredFonts.length === 0 ? (
                <div className="text-center py-4 text-gray-500 dark:text-gray-400">
                  <p>{t('no_fonts_found')}</p>
                </div>
              ) : (
                <div className="space-y-3">
                  {filteredFonts.map((font, index) => (
                    <div
                      key={index}
                      className="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                    >
                      <div className="flex items-center justify-between mb-2">
                        <span className="font-medium text-gray-900 dark:text-white">
                          {font.name}
                        </span>
                        <span className={`px-2 py-1 text-xs rounded-full ${
                          font.type === 'system' 
                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                        }`}>
                          {font.type === 'system' ? t('system_fonts') : t('user_fonts')}
                        </span>
                      </div>
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        {font.format}
                      </p>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default EditorSidebar;
