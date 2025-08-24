import { useState } from 'react';
import { useTranslation } from 'next-i18next';
import { useAuth } from '../contexts/AuthContext';
import axios from 'axios';
import { 
  Paper,
  Typography,
  TextField,
  Button,
  Snackbar,
  Alert,
  Box
} from '@mui/material';
import { FaPaperPlane } from 'react-icons/fa';

interface CommentFormProps {
  onCommentSent?: () => void;
}

const CommentForm = ({ onCommentSent }: CommentFormProps) => {
  const { t } = useTranslation('translation');
  const { token } = useAuth();
  
  // État du formulaire
  const [message, setMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  
  // État des messages
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  
  // Envoyer le commentaire
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!message.trim()) {
      setError(t('comment_empty_error'));
      return;
    }
    
    try {
      setIsLoading(true);
      
      await axios.post('/api/comments', { message }, {
        headers: { Authorization: `Bearer ${token}` }
      });
      
      setSuccess(t('comment_sent_success'));
      setMessage('');
      
      // Appeler le callback si fourni
      if (onCommentSent) {
        onCommentSent();
      }
      
    } catch (err) {
      console.error('Erreur lors de l\'envoi du commentaire', err);
      setError(t('comment_error'));
    } finally {
      setIsLoading(false);
    }
  };
  
  return (
    <Paper className="p-5 mb-5">
      <Typography variant="h6" component="h2" gutterBottom>
        {t('send_comment_to_admin')}
      </Typography>
      
      <Typography variant="body2" color="textSecondary" paragraph>
        {t('comment_description')}
      </Typography>
      
      <form onSubmit={handleSubmit}>
        <TextField
          label={t('your_message')}
          multiline
          rows={4}
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          fullWidth
          margin="normal"
          variant="outlined"
          required
        />
        
        <Box sx={{ display: 'flex', justifyContent: 'flex-end', mt: 2 }}>
          <Button
            type="submit"
            variant="contained"
            color="primary"
            startIcon={<FaPaperPlane />}
            disabled={isLoading || !message.trim()}
          >
            {isLoading ? t('sending') : t('send')}
          </Button>
        </Box>
      </form>
      
      {/* Snackbar pour les messages d'erreur */}
      <Snackbar
        open={!!error}
        autoHideDuration={6000}
        onClose={() => setError(null)}
      >
        <Alert onClose={() => setError(null)} severity="error">
          {error}
        </Alert>
      </Snackbar>
      
      {/* Snackbar pour les messages de succès */}
      <Snackbar
        open={!!success}
        autoHideDuration={6000}
        onClose={() => setSuccess(null)}
      >
        <Alert onClose={() => setSuccess(null)} severity="success">
          {success}
        </Alert>
      </Snackbar>
    </Paper>
  );
};

export default CommentForm; 