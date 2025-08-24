import { useState, useEffect } from 'react';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useRouter } from 'next/router';
import Link from 'next/link';
import axios from 'axios';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import { Template } from '../types/template';
import AccordionSection from '../components/AccordionSection';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import { fetchUserTemplates, cleanupCorruptedTemplates, cleanupCorruptedFonts } from '../services/templatesService';

export default function Account() {
  const { t } = useTranslation('translation');
  const router = useRouter();
  const { isAuthenticated, user, logout, loading: authLoading } = useAuth();
  const { isDark } = useTheme();

  const [userTemplates, setUserTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [uploadType, setUploadType] = useState<'layout' | 'cover' | 'impose'>('layout');
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadPreviewFile, setUploadPreviewFile] = useState<File | null>(null);
  const [uploadName, setUploadName] = useState('');
  
  // État pour l'upload de polices
  const [fontFile, setFontFile] = useState<File | null>(null);
  const [fontSuccess, setFontSuccess] = useState<string | null>(null);
  const [fontError, setFontError] = useState<string | null>(null);
  const [fontLoading, setFontLoading] = useState(false);
  const [userFonts, setUserFonts] = useState<string[]>([]);

  // Rediriger si non authentifié
  useEffect(() => {
    if (!authLoading && !isAuthenticated) {
      router.push('/login');
    }
  }, [isAuthenticated, authLoading, router]);

  // Charger les templates de l'utilisateur
  useEffect(() => {
    if (isAuthenticated && !authLoading) {
      fetchUserTemplates();
      fetchUserFonts();
    }
  }, [isAuthenticated, authLoading]);

  const fetchUserTemplates = async () => {
    try {
      setLoading(true);
      setError(null);
      
      // Utiliser le service qui gère les erreurs 500
      const templates = await import('../services/templatesService').then(
        module => module.fetchUserTemplates()
      );
      
      setUserTemplates(templates);
    } catch (err) {
      console.error('Erreur lors du chargement des templates:', err);
      setError(t('error_loading_templates'));
    } finally {
      setLoading(false);
    }
  };
  
  // Récupérer les polices de l'utilisateur
  const fetchUserFonts = async () => {
    try {
      const response = await axios.get('/api/fonts/user');
      setUserFonts(response.data.fonts || []);
    } catch (err) {
      console.error('Erreur lors du chargement des polices:', err);
      setFontError('Erreur lors du chargement des polices');
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setUploadFile(file);
      
      // Extraire le nom et le type du fichier
      const filename = file.name;
      
      // Pattern pour formats layout: [nom]-[format]-layout.tex
      const layoutPattern = /^(.+?)-(.+?)-layout\.tex$/i;
      
      // Pattern pour formats cover: [nom]-[format]-cover-[format_papier].tex
      const coverPattern = /^(.+?)-(.+?)-cover-(.+?)\.tex$/i;
      
      // Pattern pour formats impose: [format]-[format_papier]-[nb][type].tex
      const imposePattern = /^(.+?)-(.+?)-(\d+)(signature|spread)\.tex$/i;
      
      let extractedName = '';
      let extractedType: 'layout' | 'cover' | 'impose' = 'layout';
      
      // Vérifier le pattern de layout
      const layoutMatch = filename.match(layoutPattern);
      if (layoutMatch) {
        // Conserver le nom complet sans l'extension
        extractedName = filename.replace(/\.tex$/i, '');
        extractedType = 'layout';
        
        console.log("Nom extrait pour layout:", extractedName);
        setUploadName(extractedName);
        setUploadType(extractedType);
        return;
      }
      
      // Vérifier le pattern de cover
      const coverMatch = filename.match(coverPattern);
      if (coverMatch) {
        // Conserver le nom complet sans l'extension
        extractedName = filename.replace(/\.tex$/i, '');
        extractedType = 'cover';
        
        console.log("Nom extrait pour cover:", extractedName);
        setUploadName(extractedName);
        setUploadType(extractedType);
        return;
      }
      
      // Vérifier le pattern d'imposition
      const imposeMatch = filename.match(imposePattern);
      if (imposeMatch) {
        // Conserver le nom complet sans l'extension
        extractedName = filename.replace(/\.tex$/i, '');
        extractedType = 'impose';
        
        console.log("Nom extrait pour impose:", extractedName);
        setUploadName(extractedName);
        setUploadType(extractedType);
        return;
      }
      
      // Si aucun pattern ne correspond, utiliser le nom du fichier sans extension
      setUploadName(filename.replace(/\.tex$/i, ''));
    }
  };
  
  const handlePreviewFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setUploadPreviewFile(e.target.files[0]);
    }
  };

  // Gérer le changement de fichier pour les polices
  const handleFontChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setFontFile(e.target.files[0]);
      setFontError(null); // Réinitialiser les erreurs
    }
  };

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!uploadFile) {
      setError(t('file_required'));
      return;
    }

    if (!uploadPreviewFile) {
      setError('Une prévisualisation est requise pour le template');
      return;
    }

    if (!uploadName.trim()) {
      setError('Le nom du template n\'a pas pu être extrait du nom du fichier. Veuillez suivre les conventions de nommage indiquées ci-dessus.');
      return;
    }
    
    if (!uploadType) {
      setError('Le type de template n\'a pas pu être déterminé. Veuillez suivre les conventions de nommage indiquées ci-dessus.');
      return;
    }

    try {
      setLoading(true);
      setError(null);
      
      const formData = new FormData();
      formData.append('file', uploadFile);
      formData.append('preview', uploadPreviewFile);
      formData.append('name', uploadName);
      formData.append('type', uploadType);
      
      await axios.post('/api/templates/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      setSuccessMessage(t('template_uploaded'));
      setUploadName('');
      setUploadFile(null);
      setUploadPreviewFile(null);
      // Recharger les templates
      fetchUserTemplates();
    } catch (err: any) {
      console.error('Erreur d\'upload:', err);
      setError(err.response?.data?.message || t('upload_error'));
    } finally {
      setLoading(false);
    }
  };
  
  // Upload de police
  const handleFontUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!fontFile) {
      setFontError('Veuillez sélectionner un fichier de police');
      return;
    }
    
    // Vérifier l'extension du fichier
    const validExtensions = ['.ttf', '.otf'];
    const extension = fontFile.name.substring(fontFile.name.lastIndexOf('.')).toLowerCase();
    if (!validExtensions.includes(extension)) {
      setFontError('Format de police non supporté. Utilisez .ttf ou .otf');
      return;
    }
    
    try {
      setFontLoading(true);
      setFontError(null);
      
      const formData = new FormData();
      formData.append('font', fontFile);
      
      await axios.post('/api/fonts/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      setFontSuccess(`Police ${fontFile.name} téléversée avec succès`);
      setFontFile(null);
      // Recharger les polices
      fetchUserFonts();
      
      // Réinitialiser le champ d'input file
      const fileInput = document.getElementById('fontFile') as HTMLInputElement;
      if (fileInput) fileInput.value = '';
      
    } catch (err: any) {
      console.error('Erreur d\'upload de police:', err);
      setFontError(err.response?.data?.message || 'Erreur lors du téléversement de la police');
    } finally {
      setFontLoading(false);
    }
  };

  const handleDelete = async (templateId: string) => {
    if (window.confirm(t('confirm_delete_template'))) {
      try {
        setLoading(true);
        await axios.delete(`/api/templates/${templateId}`);
        
        // Mettre à jour la liste
        setUserTemplates(prev => prev.filter(temp => temp.id !== templateId));
        setSuccessMessage(t('template_deleted'));
      } catch (err) {
        console.error('Erreur lors de la suppression:', err);
        setError(t('delete_error'));
      } finally {
        setLoading(false);
      }
    }
  };
  
  // Supprimer une police
  const handleDeleteFont = async (fontName: string) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer la police ${fontName} ?`)) {
      try {
        await axios.delete(`/api/fonts/${encodeURIComponent(fontName)}`);
        setFontSuccess(`Police ${fontName} supprimée avec succès`);
        // Mettre à jour la liste des polices
        setUserFonts(prev => prev.filter(font => font !== fontName));
      } catch (err) {
        console.error('Erreur lors de la suppression de la police:', err);
        setFontError('Erreur lors de la suppression de la police');
      }
    }
  };

  const handleCleanupTemplates = async () => {
    if (window.confirm(t('confirm_cleanup_templates'))) {
      try {
        setLoading(true);
        const result = await cleanupCorruptedTemplates();
        
        if (result.success) {
          setSuccessMessage(result.message);
          // Recharger les templates
          fetchUserTemplates();
        } else {
          setError(result.message);
        }
      } catch (err) {
        console.error('Erreur lors du nettoyage des templates:', err);
        setError(t('cleanup_error'));
      } finally {
        setLoading(false);
      }
    }
  };

  const handleCleanupFonts = async () => {
    if (window.confirm(t('confirm_cleanup_fonts'))) {
      try {
        setFontLoading(true);
        const result = await cleanupCorruptedFonts();
        
        if (result.success) {
          setFontSuccess(result.message);
          // Recharger les polices
          fetchUserFonts();
        } else {
          setFontError(result.message);
        }
      } catch (err) {
        console.error('Erreur lors du nettoyage des polices:', err);
        setFontError('Erreur lors du nettoyage des polices');
      } finally {
        setFontLoading(false);
      }
    }
  };

  const clearMessages = () => {
    setError(null);
    setSuccessMessage(null);
  };
  
  const clearFontMessages = () => {
    setFontError(null);
    setFontSuccess(null);
  };

  // Si la page est en cours de chargement ou l'utilisateur n'est pas authentifié, afficher un message de chargement
  if (authLoading || !isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
        <NavBar />
        <div className="text-center">
          {authLoading ? (
            <div className="animate-pulse text-lg text-gray-600 dark:text-gray-400">Chargement...</div>
          ) : (
            <div className="text-lg text-red-600 dark:text-red-400">
              Vous devez être connecté pour accéder à cette page.
              <div className="mt-4">
                <Link href="/login">
                  <button className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Se connecter
                  </button>
                </Link>
              </div>
            </div>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <NavBar pageTitle={t('user_profile')} />

      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          {user && (
            <div className="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6">
              <div className="px-4 py-5 sm:px-6">
                <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                  {t('profile_information')}
                </h3>
              </div>
              <div className="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:p-0">
                <dl className="sm:divide-y sm:divide-gray-200 dark:sm:divide-gray-700">
                  <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      {t('username')}
                    </dt>
                    <dd className="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                      {user.username}
                    </dd>
                  </div>
                  <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      {t('email')}
                    </dt>
                    <dd className="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                      {user.email}
                    </dd>
                  </div>
                  <div className="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      {t('joined_on')}
                    </dt>
                    <dd className="mt-1 text-sm text-gray-900 dark:text-white sm:mt-0 sm:col-span-2">
                      {new Date(user.createdAt).toLocaleDateString()}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          )}
          
          {/* Section pour uploader des polices */}
          <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
            <div className="px-4 py-5 sm:px-6">
              <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                {t('upload_fonts')}
              </h3>
              <p className="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                {t('upload_fonts_description')}
              </p>
            </div>
            <div className="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:p-6">
              {fontError && (
                <div className="mb-4 text-red-500 text-sm bg-red-50 dark:bg-red-900 p-3 rounded-md">
                  {fontError}
                  <button 
                    onClick={clearFontMessages} 
                    className="ml-2 text-red-700 dark:text-red-300"
                  >
                    ×
                  </button>
                </div>
              )}
              
              {fontSuccess && (
                <div className="mb-4 text-green-500 text-sm bg-green-50 dark:bg-green-900 p-3 rounded-md">
                  {fontSuccess}
                  <button 
                    onClick={clearFontMessages} 
                    className="ml-2 text-green-700 dark:text-green-300"
                  >
                    ×
                  </button>
                </div>
              )}
              
              {/* Infos sur les polices supportées */}
              <AccordionSection title={t('supported_font_formats')} defaultOpen={false} className="mb-4 bg-yellow-50 dark:bg-yellow-900 border-yellow-200 dark:border-yellow-700 text-sm text-yellow-700 dark:text-yellow-200">
                <div className="text-sm text-indigo-700 dark:text-indigo-200">
                  <ul className="list-disc list-inside space-y-1">
                    <li>TrueType (.ttf)</li>
                    <li>OpenType (.otf)</li>
                  </ul>
                  <p className="mt-2">
                    <strong>Note :</strong> {t('font_rights_notice')}
                  </p>
                </div>
              </AccordionSection>
              
              <form onSubmit={handleFontUpload} className="space-y-4">
                <div>
                  <label htmlFor="fontFile" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {t('font_file')}
                  </label>
                  <input
                    type="file"
                    id="fontFile"
                    accept=".ttf,.otf"
                    onChange={handleFontChange}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {t('font_file_help')}
                  </p>
                </div>
                
                <button
                  type="submit"
                  disabled={fontLoading}
                  className={`group relative w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white ${
                    fontLoading ? 'bg-purple-400 dark:bg-purple-700' : 'bg-purple-600 dark:bg-purple-800 hover:bg-purple-700 dark:hover:bg-purple-900'
                  } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500`}
                >
                  {fontLoading ? t('uploading_font') : t('upload_font')}
                </button>
              </form>
              
              {/* Liste des polices téléversées */}
              <div className="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                <div className="flex justify-between items-center mb-3">
                  <h4 className="text-base font-medium text-gray-900 dark:text-white">
                    {t('my_fonts')}
                  </h4>
                  <button
                    onClick={handleCleanupFonts}
                    disabled={fontLoading}
                    className="text-xs px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded hover:bg-yellow-200 dark:hover:bg-yellow-800"
                  >
                    {t('cleanup_corrupted')}
                  </button>
                </div>
                
                {userFonts.length > 0 ? (
                  <ul className="divide-y divide-gray-200 dark:divide-gray-700">
                    {userFonts.map(fontName => (
                      <li key={fontName} className="py-3 flex justify-between items-center">
                        <div className="flex items-center">
                          <span className="text-sm font-medium text-gray-900 dark:text-white">{fontName}</span>
                          <span className="ml-2 px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                            {fontName.endsWith('.ttf') ? t('font_type_truetype') : t('font_type_opentype')}
                          </span>
                        </div>
                        <button
                          onClick={() => handleDeleteFont(fontName)}
                          className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 text-sm"
                        >
                          {t('delete')}
                        </button>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-sm text-gray-500 dark:text-gray-400">{t('no_fonts')}</p>
                )}
              </div>
            </div>
          </div>

          {/* Section pour uploader un nouveau template */}
          <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
            <div className="px-4 py-5 sm:px-6">
              <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                {t('upload_new_template')}
              </h3>
              <p className="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                {t('upload_template_description')}
              </p>
            </div>
            <div className="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:p-6">
              {error && (
                <div className="mb-4 text-red-500 text-sm bg-red-50 dark:bg-red-900 p-3 rounded-md">
                  {error}
                  <button 
                    onClick={clearMessages} 
                    className="ml-2 text-red-700 dark:text-red-300"
                  >
                    ×
                  </button>
                </div>
              )}
              
              {successMessage && (
                <div className="mb-4 text-green-500 text-sm bg-green-50 dark:bg-green-900 p-3 rounded-md">
                  {successMessage}
                  <button 
                    onClick={clearMessages} 
                    className="ml-2 text-green-700 dark:text-green-300"
                  >
                    ×
                  </button>
                </div>
              )}
              
              {/* Upload d'image de prévisualisation */}
              <div className="mb-4">
                <label className="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300" htmlFor="preview">
                  {t('template_preview')}
                </label>
                <input
                  type="file"
                  id="preview"
                  accept="image/png"
                  onChange={handlePreviewFileChange}
                  className="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 text-gray-900 dark:text-white bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600"
                  disabled={loading}
                />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  {t('template_preview_help')}
                </p>
              </div>
              
              {/* Guide de nomenclature */}
              <AccordionSection title={t('filename_conventions')} defaultOpen={false} className="mb-4 bg-yellow-50 dark:bg-yellow-900 border-yellow-200 dark:border-yellow-700 text-sm text-yellow-700 dark:text-yellow-200">
                <div>
                  <div className="mb-3">
                    <p className="font-medium">{t('layouts_section')}</p>
                    <p className="ml-2">{t('layouts_format')} <code className="bg-yellow-100 dark:bg-yellow-800 px-1 rounded">[nom]-[format]-layout.tex</code></p>
                    <ul className="list-disc list-inside space-y-1 ml-4">
                      <li><code>[nom]</code> : {t('layouts_name')}</li>
                      <li><code>[format]</code> : {t('layouts_book_format')}</li>
                      <li><code>layout</code> : {t('layouts_suffix')}</li>
                    </ul>
                    <p className="ml-2 mt-1">{t('layouts_example')} <code className="bg-yellow-100 dark:bg-yellow-800 px-1 rounded">Garamond-brsnoba5-layout.tex</code></p>
                  </div>
                  
                  <div className="mb-3">
                    <p className="font-medium">{t('covers_section')}</p>
                    <p className="ml-2">{t('covers_format')} <code className="bg-yellow-100 dark:bg-yellow-800 px-1 rounded">[nom]-[format]-cover-[format_papier].tex</code></p>
                    <ul className="list-disc list-inside space-y-1 ml-4">
                      <li><code>[nom]</code> : {t('covers_name')}</li>
                      <li><code>[format]</code> : {t('covers_book_format')}</li>
                      <li><code>cover</code> : {t('covers_suffix')}</li>
                      <li><code>[format_papier]</code> : {t('covers_paper_format')}</li>
                    </ul>
                    <p className="ml-2 mt-1">{t('covers_example')} <code className="bg-yellow-100 dark:bg-yellow-800 px-1 rounded">Garamond-brsnoba5-cover-A3.tex</code></p>
                  </div>
                  
                  <div>
                    <p className="font-medium">{t('impositions_section')}</p>
                    <p className="ml-2">{t('impositions_format')} <code className="bg-yellow-100 dark:bg-yellow-800 px-1 rounded">[format]-[format_papier]-[nb][type].tex</code></p>
                    <ul className="list-disc list-inside space-y-1 ml-4">
                      <li><code>[format]</code> : {t('impositions_book_format')}</li>
                      <li><code>[format_papier]</code> : {t('impositions_paper_format')}</li>
                      <li><code>[nb]</code> : {t('impositions_pages')}</li>
                      <li><code>[type]</code> : {t('impositions_type')}</li>
                    </ul>
                    <p className="ml-2 mt-1">{t('impositions_example')} <code className="bg-yellow-100 dark:bg-yellow-800 px-1 rounded">brsnoba5-A4-4signature.tex</code></p>
                  </div>
                </div>
              </AccordionSection>
              
              {/* Guides des métadonnées complètes */}
              <AccordionSection title={t('complete_metadata_examples')} defaultOpen={false} className="mb-6 bg-blue-50 dark:bg-blue-900 border-blue-200 dark:border-blue-700 text-sm text-blue-700 dark:text-blue-200">
                <div>
                  <div className="mb-3">
                    <p className="font-medium">{t('layout_example_title')}</p>
                    <pre className="bg-blue-100 dark:bg-blue-800 p-2 rounded mt-1 overflow-x-auto">
{`%% META: format=A5, font=Garamond, columns=1, margins=20mm 15mm 20mm 15mm, linespacing=1.2
%% META: header=true, footer=true, pagenumbers=true
%% META: fontsize=11pt, languages=french,english
%% META: packages=microtype,lettrine,fancyhdr,graphicx

% Configuration de la police
\\setmainfont{Garamond Premier Pro}[
    Path = /usr/local/share/fonts/custom/,
    Extension = .otf,
    UprightFont = GaramondPremrPro,
    BoldFont = GaramondPremrPro-Bd,
    ItalicFont = GaramondPremrPro-It,
    BoldItalicFont = GaramondPremrPro-BdIt
]

% Votre code LaTeX ici...

% L'emplacement du contenu Markdown converti
%CONTENT%`}</pre>
                  </div>
                  
                  <div className="mb-3">
                    <p className="font-medium">{t('cover_example_title')}</p>
                    <pre className="bg-blue-100 dark:bg-blue-800 p-2 rounded mt-1 overflow-x-auto">
{`%% META: format=A5, font=Times, spine=7mm, bleed=3mm
%% META: pagecolor=0.9,0.9,0.9, textcolor=0,0,0
%% META: variables=title,author,publisher,year,isbn,subtitle

% Configuration de la police
\\setmainfont{Times New Roman}[
    Path = /usr/local/share/fonts/custom/,
    Extension = .ttf,
    UprightFont = times,
    BoldFont = timesbd,
    ItalicFont = timesi,
    BoldItalicFont = timesbi
]

% Design de couverture avec variables
\\begin{center}
    \\vspace*{2cm}
    {\\Large \\textbf{%PUBLISHER%}}\\\\[2cm]
    {\\huge \\textbf{%TITLE%}}\\\\[0.5cm]
    {\\Large %SUBTITLE%}\\\\[3cm]
    {\\Large %AUTHOR%}\\\\[2cm]
    {\\small ISBN: %ISBN%}
\\end{center}`}</pre>
                  </div>
                  
                  <div>
                    <p className="font-medium">{t('impose_example_title')}</p>
                    <pre className="bg-blue-100 dark:bg-blue-800 p-2 rounded mt-1 overflow-x-auto">
{`%% META: source=A5, target=A3, pages=4, signature=16
%% META: binding=perfect, duplex=true, booklet=true
%% META: bleed=3mm, creep=0.1mm, trim=true

% Configuration de l'imposition
\\documentclass[a3paper]{article}
\\usepackage{pdfpages}
\\usepackage{geometry}

\\begin{document}
% Signature 1
\\includepdf[pages=1-16, nup=2x2, signature=16, landscape, delta=5mm 5mm]{%SOURCE%}
% Autres signatures au besoin...
\\end{document}`}</pre>
                  </div>
                </div>
              </AccordionSection>
              
              <form onSubmit={handleUpload} className="space-y-4">
                <div>
                  <label htmlFor="uploadFile" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {t('template_file')} (.tex)
                  </label>
                  <input
                    type="file"
                    id="uploadFile"
                    accept=".tex"
                    onChange={handleFileChange}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  />
                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {t('auto_extract_name')}
                  </p>
                </div>
                
                <button
                  type="submit"
                  disabled={loading}
                  className={`group relative w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white ${
                    loading ? 'bg-indigo-400 dark:bg-indigo-700' : 'bg-indigo-600 dark:bg-indigo-800 hover:bg-indigo-700 dark:hover:bg-indigo-900'
                  } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`}
                >
                  {loading ? t('uploading') : t('upload_template')}
                </button>
              </form>
            </div>
          </div>

          {/* Liste des templates de l'utilisateur */}
          <div className="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
            <div className="px-4 py-5 sm:px-6">
              <div className="flex justify-between items-center">
                <h3 className="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                  {t('my_templates')}
                </h3>
                <button
                  onClick={handleCleanupTemplates}
                  disabled={loading}
                  className="text-xs px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded hover:bg-yellow-200 dark:hover:bg-yellow-800"
                >
                  {t('cleanup_corrupted')}
                </button>
              </div>
            </div>
            <div className="border-t border-gray-200 dark:border-gray-700">
              {loading ? (
                <p className="px-6 py-4 text-gray-500 dark:text-gray-400">{t('loading')}</p>
              ) : userTemplates.length > 0 ? (
                <div className="mt-8">
                  <h3 className="text-xl font-bold mb-4 text-gray-900 dark:text-white">{t('your_templates')}</h3>
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {userTemplates.map((template) => (
                      <div 
                        key={template.id} 
                        className="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
                      >
                        {template.previewPath && (
                          <div className="h-40 overflow-hidden bg-gray-100 dark:bg-gray-700">
                            <img 
                              src={`/serve-preview.php?path=${template.previewPath}`} 
                              alt={`Prévisualisation de ${template.name}`}
                              className="w-full h-full object-contain"
                            />
                          </div>
                        )}
                        <div className="p-4">
                          <h4 className="font-bold text-gray-900 dark:text-white">{template.name}</h4>
                          <p className="text-sm text-gray-700 dark:text-gray-300">{t('type')}: {template.type}</p>
                          <div className="mt-2 flex justify-end">
                            <button
                              onClick={() => handleDelete(template.id)}
                              className="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium"
                              disabled={loading}
                            >
                              {t('delete')}
                            </button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <p className="px-6 py-4 text-gray-500 dark:text-gray-400">{t('no_templates')}</p>
              )}
            </div>
          </div>
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