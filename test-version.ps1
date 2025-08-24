# Script de test pour le système de versioning
Write-Host "🧪 Test du système de versioning Online Book Brew" -ForegroundColor Cyan

# Vérifier que le fichier de version existe
$versionFile = "api/version.json"
if (Test-Path $versionFile) {
    Write-Host "✅ Fichier de version trouvé: $versionFile" -ForegroundColor Green
    
    # Lire et afficher la version actuelle
    try {
        $versionData = Get-Content $versionFile | ConvertFrom-Json
        Write-Host "📋 Version actuelle:" -ForegroundColor Yellow
        Write-Host "   Version: $($versionData.version)" -ForegroundColor White
        Write-Host "   Build: $($versionData.build)" -ForegroundColor White
        Write-Host "   Dernier déploiement: $($versionData.last_deploy)" -ForegroundColor White
        Write-Host "   Commit Git: $($versionData.git_commit)" -ForegroundColor White
        Write-Host "   Date: $($versionData.deploy_date)" -ForegroundColor White
    } catch {
        Write-Host "❌ Erreur lors de la lecture du fichier de version: $($_.Exception.Message)" -ForegroundColor Red
    }
} else {
    Write-Host "❌ Fichier de version non trouvé: $versionFile" -ForegroundColor Red
}

Write-Host ""

# Vérifier que les hooks Git existent
$hookBash = ".git/hooks/post-commit"
$hookPowerShell = ".git/hooks/post-commit.ps1"

if (Test-Path $hookBash) {
    Write-Host "✅ Hook Git Bash trouvé: $hookBash" -ForegroundColor Green
} else {
    Write-Host "❌ Hook Git Bash manquant: $hookBash" -ForegroundColor Red
}

if (Test-Path $hookPowerShell) {
    Write-Host "✅ Hook Git PowerShell trouvé: $hookPowerShell" -ForegroundColor Green
} else {
    Write-Host "❌ Hook Git PowerShell manquant: $hookPowerShell" -ForegroundColor Red
}

Write-Host ""

# Vérifier que l'API de version est accessible
Write-Host "🌐 Test de l'API de version..." -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "http://localhost:8001/api/version" -Method Get -ErrorAction Stop
    if ($response.status -eq "success") {
        Write-Host "✅ API de version accessible" -ForegroundColor Green
        Write-Host "   Données reçues: $($response.data | ConvertTo-Json -Compress)" -ForegroundColor White
    } else {
        Write-Host "⚠️ API de version accessible mais statut: $($response.status)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "❌ API de version non accessible: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "   Vérifiez que le serveur local est démarré sur le port 8001" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "🎯 Pour tester le versioning automatique:" -ForegroundColor Cyan
Write-Host "   1. Faites un commit: git add . && git commit -m 'Test versioning'" -ForegroundColor White
Write-Host "   2. Vérifiez que la version s'est incrémentée dans api/version.json" -ForegroundColor White
Write-Host "   3. Déployez: .\deploy-to-morglaf.ps1 -FastDeploy" -ForegroundColor White
