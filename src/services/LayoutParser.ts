import fs from 'fs';
import path from 'path';

export interface LayoutOption {
  name: string;
  type: 'boolean' | 'text' | 'image';
  default?: boolean | string;
  description?: string;
}

export interface LayoutMetadata {
  name: string;
  path: string;
  options: {
    booleans: LayoutOption[];
    variables: LayoutOption[];
  };
}

export class LayoutParser {
  private static readonly BOOLEAN_PATTERN = /\\newif\\if(\w+)\s*\\(\w+)(true|false)?/g;
  private static readonly VARIABLE_PATTERN = /\{\{(\w+)\}\}/g;
  
  static async parseLayout(layoutPath: string): Promise<LayoutMetadata> {
    const content = await fs.promises.readFile(layoutPath, 'utf-8');
    const name = path.basename(layoutPath, '.tex');
    
    const booleanOptions: LayoutOption[] = [];
    const variableOptions: LayoutOption[] = [];
    
    // Parse boolean options
    let match;
    const booleanMatches = new Set<string>();
    while ((match = this.BOOLEAN_PATTERN.exec(content)) !== null) {
      const [, optionName, , defaultValue] = match;
      if (!booleanMatches.has(optionName)) {
        booleanMatches.add(optionName);
        booleanOptions.push({
          name: optionName,
          type: 'boolean',
          default: defaultValue === 'true',
          description: this.getOptionDescription(content, optionName)
        });
      }
    }
    
    // Parse variable placeholders
    const variables = new Set<string>();
    while ((match = this.VARIABLE_PATTERN.exec(content)) !== null) {
      const [, varName] = match;
      if (!variables.has(varName)) {
        variables.add(varName);
        variableOptions.push({
          name: varName,
          type: varName.toLowerCase().includes('image') ? 'image' : 'text',
          description: this.getOptionDescription(content, varName)
        });
      }
    }
    
    return {
      name,
      path: layoutPath,
      options: {
        booleans: booleanOptions,
        variables: variableOptions
      }
    };
  }

  private static getOptionDescription(content: string, optionName: string): string {
    // Recherche des commentaires LaTeX au-dessus des options
    const lines = content.split('\n');
    for (let i = 0; i < lines.length; i++) {
      if (lines[i].includes(optionName)) {
        // Remonter pour trouver le commentaire le plus proche
        for (let j = i - 1; j >= 0 && j >= i - 3; j--) {
          const line = lines[j].trim();
          if (line.startsWith('%') && !line.startsWith('%%')) {
            return line.substring(1).trim();
          }
        }
        break;
      }
    }
    return '';
  }
  
  static async getAllLayouts(layoutsDir: string): Promise<LayoutMetadata[]> {
    try {
      const files = await fs.promises.readdir(layoutsDir);
      const layouts = await Promise.all(
        files
          .filter(file => file.endsWith('.tex'))
          .map(file => this.parseLayout(path.join(layoutsDir, file)))
      );
      return layouts;
    } catch (error) {
      console.error('Erreur lors de la lecture des layouts:', error);
      return [];
    }
  }
} 