import { useState, useEffect } from 'react';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useRouter } from 'next/router';
import Link from 'next/link';
import axios from 'axios';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import AccordionSection from '../components/AccordionSection';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';

// Type pour les utilisateurs
interface UserData {
  id: number;
  username: string;
  email: string;
  createdAt: string;
}

// Type pour les logs
interface LogEntry {
  timestamp: string;
  level: string;
  message: string;
}

// Type pour les commentaires
interface UserComment {
  id: number;
  userId: number;
  username: string;
  content: string;
  createdAt: string;
}

// Type pour les fichiers système
interface SystemFile {
  name: string;
  path: string;
  size: number;
  modified: string;
}

// Type pour les fichiers de cache
interface CacheFile {
  type: string;
  name: string;
  path: string;
  relativePath: string;
  size: number;
  modified: string;
  age: number;
  fileCount?: number; // Optionnel, seulement pour les dossiers
}

export default function AdminPage() {
  const { t } = useTranslation('translation');
  const router = useRouter();
  const { isAuthenticated, user, loading: authLoading } = useAuth();
  const { isDark } = useTheme();

  // États pour les différentes fonctionnalités
  const [users, setUsers] = useState<UserData[]>([]);
  const [logs, setLogs] = useState<LogEntry[]>([]);
  const [comments, setComments] = useState<UserComment[]>([]);
  const [activeTab, setActiveTab] = useState<'users' | 'templates' | 'logs' | 'comments' | 'cache'>('users');
  
  // États pour les templates et polices système
  const [systemTemplates, setSystemTemplates] = useState<{
    layouts: SystemFile[];
    covers: SystemFile[];
    imposes: SystemFile[];
  }>({ layouts: [], covers: [], imposes: [] });
  const [systemFonts, setSystemFonts] = useState<SystemFile[]>([]);
  
  // État pour les fichiers de cache
  const [cacheFiles, setCacheFiles] = useState<CacheFile[]>([]);
  
  // États pour l'upload de templates
  const [uploadType, setUploadType] = useState<'layout' | 'cover' | 'impose'>('layout');
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadPreviewFile, setUploadPreviewFile] = useState<File | null>(null);
  const [uploadName, setUploadName] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Rediriger si non authentifié ou non admin
  useEffect(() => {
    if (!authLoading) {
      if (!isAuthenticated) {
        router.push('/login');
      } else if (user && !user.isAdmin) {
        router.push('/');
      }
    }
  }, [isAuthenticated, user, authLoading, router]);

  // Charger les données selon l'onglet actif
  useEffect(() => {
    if (isAuthenticated && user?.isAdmin && !authLoading) {
      if (activeTab === 'users') {
        fetchUsers();
      } else if (activeTab === 'logs') {
        fetchLogs();
      } else if (activeTab === 'comments') {
        fetchComments();
      } else if (activeTab === 'templates') {
        fetchSystemTemplates();
        fetchSystemFonts();
      } else if (activeTab === 'cache') {
        fetchCacheFiles();
      }
    }
  }, [isAuthenticated, user, authLoading, activeTab]);

  // Récupérer la liste des utilisateurs
  const fetchUsers = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/users');
      setUsers(response.data.users);
    } catch (err) {
      console.error('Erreur lors du chargement des utilisateurs:', err);
      setError('Erreur lors du chargement des utilisateurs');
    } finally {
      setLoading(false);
    }
  };

  // Récupérer les logs
  const fetchLogs = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/logs');
      setLogs(response.data.logs);
    } catch (err) {
      console.error('Erreur lors du chargement des logs:', err);
      setError('Erreur lors du chargement des logs');
    } finally {
      setLoading(false);
    }
  };

  // Récupérer les commentaires
  const fetchComments = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/comments');
      setComments(response.data.comments);
    } catch (err) {
      console.error('Erreur lors du chargement des commentaires:', err);
      setError('Erreur lors du chargement des commentaires');
    } finally {
      setLoading(false);
    }
  };

  // Récupérer les templates systèmes
  const fetchSystemTemplates = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/system-templates');
      setSystemTemplates(response.data.templates);
    } catch (err) {
      console.error('Erreur lors du chargement des templates système:', err);
      setError('Erreur lors du chargement des templates système');
    } finally {
      setLoading(false);
    }
  };
  
  // Récupérer les polices système
  const fetchSystemFonts = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/system-fonts');
      setSystemFonts(response.data.fonts);
    } catch (err) {
      console.error('Erreur lors du chargement des polices système:', err);
      setError('Erreur lors du chargement des polices système');
    } finally {
      setLoading(false);
    }
  };

  // Récupérer les fichiers de cache
  const fetchCacheFiles = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/admin/cache-files');
      setCacheFiles(response.data.cacheFiles);
    } catch (err) {
      console.error('Erreur lors du chargement des fichiers de cache:', err);
      setError('Erreur lors du chargement des fichiers de cache');
    } finally {
      setLoading(false);
    }
  };

  // Supprimer un utilisateur
  const handleDeleteUser = async (userId: number) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur #${userId} ?`)) {
      try {
        setLoading(true);
        await axios.delete(`/api/admin/users/${userId}`);
        setUsers(users.filter(user => user.id !== userId));
        setSuccessMessage(`Utilisateur #${userId} supprimé avec succès`);
      } catch (err) {
        console.error('Erreur lors de la suppression:', err);
        setError('Erreur lors de la suppression de l\'utilisateur');
      } finally {
        setLoading(false);
      }
    }
  };

  // Vider les caches
  const handleClearCache = async () => {
    if (window.confirm('Êtes-vous sûr de vouloir vider tous les caches ?')) {
      try {
        setLoading(true);
        const response = await axios.post('/api/admin/clear-cache');
        setSuccessMessage(response.data.message);
      } catch (err) {
        console.error('Erreur lors du vidage des caches:', err);
        setError('Erreur lors du vidage des caches');
      } finally {
        setLoading(false);
      }
    }
  };
  
  // Gestion du fichier de template
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
  
  // Gestion de l'image de prévisualisation
  const handlePreviewFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setUploadPreviewFile(e.target.files[0]);
    }
  };
  
  // Upload d'un template de base
  const handleUploadTemplate = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!uploadFile) {
      setError('Fichier template requis');
      return;
    }

    if (!uploadPreviewFile) {
      setError('Image de prévisualisation requise');
      return;
    }

    if (!uploadName.trim()) {
      setError('Le nom du template n\'a pas pu être extrait du nom du fichier. Veuillez suivre les conventions de nommage.');
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
      
      await axios.post('/api/admin/upload-template', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      setSuccessMessage('Template de base ajouté avec succès');
      setUploadName('');
      setUploadFile(null);
      setUploadPreviewFile(null);
    } catch (err: any) {
      console.error('Erreur d\'upload:', err);
      setError(err.response?.data?.message || 'Erreur lors de l\'upload du template');
    } finally {
      setLoading(false);
    }
  };

  // Supprimer un commentaire
  const handleDeleteComment = async (commentId: number) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer ce commentaire ?`)) {
      try {
        setLoading(true);
        await axios.delete(`/api/admin/comments/${commentId}`);
        setComments(comments.filter(comment => comment.id !== commentId));
        setSuccessMessage('Commentaire supprimé avec succès');
      } catch (err) {
        console.error('Erreur lors de la suppression du commentaire:', err);
        setError('Erreur lors de la suppression du commentaire');
      } finally {
        setLoading(false);
      }
    }
  };

  // Supprimer un template système
  const handleDeleteSystemTemplate = async (type: string, name: string) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer le template ${name} ?`)) {
      try {
        setLoading(true);
        await axios.delete(`/api/admin/system-templates/${type}/${name}`);
        setSuccessMessage(`Template ${name} supprimé avec succès`);
        fetchSystemTemplates();
      } catch (err) {
        console.error('Erreur lors de la suppression du template:', err);
        setError('Erreur lors de la suppression du template');
      } finally {
        setLoading(false);
      }
    }
  };
  
  // Supprimer une police système
  const handleDeleteSystemFont = async (name: string) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer la police ${name} ?`)) {
      try {
        setLoading(true);
        await axios.delete(`/api/admin/system-fonts/${name}`);
        setSuccessMessage(`Police ${name} supprimée avec succès`);
        fetchSystemFonts();
      } catch (err) {
        console.error('Erreur lors de la suppression de la police:', err);
        setError('Erreur lors de la suppression de la police');
      } finally {
        setLoading(false);
      }
    }
  };
  
  // Upload d'une police système
  const [systemFontFile, setSystemFontFile] = useState<File | null>(null);
  
  const handleSystemFontFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      setSystemFontFile(e.target.files[0]);
    }
  };
  
  const handleUploadSystemFont = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!systemFontFile) {
      setError('Fichier de police requis');
      return;
    }
    
    try {
      setLoading(true);
      setError(null);
      
      const formData = new FormData();
      formData.append('font', systemFontFile);
      
      await axios.post('/api/admin/system-fonts/upload', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });
      
      setSuccessMessage('Police système ajoutée avec succès');
      setSystemFontFile(null);
      
      // Réinitialiser le champ d'input file
      const fileInput = document.getElementById('systemFontFile') as HTMLInputElement;
      if (fileInput) fileInput.value = '';
      
      // Recharger les polices
      fetchSystemFonts();
    } catch (err: any) {
      console.error('Erreur d\'upload de police:', err);
      setError(err.response?.data?.message || 'Erreur lors de l\'upload de la police');
    } finally {
      setLoading(false);
    }
  };

  // Supprimer un fichier de cache
  const handleDeleteCacheFile = async (filePath: string) => {
    if (window.confirm(`Êtes-vous sûr de vouloir supprimer ce fichier ou dossier ?`)) {
      try {
        setLoading(true);
        
        await axios.post('/api/admin/cache-files/delete', { path: filePath });
        setCacheFiles(cacheFiles.filter(file => file.relativePath !== filePath));
        setSuccessMessage('Fichier ou dossier supprimé avec succès');
      } catch (err) {
        console.error('Erreur lors de la suppression:', err);
        setError(err.response?.data?.message || 'Erreur lors de la suppression');
      } finally {
        setLoading(false);
      }
    }
  };
  
  // Vider les logs
  const handleClearLogs = async () => {
    if (window.confirm('Êtes-vous sûr de vouloir vider tous les logs ?')) {
      try {
        setLoading(true);
        await axios.post('/api/admin/clear-logs');
        setLogs([]);
        setSuccessMessage('Logs vidés avec succès');
      } catch (err) {
        console.error('Erreur lors du vidage des logs:', err);
        setError('Erreur lors du vidage des logs');
      } finally {
        setLoading(false);
      }
    }
  };

  // Effacer les messages
  const clearMessages = () => {
    setError(null);
    setSuccessMessage(null);
  };

  // Si la page est en cours de chargement ou l'utilisateur n'est pas authentifié ou n'est pas admin, afficher un message de chargement
  if (authLoading || !isAuthenticated || !user?.isAdmin) {
    return (
      <div className="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
        <NavBar />
        <div className="text-center">
          {authLoading ? (
            <div className="animate-pulse text-lg text-gray-600 dark:text-gray-400">Chargement...</div>
          ) : (
            <div className="text-lg text-red-600 dark:text-red-400">
              Accès non autorisé
              <div className="mt-4">
                <Link href="/">
                  <button className="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    Retour à l'accueil
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
      <NavBar pageTitle="Administration" />

      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          {/* Afficher les messages d'erreur ou de succès */}
          {error && (
            <div className="bg-red-50 dark:bg-red-900 p-3 mb-4 rounded-md border border-red-200 dark:border-red-700">
              <div className="flex">
                <svg className="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                </svg>
                <div className="ml-3">
                  <p className="text-sm text-red-700 dark:text-red-300">{error}</p>
                </div>
                <div className="ml-auto pl-3">
                  <div className="-mx-1.5 -my-1.5">
                    <button
                      onClick={() => setError(null)}
                      className="inline-flex bg-red-50 dark:bg-red-900 rounded-md p-1.5 text-red-500 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-800 focus:outline-none"
                    >
                      <span className="sr-only">Dismiss</span>
                      <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {successMessage && (
            <div className="bg-green-50 dark:bg-green-900 p-3 mb-4 rounded-md border border-green-200 dark:border-green-700">
              <div className="flex">
                <svg className="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                </svg>
                <div className="ml-3">
                  <p className="text-sm text-green-700 dark:text-green-300">{successMessage}</p>
                </div>
                <div className="ml-auto pl-3">
                  <div className="-mx-1.5 -my-1.5">
                    <button
                      onClick={() => setSuccessMessage(null)}
                      className="inline-flex bg-green-50 dark:bg-green-900 rounded-md p-1.5 text-green-500 dark:text-green-300 hover:bg-green-100 dark:hover:bg-green-800 focus:outline-none"
                    >
                      <span className="sr-only">Dismiss</span>
                      <svg className="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Navigation par onglets */}
          <div className="mb-6">
            <div className="border-b border-gray-200 dark:border-gray-700">
              <nav className="flex -mb-px">
                <button
                  onClick={() => setActiveTab('users')}
                  className={`${
                    activeTab === 'users'
                      ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                  } flex items-center whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm`}
                >
                  Utilisateurs
                </button>
                <button
                  onClick={() => setActiveTab('templates')}
                  className={`${
                    activeTab === 'templates'
                      ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                  } flex items-center whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm`}
                >
                  Templates & Polices
                </button>
                <button
                  onClick={() => setActiveTab('logs')}
                  className={`${
                    activeTab === 'logs'
                      ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                  } flex items-center whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm`}
                >
                  Logs
                </button>
                <button
                  onClick={() => setActiveTab('comments')}
                  className={`${
                    activeTab === 'comments'
                      ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                  } flex items-center whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm`}
                >
                  Commentaires
                </button>
                <button
                  onClick={() => setActiveTab('cache')}
                  className={`${
                    activeTab === 'cache'
                      ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                  } flex items-center whitespace-nowrap py-4 px-4 border-b-2 font-medium text-sm`}
                >
                  Cache
                </button>
              </nav>
            </div>
          </div>

          {/* Contenu des onglets */}
          <div>
            {/* Gestion des utilisateurs */}
            {activeTab === 'users' && (
              <div>
                <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Gestion des utilisateurs</h2>
                <div className="overflow-x-auto">
                  <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead className="bg-gray-50 dark:bg-gray-800">
                      <tr>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom d'utilisateur</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date d'inscription</th>
                        <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                      </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                      {users.length > 0 ? (
                        users.map((user) => (
                          <tr key={user.id}>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{user.id}</td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{user.username}</td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{user.email}</td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{new Date(user.createdAt).toLocaleDateString()}</td>
                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                              {user.id !== 1 && ( // Ne pas permettre de supprimer l'admin
                                <button 
                                  onClick={() => handleDeleteUser(user.id)}
                                  className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                  disabled={loading}
                                >
                                  Supprimer
                                </button>
                              )}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={5} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                            {loading ? 'Chargement...' : 'Aucun utilisateur trouvé.'}
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {/* Gestion des templates */}
            {activeTab === 'templates' && (
              <div>
                <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Gestion des templates de base</h2>
                
                {/* Formulaire d'upload de template */}
                <div className="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                  <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-white">Ajouter un template de base</h3>
                  
                  <form onSubmit={handleUploadTemplate} className="space-y-4">
                    <div>
                      <label htmlFor="templateFile" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Fichier template (.tex)
                      </label>
                      <input
                        type="file"
                        id="templateFile"
                        accept=".tex"
                        onChange={handleFileChange}
                        className="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        disabled={loading}
                      />
                      {uploadName && (
                        <p className="mt-1 text-sm text-green-600 dark:text-green-400">
                          Type détecté: {uploadType}, Nom: {uploadName}
                        </p>
                      )}
                    </div>
                    
                    <div>
                      <label htmlFor="previewFile" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Image de prévisualisation (.png)
                      </label>
                      <input
                        type="file"
                        id="previewFile"
                        accept="image/png"
                        onChange={handlePreviewFileChange}
                        className="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        disabled={loading}
                      />
                    </div>
                    
                    <button
                      type="submit"
                      disabled={loading}
                      className={`group w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white ${
                        loading ? 'bg-blue-400 dark:bg-blue-700' : 'bg-blue-600 dark:bg-blue-800 hover:bg-blue-700 dark:hover:bg-blue-900'
                      } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500`}
                    >
                      {loading ? 'Chargement...' : 'Ajouter le template'}
                    </button>
                  </form>
                </div>
                
                {/* Liste des templates système */}
                <div className="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                  <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-white">Templates système</h3>
                  
                  {/* Layouts */}
                  <div className="mb-6">
                    <h4 className="text-base font-medium mb-2 text-gray-800 dark:text-gray-200">Layouts</h4>
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                          <tr>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taille</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Modifié le</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                          {systemTemplates.layouts.length > 0 ? (
                            systemTemplates.layouts.map((layout) => (
                              <tr key={layout.name}>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{layout.name}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{Math.round(layout.size / 1024)} KB</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{new Date(layout.modified).toLocaleString()}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                  <button
                                    onClick={() => handleDeleteSystemTemplate('layout', layout.name)}
                                    className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                    disabled={loading}
                                  >
                                    Supprimer
                                  </button>
                                </td>
                              </tr>
                            ))
                          ) : (
                            <tr>
                              <td colSpan={4} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                {loading ? 'Chargement...' : 'Aucun layout trouvé.'}
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                  
                  {/* Covers */}
                  <div className="mb-6">
                    <h4 className="text-base font-medium mb-2 text-gray-800 dark:text-gray-200">Couvertures</h4>
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                          <tr>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taille</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Modifié le</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                          {systemTemplates.covers.length > 0 ? (
                            systemTemplates.covers.map((cover) => (
                              <tr key={cover.name}>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{cover.name}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{Math.round(cover.size / 1024)} KB</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{new Date(cover.modified).toLocaleString()}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                  <button
                                    onClick={() => handleDeleteSystemTemplate('cover', cover.name)}
                                    className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                    disabled={loading}
                                  >
                                    Supprimer
                                  </button>
                                </td>
                              </tr>
                            ))
                          ) : (
                            <tr>
                              <td colSpan={4} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                {loading ? 'Chargement...' : 'Aucune couverture trouvée.'}
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                  
                  {/* Imposes */}
                  <div>
                    <h4 className="text-base font-medium mb-2 text-gray-800 dark:text-gray-200">Impositions</h4>
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-800">
                          <tr>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taille</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Modifié le</th>
                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                          {systemTemplates.imposes.length > 0 ? (
                            systemTemplates.imposes.map((impose) => (
                              <tr key={impose.name}>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{impose.name}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{Math.round(impose.size / 1024)} KB</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{new Date(impose.modified).toLocaleString()}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                  <button
                                    onClick={() => handleDeleteSystemTemplate('impose', impose.name)}
                                    className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                    disabled={loading}
                                  >
                                    Supprimer
                                  </button>
                                </td>
                              </tr>
                            ))
                          ) : (
                            <tr>
                              <td colSpan={4} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                {loading ? 'Chargement...' : 'Aucune imposition trouvée.'}
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                
                {/* Gestion des polices système */}
                <div className="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                  <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-white">Polices système</h3>
                  
                  {/* Formulaire d'upload de police */}
                  <form onSubmit={handleUploadSystemFont} className="space-y-4 mb-6">
                    <div>
                      <label htmlFor="systemFontFile" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Fichier de police (.ttf, .otf)
                      </label>
                      <input
                        type="file"
                        id="systemFontFile"
                        accept=".ttf,.otf"
                        onChange={handleSystemFontFileChange}
                        className="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        disabled={loading}
                      />
                    </div>
                    
                    <button
                      type="submit"
                      disabled={loading}
                      className={`group w-full sm:w-auto flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white ${
                        loading ? 'bg-purple-400 dark:bg-purple-700' : 'bg-purple-600 dark:bg-purple-800 hover:bg-purple-700 dark:hover:bg-purple-900'
                      } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500`}
                    >
                      {loading ? 'Chargement...' : 'Ajouter une police'}
                    </button>
                  </form>
                  
                  {/* Liste des polices */}
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                      <thead className="bg-gray-50 dark:bg-gray-800">
                        <tr>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taille</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Modifié le</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        {systemFonts.length > 0 ? (
                          systemFonts.map((font) => (
                            <tr key={font.name}>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{font.name}</td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{Math.round(font.size / 1024)} KB</td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{new Date(font.modified).toLocaleString()}</td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                <button
                                  onClick={() => handleDeleteSystemFont(font.name)}
                                  className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                  disabled={loading}
                                >
                                  Supprimer
                                </button>
                              </td>
                            </tr>
                          ))
                        ) : (
                          <tr>
                            <td colSpan={4} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                              {loading ? 'Chargement...' : 'Aucune police trouvée.'}
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}

            {/* Logs */}
            {activeTab === 'logs' && (
              <div>
                <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Logs système</h2>
                
                <div className="mb-4 flex space-x-2">
                  <button 
                    onClick={handleClearLogs} 
                    className="bg-red-500 dark:bg-red-600 hover:bg-red-600 dark:hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                    disabled={loading}
                  >
                    Vider les logs
                  </button>
                </div>
                
                <div className="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 overflow-x-auto">
                  {logs.length > 0 ? (
                    <pre className="text-xs dark:text-gray-300">
                      {logs.map((log, index) => (
                        <div key={index} className={`py-1 ${
                          log.level === 'ERROR' ? 'text-red-600 dark:text-red-400' : 
                          log.level === 'WARNING' ? 'text-yellow-600 dark:text-yellow-400' : 
                          'text-gray-800 dark:text-gray-300'
                        }`}>
                          [{log.timestamp}] [{log.level}] {log.message}
                        </div>
                      ))}
                    </pre>
                  ) : (
                    <p className="py-4 text-center text-gray-500 dark:text-gray-400">
                      {loading ? 'Chargement des logs...' : 'Aucun log disponible.'}
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* Commentaires */}
            {activeTab === 'comments' && (
              <div>
                <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Commentaires des utilisateurs</h2>
                <div className="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
                  {comments.length > 0 ? (
                    comments.map((comment) => (
                      <div key={comment.id} className="p-4">
                        <div className="flex justify-between items-start">
                          <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-white">
                              {comment.username} <span className="text-gray-500 dark:text-gray-400">• {new Date(comment.createdAt).toLocaleString()}</span>
                            </p>
                            <p className="mt-1 text-gray-800 dark:text-gray-200">{comment.content}</p>
                          </div>
                          <button
                            onClick={() => handleDeleteComment(comment.id)}
                            className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 text-sm"
                            disabled={loading}
                          >
                            Supprimer
                          </button>
                        </div>
                      </div>
                    ))
                  ) : (
                    <p className="py-4 text-center text-gray-500 dark:text-gray-400">
                      {loading ? 'Chargement des commentaires...' : 'Aucun commentaire disponible.'}
                    </p>
                  )}
                </div>
              </div>
            )}

            {/* Fichiers de cache */}
            {activeTab === 'cache' && (
              <div>
                <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Gestion du cache</h2>
                
                <div className="mb-4 flex space-x-2">
                  <button 
                    onClick={handleClearCache} 
                    className="bg-yellow-500 dark:bg-yellow-600 hover:bg-yellow-600 dark:hover:bg-yellow-700 text-white px-4 py-2 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                    disabled={loading}
                  >
                    Vider les caches (fichiers &gt; 1h)
                  </button>
                  <button 
                    onClick={fetchCacheFiles} 
                    className="bg-blue-500 dark:bg-blue-600 hover:bg-blue-600 dark:hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    disabled={loading}
                  >
                    Actualiser
                  </button>
                </div>
                
                <div className="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 mb-6">
                  <h3 className="text-lg font-medium mb-4 text-gray-900 dark:text-white">Fichiers temporaires</h3>
                  
                  <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                      <thead className="bg-gray-50 dark:bg-gray-800">
                        <tr>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nom</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Taille</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Détails</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                          <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                        </tr>
                      </thead>
                      <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                        {cacheFiles.length > 0 ? (
                          cacheFiles.map((file, index) => (
                            <tr key={index}>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {file.type === 'directory' ? (
                                  <div className="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    Dossier
                                  </div>
                                ) : (
                                  <div className="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    Fichier
                                  </div>
                                )}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {file.name}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {file.size < 1024 ? `${file.size} B` : 
                                 file.size < 1024 * 1024 ? `${Math.round(file.size / 1024)} KB` : 
                                 `${Math.round(file.size / (1024 * 1024))} MB`}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {file.type === 'directory' ? (
                                  `${file.fileCount} fichiers`
                                ) : (
                                  `Âge: ${file.age < 60 ? `${file.age} sec` : 
                                          file.age < 3600 ? `${Math.round(file.age / 60)} min` : 
                                          `${Math.round(file.age / 3600)} h`}`
                                )}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {new Date(file.modified).toLocaleString()}
                              </td>
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white flex space-x-2">
                                <button
                                  onClick={() => handleDeleteCacheFile(file.relativePath)}
                                  className="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300"
                                  disabled={loading}
                                >
                                  Supprimer
                                </button>
                              </td>
                            </tr>
                          ))
                        ) : (
                          <tr>
                            <td colSpan={6} className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                              {loading ? 'Chargement...' : 'Aucun fichier de cache trouvé.'}
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            )}
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