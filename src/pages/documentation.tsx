import { useTranslation } from 'next-i18next';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import Link from 'next/link';
import { FaBook, FaImage, FaColumns } from 'react-icons/fa';
import { useTheme } from '../contexts/ThemeContext';
import NavBar from '../components/NavBar';
import Footer from '../components/Footer';

export default function Documentation() {
  const { t } = useTranslation('translation');
  const { isDark } = useTheme();

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
      <NavBar pageTitle={t('documentation_title')} />

      <main className="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        <div className="prose prose-lg dark:prose-invert max-w-none">
          <p className="text-lg text-gray-700 dark:text-gray-300">
            {t('documentation_intro')}
          </p>

          <div className="mt-8 grid grid-cols-1 gap-8 md:grid-cols-3">
            <div className="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
              <div className="px-4 py-5 sm:p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-md p-3">
                    <FaBook className="h-6 w-6 text-green-600 dark:text-green-400" />
                  </div>
                  <div className="ml-5">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">{t('layout')}</h3>
                  </div>
                </div>
                <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                  <p className="mb-2">
                    Un <strong>layout</strong> (mise en page) définit la structure interne de votre livre :
                  </p>
                  <ul className="list-disc pl-5 space-y-1">
                    <li>Polices de caractères</li>
                    <li>Marges</li>
                    <li>En-têtes et pieds de page</li>
                    <li>Style des titres</li>
                    <li>Formatage des paragraphes</li>
                  </ul>
                  <p className="mt-3">
                    Le nom des fichiers de layout suit la convention : <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">[nom]-[format]-layout.tex</code>
                  </p>
                  <p className="mt-2">
                    Exemples : <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Garamond-brsnoba5-layout.tex</code>, <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Times-a5-layout.tex</code>
                  </p>
                  <p className="mt-3">
                    Lorsque vous sélectionnez un layout, il détermine également le format final de votre livre (ex: A5, A4, etc.).
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
              <div className="px-4 py-5 sm:p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-md p-3">
                    <FaImage className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                  </div>
                  <div className="ml-5">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">{t('cover')}</h3>
                  </div>
                </div>
                <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                  <p className="mb-2">
                    La <strong>couverture</strong> est la page extérieure de votre livre. Elle comprend :
                  </p>
                  <ul className="list-disc pl-5 space-y-1">
                    <li>Première de couverture (avant)</li>
                    <li>Quatrième de couverture (arrière)</li>
                    <li>Dos (tranche)</li>
                  </ul>
                  <p className="mt-3">
                    Le nom des fichiers de couverture suit la convention : <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">[nom]-[format]-cover-[format_papier].tex</code>
                  </p>
                  <p className="mt-2">
                    Exemples : <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Classic-brsnoba5-cover-A4.tex</code>, <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Modern-a5-cover-A3.tex</code>
                  </p>
                  <p className="mt-3">
                    Les couvertures sont compatibles avec différents formats de livre et comprennent des champs personnalisables comme le titre, l'auteur, etc.
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
              <div className="px-4 py-5 sm:p-6">
                <div className="flex items-center">
                  <div className="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-md p-3">
                    <FaColumns className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                  </div>
                  <div className="ml-5">
                    <h3 className="text-lg font-medium text-gray-900 dark:text-white">{t('impose')}</h3>
                  </div>
                </div>
                <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                  <p className="mb-2">
                    <strong>L'imposition</strong> est la façon dont les pages sont organisées sur une feuille d'impression :
                  </p>
                  <ul className="list-disc pl-5 space-y-1">
                    <li>Organise plusieurs pages sur une même feuille</li>
                    <li>Permet de plier et couper le papier pour créer un cahier</li>
                    <li>Optimise l'utilisation du papier lors de l'impression</li>
                  </ul>
                  <p className="mt-3">
                    Le nom des fichiers d'imposition suit la convention : <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">[format]-[format_papier]-[nb][type].tex</code>
                  </p>
                  <p className="mt-2">
                    Exemples : <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">brsnoba5-A4-16signature.tex</code> (16 pages par cahier), <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">a5-A3-2spread.tex</code> (2 pages côte à côte)
                  </p>
                  <p className="mt-3">
                    Types d'imposition :
                  </p>
                  <ul className="list-disc pl-5 space-y-1">
                    <li><strong>Signature</strong> : Pages organisées pour former des cahiers à plier</li>
                    <li><strong>Spread</strong> : Pages disposées côte à côte (ex: 2 pages A5 sur une feuille A4)</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <h2 className="mt-12 text-2xl font-bold text-gray-900 dark:text-white">Processus de création d'un livre</h2>
          
          <div className="mt-6 bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <div className="px-4 py-5 sm:p-6">
              <ol className="list-decimal pl-5 space-y-4">
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Rédaction du contenu</strong> : Écrivez ou collez votre texte dans l'éditeur Markdown. Vous pouvez utiliser le formatage Markdown pour structurer votre document (titres, listes, gras, italique, etc.).
                </li>
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Choix du layout</strong> : Sélectionnez un layout qui correspond au format de livre que vous souhaitez créer. Le layout détermine la mise en page interne de votre livre.
                </li>
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Paramétrage des métadonnées</strong> : Ajoutez les informations comme le titre, l'auteur, et d'autres détails qui apparaîtront dans votre livre.
                </li>
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Compilation du livre</strong> : Cliquez sur le bouton "Compiler le livre" pour générer un PDF de votre contenu avec la mise en page choisie.
                </li>
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Création de la couverture</strong> : Sélectionnez un template de couverture compatible avec votre format de livre, configurez-le et compilez-le.
                </li>
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Imposition (optionnelle)</strong> : Si vous prévoyez d'imprimer et relier vous-même votre livre, appliquez une imposition pour organiser les pages sur les feuilles d'impression.
                </li>
                <li className="text-gray-700 dark:text-gray-300">
                  <strong>Téléchargement</strong> : Téléchargez les PDF générés pour l'impression ou la distribution électronique.
                </li>
              </ol>
            </div>
          </div>

          <h2 className="mt-10 text-2xl font-bold text-gray-900 dark:text-white">Conseils pour la création de livres</h2>
          
          <div className="mt-6 bg-white dark:bg-gray-800 shadow overflow-hidden rounded-lg">
            <div className="px-4 py-5 sm:p-6">
              <ul className="list-disc pl-5 space-y-3 text-gray-700 dark:text-gray-300">
                <li>
                  <strong>Édition initiale</strong> : Rédigez et corrigez votre texte dans votre éditeur préféré avant de l'importer dans OnlineBookBrew.
                </li>
                <li>
                  <strong>Images</strong> : Utilisez la syntaxe <code className="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">![[nom-image.jpg]]</code> pour insérer des images. Téléversez vos images via le panneau latéral.
                </li>
                <li>
                  <strong>Formats de papier</strong> : Assurez-vous que le format d'imposition choisi correspond à votre imprimante ou service d'impression.
                </li>
                <li>
                  <strong>Épaisseur du dos</strong> : Pour les couvertures, ajustez l'épaisseur du papier en fonction du nombre de pages de votre livre.
                </li>
                <li>
                  <strong>Testez l'imposition</strong> : Imprimez quelques pages d'essai pour vérifier que l'imposition fonctionne comme prévu avant d'imprimer tout le livre.
                </li>
                <li>
                  <strong>Utilisateurs enregistrés</strong> : Créez un compte pour téléverser vos propres templates LaTeX et polices de caractères.
                </li>
              </ul>
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