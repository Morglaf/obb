# Online Book Brew

Application web permettant de créer des livres PDF à partir de Markdown.

## Fonctionnalités

- Éditeur Markdown intégré avec prévisualisation en temps réel
- Conversion vers PDF haute qualité via LaTeX intégré au conteneur processor
- Création de couvertures personnalisées
- Imposition pour l'impression de livrets (spreads ou signatures)
- Support pour diverses polices et mises en page

## Installation

### Prérequis

- Docker et Docker Compose
- PHP 8.0 ou supérieur
- Node.js et npm
- PowerShell (pour Windows) ou Bash (pour Linux/Mac)

### Étapes d'installation

1. Clonez ce dépôt :
   ```bash
   git clone https://github.com/Morglaf/obb.git
   cd obb
   ```

2. **Configuration de l'environnement** (IMPORTANT pour la sécurité) :
   ```bash
   # Copiez le fichier d'exemple
   cp env.example .env
   
   # Éditez .env avec vos vraies valeurs
   # CHANGEZ OBLIGATOIREMENT JWT_SECRET et POSTGRES_PASSWORD !
   nano .env
   ```

3. Démarrez les services avec Docker Compose :
   ```bash
   docker-compose up -d --build
   ```

4. Accédez à l'application dans votre navigateur :
   ```
   http://localhost:3000
   ```

### ⚠️ Configuration de sécurité

**AVANT de déployer en production :**
- Changez `JWT_SECRET` pour une clé forte et unique
- Changez `POSTGRES_PASSWORD` pour un mot de passe sécurisé
- Vérifiez que `.env` est bien dans `.gitignore`
- Ne commitez JAMAIS de vraies informations d'authentification

## Déploiement

### Déploiement local avec Docker

1. **Démarrez les services** :
   ```bash
   docker-compose up -d --build
   ```

2. **Accédez à l'application** :
   ```
   http://localhost:3000
   ```

### Déploiement en production

1. **Configurez votre environnement** :
   ```bash
   cp env.example .env
   # Éditez .env avec vos vraies valeurs
   ```

2. **Démarrez les services** :
   ```bash
   docker-compose up -d --build
   ```

3. **Configurez votre reverse proxy** pour pointer vers :
   - Frontend : port 3000
   - API : port 8080

## Architecture

L'application est composée de plusieurs composants :

- **Frontend** : Application Next.js avec éditeur Markdown
- **Backend API** : API PHP pour gérer les conversions
- **Base de données** : PostgreSQL pour stocker les documents
- **Conteneur processor** : Conteneur unique intégrant tous les outils de traitement
  - `pandoc` : Conversion de Markdown vers LaTeX
  - `xelatex` : Moteur LaTeX pour la génération de PDF
  - `pdftk` : Manipulation des PDF pour l'imposition

## Utilisation

1. Créez ou collez votre contenu Markdown dans l'éditeur
2. Sélectionnez un modèle de mise en page
3. Ajoutez une couverture si nécessaire
4. Choisissez les options d'imposition pour l'impression
5. Cliquez sur "Générer" pour créer votre PDF
6. Téléchargez le résultat

## Développement

Pour développer et étendre cette application :

1. Le frontend se trouve dans le dossier racine du projet
2. L'API PHP se trouve dans le dossier `api/`
3. Les modèles LaTeX sont dans le dossier `typeset/`

## Structure du projet

- `api/` : API backend en PHP (Slim Framework)
- `src/` : Frontend en React/Next.js
- `public/` : Fichiers statiques et dossiers pour les fichiers générés
- `typeset/` : Templates LaTeX, polices et fichiers de configuration
  - `layout/` : Fichiers de mise en page LaTeX
  - `cover/` : Templates de couvertures
  - `impose/` : Configurations pour l'imposition (multi-pages par feuille)
- `workspace/` : Dossier temporaire pour la génération des documents
- `.next/` : Build de Next.js (généré automatiquement)

## Fichiers Docker

- `docker-compose.yml` : Configuration Docker pour le déploiement
- `Dockerfile.frontend` : Image Docker pour le frontend Next.js
- `Dockerfile.processor` : Image Docker pour le conteneur de traitement PDF
- `Dockerfile.php-api` : Image Docker pour l'API PHP

## Commandes utiles

### Docker
```bash
# Démarrer tous les services
docker-compose up -d

# Voir les logs
docker-compose logs -f

# Arrêter tous les services
docker-compose down

# Reconstruire les images
docker-compose build --no-cache
```

### Déploiement

Pour déployer en production, utilisez les commandes Docker standard :

```bash
# Démarrer tous les services
docker-compose up -d

# Voir les logs
docker-compose logs -f

# Arrêter tous les services
docker-compose down

# Reconstruire les images
docker-compose build --no-cache
```