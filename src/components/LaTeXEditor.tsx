import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'next-i18next';
import { FaSave, FaDownload, FaUndo, FaRedo, FaEye, FaEyeSlash } from 'react-icons/fa';

interface LaTeXEditorProps {
  content: string;
  onChange: (content: string) => void;
  onSave: () => void;
  templateType: 'layout' | 'cover' | 'impose';
  templateName: string;
}

const LaTeXEditor: React.FC<LaTeXEditorProps> = ({
  content,
  onChange,
  onSave,
  templateType,
  templateName
}) => {
  const { t } = useTranslation('translation');
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const [isDirty, setIsDirty] = useState(false);
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const [undoStack, setUndoStack] = useState<string[]>([]);
  const [redoStack, setRedoStack] = useState<string[]>([]);
  const [showPreview, setShowPreview] = useState(false);
  const [cursorPosition, setCursorPosition] = useState(0);

  // Sauvegarde automatique toutes les 30 secondes si modifié
  useEffect(() => {
    if (!isDirty) return;

    const autoSaveTimer = setTimeout(() => {
      onSave();
      setIsDirty(false);
      setLastSaved(new Date());
    }, 30000);

    return () => clearTimeout(autoSaveTimer);
  }, [content, isDirty, onSave]);

  // Gérer les changements avec historique
  const handleChange = (newContent: string) => {
    // Ajouter à l'historique undo
    setUndoStack(prev => [...prev, content]);
    setRedoStack([]); // Vider redo quand on fait une nouvelle modification
    
    onChange(newContent);
    setIsDirty(true);
  };

  // Annuler
  const handleUndo = () => {
    if (undoStack.length === 0) return;
    
    const previousContent = undoStack[undoStack.length - 1];
    const newUndoStack = undoStack.slice(0, -1);
    
    setRedoStack(prev => [content, ...prev]);
    setUndoStack(newUndoStack);
    onChange(previousContent);
    setIsDirty(true);
  };

  // Rétablir
  const handleRedo = () => {
    if (redoStack.length === 0) return;
    
    const nextContent = redoStack[0];
    const newRedoStack = redoStack.slice(1);
    
    setUndoStack(prev => [...prev, content]);
    setRedoStack(newRedoStack);
    onChange(nextContent);
    setIsDirty(true);
  };

  // Sauvegarder manuellement
  const handleManualSave = () => {
    onSave();
    setIsDirty(false);
    setLastSaved(new Date());
  };

  // Télécharger le template
  const handleDownload = () => {
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${templateName}.tex`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  };

  // Insérer du texte à la position du curseur
  const insertText = useCallback((text: string) => {
    if (!textareaRef.current) return;
    
    const textarea = textareaRef.current;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    
    const newContent = content.substring(0, start) + text + content.substring(end);
    onChange(newContent);
    
    // Positionner le curseur après le texte inséré
    setTimeout(() => {
      textarea.focus();
      textarea.setSelectionRange(start + text.length, start + text.length);
    }, 0);
  }, [content, onChange]);

  // Gérer les raccourcis clavier
  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    // Ctrl+S pour sauvegarder
    if (e.ctrlKey && e.key === 's') {
      e.preventDefault();
      handleManualSave();
    }
    
    // Ctrl+Z pour annuler
    if (e.ctrlKey && e.key === 'z') {
      e.preventDefault();
      handleUndo();
    }
    
    // Ctrl+Y pour rétablir
    if (e.ctrlKey && e.key === 'y') {
      e.preventDefault();
      handleRedo();
    }
    
    // Tab pour indenter
    if (e.key === 'Tab') {
      e.preventDefault();
      insertText('  '); // 2 espaces
    }
  }, [handleManualSave, handleUndo, handleRedo, insertText]);

  // Mettre à jour la position du curseur
  const handleCursorChange = () => {
    if (textareaRef.current) {
      setCursorPosition(textareaRef.current.selectionStart);
    }
  };

  return (
    <div className="h-full flex flex-col">
      {/* En-tête de l'éditeur */}
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
            {t('latex_editor')}
          </h3>
          <p className="text-sm text-gray-600 dark:text-gray-400">
            {templateType}: {templateName}
          </p>
        </div>
        
        {/* Barre d'outils */}
        <div className="flex items-center space-x-2">
          <button
            onClick={() => setShowPreview(!showPreview)}
            className={`p-2 text-sm rounded-md transition-colors ${
              showPreview 
                ? 'bg-blue-500 text-white' 
                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
            }`}
            title={showPreview ? t('hide_preview') : t('show_preview')}
          >
            {showPreview ? <FaEyeSlash /> : <FaEye />}
          </button>
          
          <button
            onClick={handleUndo}
            disabled={undoStack.length === 0}
            className="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title={`${t('undo')} (Ctrl+Z)`}
          >
            <FaUndo />
          </button>
          
          <button
            onClick={handleRedo}
            disabled={redoStack.length === 0}
            className="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
            title={`${t('redo')} (Ctrl+Y)`}
          >
            <FaRedo />
          </button>
          
          <button
            onClick={handleManualSave}
            className="flex items-center px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm rounded-md transition-colors"
            title={`${t('save')} (Ctrl+S)`}
          >
            <FaSave className="mr-2" />
            {t('save')}
          </button>
          
          <button
            onClick={handleDownload}
            className="flex items-center px-3 py-2 bg-green-500 hover:bg-green-600 text-white text-sm rounded-md transition-colors"
            title={t('download_template')}
          >
            <FaDownload className="mr-2" />
            {t('download_template')}
          </button>
        </div>
      </div>

      {/* Statut de sauvegarde */}
      <div className="mb-3 flex items-center justify-between">
        <div className="flex items-center space-x-4">
          {isDirty && (
            <span className="text-orange-500 dark:text-orange-400 text-sm flex items-center">
              <div className="w-2 h-2 bg-orange-500 rounded-full mr-2 animate-pulse"></div>
              {t('unsaved_changes')}
            </span>
          )}
          
          {lastSaved && (
            <span className="text-gray-500 dark:text-gray-400 text-sm">
              {t('last_saved')}: {lastSaved.toLocaleTimeString()}
            </span>
          )}
        </div>
        
        <div className="text-xs text-gray-500 dark:text-gray-400">
          {t('auto_save_enabled')} • Ln {Math.floor(cursorPosition / 80) + 1}, Col {cursorPosition % 80 + 1}
        </div>
      </div>

      {/* Zone d'édition avec prévisualisation */}
      <div className="flex-1 flex space-x-4">
        {/* Éditeur principal */}
        <div className="flex-1 relative">
          <textarea
            ref={textareaRef}
            value={content}
            onChange={(e) => handleChange(e.target.value)}
            onKeyDown={handleKeyDown}
            onSelect={handleCursorChange}
            onKeyUp={handleCursorChange}
            onMouseUp={handleCursorChange}
            className="w-full h-full p-4 font-mono text-sm bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-300 dark:border-gray-600 rounded-lg resize-none focus:ring-2 focus:ring-blue-500 focus:border-transparent leading-relaxed"
            placeholder={`% Template LaTeX ${templateType}
% Utilisez les variables disponibles comme $title, $author, etc.

\\documentclass[12pt]{article}
\\usepackage[utf8]{inputenc}
\\usepackage{geometry}

\\title{$title}
\\author{$author}
\\date{$date}

\\begin{document}

% Votre contenu ici...

\\end{document}`}
            spellCheck={false}
            wrap="off"
          />
          
          {/* Numérotation des lignes */}
          <div className="absolute left-0 top-0 bottom-0 w-12 bg-gray-100 dark:bg-gray-800 border-r border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400 overflow-hidden">
            {content.split('\n').map((_, index) => (
              <div key={index} className="px-2 py-1 text-right">
                {index + 1}
              </div>
            ))}
          </div>
        </div>

        {/* Prévisualisation LaTeX */}
        {showPreview && (
          <div className="w-1/3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg p-4 overflow-auto">
            <h4 className="text-sm font-medium text-gray-900 dark:text-white mb-3">
              {t('latex_preview')}
            </h4>
            <div className="text-xs font-mono text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
              {content}
            </div>
          </div>
        )}
      </div>

      {/* Aide rapide et raccourcis */}
      <div className="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
        <h4 className="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">
          💡 {t('quick_tips')} & ⌨️ {t('keyboard_shortcuts')}
        </h4>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <h5 className="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1">
              {t('latex_basics')}:
            </h5>
            <ul className="text-xs text-blue-700 dark:text-blue-300 space-y-1">
              <li>• {t('use_variables')}: $title, $author, $date</li>
              <li>• {t('latex_commands')}: \\documentclass, \\usepackage</li>
              <li>• {t('comments')}: % pour les commentaires</li>
              <li>• {t('auto_save_info')}</li>
            </ul>
          </div>
          <div>
            <h5 className="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1">
              {t('shortcuts')}:
            </h5>
            <ul className="text-xs text-blue-700 dark:text-blue-300 space-y-1">
              <li>• <kbd className="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs">Ctrl+S</kbd> {t('save')}</li>
              <li>• <kbd className="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs">Ctrl+Z</kbd> {t('undo')}</li>
              <li>• <kbd className="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs">Ctrl+Y</kbd> {t('redo')}</li>
              <li>• <kbd className="px-1 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs">Tab</kbd> {t('indent')}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LaTeXEditor;
