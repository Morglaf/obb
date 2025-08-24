import Link from 'next/link';
import { useTranslation } from 'next-i18next';
import { useVersion } from '../hooks/useVersion';
import { FaCodeBranch, FaCalendarAlt } from 'react-icons/fa';

export default function Footer() {
  const { t } = useTranslation('translation');
  const { version, loading, error } = useVersion();
  
  return (
    <footer className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
      <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div className="text-center space-y-2">
          <p className="text-sm text-gray-500 dark:text-gray-400">
            © {new Date().getFullYear()} Online Book Brew. {t('all_rights_reserved')}
          </p>
          
          {/* Informations de version */}
          {!loading && !error && version && (
            <div className="flex items-center justify-center space-x-4 text-xs text-gray-400 dark:text-gray-500">
              <div className="flex items-center space-x-1">
                <FaCodeBranch className="w-3 h-3" />
                <span>v{version.last_deploy}</span>
              </div>
              <div className="flex items-center space-x-1">
                <FaCalendarAlt className="w-3 h-3" />
                <span>{new Date(version.deploy_date).toLocaleDateString('fr-FR')}</span>
              </div>
              <div className="text-xs opacity-75">
                #{version.git_commit}
              </div>
            </div>
          )}
          
          <p className="text-xs text-gray-400 dark:text-gray-500">
            <Link href="/contact">
              <span className="text-indigo-500 dark:text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 cursor-pointer">
                {t('contact_us')}
              </span>
            </Link>
            {' • '}
            <Link href="/documentation">
              <span className="text-indigo-500 dark:text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 cursor-pointer">
                {t('documentation')}
              </span>
            </Link>
          </p>
        </div>
      </div>
    </footer>
  );
} 