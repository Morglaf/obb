const fs = require('fs');
const { execSync } = require('child_process');
const path = require('path');

// Fonction pour exécuter une commande Git et retourner le résultat
function getGitInfo() {
  try {
    // Récupérer le hash du dernier commit
    const commitHash = execSync('git rev-parse --short HEAD', { encoding: 'utf8' }).trim();
    
    // Récupérer la date du dernier commit
    const commitDate = execSync('git log -1 --format=%cd --date=short', { encoding: 'utf8' }).trim();
    
    // Récupérer le nombre total de commits
    const commitCount = execSync('git rev-list --count HEAD', { encoding: 'utf8' }).trim();
    
    // Récupérer la branche actuelle
    const branch = execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
    
    return {
      commitHash,
      commitDate,
      commitCount,
      branch
    };
  } catch (error) {
    console.warn('Git non disponible ou pas de repository:', error.message);
    return {
      commitHash: 'unknown',
      commitDate: new Date().toISOString().split('T')[0],
      commitCount: '0',
      branch: 'unknown'
    };
  }
}

// Fonction pour lire package.json
function getPackageInfo() {
  const packagePath = path.join(__dirname, '..', 'package.json');
  const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
  
  return {
    version: packageJson.version,
    name: packageJson.name
  };
}

// Fonction principale
function generateVersion() {
  const gitInfo = getGitInfo();
  const packageInfo = getPackageInfo();
  
  // Créer la version complète
  const fullVersion = `${packageInfo.version}.${gitInfo.commitCount}`;
  const buildDate = new Date().toISOString();
  
  // Créer l'objet de version
  const versionInfo = {
    version: packageInfo.version,
    fullVersion,
    commitHash: gitInfo.commitHash,
    commitDate: gitInfo.commitDate,
    commitCount: gitInfo.commitCount,
    branch: gitInfo.branch,
    buildDate,
    buildDateFormatted: new Date().toLocaleDateString('fr-FR', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  };
  
  // Écrire dans un fichier JSON pour Next.js (dossier public)
  const outputPath = path.join(__dirname, '..', 'public', 'version.json');
  fs.writeFileSync(outputPath, JSON.stringify(versionInfo, null, 2));
  
  // Écrire aussi dans un fichier PHP pour l'API
  const phpOutputPath = path.join(__dirname, '..', 'api', 'version.json');
  fs.writeFileSync(phpOutputPath, JSON.stringify(versionInfo, null, 2));
  
  console.log('✅ Version générée:', fullVersion);
  console.log('📝 Commit:', gitInfo.commitHash);
  console.log('📅 Date:', gitInfo.commitDate);
  console.log('🔢 Build:', gitInfo.commitCount);
  
  return versionInfo;
}

// Exécuter si appelé directement
if (require.main === module) {
  generateVersion();
}

module.exports = { generateVersion };
