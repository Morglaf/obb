import { useState } from 'react';
import axios from 'axios';
import { TemplateOptions } from '../types/templates';
import { extractMarkdownMetadata, formatDocumentDisplayName, generateDocumentFilename, DocumentMetadata } from '../utils/documentUtils';

interface ConversionResult {
  pdfUrl: string;
  documentId?: string;
  metadata?: DocumentMetadata; // Added for new_handleSuccess
  filename?: string; // Added for new_handleSuccess
  creation_time?: string; // Added for new_handleSuccess
}

interface DocumentHistoryItem {
  id: string;
  url: string;
  metadata: DocumentMetadata;
  displayName: string;
  filename: string;
  createdAt: Date;
}

interface ConversionHook {
  isLoading: boolean;
  status: string;
  pdfUrl: string;
  errorMessage: string;
  documentHistory: DocumentHistoryItem[];
  convertDocument: (content: string, template: TemplateOptions, conversionMethod?: string) => Promise<void>;
  compileCover: (content: string, template: TemplateOptions, conversionMethod?: string) => Promise<void>;
  imposeDocument: (content: string, template: TemplateOptions, conversionMethod?: string) => Promise<void>;
  clearResults: () => void;
}

// Utiliser le proxy Next.js au lieu d'une URL absolue
const API_URL = '';

/**
 * Hook personnalisé pour gérer la conversion des documents
 */
export const useDocumentConversion = (): ConversionHook => {
  const [isLoading, setIsLoading] = useState(false);
  const [status, setStatus] = useState('');
  const [pdfUrl, setPdfUrl] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [documentHistory, setDocumentHistory] = useState<DocumentHistoryItem[]>([]);

  /**
   * Fonction utilitaire pour mettre à jour l'état après un succès
   */
  const handleSuccess = (result: ConversionResult, content: string, template: TemplateOptions, operationType: 'convert' | 'compileCover' | 'imposeDocument' = 'convert') => {
    setStatus('Conversion réussie !');
    setPdfUrl(result.pdfUrl);
    
    if (result.documentId) {
      // Utiliser UNIQUEMENT les métadonnées de template (variables comme {{titre}}, {{auteur}})
      // qui sont saisies dans l'interface TemplateSelector
      const metadata: DocumentMetadata = {};
      
      if (template.metadata) {
        // Mapper les variables de template vers les métadonnées du document
        if (template.metadata.titre) {
          metadata.title = template.metadata.titre;
        } else if (template.metadata.title) {
          metadata.title = template.metadata.title;
        }
        
        if (template.metadata.auteur) {
          metadata.author = template.metadata.auteur;
        } else if (template.metadata.author) {
          metadata.author = template.metadata.author;
        }
        
        // Ajouter d'autres variables si elles existent
        if (template.metadata.edition) {
          metadata.description = template.metadata.edition;
        }
      }
      
      // Si pas de métadonnées de template, utiliser "sans titre"
      if (!metadata.title) {
        metadata.title = 'sans titre';
      }
      
      // Utiliser l'heure de création renvoyée par l'API ou l'heure actuelle
      const now = result.creation_time ? new Date(result.creation_time) : new Date();
      
      // Déterminer le type de document pour la génération du nom
      let documentType: 'document' | 'cover' | 'impose' = 'document';
      if (operationType === 'compileCover') {
        documentType = 'cover';
      } else if (operationType === 'imposeDocument') {
        documentType = 'impose';
      }
      
      // Utiliser le nom de fichier renvoyé par l'API si disponible
      const filename = result.filename || generateDocumentFilename(metadata, result.documentId, documentType, now);
      
      // Générer le nom d'affichage
      const displayName = formatDocumentDisplayName(metadata, result.documentId, documentType, now);
      
      const newDocument: DocumentHistoryItem = {
        id: result.documentId,
        url: result.pdfUrl,
        metadata,
        displayName,
        filename,
        createdAt: now
      };
      
      setDocumentHistory(prev => [
        newDocument,
        ...prev.slice(0, 4)
      ]);
    }
  };

  /**
   * Fonction utilitaire pour mettre à jour l'état en cas d'erreur
   */
  const handleError = (error: any, operation: string) => {
    const errorMessage = error.response?.data?.message || error.message || 'Erreur inconnue';
    setStatus(`Erreur lors de ${operation}`);
    setErrorMessage(errorMessage);
    console.error('Erreur détaillée:', error);
  };

  /**
   * Construire une URL PDF complète
   */
  const buildFullPdfUrl = (pdfUrl: string): string => {
    if (pdfUrl.startsWith('http://') || pdfUrl.startsWith('https://')) {
      return pdfUrl;
    }
    return `${API_URL}${pdfUrl}`;
  };

  /**
   * Réinitialiser les résultats
   */
  const clearResults = () => {
    setStatus('');
    setPdfUrl('');
    setErrorMessage('');
  };

  /**
   * Convertir un document en PDF
   */
  const convertDocument = async (content: string, template: TemplateOptions, conversionMethod: string = 'pandoc_direct'): Promise<void> => {
    if (!template.layout) {
      setStatus('Erreur');
      setErrorMessage('Veuillez sélectionner une mise en page');
      return;
    }
    
    try {
      setIsLoading(true);
      setStatus('Conversion en cours...');
      setErrorMessage('');
      setPdfUrl('');
      
      // Vérifier si c'est un template utilisateur et générer une erreur si pas d'userId
      if (template.isUserTemplate && !template.userId) {
        throw new Error("Template utilisateur détecté sans userId. L'ID utilisateur est obligatoire pour utiliser un template personnalisé.");
      }

      const response = await axios.post(`${API_URL}/api/convert`, {
        content,
        template,
        conversionMethod
      }, {
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (response.data.status === 'success') {
        const fullPdfUrl = buildFullPdfUrl(response.data.pdf_url);
        handleSuccess({
          pdfUrl: fullPdfUrl,
          documentId: response.data.document_id,
          metadata: response.data.metadata, // Pass metadata to handleSuccess
          filename: response.data.filename, // Pass filename to handleSuccess
          creation_time: response.data.creation_time // Pass creation_time to handleSuccess
        }, content, template, 'convert');
      } else {
        throw new Error(response.data.message || 'Erreur inconnue');
      }
    } catch (error: any) {
      handleError(error, 'de la conversion');
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Compiler une couverture
   */
  const compileCover = async (content: string, template: TemplateOptions, conversionMethod: string = 'pandoc_direct'): Promise<void> => {
    if (!template.cover) {
      setStatus('Erreur');
      setErrorMessage('Veuillez sélectionner une couverture');
      return;
    }
    
    try {
      setIsLoading(true);
      setStatus('Compilation de la couverture en cours...');
      setErrorMessage('');
      setPdfUrl('');
      
      // Vérifier si c'est un template utilisateur et générer une erreur si pas d'userId
      if (template.coverIsUserTemplate && !template.userId) {
        throw new Error("Template utilisateur de couverture détecté sans userId. L'ID utilisateur est obligatoire pour utiliser un template personnalisé.");
      }
      
      const response = await axios.post(`${API_URL}/api/compile-cover`, {
        content,
        template,
        conversionMethod
      }, {
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (response.data.status === 'success') {
        const fullPdfUrl = buildFullPdfUrl(response.data.pdf_url);
        handleSuccess({
          pdfUrl: fullPdfUrl,
          documentId: response.data.document_id,
          metadata: response.data.metadata, // Pass metadata to handleSuccess
          filename: response.data.filename, // Pass filename to handleSuccess
          creation_time: response.data.creation_time // Pass creation_time to handleSuccess
        }, content, template, 'compileCover');
      } else {
        throw new Error(response.data.message || 'Erreur inconnue');
      }
    } catch (error: any) {
      handleError(error, 'de la compilation de la couverture');
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Imposer un document
   */
  const imposeDocument = async (content: string, template: TemplateOptions, conversionMethod: string = 'pandoc_direct'): Promise<void> => {
    if (!template.layout) {
      setStatus('Erreur');
      setErrorMessage('Veuillez sélectionner une mise en page');
      return;
    }
    
    if (!template.impose) {
      setStatus('Erreur');
      setErrorMessage('Veuillez sélectionner une imposition');
      return;
    }
    
    try {
      setIsLoading(true);
      setStatus('Imposition en cours...');
      setErrorMessage('');
      setPdfUrl('');
      
      // Vérifier si c'est un template utilisateur d'imposition et générer une erreur si pas d'userId
      if ((template.isUserTemplate || template.imposeIsUserTemplate) && !template.userId) {
        throw new Error("Template utilisateur d'imposition détecté sans userId. L'ID utilisateur est obligatoire pour utiliser un template personnalisé.");
      }
      
      const response = await axios.post(`${API_URL}/api/impose`, {
        content,
        template,
        conversionMethod
      }, {
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (response.data.status === 'success') {
        const fullPdfUrl = buildFullPdfUrl(response.data.pdf_url);
        handleSuccess({
          pdfUrl: fullPdfUrl,
          documentId: response.data.document_id,
          metadata: response.data.metadata, // Pass metadata to handleSuccess
          filename: response.data.filename, // Pass filename to handleSuccess
          creation_time: response.data.creation_time // Pass creation_time to handleSuccess
        }, content, template, 'imposeDocument');
      } else {
        throw new Error(response.data.message || 'Erreur inconnue');
      }
    } catch (error: any) {
      handleError(error, 'de l\'imposition');
    } finally {
      setIsLoading(false);
    }
  };

  return {
    isLoading,
    status,
    pdfUrl,
    errorMessage,
    documentHistory,
    convertDocument,
    compileCover,
    imposeDocument,
    clearResults
  };
}; 