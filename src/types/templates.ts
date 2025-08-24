/**
 * Types et interfaces liés aux templates de mise en page
 */

/**
 * Interface pour les options de mise en page
 */
export interface LayoutOption {
  name: string;
  type: 'boolean' | 'text' | 'image';
  default?: boolean | string;
  description?: string;
}

/**
 * Interface pour les métadonnées de mise en page
 */
export interface LayoutMetadata {
  name: string;
  path: string;
  style?: string;
  format?: string;
  title?: string;
  description?: string;
  version?: string;
  preview_url?: string | null;
  options: {
    booleans: LayoutOption[];
    variables: LayoutOption[];
  };
  metadata?: {
    title?: string;
    description?: string;
    version?: string;
    author?: string;
    font?: string;
  };
  isUserTemplate?: boolean;
  userId?: number;
}

/**
 * Interface pour les métadonnées de couverture
 */
export interface CoverMetadata {
  name: string;
  title?: string;
  description?: string;
  version?: string;
  author?: string;
  filename?: string;
  format?: string;
  paperFormat?: string;
  isUserTemplate?: boolean;
  userId?: number;
}

/**
 * Interface pour les métadonnées d'imposition
 */
export interface ImposeMetadata {
  name: string;
  title?: string;
  description?: string;
  version?: string;
  author?: string;
  filename?: string;
  format?: string;
  paperFormat?: string;
  isSpread?: boolean;
  isUserTemplate?: boolean;
  userId?: number;
}

/**
 * Interface pour les options du template sélectionné
 */
export interface TemplateOptions {
  layout: string;
  cover: string;
  impose: string;
  booleanOptions: Record<string, boolean>;
  metadata: Record<string, string>;
  paperThickness: number;
  isUserTemplate?: boolean;
  coverIsUserTemplate?: boolean;
  imposeIsUserTemplate?: boolean;
  userId?: number;
}

/**
 * Interface pour les props du sélecteur de template
 */
export interface TemplateSelectorProps {
  onChange: (options: TemplateOptions) => void;
  layouts: LayoutMetadata[];
  covers: string[] | CoverMetadata[];
  imposes: string[] | ImposeMetadata[];
  invalidFiles?: {
    layouts: {file: string, error: string}[];
    covers: {file: string, error: string}[];
    imposes: {file: string, error: string}[];
  };
} 