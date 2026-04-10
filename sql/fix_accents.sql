-- Fix: remplace tous les accents par des equivalents ASCII dans la base
-- A executer une seule fois depuis le prompt mysql>
-- SOURCE C:/chemin/ProjetBD_Caroline_Ibtissam_Liza_Koceila_2026/sql/fix_accents.sql

SET NAMES utf8mb4;

-- TYPE_UNIVERSITE
UPDATE TYPE_UNIVERSITE SET libelle = 'Grande Ecole' WHERE libelle LIKE '%cole';
UPDATE TYPE_UNIVERSITE SET libelle = 'Privee'       WHERE libelle LIKE 'Priv%';

-- PAYS - continent
UPDATE PAYS SET continent = 'Amerique du Nord' WHERE continent LIKE 'Am%rique%';
UPDATE PAYS SET continent = 'Oceanie'          WHERE continent LIKE 'Oc%anie';

-- PAYS - nom
UPDATE PAYS SET nom = 'Etats-Unis'    WHERE nom LIKE '%tats-Unis';
UPDATE PAYS SET nom = 'Coree du Sud'  WHERE nom LIKE 'Cor%e du Sud';

-- UNIVERSITE - noms avec accents
UPDATE UNIVERSITE SET nom = 'Universite PSL' WHERE nom LIKE 'Universit% PSL';

-- Verification finale
SELECT 'TYPE_UNIVERSITE' AS tbl, libelle AS val FROM TYPE_UNIVERSITE;
SELECT 'PAYS_continent'  AS tbl, DISTINCT continent AS val FROM PAYS;
SELECT 'PAYS_nom'        AS tbl, nom AS val FROM PAYS;
