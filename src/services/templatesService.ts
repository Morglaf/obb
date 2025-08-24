import axios from 'axios';
import { Template } from '../types/template';

export const updateTableStructure = async (): Promise<{ success: boolean; message: string; changes?: string[] }> => {
  try {
    const response = await axios.post('/api/templates/update-structure');
    
    return { 
      success: true, 
      message: response.data.message || 'Structure de la table mise à jour', 
      changes: response.data.changes || []
    };
  } catch (error: any) {
    console.error('Erreur lors de la mise à jour de la structure:', error);
    return { 
      success: false, 
      message: error.response?.data?.message || 'Erreur lors de la mise à jour de la structure' 
    };
  }
};

export const fetchUserTemplates = async (): Promise<Template[]> => {
  try {
    const response = await axios.get('/api/templates/user');
    return response.data.templates || [];
  } catch (error: any) {
    // Si l'erreur est 500, essayons de mettre à jour la structure de la table et de réessayer
    if (error.response && error.response.status === 500) {
      console.warn('Erreur 500 détectée, tentative de mise à jour de la structure de la table...');
      const updateResult = await updateTableStructure();
      
      if (updateResult.success) {
        console.log('Structure mise à jour, nouvel essai de récupération des templates...');
        // Réessayer après la mise à jour
        try {
          const newResponse = await axios.get('/api/templates/user');
          return newResponse.data.templates || [];
        } catch (retryError) {
          console.error('Échec de la récupération après mise à jour:', retryError);
          throw retryError;
        }
      }
    }
    
    console.error('Erreur lors de la récupération des templates utilisateur:', error);
    throw error;
  }
};

export const uploadTemplate = async (
  file: File, 
  previewFile: File,
  name: string, 
  type: 'layout' | 'cover' | 'impose'
): Promise<{ success: boolean; message: string }> => {
  try {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('preview', previewFile);
    formData.append('name', name);
    formData.append('type', type);
    
    const response = await axios.post('/api/templates/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    return { 
      success: true, 
      message: response.data.message || 'Template uploaded successfully' 
    };
  } catch (error: any) {
    console.error('Erreur lors de l\'upload du template:', error);
    return { 
      success: false, 
      message: error.response?.data?.message || 'Erreur lors de l\'upload du template' 
    };
  }
};

export const deleteTemplate = async (templateId: string): Promise<{ success: boolean; message: string }> => {
  try {
    const response = await axios.delete(`/api/templates/${templateId}`);
    return { 
      success: true, 
      message: response.data.message || 'Template deleted successfully' 
    };
  } catch (error: any) {
    console.error('Erreur lors de la suppression du template:', error);
    return { 
      success: false, 
      message: error.response?.data?.message || 'Erreur lors de la suppression du template' 
    };
  }
};

export const cleanupCorruptedTemplates = async (): Promise<{ success: boolean; message: string; deletedCount?: number }> => {
  try {
    const response = await axios.post('/api/templates/cleanup');
    
    return { 
      success: true, 
      message: response.data.message || 'Nettoyage des templates terminé', 
      deletedCount: response.data.deleted_count || 0
    };
  } catch (error: any) {
    console.error('Erreur lors du nettoyage des templates:', error);
    return { 
      success: false, 
      message: error.response?.data?.message || 'Erreur lors du nettoyage des templates' 
    };
  }
};

export const cleanupCorruptedFonts = async (): Promise<{ success: boolean; message: string; deletedCount?: number }> => {
  try {
    const response = await axios.post('/api/fonts/cleanup');
    
    return { 
      success: true, 
      message: response.data.message || 'Nettoyage des polices terminé', 
      deletedCount: response.data.deleted_count || 0
    };
  } catch (error: any) {
    console.error('Erreur lors du nettoyage des polices:', error);
    return { 
      success: false, 
      message: error.response?.data?.message || 'Erreur lors du nettoyage des polices' 
    };
  }
};

export const getTemplateContent = async (templateId: string): Promise<any> => {
  try {
    const response = await axios.get(`/api/templates/content/${templateId}`);
    
    if (response.data.status === 'success') {
      return response.data.template;
    } else {
      throw new Error(response.data.message || 'Erreur lors de la récupération du contenu du template');
    }
  } catch (error: any) {
    console.error('Erreur lors de la récupération du contenu du template:', error);
    throw error;
  }
}; 