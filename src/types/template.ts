export interface Template {
  id: string;
  name: string;
  type: 'layout' | 'cover' | 'impose';
  path: string;
  previewPath?: string;
  isUserTemplate?: boolean;
  userId?: number;
  createdAt?: string;
} 