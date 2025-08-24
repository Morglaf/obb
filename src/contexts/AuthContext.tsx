import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import axios from 'axios';
import { AuthContextType, LoginCredentials, RegisterCredentials, User } from '../types/auth';

// Valeurs par défaut du contexte
const defaultAuthContext: AuthContextType = {
  isAuthenticated: false,
  user: null,
  token: null,
  loading: false,
  error: null,
  login: async () => {},
  register: async () => {},
  logout: () => {},
  clearError: () => {},
};

// Création du contexte
const AuthContext = createContext<AuthContextType>(defaultAuthContext);

// Hook personnalisé pour utiliser le contexte
export const useAuth = () => useContext(AuthContext);

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Vérifier s'il y a un token au chargement
  useEffect(() => {
    const storedToken = localStorage.getItem('auth_token');
    if (storedToken) {
      setToken(storedToken);
      fetchUserData(storedToken);
    } else {
      setLoading(false);
    }
  }, []);

  // Configurer l'intercepteur pour les requêtes authentifiées
  useEffect(() => {
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
      delete axios.defaults.headers.common['Authorization'];
    }
  }, [token]);

  // Récupérer les données de l'utilisateur avec le token
  const fetchUserData = async (authToken: string) => {
    try {
      setLoading(true);
      const response = await axios.get('/api/auth/me', {
        headers: {
          Authorization: `Bearer ${authToken}`
        }
      });
      
      if (response.data.user) {
        setUser(response.data.user);
        setIsAuthenticated(true);
      } else {
        // Token invalide
        localStorage.removeItem('auth_token');
        setToken(null);
        setIsAuthenticated(false);
      }
    } catch (err) {
      console.error('Erreur lors de la récupération des données utilisateur:', err);
      localStorage.removeItem('auth_token');
      setToken(null);
      setIsAuthenticated(false);
      setError('Session expirée. Veuillez vous reconnecter.');
    } finally {
      setLoading(false);
    }
  };

  // Connexion
  const login = async (credentials: LoginCredentials) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await axios.post('/api/auth/login', credentials);
      
      const { token: authToken, user: userData } = response.data;
      
      localStorage.setItem('auth_token', authToken);
      setToken(authToken);
      setUser(userData);
      setIsAuthenticated(true);
    } catch (err: any) {
      console.error('Erreur de connexion:', err);
      setError(err.response?.data?.message || 'Erreur de connexion. Veuillez réessayer.');
    } finally {
      setLoading(false);
    }
  };

  // Inscription
  const register = async (credentials: RegisterCredentials) => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await axios.post('/api/auth/register', credentials);
      
      const { token: authToken, user: userData } = response.data;
      
      localStorage.setItem('auth_token', authToken);
      setToken(authToken);
      setUser(userData);
      setIsAuthenticated(true);
    } catch (err: any) {
      console.error('Erreur d\'inscription:', err);
      setError(err.response?.data?.message || 'Erreur d\'inscription. Veuillez réessayer.');
    } finally {
      setLoading(false);
    }
  };

  // Déconnexion
  const logout = () => {
    localStorage.removeItem('auth_token');
    setToken(null);
    setUser(null);
    setIsAuthenticated(false);
  };

  // Effacer les erreurs
  const clearError = () => {
    setError(null);
  };

  const value = {
    isAuthenticated,
    user,
    token,
    loading,
    error,
    login,
    register,
    logout,
    clearError,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export default AuthContext; 