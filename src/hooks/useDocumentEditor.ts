import { useState, useCallback } from 'react';

// Document markdown par défaut
const DEFAULT_MARKDOWN = `# Bienvenue sur Online Book Brew

## Introduction

Ceci est un outil de conversion Markdown vers PDF utilisant LaTeX.

## Fonctionnalités

- Édition de texte avec coloration syntaxique
- Conversion vers PDF haute qualité
- Support des équations LaTeX
- Génération automatique de table des matières

## Exemples d'équations

Équation simple : $E = mc^2$

Équation de dérivée : 
$$\\frac{d}{dx}\\left( \\int_{0}^{x} f(u)\\,du\\right)=f(x)$$

Formule de sommation :
$$\\sum_{i=1}^{n} i = \\frac{n(n+1)}{2}$$

## Comment utiliser

1. Écrivez votre document en Markdown
2. Sélectionnez vos options de mise en page
3. Cliquez sur "Convertir en PDF"
4. Téléchargez le résultat
`;

interface EditorState {
  markdown: string;
  showTemplateOptions: boolean;
}

interface EditorHook extends EditorState {
  setMarkdown: (text: string) => void;
  resetMarkdown: () => void;
  clearMarkdown: () => void;
  toggleTemplateOptions: () => void;
}

/**
 * Hook personnalisé pour gérer l'édition de documents
 */
export const useDocumentEditor = (): EditorHook => {
  const [markdown, setMarkdownState] = useState<string>(DEFAULT_MARKDOWN);
  const [showTemplateOptions, setShowTemplateOptions] = useState<boolean>(false);
  
  /**
   * Mettre à jour le contenu du document
   */
  const setMarkdown = useCallback((text: string): void => {
    setMarkdownState(text);
  }, []);
  
  /**
   * Réinitialiser le document au contenu par défaut
   */
  const resetMarkdown = useCallback((): void => {
    if (confirm("Êtes-vous sûr de vouloir réinitialiser le contenu ?")) {
      setMarkdownState(DEFAULT_MARKDOWN);
    }
  }, []);
  
  /**
   * Vider complètement l'éditeur
   */
  const clearMarkdown = useCallback((): void => {
    if (confirm("Êtes-vous sûr de vouloir vider complètement l'éditeur ?")) {
      setMarkdownState('');
    }
  }, []);
  
  /**
   * Basculer l'affichage des options de template
   */
  const toggleTemplateOptions = useCallback((): void => {
    setShowTemplateOptions(prev => !prev);
  }, []);
  
  return {
    markdown,
    showTemplateOptions,
    setMarkdown,
    resetMarkdown,
    clearMarkdown,
    toggleTemplateOptions
  };
}; 