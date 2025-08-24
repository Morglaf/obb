/**
 * Utilitaires pour le traitement des tableaux et collections
 */

import { CoverMetadata, ImposeMetadata } from '../types/templates';

/**
 * Vérifie si un tableau est composé uniquement de chaînes de caractères
 * @param arr Tableau à vérifier
 * @returns true si tous les éléments sont des chaînes
 */
export const isStringArray = (arr: unknown[]): arr is string[] => {
  return arr.length === 0 || arr.every(item => typeof item === 'string');
};

/**
 * Filtre les éléments non définis d'un tableau de couvertures
 * @param items Tableau de couvertures
 * @returns Tableau nettoyé
 */
export const getValidCovers = (items: (string | CoverMetadata)[]): (string | CoverMetadata)[] => {
  return Array.isArray(items) ? items.filter(Boolean) : [];
};

/**
 * Filtre les éléments non définis d'un tableau d'impositions
 * @param items Tableau d'impositions
 * @returns Tableau nettoyé
 */
export const getValidImposes = (items: (string | ImposeMetadata)[]): (string | ImposeMetadata)[] => {
  return Array.isArray(items) ? items.filter(Boolean) : [];
}; 