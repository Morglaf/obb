import React, { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useAuth } from '../contexts/AuthContext';
import { useRouter } from 'next/router';
import Head from 'next/head';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';
import TemplateList from '../components/TemplateList';
import EditorSidebar from '../components/EditorSidebar';
import LaTeXEditor from '../components/LaTeXEditor';

interface Template {
  id: number;
  name: string;
  type: 'layout' | 'cover' | 'impose';
  file_path: string;
  preview_path?: string;
  created_at: string;
}

export default function Editor() {
  const { t } = useTranslation('translation');
  const { isAuthenticated, user } = useAuth();
  const router = useRouter();
  
  // États de l'éditeur
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const [editorContent, setEditorContent] = useState<string>('');
  const [templateType, setTemplateType] = useState<'layout' | 'cover' | 'impose'>('layout');
  const [templateName, setTemplateName] = useState<string>('');
  const [showSidebar, setShowSidebar] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [isNewTemplate, setIsNewTemplate] = useState(false);
  const [loading, setLoading] = useState(false);

  // Rediriger si non authentifié
  useEffect(() => {
    if (!isAuthenticated) {
      router.push('/login');
    }
  }, [isAuthenticated, router]);

  if (!isAuthenticated) {
    return null;
  }

  // Charger le contenu d'un template
  const loadTemplateContent = async (template: Template) => {
    try {
      setLoading(true);
      
      // Récupérer le token d'authentification
      const token = localStorage.getItem('auth_token');
      if (!token) {
        alert('Token d\'authentification manquant');
        return;
      }
      
      const response = await fetch(`/api/templates/content/${template.id}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const data = await response.json();
        // Le contenu est dans data.template.content
        setEditorContent(data.template.content);
        setSelectedTemplate(template);
        setTemplateType(template.type);
        setTemplateName(template.name);
        setIsEditing(true);
        setIsNewTemplate(false);
      } else {
        const errorData = await response.json().catch(() => ({}));
        console.error('Erreur API:', response.status, errorData);
        alert(`Erreur ${response.status}: ${errorData.message || 'Erreur lors du chargement du template'}`);
      }
    } catch (err) {
      console.error('Erreur lors du chargement du template:', err);
      alert('Erreur lors du chargement du template');
    } finally {
      setLoading(false);
    }
  };

  // Sauvegarder le template
  const saveTemplate = async () => {
    if (!isEditing) return;

    try {
      if (isNewTemplate) {
        // Créer un nouveau template
        const response = await fetch('/api/templates', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          },
          body: JSON.stringify({
            name: templateName,
            type: templateType,
            content: editorContent
          })
        });

        if (response.ok) {
          const newTemplate = await response.json();
          setSelectedTemplate(newTemplate.template);
          setIsNewTemplate(false);
          alert('Template créé avec succès !');
        } else {
          const errorData = await response.json().catch(() => ({}));
          console.error('Erreur création template:', response.status, errorData);
          alert('Erreur lors de la création du template');
        }
      } else if (selectedTemplate) {
        // Mettre à jour un template existant
        const response = await fetch(`/api/templates/${selectedTemplate.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
          },
          body: JSON.stringify({
            content: editorContent
          })
        });

        if (response.ok) {
          console.log('Template sauvegardé');
        } else {
          const errorData = await response.json().catch(() => ({}));
          console.error('Erreur sauvegarde template:', response.status, errorData);
          alert('Erreur lors de la sauvegarde');
        }
      }
    } catch (err) {
      console.error('Erreur lors de la sauvegarde:', err);
      alert('Erreur lors de la sauvegarde');
    }
  };

  // Créer un nouveau template
  const handleNewTemplate = () => {
    setSelectedTemplate(null);
    setEditorContent(`% Nouveau template ${templateType}
% Utilisez les variables disponibles comme $title, $author, etc.

\\documentclass[12pt]{article}
\\usepackage[utf8]{inputenc}
\\usepackage{geometry}

\\title{$title}
\\author{$author}
\\date{$date}

\\begin{document}

% Votre contenu ici...

\\end{document}`);
    setTemplateName(`Nouveau-${templateType}`);
    setIsEditing(true);
    setIsNewTemplate(true);
  };

  // Sélectionner un template existant
  const handleSelectTemplate = (template: Template) => {
    loadTemplateContent(template);
  };

  // Insérer une variable dans l'éditeur
  const handleInsertVariable = useCallback((variable: string) => {
    setEditorContent(prev => {
      // Insérer la variable à la position actuelle ou à la fin
      return prev + variable;
    });
  }, []);

  // Changer le type de template
  const handleTemplateTypeChange = (newType: 'layout' | 'cover' | 'impose') => {
    setTemplateType(newType);
    if (isNewTemplate) {
      // Mettre à jour le contenu par défaut selon le nouveau type
      setEditorContent(`% Nouveau template ${newType}
% Utilisez les variables disponibles comme $title, $author, etc.

\\documentclass[12pt]{article}
\\usepackage[utf8]{inputenc}
\\usepackage{geometry}

\\title{$title}
\\author{$author}
\\date{$date}

\\begin{document}

% Votre contenu ici...

\\end{document}`);
      setTemplateName(`Nouveau-${newType}`);
    }
  };

  return (
    <>
      <Head>
        <title>{t('editor')} - Online Book Brew</title>
        <meta name="description" content={t('editor_description')} />
      </Head>

      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
        <NavBar pageTitle={t('editor')} />
        
        <div className="flex-1 container mx-auto px-4 py-8">
          <div className="mb-6">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
              {t('editor')}
            </h1>
            <p className="text-gray-600 dark:text-gray-400 mt-2">
              {t('editor_description')}
            </p>
          </div>

          {/* Sélecteur de type de template pour nouveaux templates */}
          {isNewTemplate && (
            <div className="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
              <h3 className="text-lg font-medium text-blue-800 dark:text-blue-200 mb-3">
                {t('template_type_selection')}
              </h3>
              <div className="flex space-x-3">
                {(['layout', 'cover', 'impose'] as const).map((type) => (
                  <button
                    key={type}
                    onClick={() => handleTemplateTypeChange(type)}
                    className={`px-4 py-2 rounded-md transition-colors ${
                      templateType === type
                        ? 'bg-blue-500 text-white'
                        : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                    }`}
                  >
                    {t(type)}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Interface de l'éditeur - 2 colonnes */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[calc(100vh-350px)]">
            {/* Colonne gauche (1/3) - Liste des templates + Sidebar */}
            <div className="lg:col-span-1 space-y-4">
              {/* Liste des templates */}
              <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
                <TemplateList
                  onSelectTemplate={handleSelectTemplate}
                  onNewTemplate={handleNewTemplate}
                />
              </div>

              {/* Sidebar switchable */}
              {showSidebar && (
                <div className="max-h-[calc(100vh-500px)] overflow-y-auto">
                  <EditorSidebar
                    templateType={templateType}
                    isVisible={showSidebar}
                    onToggleVisibility={() => setShowSidebar(false)}
                    onInsertVariable={handleInsertVariable}
                  />
                </div>
              )}
            </div>

            {/* Colonne droite (2/3) - Éditeur LaTeX */}
            <div className="lg:col-span-2">
              <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 h-full">
                {loading ? (
                  <div className="text-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-4"></div>
                    <p className="text-gray-500 dark:text-gray-400">{t('loading')}</p>
                  </div>
                ) : isEditing ? (
                  <LaTeXEditor
                    content={editorContent}
                    onChange={setEditorContent}
                    onSave={saveTemplate}
                    templateType={templateType}
                    templateName={templateName}
                  />
                ) : (
                  <div className="text-center py-8 text-gray-500 dark:text-gray-400">
                    <p>{t('select_template_to_edit')}</p>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Bouton toggle sidebar */}
          {!showSidebar && (
            <button
              onClick={() => setShowSidebar(true)}
              className="fixed left-4 top-1/2 transform -translate-y-1/2 bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-r-lg shadow-lg transition-all z-50"
              title={t('show_sidebar')}
            >
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </button>
          )}
        </div>

        <Footer />
      </div>
    </>
  );
}

export async function getStaticProps({ locale }: { locale: string }) {
  return {
    props: {
      ...(await serverSideTranslations(locale, ['translation'])),
    },
  };
}
