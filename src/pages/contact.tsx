import { useState } from 'react';
import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useRouter } from 'next/router';
import Link from 'next/link';
import axios from 'axios';
import { useAuth } from '../contexts/AuthContext';
import { useTheme } from '../contexts/ThemeContext';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';

export default function ContactPage() {
  const { t } = useTranslation('translation');
  const router = useRouter();
  const { isAuthenticated, user } = useAuth();
  const { isDark } = useTheme();

  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!message.trim()) {
      setError('Veuillez saisir un message');
      return;
    }
    
    try {
      setLoading(true);
      setError(null);
      
      // Appeler l'API pour envoyer le commentaire
      await axios.post('/api/comments', {
        content: message
      });
      
      setSuccess(true);
      setMessage('');
    } catch (err: any) {
      console.error('Erreur lors de l\'envoi du message:', err);
      setError(err.response?.data?.message || 'Erreur lors de l\'envoi du message');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <NavBar pageTitle={t('contact_us')} />

      <main className="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div className="px-4 py-6 sm:px-0">
          <div className="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 className="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
              Envoyez un message
            </h2>
            
            {success ? (
              <div className="bg-green-50 dark:bg-green-900 p-4 rounded-md mb-6">
                <p className="text-green-700 dark:text-green-300">
                  Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.
                </p>
                <button
                  onClick={() => setSuccess(false)}
                  className="mt-2 text-green-700 dark:text-green-300 underline"
                >
                  Envoyer un autre message
                </button>
              </div>
            ) : (
              <form onSubmit={handleSubmit} className="space-y-4">
                {error && (
                  <div className="bg-red-50 dark:bg-red-900 p-3 rounded-md text-red-700 dark:text-red-300 text-sm">
                    {error}
                  </div>
                )}
                
                {!isAuthenticated && (
                  <div className="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-md">
                    <p className="text-yellow-700 dark:text-yellow-300 text-sm">
                      Vous n'êtes pas connecté. Pour obtenir une réponse personnalisée, veuillez vous{' '}
                      <Link href="/login">
                        <span className="underline cursor-pointer">connecter</span>
                      </Link>.
                    </p>
                  </div>
                )}
                
                <div>
                  <label htmlFor="message" className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Votre message
                  </label>
                  <textarea
                    id="message"
                    name="message"
                    rows={6}
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    className="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-gray-900 dark:text-white bg-white dark:bg-gray-700 sm:text-sm"
                    placeholder="Décrivez votre question ou votre problème..."
                    required
                  />
                </div>
                
                <div>
                  <button
                    type="submit"
                    disabled={loading}
                    className={`w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white ${
                      loading
                        ? 'bg-indigo-400 dark:bg-indigo-600'
                        : 'bg-indigo-600 dark:bg-indigo-700 hover:bg-indigo-700 dark:hover:bg-indigo-800'
                    } focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500`}
                  >
                    {loading ? 'Envoi en cours...' : 'Envoyer le message'}
                  </button>
                </div>
              </form>
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