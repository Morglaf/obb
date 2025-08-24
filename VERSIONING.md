# Système de Versioning Automatique - Online Book Brew

## 🎯 **Vue d'ensemble**

Ce système de versioning automatique incrémente automatiquement le numéro de build à chaque déploiement et affiche les informations de version dans le footer de l'application.

## 📁 **Fichiers impliqués**

- `api/version.json` - Fichier de version principal
- `deploy-to-morglaf.ps1` - Script PowerShell avec gestion automatique des versions
- `.git/hooks/post-commit` - Hook Git Bash pour incrémentation automatique
- `.git/hooks/post-commit.ps1` - Hook Git PowerShell alternatif
- `api/routes/api.php` - Route API `/api/version`
- `src/hooks/useVersion.ts` - Hook React pour récupérer la version
- `src/components/Footer.tsx` - Affichage de la version dans le footer

## 🔄 **Fonctionnement automatique**

### 1. **Incrémentation automatique**
- À chaque commit, le hook Git `post-commit` s'exécute automatiquement
- Le numéro de build est incrémenté automatiquement
- Les informations Git (commit hash, date) sont mises à jour
- Le fichier `version.json` est mis à jour et commité automatiquement

### 2. **Format de version**
```json
{
  "version": "1.0.0",           // Version majeure (manuelle)
  "build": 15,                   // Numéro de build (auto-incrémenté)
  "last_deploy": "1.0.0.15",    // Version complète
  "git_commit": "a1b2c3d",      // Hash du dernier commit
  "deploy_date": "2025-01-27 14:30:00"  // Date du déploiement
}
```

### 3. **Affichage dans l'interface**
Le footer affiche automatiquement :
- **Version** : `v1.0.0.15` avec icône de branche
- **Date** : `27/01/2025` avec icône de calendrier  
- **Commit** : `#a1b2c3d` (hash court)

## 🚀 **Utilisation**

### **Déploiement normal**
```powershell
.\deploy-to-morglaf.ps1
```
- Met à jour automatiquement la version
- Déploie sur le serveur
- Affiche la nouvelle version dans le footer

### **Déploiement rapide**
```powershell
.\deploy-to-morglaf.ps1 -FastDeploy
```
- Version mise à jour automatiquement
- Déploiement incrémental

### **Sans Git (déploiement direct)**
```powershell
.\deploy-to-morglaf.ps1 -NoGit
```
- Version mise à jour localement
- Pas de push vers GitHub

## 🔧 **Configuration**

### **Changer la version majeure**
Éditer manuellement `api/version.json` :
```json
{
  "version": "2.0.0",  // Changer ici
  "build": 0,           // Remettre à 0
  ...
}
```

### **Désactiver l'auto-commit**
Si vous ne voulez pas que le hook commit automatiquement :
```bash
chmod -x .git/hooks/post-commit
```

## 📊 **Exemples de versions**

| Commit | Version | Build | Affichage |
|--------|---------|-------|-----------|
| Initial | 1.0.0 | 0 | v1.0.0.0 |
| Premier déploiement | 1.0.0 | 1 | v1.0.0.1 |
| Correction bug | 1.0.0 | 2 | v1.0.0.2 |
| Nouvelle fonctionnalité | 1.1.0 | 0 | v1.1.0.0 |

## 🐛 **Dépannage**

### **Version ne s'affiche pas**
1. Vérifier que l'API `/api/version` fonctionne
2. Vérifier les logs du conteneur PHP
3. Vérifier que `api/version.json` existe et est accessible

### **Version ne s'incrémente pas**
1. Vérifier que les hooks Git sont exécutables
2. Vérifier les permissions sur `.git/hooks/`
3. Vérifier que `jq` est installé (pour le hook Bash)

### **Erreur lors du déploiement**
1. Vérifier que le fichier `version.json` est valide JSON
2. Vérifier les permissions d'écriture
3. Vérifier la connexion Git

## 🔮 **Évolutions futures**

- **Tags Git automatiques** : Créer des tags Git pour chaque version
- **Changelog automatique** : Générer un changelog basé sur les commits
- **Notifications** : Notifier l'équipe des nouveaux déploiements
- **Rollback** : Système de retour en arrière automatique
- **Métriques** : Statistiques de déploiement et de stabilité

---

**Note** : Ce système est conçu pour fonctionner avec l'infrastructure Docker existante et s'intègre parfaitement avec le workflow de déploiement actuel.
