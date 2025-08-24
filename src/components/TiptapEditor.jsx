'use client'

import { useState, useCallback, useEffect, useRef } from 'react'
import { useEditor, EditorContent } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import MarkdownIt from 'markdown-it'
import texmath from 'markdown-it-texmath'
import katex from 'katex'
import 'katex/dist/katex.min.css'

// Initialisation de markdown-it avec le support des équations
const md = new MarkdownIt({
  html: true, // Permettre le HTML pour les sauts de ligne
  breaks: true, // Conserver les sauts de ligne simples
  linkify: true,
  typographer: true
})

// Support des tableaux Markdown (syntaxe standard)
md.renderer.rules.table_open = function() {
  return '<table class="markdown-table">';
};

md.renderer.rules.table_close = function() {
  return '</table>';
};

md.renderer.rules.thead_open = function() {
  return '<thead>';
};

md.renderer.rules.thead_close = function() {
  return '</thead>';
};

md.renderer.rules.tbody_open = function() {
  return '<tbody>';
};

md.renderer.rules.tbody_close = function() {
  return '</tbody>';
};

md.renderer.rules.tr_open = function() {
  return '<tr>';
};

md.renderer.rules.tr_close = function() {
  return '</tr>';
};

md.renderer.rules.th_open = function() {
  return '<th>';
};

md.renderer.rules.th_close = function() {
  return '</th>';
};

md.renderer.rules.td_open = function() {
  return '<td>';
};

md.renderer.rules.td_close = function() {
  return '</td>';
};

// Activation du plugin texmath avec KaTeX
md.use(texmath, {
  engine: katex,
  delimiters: 'dollars',
  katexOptions: { macros: { "\\RR": "\\mathbb{R}" } }
})

// Composant pour un bouton de la barre d'outils
const ToolbarButton = ({ icon, title, onClick, isActive = false }) => (
  <button
    onClick={onClick}
    className={`px-2 py-1 border rounded text-sm font-medium focus:outline-none ${
      isActive 
        ? 'bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 border-indigo-300 dark:border-indigo-700' 
        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'
    }`}
    title={title}
  >
    {icon}
  </button>
)

const TiptapEditor = ({ 
  content = '', 
  onUpdate,
  isDark = false 
}) => {
  // État local pour le mode source et le contenu markdown
  const [isSourceMode, setIsSourceMode] = useState(false);
  const [markdown, setMarkdown] = useState(content); 
  const [lastSyncedMarkdown, setLastSyncedMarkdown] = useState(content);
  const [isClient, setIsClient] = useState(false);
  const isSyncing = useRef(false);
  
  // Effet pour détecter le rendu côté client
  useEffect(() => {
    setIsClient(true);
  }, []);
  
  // Convertir HTML en markdown pour le mode source
  const htmlToMarkdown = (html) => {
    if (!html) return '';
    
    // Traitement spécial des marqueurs de paragraphe
    let preprocessedHtml = html
      // Normaliser les marqueurs de paragraphe
      .replace(/<p>\s*&nbsp;\s*<\/p>/g, '<p class="empty-paragraph"></p>')
      .replace(/<p><br><\/p>/g, '<p class="empty-paragraph"></p>')
      .replace(/<p>\s*<\/p>/g, '<p class="empty-paragraph"></p>')
      .replace(/<div><br><\/div>/g, '<p class="empty-paragraph"></p>')
      .replace(/<div>\s*<\/div>/g, '<p class="empty-paragraph"></p>');
    
    // Simplification du HTML
    let cleanHtml = preprocessedHtml
      .replace(/<li>(.*?)<\/li>/g, (match, content) => {
        return '<li>' + content.replace(/<\/?p[^>]*>/g, '') + '</li>';
      })
      .replace(/<p[^>]*class="[^"]*"[^>]*>(.*?)<\/p>/g, function(match, content) {
        if (match.includes('empty-paragraph')) {
          return '\n\n';
        }
        return '<p>' + content + '</p>';
      });
    
    // Conversion de base
    let md = cleanHtml
      .replace(/<p[^>]*>(.*?)<\/p>/g, '$1\n\n')
      .replace(/<h1[^>]*>(.*?)<\/h1>/g, '# $1\n\n')
      .replace(/<h2[^>]*>(.*?)<\/h2>/g, '## $1\n\n')
      .replace(/<h3[^>]*>(.*?)<\/h3>/g, '### $1\n\n')
      .replace(/<h4[^>]*>(.*?)<\/h4>/g, '#### $1\n\n')
      .replace(/<h5[^>]*>(.*?)<\/h5>/g, '##### $1\n\n')
      .replace(/<h6[^>]*>(.*?)<\/h6>/g, '###### $1\n\n')
      .replace(/<strong>(.*?)<\/strong>/g, '**$1**')
      .replace(/<b>(.*?)<\/b>/g, '**$1**')
      .replace(/<em>(.*?)<\/em>/g, '*$1*')
      .replace(/<i>(.*?)<\/i>/g, '*$1*')
      .replace(/<s>(.*?)<\/s>/g, '~~$1~~')
      .replace(/<del>(.*?)<\/del>/g, '~~$1~~')
      .replace(/<a href="(.*?)"[^>]*>(.*?)<\/a>/g, '[$2]($1)')
      .replace(/<img src="(.*?)"[^>]*>/g, '![]($1)')
      .replace(/<ul[^>]*>([\s\S]*?)<\/ul>/g, function(match, content) {
        return content.replace(/<li[^>]*>([\s\S]*?)<\/li>/g, '- $1\n') + '\n';
      })
      .replace(/<ol[^>]*>([\s\S]*?)<\/ol>/g, function(match, content) {
        let index = 1;
        return content.replace(/<li[^>]*>([\s\S]*?)<\/li>/g, function(match, item) {
          return `${index++}. ${item}\n`;
        }) + '\n';
      })
      .replace(/<blockquote[^>]*>([\s\S]*?)<\/blockquote>/g, '> $1\n\n')
      .replace(/<code[^>]*>(.*?)<\/code>/g, '`$1`')
      .replace(/<pre[^>]*><code[^>]*>(.*?)<\/code><\/pre>/g, '```\n$1\n```\n\n')
      .replace(/<hr[^>]*>/g, '---\n\n')
      // Gestion des tableaux - DOIT être fait AVANT la suppression générale des balises
      .replace(/<table[^>]*>([\s\S]*?)<\/table>/g, function(match, content) {
        let tableMd = '\n';
        // Traiter chaque ligne
        const rows = content.match(/<tr[^>]*>([\s\S]*?)<\/tr>/g) || [];
        rows.forEach((row, rowIndex) => {
          const cells = row.match(/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/g) || [];
          if (cells.length > 0) {
            const cellContents = cells.map(cell => {
              const cellContent = cell.replace(/<(?:th|td)[^>]*>([\s\S]*?)<\/(?:th|td)>/g, '$1');
              return cellContent.trim();
            });
            tableMd += '| ' + cellContents.join(' | ') + ' |\n';
            
            // Ajouter la ligne de séparation après l'en-tête
            if (rowIndex === 0) {
              tableMd += '|' + cellContents.map(() => ' --- ').join('|') + '|\n';
            }
          }
        });
        return tableMd;
      })
      .replace(/<br\s*\/?>\s*<br\s*\/?>/g, '\n\n')
      .replace(/<br\s*\/?>/g, '\n')
      .replace(/&nbsp;/g, ' ')
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&amp;/g, '&')
      .replace(/&quot;/g, '"')
      // Supprimer toutes les balises HTML restantes (SAUF les tableaux déjà traités)
      .replace(/<(?!\/?table|\/?thead|\/?tbody|\/?tr|\/?th|\/?td)[^>]*>/g, '')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
    
    return md;
  };

  // Convertir markdown en HTML pour l'éditeur visuel
  const markdownToHtml = (markdownText) => {
    if (!markdownText) return '';
    
    // Approche plus simple et compatible pour préserver les paragraphes
    // On traite uniquement les sauts de ligne entre contenus (pas au début ou fin)
    let lines = markdownText.split('\n');
    let result = [];
    let emptyLineCount = 0;
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      
      if (line.trim() === '') {
        emptyLineCount++;
        // Si on a un saut de paragraphe (deux lignes vides consécutives)
        if (emptyLineCount === 2) {
          // Ajouter un marqueur de paragraphe vide
          result.push('<p>&nbsp;</p>');
          emptyLineCount = 0;
        }
      } else {
        // Si on avait accumulé une seule ligne vide, on la préserve
        if (emptyLineCount === 1) {
          result.push('');
        }
        result.push(line);
        emptyLineCount = 0;
      }
    }
    
    return md.render(result.join('\n'));
  };

  // Initialiser l'éditeur standard
  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        paragraph: {
          HTMLAttributes: {
            class: '',
          },
        },
      }),
      // Extensions pour les tableaux
      Table.configure({
        resizable: true,
        HTMLAttributes: {
          class: 'markdown-table',
        },
      }),
      TableRow,
      TableHeader,
      TableCell,
    ],
    content: markdownToHtml(content),
    autofocus: false,
    immediatelyRender: false,
    onUpdate: ({ editor }) => {
      if (isSyncing.current) return;
      
      // Uniquement mettre à jour si explicitement demandé
      if (onUpdate) {
        const html = editor.getHTML();
        const mdContent = htmlToMarkdown(html);
        onUpdate(mdContent);
      }
    },
    
  });

  // Basculer entre les modes (visuel/source)
  const toggleSourceMode = useCallback(() => {
    if (isSourceMode) {
      // Passage du mode source au mode visuel
      isSyncing.current = true;
      
      // Conversion directe sans prétraitement
      const htmlContent = markdownToHtml(markdown);
      
      // Application après un délai pour éviter des problèmes de timing
      setTimeout(() => {
        editor?.commands.setContent(htmlContent);
        setLastSyncedMarkdown(markdown);
        isSyncing.current = false;
      }, 0);
    } else {
      // Passage du mode visuel au mode source
      const htmlContent = editor?.getHTML() || '';
      const mdContent = htmlToMarkdown(htmlContent);
      setMarkdown(mdContent);
      setLastSyncedMarkdown(mdContent);
    }
    setIsSourceMode(!isSourceMode);
  }, [isSourceMode, markdown, editor]);

  // Mettre à jour le contenu source
  const handleMarkdownChange = useCallback((e) => {
    const value = e.target.value;
    setMarkdown(value);
    if (onUpdate) {
      onUpdate(value);
    }
  }, [onUpdate]);

  // Actions de formatage
  const toggleBold = useCallback(() => {
    editor?.chain().toggleBold().focus().run();
  }, [editor]);

  const toggleItalic = useCallback(() => {
    editor?.chain().toggleItalic().focus().run();
  }, [editor]);

  const toggleH1 = useCallback(() => {
    editor?.chain().toggleHeading({ level: 1 }).focus().run();
  }, [editor]);

  const toggleH2 = useCallback(() => {
    editor?.chain().toggleHeading({ level: 2 }).focus().run();
  }, [editor]);

  const toggleH3 = useCallback(() => {
    editor?.chain().toggleHeading({ level: 3 }).focus().run();
  }, [editor]);

  const toggleBulletList = useCallback(() => {
    editor?.chain().toggleBulletList().focus().run();
  }, [editor]);

  const toggleOrderedList = useCallback(() => {
    editor?.chain().toggleOrderedList().focus().run();
  }, [editor]);

  const toggleBlockquote = useCallback(() => {
    editor?.chain().toggleBlockquote().focus().run();
  }, [editor]);

  const addCodeBlock = useCallback(() => {
    editor?.chain().toggleCodeBlock().focus().run();
  }, [editor]);

  // Fonctions spéciales pour le markdown scientifique
  const addPageBreak = useCallback(() => {
    editor?.chain().focus().insertContent('<p>\\newpage</p>').run();
  }, [editor]);

  const addInlineComment = useCallback(() => {
    editor?.chain().focus().insertContent('^[commentaire ici]').run();
  }, [editor]);

  const addDoubleLineBreak = useCallback(() => {
    editor?.chain().focus().insertContent('<p><br/></p>').run();
  }, [editor]);

  // Fonctions pour les tableaux avec Tiptap
  const addTable = useCallback(() => {
    editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
  }, [editor]);

  const addTableRow = useCallback(() => {
    editor?.chain().focus().addRowAfter().run();
  }, [editor]);

  const deleteTableRow = useCallback(() => {
    editor?.chain().focus().deleteRow().run();
  }, [editor]);

  const addTableColumn = useCallback(() => {
    editor?.chain().focus().addColumnAfter().run();
  }, [editor]);

  const deleteTableColumn = useCallback(() => {
    editor?.chain().focus().deleteColumn().run();
  }, [editor]);

  const deleteTable = useCallback(() => {
    editor?.chain().focus().deleteTable().run();
  }, [editor]);

  // Styles de base
  const themeClass = isDark ? 'dark-theme' : 'light-theme';

  return (
    <div className="tiptap-editor w-full h-full flex flex-col">
      <div className="format-toolbar z-10 sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 p-1 flex flex-wrap gap-1">
        <ToolbarButton 
          icon={isSourceMode ? "Visuel" : "Source"} 
          title={isSourceMode ? "Mode visuel" : "Mode source"} 
          onClick={toggleSourceMode} 
          isActive={isSourceMode}
        />
        <span className="mx-1 text-gray-300 dark:text-gray-600">|</span>
        
        {!isSourceMode && editor && (
          <>
            <ToolbarButton 
              icon="B" 
              title="Gras" 
              onClick={toggleBold} 
              isActive={editor.isActive('bold')}
            />
            <ToolbarButton 
              icon="I" 
              title="Italique" 
              onClick={toggleItalic} 
              isActive={editor.isActive('italic')}
            />
            <span className="mx-1 text-gray-300 dark:text-gray-600">|</span>
            <ToolbarButton 
              icon="H1" 
              title="Titre 1" 
              onClick={toggleH1} 
              isActive={editor.isActive('heading', { level: 1 })}
            />
            <ToolbarButton 
              icon="H2" 
              title="Titre 2" 
              onClick={toggleH2} 
              isActive={editor.isActive('heading', { level: 2 })}
            />
            <ToolbarButton 
              icon="H3" 
              title="Titre 3" 
              onClick={toggleH3} 
              isActive={editor.isActive('heading', { level: 3 })}
            />
            <span className="mx-1 text-gray-300 dark:text-gray-600">|</span>
            <ToolbarButton 
              icon="•" 
              title="Liste à puces" 
              onClick={toggleBulletList} 
              isActive={editor.isActive('bulletList')}
            />
            <ToolbarButton 
              icon="1." 
              title="Liste numérotée" 
              onClick={toggleOrderedList} 
              isActive={editor.isActive('orderedList')}
            />
            <span className="mx-1 text-gray-300 dark:text-gray-600">|</span>
            <ToolbarButton 
              icon="❝" 
              title="Citation" 
              onClick={toggleBlockquote} 
              isActive={editor.isActive('blockquote')}
            />
            <ToolbarButton 
              icon="<>" 
              title="Bloc de code" 
              onClick={addCodeBlock} 
              isActive={editor.isActive('codeBlock')}
            />
            <span className="mx-1 text-gray-300 dark:text-gray-600">|</span>
            <ToolbarButton 
              icon="⎯⎯" 
              title="Saut de page LaTeX (\newpage)" 
              onClick={addPageBreak} 
            />
            <ToolbarButton 
              icon="^[...]" 
              title="Commentaire inline" 
              onClick={addInlineComment} 
            />
            <ToolbarButton 
              icon="¶" 
              title="Double saut de ligne" 
              onClick={addDoubleLineBreak} 
            />
            <span className="mx-1 text-gray-300 dark:text-gray-600">|</span>
            <ToolbarButton 
              icon="⊞" 
              title="Ajouter un tableau" 
              onClick={addTable} 
            />
            <ToolbarButton 
              icon="➕" 
              title="Ajouter une ligne" 
              onClick={addTableRow} 
            />
            <ToolbarButton 
              icon="➖" 
              title="Supprimer une ligne" 
              onClick={deleteTableRow} 
            />
            <ToolbarButton 
              icon="⏹️" 
              title="Ajouter une colonne" 
              onClick={addTableColumn} 
            />
            <ToolbarButton 
              icon="🗑️" 
              title="Supprimer le tableau" 
              onClick={deleteTable} 
            />
          </>
        )}
      </div>
      
      <div className="flex-grow overflow-hidden">
        {isSourceMode ? (
          <textarea
            value={markdown}
            onChange={handleMarkdownChange}
            className={`w-full h-full min-h-[450px] p-4 font-mono text-sm border border-gray-200 dark:border-gray-700 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 ${themeClass}`}
            spellCheck="false"
          />
        ) : (
          <div className={`${themeClass} h-full border border-gray-200 dark:border-gray-700 rounded-md overflow-auto`}>
            {isClient && editor && <EditorContent editor={editor} className="h-full" />}
          </div>
        )}
      </div>
    </div>
  );
};

export default TiptapEditor 