import { useCallback } from 'react';
import { useTranslation } from 'next-i18next';
import { useRouter } from 'next/router';
import { useTheme } from '../contexts/ThemeContext';
import { useAuth } from '../contexts/AuthContext';
import Link from 'next/link';
import Image from 'next/image';
import { FaQuestionCircle, FaUser, FaSignInAlt, FaEdit } from 'react-icons/fa';
import LanguageSelector from './LanguageSelector';

interface NavBarProps {
  pageTitle?: string;
}

export default function NavBar({
  pageTitle
}: NavBarProps) {
  const { isDark, toggleTheme } = useTheme();
  const { isAuthenticated, user, logout } = useAuth();
  const router = useRouter();
  const { t } = useTranslation('translation');

  // Gérer le changement de langue
  const handleLanguageChange = useCallback(async (newLocale: string) => {
    const { pathname, asPath, query } = router;
    await router.push({ pathname, query }, asPath, { locale: newLocale });
  }, [router]);

  return (
    <header className="bg-white dark:bg-gray-800 shadow">
      <div className="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <div className="flex items-center">
          <div className="relative">
            <div className={`absolute left-0 -bottom-8 flex items-center justify-center ${isDark ? 'bg-white rounded-full' : ''}`}>
              <Link href="/">
                <Image 
                  src="/images/logo.svg" 
                  alt="Online Book Brew Logo" 
                  width={280} 
                  height={280} 
                  className="h-auto"
                  priority
                />
              </Link>
            </div>
            {/* Div fantôme pour maintenir l'espace */}
            <div className="invisible">
              <div style={{ width: '80px', height: '50px' }}></div>
            </div>
          </div>
          
          {pageTitle && (
            <h1 className="text-xl font-bold text-gray-900 dark:text-white ml-4">
              {pageTitle}
            </h1>
          )}
        </div>
        
        <div className="flex space-x-2 items-center">
          {/* Bouton de documentation */}
          <Link href="/documentation">
            <button
              className="p-2 border rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors flex items-center justify-center w-9 h-9 border-gray-300 dark:border-gray-600 text-green-600 dark:text-green-400 hover:bg-gray-50 dark:hover:bg-gray-700"
              aria-label="Documentation"
              title={t('documentation')}
            >
              <FaQuestionCircle />
            </button>
          </Link>
          
          {/* Bouton Admin pour les administrateurs */}
          {user?.isAdmin && (
            <Link href="/admin">
              <button
                className="p-2 border rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors flex items-center justify-center w-9 h-9 bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-200 border-purple-300 dark:border-purple-700"
                aria-label="Administration"
                title={t('admin_panel')}
              >
                <span className="text-sm font-bold">A</span>
              </button>
            </Link>
          )}
          
          <LanguageSelector 
            currentLocale={router.locale || 'fr'}
            onLanguageChange={handleLanguageChange}
          />
          
          <button
            onClick={toggleTheme}
            className={`p-0 border rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors flex items-center justify-center w-9 h-9
              ${isDark
                ? 'bg-gray-700 text-yellow-300 border-gray-600 hover:bg-gray-600 focus:ring-yellow-400'
                : 'bg-gray-200 text-gray-800 border-gray-300 hover:bg-gray-300 focus:ring-indigo-500'}
            `}
            aria-label="Basculer le thème sombre"
          >
            {isDark ? '☾' : '☀️'}
          </button>
          
          {isAuthenticated ? (
            <div className="flex space-x-2">
              {/* Bouton Éditeur */}
              <Link href="/editor">
                <button className="p-2 border border-green-500 dark:border-green-600 bg-green-500 dark:bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-600 dark:hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center justify-center w-9 h-9 mr-2" title={t('editor')}>
                  <FaEdit />
                </button>
              </Link>

              <Link href="/account">
                <button className="p-2 border border-blue-500 dark:border-blue-600 bg-blue-500 dark:bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center justify-center w-9 h-9" title={user?.username || t('my_account')}>
                  <FaUser />
                </button>
              </Link>
              <button
                onClick={logout}
                className="p-2 border border-red-500 dark:border-red-600 bg-red-500 dark:bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-600 dark:hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 flex items-center justify-center w-9 h-9"
                title={t('logout')}
              >
                <FaSignInAlt className="transform rotate-180" />
              </button>
            </div>
          ) : (
            <Link href="/login">
              <button className="p-2 border border-blue-500 dark:border-blue-600 bg-blue-500 dark:bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center justify-center w-9 h-9" title={t('login_register')}>
                <FaSignInAlt />
              </button>
            </Link>
          )}
        </div>
      </div>
    </header>
  );
} 