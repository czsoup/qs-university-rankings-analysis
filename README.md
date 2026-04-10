# QS World University Rankings — Projet BD n°6

**M1 Informatique · Bases de données · Avril 2026**  
**Équipe :** Caroline · Ibtissam · Liza · Koceila

> **Pour voir le site en 5 minutes :** suivre les étapes dans l'ordre, de la section
> "Installation" jusqu'à "Lancer le site". Chaque commande est copiable telle quelle.

---

## État du projet

| Composant | Statut | Détail |
|-----------|--------|--------|
| Base de données | ✅ Opérationnel | 50 universités · 13 pays · 4 éditions · 200 scores |
| SQL DDL | ✅ Complet | 6 tables, index, contraintes UNIQUE/CHECK/FK |
| SQL DML | ✅ Complet | Données réelles QS 2025 (CSV officiel) + interpolations |
| Interface web | ✅ Complète | 6 pages PHP + graphiques Chart.js |
| Requêtes analytiques | ✅ Complètes | R1 à R8 dans stats.php |
| Requête bonus | ✅ Implémentée | Moteur pondéré dans recommandation.php (+2 pts) |
| MCD / MLD | ✅ Complets | rapport/mcd.png + rapport/mld.png + rapport/rapport.txt |

---

## Prérequis

- Windows 10 ou 11
- Connexion internet (Tailwind CSS, Chart.js et Font Awesome chargés via CDN)
- PowerShell (inclus dans Windows — clic droit sur le menu Démarrer → "Windows PowerShell")

---

## INSTALLATION COMPLÈTE (première fois uniquement)

> **Si tu as déjà PHP et MySQL installés** (ex. via XAMPP), saute à la section
> **"Alternative XAMPP/WAMP"** plus bas.

---

### Étape 1 — Installer Scoop (gestionnaire de paquets Windows)

Ouvre **PowerShell** et colle exactement ces deux lignes :

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
Invoke-RestMethod -Uri https://get.scoop.sh | Invoke-Expression
```

Ferme et rouvre PowerShell une fois terminé.

---

### Étape 2 — Installer PHP et MySQL

```powershell
scoop install php mysql
```

Durée : 2–5 minutes selon ta connexion. Attends la fin complète.

Vérification :

```powershell
php -v
mysql --version
```

Les deux commandes doivent afficher un numéro de version.

---

### Étape 3 — Activer l'extension pdo_mysql dans PHP

```powershell
notepad "$env:USERPROFILE\scoop\apps\php\current\php.ini"
```

Dans le fichier qui s'ouvre, fais **Ctrl+F** et cherche :

```
;extension=pdo_mysql
```

Supprime le `;` au début de la ligne pour obtenir :

```
extension=pdo_mysql
```

Fais **Ctrl+S** pour sauvegarder, puis ferme le Bloc-notes.

Vérifie que ça marche :

```powershell
php -m | findstr pdo_mysql
```

Résultat attendu : `pdo_mysql` (si rien ne s'affiche, recommence l'étape 3)

---

### Étape 4 — Initialiser le serveur MySQL

> **À faire une seule fois.** Si MySQL a déjà été initialisé, ignore cette étape
> (si tu as une erreur "already exists", c'est normal, passe à l'étape 5).

```powershell
mysqld --initialize-insecure
```

Cette commande crée les fichiers système de MySQL avec un compte `root` sans mot de passe.

---

### Étape 5 — Créer la base de données

Démarre d'abord le serveur MySQL :

```powershell
Start-Process mysqld -WindowStyle Hidden
Start-Sleep -Seconds 3
```

Puis crée la base :

```powershell
mysql -u root -e "CREATE DATABASE IF NOT EXISTS qs_rankings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Si tu vois `Query OK` ou aucune erreur : c'est bon.

---

### Étape 6 — Importer les scripts SQL

> **Note :** La redirection `<` ne fonctionne pas dans PowerShell. On utilise le **pipe** `Get-Content | mysql`.

**6.1 — Place-toi dans le dossier du projet**

```powershell
cd "C:\Users\TON_NOM\Documents\...\ProjetBD_Caroline_Ibtissam_Liza_Koceila_2026"
```

(Remplace le chemin par le tien — tu peux copier le chemin depuis l'explorateur de fichiers.)

**6.2 — Crée la base de données**

```powershell
mysql -u root -e "DROP DATABASE IF EXISTS qs_rankings; CREATE DATABASE qs_rankings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**6.3 — Importe les scripts via pipe** (depuis PowerShell, dans le dossier du projet)

```powershell
Get-Content "sql\creation.sql" -Raw | mysql -u root qs_rankings
Get-Content "sql\insertion.sql" -Raw | mysql -u root qs_rankings
```

Si MySQL n'est pas démarré, lance d'abord :

```powershell
Start-Process mysqld -WindowStyle Hidden
Start-Sleep -Seconds 3
```

**6.4 — Vérifie que tout est bien importé**

```powershell
mysql -u root qs_rankings -e "SELECT COUNT(*) FROM SCORE_QS; SELECT COUNT(*) FROM UNIVERSITE;"
```

Résultats attendus : `200` scores et `50` universités.

---

## LANCER LE SITE

> À faire **à chaque fois** que tu veux voir le site (après avoir redémarré le PC).

**Ouvre PowerShell** et exécute dans l'ordre :

```powershell
# 1. Démarrer MySQL en arrière-plan
Start-Process mysqld -WindowStyle Hidden
Start-Sleep -Seconds 3

# 2. Aller dans le dossier du projet
cd "C:\Users\zgcar\Documents\Master_BIG_DATA\COURS\M1S2\1_Representation_connaissance_gestions_donnees\ProjetBD_Caroline_Ibtissam_Liza_Koceila_2026"

# 3. Lancer le serveur PHP
php -S localhost:8000 -t web
```

Puis ouvre ton navigateur et va sur : **http://localhost:8000**

> Laisse la fenêtre PowerShell ouverte pendant que tu navigues sur le site.
> Pour arrêter : appuie sur `Ctrl+C` dans PowerShell.

---

## Alternative XAMPP / WAMP (si déjà installé)

Si tu as déjà XAMPP ou WAMP, tu n'as pas besoin de Scoop.

**Avec XAMPP :**

1. Lance XAMPP Control Panel → Start **Apache** et **MySQL**
2. Ouvre **http://localhost/phpmyadmin**
3. Crée une base nommée `qs_rankings` (utf8mb4 / utf8mb4_unicode_ci)
4. Clique sur la base → onglet **Importer** → importe `sql/creation.sql` puis `sql/insertion.sql`
5. Copie le dossier `web/` dans `C:\xampp\htdocs\ProjetBD\`
6. Ouvre **http://localhost/ProjetBD/**

> Si la connexion échoue, vérifie dans `web/connexion.php` que `DB_PASS` correspond
> au mot de passe MySQL de ton XAMPP (souvent vide par défaut).

---

## Configuration de la connexion BDD

Si besoin, modifie `web/connexion.php` :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'qs_rankings');
define('DB_USER', 'root');
define('DB_PASS', '');       // Vide pour Scoop --initialize-insecure
                              // Mettre le mot de passe XAMPP si différent
define('DB_PORT', '3306');
```

---

## Pages du site

| Page | URL | Ce qu'on voit |
|------|-----|---------------|
| Dashboard | http://localhost:8000 | Top 10 mondial, graphiques continents/types |
| Fiche université | http://localhost:8000/universite.php?id=1 | Radar 6 critères, comparaison QS vs ARWU, trajectoire |
| Par pays | http://localhost:8000/pays.php?pays=FR | Universités françaises, évolution rang moyen |
| Recommandation | http://localhost:8000/recommandation.php | 6 curseurs de pondération, top 10 personnalisé |
| Comparaison | http://localhost:8000/comparaison.php?id1=1&id2=2 | Radar superposé, tableau, double line chart |
| Statistiques | http://localhost:8000/stats.php | 8 requêtes analytiques interactives (R1–R8) |

---

## Données en base

Source : **CSV officiel QS World University Rankings 2025** (topuniversities.com / Kaggle)

| Table | Contenu |
|-------|---------|
| PAYS | 13 pays (États-Unis, Royaume-Uni, France, Suisse, Singapour, Australie, Chine, Hong Kong, Canada, Allemagne, Japon, Corée du Sud, Pays-Bas) |
| TYPE_UNIVERSITE | 4 types : Publique · Privée · Institut technologique · Grande École |
| UNIVERSITE | 50 universités (top 50 QS 2025), dont **PSL** et **Institut Polytechnique de Paris** |
| EDITION_QS | 4 éditions : 2022 · 2023 · 2024 · **2025 (données réelles)** |
| SCORE_QS | 200 lignes (50 × 4 éditions) |
| CLASSEMENT_REF | 15 entrées ARWU Shanghai 2024 (pour requête R3 + comparaison fiche université) |

**Méthodologie des éditions :**
- **2025** : données réelles extraites du CSV QS 2025
- **2024** : rangs `RANK_2024` réels du CSV + scores interpolés ±4 %
- **2023** : interpolation ±8 % depuis 2024
- **2022** : interpolation ±12 % depuis 2023

---

## Requêtes analytiques (R1–R8)

| Req. | Titre | Technique SQL |
|------|-------|---------------|
| R1 | Employabilité > Réputation académique | Comparaison inter-colonnes + ORDER BY |
| R2 | Évolution universités françaises | GROUP BY édition + AVG() |
| R3 | Top QS absentes de l'ARWU | NOT IN (sous-requête) |
| R4 | Pays homogènes en employabilité | ALL (sous-requête universelle) |
| R5 | Score global > moyenne nationale | Sous-requête scalaire corrélée |
| R6 | Critère le plus discriminant | MAX–MIN + AVG (dispersion simulée) |
| R7 | Universités stables (amplitude < 10) | HAVING MAX(rang)–MIN(rang) < 10 |
| R8 | Top 10 ratio étudiants/enseignants | RANK() OVER (window function) |
| Bonus | Moteur de recommandation pondéré | Score personnalisé avec 6 poids |

---

## Structure des fichiers

```
ProjetBD_Caroline_Ibtissam_Liza_Koceila_2026/
│
├── sql/
│   ├── creation.sql          DDL : 6 tables, FK, UNIQUE, CHECK, index
│   └── insertion.sql         DML : 50 universités, 4 éditions, 200 scores
│
├── web/
│   ├── connexion.php         Connexion PDO centralisée
│   ├── index.php             Dashboard mondial
│   ├── universite.php        Fiche université (radar + QS vs ARWU + trajectoire)
│   ├── pays.php              Exploration par pays
│   ├── recommandation.php    Moteur de recommandation personnalisé (bonus)
│   ├── comparaison.php       Comparateur côte-à-côte
│   ├── stats.php             8 requêtes analytiques interactives
│   ├── partials/
│   │   ├── header.php        Navigation + CDN (Tailwind, Chart.js, Font Awesome)
│   │   └── footer.php        Pied de page
│   └── css/
│       └── style.css         Surcharges CSS personnalisées
│
├── rapport/
│   ├── rapport.txt           Rapport complet (à convertir en PDF)
│   ├── mcd.png               Schéma MCD (image à insérer dans le PDF)
│   ├── mld.png               Schéma MLD (image à insérer dans le PDF)
│   └── generate_schemas.py   Script Python pour regénérer les schémas
│
├── dataset/
│   ├── QS World University Rankings 2025 (Top global universities).csv
│   ├── 2026 QS World University Rankings 1.3 (For qs.com).xlsx
│   └── generate_insertion.py  Script Python de génération de insertion.sql
│
└── ENONCE_PROJET_GRP6.pdf
```

---

## Critères d'évaluation couverts

| Critère | Points | Fichier(s) |
|---------|--------|------------|
| Modélisation MCD + MLD | 5 pts | `rapport/mcd.png` + `rapport/mld.png` + `rapport/rapport.txt` |
| Script SQL DDL + DML | 4 pts | `sql/creation.sql` + `sql/insertion.sql` |
| Requêtes SQL R1–R8 | 6 pts | `web/stats.php` |
| Interface PHP/MySQL | 6 pts | 6 pages PHP + Chart.js |
| Rapport | 4 pts | `rapport/rapport.txt` → PDF |
| **Bonus** recommandation | +2 pts | `web/recommandation.php` |
| **Total** | **25 (+2)** | |

---

## Problèmes fréquents

| Problème | Cause probable | Solution |
|----------|----------------|----------|
| Page blanche ou "Service indisponible" | MySQL pas démarré | `Start-Process mysqld -WindowStyle Hidden` |
| `pdo_mysql not found` | Extension pas activée | Décommenter `extension=pdo_mysql` dans `php.ini` (étape 3) |
| `Can't connect to MySQL server` | MySQL pas encore prêt | Attendre 5 sec après `Start-Process mysqld` |
| `L'operateur < est reserve` | PowerShell bloque la redirection `<` | Utiliser `Get-Content "sql\fichier.sql" -Raw \| mysql -u root qs_rankings` |
| `Table doesn't exist` | Scripts SQL pas importés | Relancer l'étape 6 |
| Graphiques blancs | Cache navigateur | `Ctrl+Shift+R` (rechargement forcé) |
| Caractères cassés (é, è…) | Encodage BDD | Vérifier `SET NAMES utf8mb4` dans `connexion.php` |
| `Access denied for user root` | MySQL initialisé avec mot de passe | Ajouter `-p` : `mysql -u root -p qs_rankings` |
| Port 8000 déjà utilisé | Autre appli sur ce port | Changer : `php -S localhost:8080 -t web` |

---

## Génération du ZIP de rendu

```powershell
cd "C:\Users\zgcar\Documents\Master_BIG_DATA\COURS\M1S2\1_Representation_connaissance_gestions_donnees\ProjetBD_Caroline_Ibtissam_Liza_Koceila_2026"
Compress-Archive -Path "rapport","sql","web" -DestinationPath "..\ProjetBD_Caroline_Ibtissam_Liza_Koceila_2026.zip" -Force
```

Contenu attendu dans le ZIP :
```
rapport/rapport.pdf
rapport/mcd.png
rapport/mld.png
sql/creation.sql
sql/insertion.sql
web/connexion.php
web/index.php
web/universite.php
web/pays.php
web/recommandation.php
web/comparaison.php
web/stats.php
web/partials/header.php
web/partials/footer.php
web/css/style.css
```

---

## Stack technique

| Outil | Version | Mode |
|-------|---------|------|
| PHP | 8.2+ | Serveur intégré (`php -S`) |
| MySQL | 8.0+ | Scoop ou XAMPP |
| Tailwind CSS | 3.x | CDN |
| Chart.js | 4.4.1 | CDN |
| Font Awesome | 6.5.1 Free | CDN |

> Aucun Composer, aucun npm, aucun build — tout fonctionne directement.
