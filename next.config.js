const { i18n } = require('./next-i18next.config');
/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  output: 'standalone',
  i18n: {
    // Langues supportées
    locales: ['fr', 'en', 'de', 'es'],
    // Langue par défaut
    defaultLocale: 'fr',
  },
  // Désactiver les source maps en production pour éviter les erreurs 404
  productionBrowserSourceMaps: false,
  
  // Variables d'environnement publiques
  env: {
    API_BASE_URL: process.env.API_BASE_URL || 'http://localhost:8080',
  },
  
  // Configuration du proxy pour les requêtes API
  async rewrites() {
    const apiBaseUrl = process.env.API_BASE_URL || 'http://localhost:8080';
    
    return [
      {
        source: '/api/:path*',
        destination: `${apiBaseUrl}/api/:path*`, // Redirection vers le serveur PHP
      },
      {
        source: '/workspace/:path*',
        destination: `${apiBaseUrl}/workspace/:path*`, // Redirection vers les fichiers de workspace
      },
      {
        source: '/serve-image.php/:path*',
        destination: `${apiBaseUrl}/serve-image.php/:path*`, // Redirection pour les images
      },
      {
        source: '/delete-image.php/:path*',
        destination: `${apiBaseUrl}/delete-image.php/:path*`, // Redirection pour la suppression d'images
      },
      // Supprimé le rewrite pour serve-preview.php - nginx s'en occupe directement
      {
        source: '/pdf/:path*',
        destination: `${apiBaseUrl}/pdf/:path*`, // Redirection pour les téléchargements PDF
      },
    ];
  },
  
  // Configuration pour la production
  ...(process.env.NODE_ENV === 'production' && {
    // Optimisations pour la production
    compress: true,
    poweredByHeader: false,
    generateEtags: false,
  }),
};

module.exports = nextConfig 