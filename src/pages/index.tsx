import { useCallback, useState, useEffect } from 'react';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useRouter } from 'next/router';
import { useTheme } from '../contexts/ThemeContext';
import { useAuth } from '../contexts/AuthContext';
import { useDocumentConversion } from '../hooks/useDocumentConversion';
import { useTemplates } from '../hooks/useTemplates';
import { useDocumentEditor } from '../hooks/useDocumentEditor';
import TemplateSelector from '../components/TemplateSelector';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import dynamic from 'next/dynamic';
import Link from 'next/link';
import Image from 'next/image';
import { FaHistory, FaSpinner, FaTimes, FaFileDownload, FaFilePdf, FaBook, FaFileAlt, FaUser, FaSignInAlt, FaCog, FaQuestionCircle } from 'react-icons/fa';

// Import TiptapEditor avec dynamic pour éviter les erreurs SSR
const TiptapEditor = dynamic(
  () => import('../components/TiptapEditor'),
  { ssr: false }
);

export default function Home() {
  // État pour le panneau d'historique
  const [isHistoryPanelOpen, setIsHistoryPanelOpen] = useState(false);
  
  // État pour la méthode de conversion
  const [conversionMethod, setConversionMethod] = useState('pandoc_direct');
  
  // Charger la méthode de conversion depuis localStorage
  useEffect(() => {
    const savedMethod = localStorage.getItem('conversionMethod');
    if (savedMethod) {
      setConversionMethod(savedMethod);
    }
  }, []);
  
  // Sauvegarder la méthode de conversion dans localStorage
  const handleConversionMethodChange = useCallback((method: string) => {
    setConversionMethod(method);
    localStorage.setItem('conversionMethod', method);
  }, []);
  
  // Utiliser les hooks personnalisés
  const { isDark, toggleTheme } = useTheme();
  const { isAuthenticated, user, logout } = useAuth();
  const { 
    layouts, 
    covers, 
    imposes, 
    invalidFiles, 
    templateOptions,
    setTemplateOptions 
  } = useTemplates();
  const {
    markdown,
    showTemplateOptions,
    setMarkdown,
    resetMarkdown,
    clearMarkdown,
    toggleTemplateOptions
  } = useDocumentEditor();
  const {
    isLoading,
    status,
    pdfUrl,
    errorMessage,
    documentHistory,
    convertDocument,
    compileCover,
    imposeDocument
  } = useDocumentConversion();

  const router = useRouter();
  const { t } = useTranslation('translation');

  // Ouvrir automatiquement le panneau d'historique quand un nouveau document est généré
  useEffect(() => {
    if (pdfUrl) {
      setIsHistoryPanelOpen(true);
    }
  }, [pdfUrl]);

  // Gérer le changement de langue
  const handleLanguageChange = useCallback(async (newLocale: string) => {
    const { pathname, asPath, query } = router;
    await router.push({ pathname, query }, asPath, { locale: newLocale });
  }, [router]);

  // Gestionnaire des options de template
  const handleTemplateOptionsChange = useCallback((options) => {
    setTemplateOptions(options);
  }, [setTemplateOptions]);

  // Gestionnaires de conversion
  const handleConvert = useCallback(async () => {
    if (!templateOptions.layout) {
      alert('Veuillez sélectionner une mise en page');
      return;
    }
    
    await convertDocument(markdown, templateOptions, conversionMethod);
  }, [markdown, templateOptions, conversionMethod, convertDocument]);

  const handleCompileCover = useCallback(async () => {
    if (!templateOptions.cover) {
      alert('Veuillez sélectionner une couverture');
      return;
    }
    
    await compileCover(markdown, templateOptions, conversionMethod);
  }, [markdown, templateOptions, conversionMethod, compileCover]);

  const handleImpose = useCallback(async () => {
    if (!templateOptions.layout || !templateOptions.impose) {
      alert('Veuillez sélectionner une mise en page et une imposition');
      return;
    }
    
    await imposeDocument(markdown, templateOptions, conversionMethod);
  }, [markdown, templateOptions, conversionMethod, imposeDocument]);

  // Animation pour les boutons
  const getButtonContent = (isLoading: boolean, action: string, icon: JSX.Element) => {
    if (isLoading) {
      return (
        <span className="flex items-center">
          <FaSpinner className="animate-spin mr-2" />
          {t('conversion')}
        </span>
      );
    }
    return (
      <span className="flex items-center">
        {icon}
        <span className="ml-2">{t(action)}</span>
      </span>
    );
  };

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <NavBar />

      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0 relative flex">
          {/* Contenu principal */}
          <div className={`flex-1 ${isHistoryPanelOpen ? 'mr-80' : ''}`}>
            {showTemplateOptions && (
              <TemplateSelector 
                onChange={handleTemplateOptionsChange}
                layouts={layouts}
                covers={covers}
                imposes={imposes}
                invalidFiles={invalidFiles}
              />
            )}
            
            {/* Boutons de génération au-dessus de l'éditeur */}
            <div className="bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-4 flex flex-wrap gap-3 items-center">
              <button
                onClick={toggleTemplateOptions}
                className={`inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm transition-all duration-300 ${
                  showTemplateOptions 
                    ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 border-indigo-300 dark:border-indigo-700' 
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 border-gray-300 dark:border-gray-600'
                }`}
                title={showTemplateOptions ? t('hide_options') : t('show_options')}
              >
                <FaCog className="mr-2" />
                <span>{showTemplateOptions ? t('hide_options') : t('show_options')}</span>
              </button>
              
              {/* Sélecteur de méthode de conversion */}
              <div className="flex items-center space-x-2">
                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                  {t('conversion_method')}:
                </label>
                <select
                  value={conversionMethod}
                  onChange={(e) => handleConversionMethodChange(e.target.value)}
                  className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="pandoc_direct">{t('pandoc_direct')}</option>
                  <option value="obsidian_export">{t('obsidian_export')}</option>
                </select>
              </div>
              
              <button
                onClick={handleConvert}
                disabled={isLoading || !markdown.trim() || !templateOptions?.layout}
                className={`inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white transition-all duration-300 ${
                  isLoading || !markdown.trim() || !templateOptions?.layout
                    ? 'bg-indigo-300 dark:bg-indigo-700 cursor-not-allowed' 
                    : 'bg-indigo-600 dark:bg-indigo-800 hover:bg-indigo-700 dark:hover:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transform hover:scale-105'
                }`}
              >
                {getButtonContent(isLoading, 'convert', <FaBook />)}
              </button>
              
              <button
                onClick={handleCompileCover}
                disabled={isLoading || !markdown.trim() || !templateOptions?.cover}
                className={`inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white transition-all duration-300 ${
                  isLoading || !markdown.trim() || !templateOptions?.cover
                    ? 'bg-green-300 dark:bg-green-700 cursor-not-allowed' 
                    : 'bg-green-600 dark:bg-green-800 hover:bg-green-700 dark:hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transform hover:scale-105'
                }`}
              >
                {getButtonContent(isLoading, 'compile_cover', <FaFileAlt />)}
              </button>
              
              <button
                onClick={handleImpose}
                disabled={isLoading || !markdown.trim() || !templateOptions?.layout || !templateOptions?.impose}
                className={`inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white transition-all duration-300 ${
                  isLoading || !markdown.trim() || !templateOptions?.layout || !templateOptions?.impose
                    ? 'bg-purple-300 dark:bg-purple-700 cursor-not-allowed' 
                    : 'bg-purple-600 dark:bg-purple-800 hover:bg-purple-700 dark:hover:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transform hover:scale-105'
                }`}
              >
                {getButtonContent(isLoading, 'impose', <FaFilePdf />)}
              </button>
              
              {status && (
                <span className={`text-sm flex-grow text-center my-auto ${errorMessage ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'}`}>
                  {errorMessage ? t('conversion_error') : status}
                </span>
              )}
              
              {pdfUrl && (
                <a
                  href={pdfUrl}
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-indigo-700 dark:text-indigo-200 bg-indigo-100 dark:bg-indigo-900 hover:bg-indigo-200 dark:hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transform hover:scale-105 transition-all duration-300"
                  onClick={(e) => {
                    e.preventDefault();
                    // Générer le nom de fichier pour le téléchargement
                    if (documentHistory.length > 0) {
                      const latestDoc = documentHistory[0];
                      // Forcer le téléchargement avec le nom de fichier généré
                      fetch(pdfUrl)
                        .then(response => response.blob())
                        .then(blob => {
                          const url = window.URL.createObjectURL(blob);
                          const link = document.createElement('a');
                          link.href = url;
                          link.download = latestDoc.filename + '.pdf';
                          document.body.appendChild(link);
                          link.click();
                          document.body.removeChild(link);
                          window.URL.revokeObjectURL(url);
                        })
                        .catch(error => {
                          console.error('Erreur lors du téléchargement:', error);
                          // Fallback : téléchargement direct
                          window.open(pdfUrl, '_blank');
                        });
                    } else {
                      window.location.href = pdfUrl;
                    }
                  }}
                >
                  <FaFileDownload className="mr-2" />
                  {t('download_pdf')}
                </a>
              )}

              <div className="ml-auto">
                <button
                  onClick={() => setIsHistoryPanelOpen(!isHistoryPanelOpen)}
                  className={`inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm transition-all duration-300 ${
                    isHistoryPanelOpen
                      ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200 border-blue-300 dark:border-blue-700' 
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 border-gray-300 dark:border-gray-600'
                  } ${documentHistory.length > 0 ? 'relative' : ''}`}
                  aria-label="Historique des documents"
                  title={t('document_history')}
                >
                  <FaHistory className="mr-2" />
                  <span>{t('history')}</span>
                  {documentHistory.length > 0 && (
                    <span className="absolute -top-2 -right-2 flex items-center justify-center bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5">
                      {documentHistory.length}
                    </span>
                  )}
                </button>
              </div>
            </div>
            
            {errorMessage && (
              <div className="bg-red-50 dark:bg-red-900 p-3 rounded-md border border-red-200 dark:border-red-700 mb-4">
                <p className="text-sm text-red-600 dark:text-red-400">
                  {errorMessage}
                </p>
              </div>
            )}
            
            <div className="border-4 border-dashed border-gray-200 dark:border-gray-700 rounded-lg h-[550px] flex flex-col">
              <div className="h-full">
                <TiptapEditor
                  content={markdown}
                  onUpdate={setMarkdown}
                  isDark={isDark}
                />
              </div>
            </div>
          </div>
          
          {/* Panneau d'historique latéral */}
          {isHistoryPanelOpen && (
            <div className="fixed right-0 top-0 bottom-0 w-80 bg-white dark:bg-gray-800 shadow-lg pt-20 transition-all duration-300 transform translate-x-0 z-10">
              <div className="p-4 h-full flex flex-col">
                <div className="flex justify-between items-center mb-4">
                  <h2 className="text-lg font-semibold text-gray-700 dark:text-gray-200">
                    <FaHistory className="inline mr-2" />
                    {t('recent_documents')}
                  </h2>
                  <button 
                    onClick={() => setIsHistoryPanelOpen(false)}
                    className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                  >
                    <FaTimes size={20} />
                  </button>
                </div>
                
                <div className="flex-grow overflow-y-auto">
                  {documentHistory.length > 0 ? (
                    <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                      {documentHistory.map((doc, index) => (
                        <li key={doc.id} className="py-3 hover:bg-gray-50 dark:hover:bg-gray-700 px-2 rounded">
                          <a
                            href={doc.url}
                            className="flex items-center text-indigo-600 dark:text-indigo-300 hover:text-indigo-800 dark:hover:text-indigo-400"
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(e) => {
                              e.preventDefault();
                              // Télécharger avec le nom de fichier généré
                              fetch(doc.url)
                                .then(response => response.blob())
                                .then(blob => {
                                  const url = window.URL.createObjectURL(blob);
                                  const link = document.createElement('a');
                                  link.href = url;
                                  link.download = doc.filename + '.pdf';
                                  document.body.appendChild(link);
                                  link.click();
                                  document.body.removeChild(link);
                                  window.URL.revokeObjectURL(url);
                                })
                                .catch(error => {
                                  console.error('Erreur lors du téléchargement:', error);
                                  // Fallback : téléchargement direct
                                  window.open(doc.url, '_blank');
                                });
                            }}
                          >
                            <FaFilePdf className="mr-2" />
                            <div className="flex-1 min-w-0">
                              <div className="text-sm font-medium truncate">
                                {doc.displayName}
                              </div>
                              <div className="text-xs text-gray-500 dark:text-gray-400 truncate">
                                ID: {doc.id}
                              </div>
                            </div>
                          </a>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <div className="text-gray-500 dark:text-gray-400 text-center mt-10">
                      <p>Aucun document récent</p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      </main>
      
      <Footer />
    </div>
  );
}

export async function getStaticProps({ locale }: { locale: string }) {
  return {
    props: {
      ...(await serverSideTranslations(locale, ['translation'])),
    },
  };
} 