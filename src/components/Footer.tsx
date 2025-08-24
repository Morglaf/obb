import Link from 'next/link';
import { useTranslation } from 'next-i18next';
import VersionInfo from './VersionInfo';

export default function Footer() {
  const { t } = useTranslation('translation');
  
  return (
    <footer className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
      <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div className="text-center space-y-3">
          <p className="text-sm text-gray-500 dark:text-gray-400">
            © 2024 Online Book Brew. {t('all_rights_reserved')}
          </p>
          
          {/* Informations de version */}
          <VersionInfo />
          
          <p className="text-xs text-gray-400 dark:text-gray-500">
            <Link href="/contact">
              <span className="text-indigo-500 dark:text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 cursor-pointer">
                {t('contact_us')}
              </span>
            </Link>
            {' • '}
            <Link href="/documentation">
              <span className="text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 cursor-pointer">
                {t('documentation')}
              </span>
            </Link>
          </p>
        </div>
      </div>
    </footer>
  );
} 