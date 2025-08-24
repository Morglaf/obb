# API OnlineBookBrew

Cette API gère la conversion de documents Markdown en PDF avec LaTeX pour l'application OnlineBookBrew.

## Structure du projet

```
api/
├── config/                 # Configuration de l'application
│   └── app.php             # Configuration centralisée
├── public/                 # Point d'entrée public
│   └── index.php           # Point d'entrée de l'API
├── routes/                 # Définition des routes
│   └── api.php             # Routes de l'API
├── src/                    # Code source
│   ├── Controllers/        # Contrôleurs pour gérer les requêtes
│   │   ├── ConversionController.php  # Conversion de documents
│   │   ├── MediaController.php       # Gestion des médias
│   │   └── TemplateController.php    # Gestion des templates
│   ├── Services/           # Services métier
│   │   ├── ConversionService.php     # Service de conversion
│   │   └── TemplateService.php       # Service de templates
│   └── Utils/              # Utilitaires
│       ├── FileUtils.php             # Utilitaires fichiers
│       └── TemplateUtils.php         # Utilitaires templates
├── vendor/                 # Dépendances (géré par Composer)
└── composer.json           # Configuration Composer
```

## Installation

1. Installer les dépendances avec Composer :

```bash
composer install
```

2. S'assurer que les dossiers requis existent et sont accessibles en écriture :
   - `workspace/`
   - `public/files/`
   - `public/uploads/`

## Fonctionnalités principales

- Conversion de documents Markdown en PDF
- Gestion des templates LaTeX (layouts, covers, impose)
- Upload et gestion d'images
- Prévisualisation de templates

## Routes API

### Conversion de documents
- `POST /api/convert` - Convertir un document Markdown en PDF
- `POST /api/compile-cover` - Compiler uniquement une couverture
- `POST /api/impose` - Imposer un document selon un plan d'imposition
- `GET /api/pdf/{id}` - Récupérer un PDF généré

### Gestion des templates
- `GET /api/layouts` - Récupérer tous les templates disponibles
- `GET /api/cover-variables/{cover}` - Récupérer les variables d'une couverture
- `GET /preview` - Prévisualiser un template

### Gestion des médias
- `POST /api/upload/image` - Uploader une image
- `GET /api/uploads/{filename}` - Récupérer une image uploadée
- `GET /workspace/{path}` - Accéder à un fichier du workspace 