import React, { useState, useRef, useEffect } from 'react';

// Composants de drapeaux SVG simples
const FrenchFlag = () => (
  <svg width="20" height="15" viewBox="0 0 20 15" className="rounded-sm">
    <rect width="7" height="15" fill="#0055A4"/>
    <rect x="7" width="6" height="15" fill="#FFFFFF"/>
    <rect x="13" width="7" height="15" fill="#EF4135"/>
  </svg>
);

const BritishFlag = () => (
  <svg width="20" height="15" viewBox="0 0 20 15" className="rounded-sm">
    <rect width="20" height="15" fill="#012169"/>
    <path d="M0,0 L20,15 M20,0 L0,15" stroke="#FFFFFF" strokeWidth="2"/>
    <path d="M10,0 L10,15 M0,7.5 L20,7.5" stroke="#FFFFFF" strokeWidth="3"/>
    <path d="M0,0 L20,15 M20,0 L0,15" stroke="#C8102E" strokeWidth="1"/>
    <path d="M10,0 L10,15 M0,7.5 L20,7.5" stroke="#C8102E" strokeWidth="1.5"/>
  </svg>
);

const GermanFlag = () => (
  <svg width="20" height="15" viewBox="0 0 20 15" className="rounded-sm">
    <rect width="20" height="5" fill="#000000"/>
    <rect y="5" width="20" height="5" fill="#DD0000"/>
    <rect y="10" width="20" height="5" fill="#FFCE00"/>
  </svg>
);

const SpanishFlag = () => (
  <svg width="20" height="15" viewBox="0 0 20 15" className="rounded-sm">
    <rect width="20" height="15" fill="#AA151B"/>
    <rect y="3.75" width="20" height="7.5" fill="#F1BF00"/>
  </svg>
);

interface LanguageSelectorProps {
  currentLocale: string;
  onLanguageChange: (locale: string) => void;
}

const languages = [
  { code: 'fr', flag: <FrenchFlag />, name: 'Français' },
  { code: 'en', flag: <BritishFlag />, name: 'English' },
  { code: 'de', flag: <GermanFlag />, name: 'Deutsch' },
  { code: 'es', flag: <SpanishFlag />, name: 'Español' },
];

export default function LanguageSelector({ currentLocale, onLanguageChange }: LanguageSelectorProps) {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const currentLanguage = languages.find(lang => lang.code === currentLocale) || languages[0];

  // Fermer le dropdown quand on clique en dehors
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const handleLanguageSelect = (languageCode: string) => {
    onLanguageChange(languageCode);
    setIsOpen(false);
  };

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Bouton principal */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="h-9 px-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 flex items-center justify-between min-w-[60px]"
        aria-label="Sélecteur de langue"
      >
        <span className="flex items-center">
          <span className="mr-1">
            {currentLanguage.flag}
          </span>
        </span>
        <svg 
          className={`fill-current h-4 w-4 transition-transform ml-1 ${isOpen ? 'rotate-180' : ''}`} 
          xmlns="http://www.w3.org/2000/svg" 
          viewBox="0 0 20 20"
        >
          <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
        </svg>
      </button>

      {/* Dropdown */}
      {isOpen && (
        <div className="absolute top-full left-0 mt-1 w-40 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg z-50">
          {languages.map((language) => (
            <button
              key={language.code}
              onClick={() => handleLanguageSelect(language.code)}
              className={`w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center ${
                currentLocale === language.code 
                  ? 'bg-indigo-50 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200' 
                  : 'text-gray-900 dark:text-gray-100'
              } ${language === languages[0] ? 'rounded-t-md' : ''} ${language === languages[languages.length - 1] ? 'rounded-b-md' : ''}`}
            >
                             <span className="mr-2">
                 {language.flag}
               </span>
              <span>{language.name}</span>
              {currentLocale === language.code && (
                <span className="ml-auto text-indigo-600 dark:text-indigo-400">✓</span>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
} 