-- ==========================================================================
-- railway_init.sql  :  Pour Railway uniquement (base 'railway' deja existante)
-- Usage : mysql -h HOST -u USER -pPASS --port PORT --protocol=TCP railway < sql/railway_init.sql
-- ==========================================================================

-- ===========================================================================
-- QS World University Rankings — DDL
-- MySQL 8.0 | utf8mb4 | InnoDB
-- Exécuter : mysql -u root -p qs_rankings < sql/creation.sql
-- ===========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. PAYS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS PAYS (
    id_pays    INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL,
    code_iso   CHAR(2)      NOT NULL,
    continent  VARCHAR(50)  NOT NULL,
    CONSTRAINT uq_pays_code UNIQUE (code_iso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. TYPE_UNIVERSITE
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS TYPE_UNIVERSITE (
    id_type  INT         UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    libelle  VARCHAR(60) NOT NULL,
    CONSTRAINT uq_type_libelle UNIQUE (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. UNIVERSITE
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS UNIVERSITE (
    id_univ   INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(200) NOT NULL,
    acronyme  VARCHAR(20)  DEFAULT NULL,
    ville     VARCHAR(100) NOT NULL,
    id_pays   INT          UNSIGNED NOT NULL,
    id_type   INT          UNSIGNED NOT NULL,
    CONSTRAINT fk_univ_pays FOREIGN KEY (id_pays) REFERENCES PAYS(id_pays)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_univ_type FOREIGN KEY (id_type) REFERENCES TYPE_UNIVERSITE(id_type)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. EDITION_QS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS EDITION_QS (
    id_edition INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    annee      YEAR         NOT NULL,
    CONSTRAINT uq_edition_annee UNIQUE (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. SCORE_QS  (table centrale)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS SCORE_QS (
    id_score        INT              UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rang            SMALLINT         UNSIGNED NOT NULL,
    score_rep_acad  DECIMAL(5,1)     DEFAULT NULL COMMENT 'Réputation académique (40%)',
    score_employeur DECIMAL(5,1)     DEFAULT NULL COMMENT 'Réputation employeur (10%)',
    score_ratio     DECIMAL(5,1)     DEFAULT NULL COMMENT 'Ratio étudiants/enseignants (20%)',
    score_citations DECIMAL(5,1)     DEFAULT NULL COMMENT 'Citations par enseignant (20%)',
    score_intl_etu  DECIMAL(5,1)     DEFAULT NULL COMMENT 'Étudiants internationaux (5%)',
    score_intl_ens  DECIMAL(5,1)     DEFAULT NULL COMMENT 'Enseignants internationaux (5%)',
    score_global    DECIMAL(5,1)     NOT NULL,
    id_univ         INT              UNSIGNED NOT NULL,
    id_edition      INT              UNSIGNED NOT NULL,
    CONSTRAINT uq_qs            UNIQUE  (id_univ, id_edition),
    CONSTRAINT chk_global       CHECK   (score_global    BETWEEN 0 AND 100),
    CONSTRAINT chk_rep_acad     CHECK   (score_rep_acad  IS NULL OR score_rep_acad  BETWEEN 0 AND 100),
    CONSTRAINT chk_employeur    CHECK   (score_employeur IS NULL OR score_employeur BETWEEN 0 AND 100),
    CONSTRAINT chk_ratio        CHECK   (score_ratio     IS NULL OR score_ratio     BETWEEN 0 AND 100),
    CONSTRAINT chk_citations    CHECK   (score_citations IS NULL OR score_citations BETWEEN 0 AND 100),
    CONSTRAINT chk_intl_etu     CHECK   (score_intl_etu  IS NULL OR score_intl_etu  BETWEEN 0 AND 100),
    CONSTRAINT chk_intl_ens     CHECK   (score_intl_ens  IS NULL OR score_intl_ens  BETWEEN 0 AND 100),
    CONSTRAINT fk_score_univ    FOREIGN KEY (id_univ)    REFERENCES UNIVERSITE(id_univ)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_score_edition FOREIGN KEY (id_edition) REFERENCES EDITION_QS(id_edition)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index de performance (AC-04 : requêtes < 200 ms)
-- (id_univ, id_edition) déjà couvert par la contrainte UNIQUE uq_qs
CREATE INDEX idx_score_employeur    ON SCORE_QS (score_employeur);
CREATE INDEX idx_score_global       ON SCORE_QS (score_global);
CREATE INDEX idx_score_ratio        ON SCORE_QS (score_ratio);
CREATE INDEX idx_score_rang         ON SCORE_QS (rang);

-- ---------------------------------------------------------------------------
-- 6. CLASSEMENT_REF  (table auxiliaire pour R3 — NOT IN)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS CLASSEMENT_REF (
    id_ref          INT          UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_institution VARCHAR(200) NOT NULL,
    source          VARCHAR(100) NOT NULL COMMENT 'Ex: ARWU, THE, CWTS Leiden'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ==========================================================================
-- QS World University Rankings — DML
-- Généré automatiquement depuis : QS World University Rankings 2025.csv
-- Top 50 universités · 4 éditions (2022, 2023, 2024, 2025)
-- Éditions 2022/2023 : scores interpolés (±12%/±8%) depuis les données 2025 réelles
-- Édition 2024 : rangs réels RANK_2024 du CSV + scores interpolés ±4%
-- Édition 2025 : données réelles du CSV QS 2025
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- PAYS
-- ---------------------------------------------------------------------------
INSERT INTO PAYS (id_pays, nom, code_iso, continent) VALUES
(1, 'Etats-Unis', 'US', 'Amerique du Nord'),
(2, 'Royaume-Uni', 'GB', 'Europe'),
(3, 'Suisse', 'CH', 'Europe'),
(4, 'Singapour', 'SG', 'Asie'),
(5, 'Australie', 'AU', 'Oceanie'),
(6, 'Chine', 'CN', 'Asie'),
(7, 'Hong Kong', 'HK', 'Asie'),
(8, 'France', 'FR', 'Europe'),
(9, 'Canada', 'CA', 'Amerique du Nord'),
(10, 'Allemagne', 'DE', 'Europe'),
(11, 'Coree du Sud', 'KR', 'Asie'),
(12, 'Japon', 'JP', 'Asie'),
(13, 'Pays-Bas', 'NL', 'Europe');

-- ---------------------------------------------------------------------------
-- TYPE_UNIVERSITE
-- ---------------------------------------------------------------------------
INSERT INTO TYPE_UNIVERSITE (id_type, libelle) VALUES
(1, 'Institut technologique'),
(2, 'Publique'),
(3, 'Privee'),
(4, 'Grande Ecole');

-- ---------------------------------------------------------------------------
-- UNIVERSITE
-- ---------------------------------------------------------------------------
INSERT INTO UNIVERSITE (id_univ, nom, acronyme, ville, id_pays, id_type) VALUES
(1, 'Massachusetts Institute of Technology (MIT)', 'MIT', 'Cambridge', 1, 1),
(2, 'Imperial College London', 'ICL', 'Londres', 2, 2),
(3, 'University of Oxford', 'Oxford', 'Oxford', 2, 2),
(4, 'Harvard University', 'Harvard', 'Cambridge', 1, 3),
(5, 'University of Cambridge', 'Cambridge', 'Cambridge', 2, 2),
(6, 'Stanford University', 'Stanford', 'Stanford', 1, 3),
(7, 'ETH Zurich - Swiss Federal Institute of Technology', 'ETH', 'Zurich', 3, 1),
(8, 'National University of Singapore (NUS)', 'NUS', 'Singapour', 4, 2),
(9, 'UCL', 'UCL', 'Londres', 2, 2),
(10, 'California Institute of Technology (Caltech)', 'Caltech', 'Pasadena', 1, 1),
(11, 'University of Pennsylvania', 'UPenn', 'Philadelphie', 1, 3),
(12, 'University of California, Berkeley (UCB)', 'UC Berkeley', 'Berkeley', 1, 2),
(13, 'The University of Melbourne', 'UniMelb', 'Melbourne', 5, 2),
(14, 'Peking University', 'PKU', 'Pekin', 6, 2),
(15, 'Nanyang Technological University, Singapore (NTU)', 'NTU', 'Singapour', 4, 1),
(16, 'Cornell University', 'Cornell', 'Ithaca', 1, 3),
(17, 'The University of Hong Kong', 'HKU', 'Hong Kong', 7, 2),
(18, 'The University of Sydney', 'USyd', 'Sydney', 5, 2),
(19, 'The University of New South Wales (UNSW Sydney)', 'UNSW', 'Sydney', 5, 2),
(20, 'Tsinghua University', 'Tsinghua', 'Pekin', 6, 2),
(21, 'University of Chicago', 'UChicago', 'Chicago', 1, 3),
(22, 'Princeton University', 'Princeton', 'Princeton', 1, 3),
(23, 'Yale University', 'Yale', 'New Haven', 1, 3),
(24, 'Universite PSL', '', 'N/A', 8, 2),
(25, 'University of Toronto', 'UofT', 'Toronto', 9, 2),
(26, 'EPFL', 'EPFL', 'Lausanne', 3, 1),
(27, 'The University of Edinburgh', 'UoE', 'Edinburgh', 2, 2),
(28, 'Technical University of Munich', 'TUM', 'Munich', 10, 1),
(29, 'McGill University', 'McGill', 'Montreal', 9, 2),
(30, 'The Australian National University', 'ANU', 'Canberra', 5, 2),
(31, 'Seoul National University', 'SNU', 'Seoul', 11, 2),
(32, 'Johns Hopkins University', 'JHU', 'Baltimore', 1, 3),
(33, 'The University of Tokyo', 'UTokyo', 'Tokyo', 12, 2),
(34, 'Columbia University', 'Columbia', 'New York', 1, 3),
(35, 'The University of Manchester', 'UoM', 'Manchester', 2, 2),
(36, 'The Chinese University of Hong Kong (CUHK)', 'CUHK', 'Hong Kong', 7, 2),
(37, 'Monash University', 'Monash', 'Melbourne', 5, 2),
(38, 'University of British Columbia', 'UBC', 'Vancouver', 9, 2),
(39, 'Fudan University', 'Fudan', 'Shanghai', 6, 2),
(40, 'King''s College London', 'KCL', 'Londres', 2, 2),
(41, 'The University of Queensland', 'UQ', 'Brisbane', 5, 2),
(42, 'University of California, Los Angeles (UCLA)', 'UCLA', 'Los Angeles', 1, 2),
(43, 'New York University (NYU)', 'NYU', 'New York', 1, 3),
(44, 'University of Michigan-Ann Arbor', 'UMich', 'Ann Arbor', 1, 2),
(45, 'Shanghai Jiao Tong University', 'SJTU', 'Shanghai', 6, 1),
(46, 'Institut Polytechnique de Paris', 'IP Paris', 'Palaiseau', 8, 4),
(47, 'The Hong Kong University of Science and Technology', 'HKUST', 'Hong Kong', 7, 1),
(48, 'Zhejiang University', 'ZJU', 'Hangzhou', 6, 2),
(49, 'Delft University of Technology', 'TU Delft', 'Delft', 13, 1),
(50, 'Kyoto University', 'KyotoU', 'Kyoto', 12, 2);

-- ---------------------------------------------------------------------------
-- EDITION_QS
-- ---------------------------------------------------------------------------
INSERT INTO EDITION_QS (id_edition, annee) VALUES
(1, 2022),
(2, 2023),
(3, 2024),
(4, 2025);

-- ---------------------------------------------------------------------------
-- CLASSEMENT_REF (Shanghai ARWU 2024 — pour requête R3 NOT IN)
-- ---------------------------------------------------------------------------
INSERT INTO CLASSEMENT_REF (id_ref, nom_institution, source) VALUES
(1, 'Harvard University', 'ARWU Shanghai 2024'),
(2, 'Stanford University', 'ARWU Shanghai 2024'),
(3, 'Massachusetts Institute of Technology (MIT)', 'ARWU Shanghai 2024'),
(4, 'University of Cambridge', 'ARWU Shanghai 2024'),
(5, 'University of California, Berkeley (UCB)', 'ARWU Shanghai 2024'),
(6, 'Princeton University', 'ARWU Shanghai 2024'),
(7, 'Columbia University', 'ARWU Shanghai 2024'),
(8, 'University of Chicago', 'ARWU Shanghai 2024'),
(9, 'Yale University', 'ARWU Shanghai 2024'),
(10, 'Johns Hopkins University', 'ARWU Shanghai 2024'),
(11, 'University of California, Los Angeles (UCLA)', 'ARWU Shanghai 2024'),
(12, 'Cornell University', 'ARWU Shanghai 2024'),
(13, 'University of Michigan-Ann Arbor', 'ARWU Shanghai 2024'),
(14, 'University of Toronto', 'ARWU Shanghai 2024'),
(15, 'The University of Tokyo', 'ARWU Shanghai 2024');

-- ---------------------------------------------------------------------------
-- SCORE_QS — Édition 2022
-- ---------------------------------------------------------------------------
INSERT INTO SCORE_QS (rang, score_rep_acad, score_employeur, score_ratio, score_citations, score_intl_etu, score_intl_ens, score_global, id_univ, id_edition) VALUES
(11, 100.0, 83.0, 93.8, 100.0, 79.2, 100.0, 100.0, 1, 1),
(13, 100.0, 96.7, 92.6, 89.4, 98.2, 100.0, 100.0, 2, 1),
(1, 100.0, 100.0, 93.5, 90.1, 100.0, 97.7, 89.0, 3, 1),
(17, 93.1, 100.0, 90.8, 100.0, 62.6, 76.9, 97.4, 4, 1),
(4, 100.0, 96.2, 95.0, 72.6, 87.1, 100.0, 100.0, 5, 1),
(1, 85.6, 96.7, 100.0, 100.0, 57.8, 62.3, 95.0, 6, 1),
(1, 89.7, 98.0, 64.8, 100.0, 98.3, 93.9, 81.1, 7, 1),
(7, 100.0, 79.9, 66.0, 89.8, 77.9, 94.3, 93.4, 8, 1),
(6, 100.0, 100.0, 82.9, 74.8, 97.4, 81.0, 89.9, 9, 1),
(12, 100.0, 89.5, 92.6, 92.5, 72.5, 100.0, 85.4, 10, 1),
(1, 90.8, 80.9, 100.0, 75.7, 78.5, 82.4, 96.2, 11, 1),
(6, 100.0, 96.9, 20.5, 100.0, 63.1, 90.2, 94.8, 12, 1),
(5, 100.0, 88.1, 16.4, 78.8, 98.5, 100.0, 79.2, 13, 1),
(4, 81.4, 96.2, 100.0, 100.0, 22.1, 50.0, 87.6, 14, 1),
(26, 100.0, 84.7, 91.3, 94.4, 85.6, 90.6, 96.8, 15, 1),
(14, 100.0, 90.3, 57.6, 82.1, 72.4, 50.0, 95.7, 16, 1),
(41, 100.0, 50.6, 73.7, 96.5, 100.0, 100.0, 93.8, 17, 1),
(5, 88.0, 92.5, 12.0, 100.0, 95.7, 95.4, 92.1, 18, 1),
(24, 87.0, 100.0, 20.3, 100.0, 100.0, 97.7, 79.5, 19, 1),
(26, 97.1, 100.0, 98.9, 100.0, 14.4, 20.2, 92.4, 20, 1),
(5, 94.5, 91.3, 99.8, 57.1, 79.5, 81.6, 91.7, 21, 1),
(10, 100.0, 100.0, 60.1, 100.0, 53.8, 9.4, 93.0, 22, 1),
(4, 97.1, 93.1, 95.8, 39.0, 62.4, 100.0, 90.7, 23, 1),
(23, 84.1, 99.2, 84.5, 78.6, 65.4, 64.3, 92.6, 24, 1),
(34, 100.0, 88.6, 46.0, 56.3, 96.3, 89.7, 87.8, 25, 1),
(45, 86.3, 70.7, 99.5, 95.7, 87.5, 100.0, 80.4, 26, 1),
(23, 84.1, 100.0, 64.0, 54.4, 100.0, 98.5, 75.5, 27, 1),
(56, 76.7, 90.9, 76.4, 80.0, 89.1, 73.1, 79.1, 28, 1),
(35, 100.0, 93.7, 64.0, 53.7, 79.9, 77.9, 80.4, 29, 1),
(34, 91.4, 76.6, 37.2, 80.0, 90.3, 100.0, 91.9, 30, 1),
(30, 87.0, 89.5, 84.8, 60.4, 16.3, 9.9, 83.1, 31, 1),
(34, 76.9, 59.3, 89.4, 85.7, 93.3, 61.2, 82.9, 32, 1),
(16, 92.4, 100.0, 85.4, 51.9, 28.2, 10.0, 81.3, 33, 1),
(25, 100.0, 96.1, 85.6, 34.8, 96.7, 43.1, 89.4, 34, 1),
(22, 98.7, 85.6, 50.7, 41.4, 100.0, 96.1, 73.7, 35, 1),
(38, 90.2, 56.7, 64.0, 100.0, 87.2, 99.6, 73.1, 36, 1),
(39, 84.3, 72.8, 10.0, 96.7, 100.0, 100.0, 83.3, 37, 1),
(32, 98.9, 100.0, 35.8, 61.1, 80.1, 91.9, 82.7, 38, 1),
(51, 85.0, 95.4, 66.0, 81.0, 35.5, 85.5, 76.5, 39, 1),
(47, 100.0, 87.9, 56.7, 60.1, 100.0, 90.6, 82.2, 40, 1),
(31, 94.3, 77.9, 23.6, 97.3, 100.0, 100.0, 83.2, 41, 1),
(26, 100.0, 92.8, 35.8, 76.7, 25.4, 44.6, 74.2, 42, 1),
(41, 93.9, 100.0, 76.6, 26.1, 86.7, 34.8, 80.8, 43, 1),
(28, 96.5, 88.2, 92.5, 44.9, 41.1, 59.5, 75.4, 44, 1),
(44, 95.6, 89.4, 64.6, 90.2, 18.9, 23.1, 84.7, 45, 1),
(28, 45.7, 100.0, 90.6, 100.0, 88.6, 93.1, 89.6, 46, 1),
(75, 85.7, 48.0, 57.9, 86.6, 99.5, 87.7, 81.3, 47, 1),
(54, 76.5, 100.0, 53.8, 89.7, 15.6, 89.7, 68.3, 48, 1),
(60, 76.6, 88.1, 37.1, 72.5, 100.0, 100.0, 74.2, 49, 1),
(58, 100.0, 96.2, 100.0, 39.2, 17.0, 17.8, 83.6, 50, 1);

-- ---------------------------------------------------------------------------
-- SCORE_QS — Édition 2023
-- ---------------------------------------------------------------------------
INSERT INTO SCORE_QS (rang, score_rep_acad, score_employeur, score_ratio, score_citations, score_intl_etu, score_intl_ens, score_global, id_univ, id_edition) VALUES
(1, 100.0, 89.0, 91.8, 93.6, 89.8, 100.0, 100.0, 1, 2),
(6, 100.0, 100.0, 100.0, 99.7, 94.5, 97.6, 100.0, 2, 2),
(3, 100.0, 100.0, 92.2, 86.3, 100.0, 91.6, 95.2, 3, 2),
(11, 94.7, 100.0, 93.9, 94.0, 68.5, 77.0, 91.8, 4, 2),
(8, 100.0, 100.0, 100.0, 79.1, 98.9, 100.0, 98.0, 5, 2),
(3, 94.4, 100.0, 100.0, 100.0, 61.7, 66.4, 93.5, 6, 2),
(4, 91.9, 94.1, 66.8, 95.4, 99.5, 100.0, 90.6, 7, 2),
(1, 100.0, 85.2, 66.4, 91.8, 86.3, 90.8, 96.8, 8, 2),
(4, 94.8, 95.0, 91.7, 72.2, 96.5, 91.7, 99.6, 9, 2),
(7, 100.0, 89.8, 100.0, 99.2, 81.1, 100.0, 95.5, 10, 2),
(9, 88.3, 82.5, 100.0, 75.3, 71.1, 88.7, 91.5, 11, 2),
(16, 99.8, 100.0, 22.3, 97.2, 60.2, 87.8, 84.9, 12, 2),
(9, 100.0, 93.7, 14.9, 88.3, 95.4, 95.9, 88.9, 13, 2),
(12, 91.5, 99.4, 100.0, 95.8, 24.8, 51.8, 83.9, 14, 2),
(25, 98.5, 77.9, 85.0, 100.0, 80.1, 100.0, 88.9, 15, 2),
(5, 100.0, 89.5, 52.1, 90.5, 65.1, 54.2, 86.1, 16, 2),
(30, 100.0, 54.6, 74.9, 87.1, 98.1, 100.0, 90.3, 17, 2),
(17, 92.3, 99.4, 11.2, 100.0, 100.0, 96.8, 88.4, 18, 2),
(21, 84.7, 99.2, 20.5, 93.8, 100.0, 92.8, 81.8, 19, 2),
(24, 93.3, 100.0, 96.7, 92.8, 13.2, 19.7, 87.8, 20, 2),
(10, 100.0, 95.4, 98.8, 62.5, 85.0, 78.0, 87.4, 21, 2),
(9, 100.0, 100.0, 56.1, 91.4, 54.0, 9.9, 93.3, 22, 2),
(8, 100.0, 100.0, 100.0, 36.7, 64.3, 100.0, 84.0, 23, 2),
(17, 75.9, 100.0, 89.8, 81.2, 63.8, 57.9, 88.0, 24, 2),
(25, 98.3, 97.5, 41.2, 52.7, 100.0, 91.3, 90.6, 25, 2),
(42, 87.0, 65.6, 97.3, 100.0, 93.2, 100.0, 84.9, 26, 2),
(29, 93.4, 100.0, 64.2, 51.2, 100.0, 95.2, 75.7, 27, 2),
(45, 82.8, 100.0, 75.7, 75.3, 96.4, 78.4, 79.4, 28, 2),
(27, 93.0, 93.9, 58.7, 52.8, 80.5, 79.0, 87.0, 29, 2),
(27, 85.4, 82.0, 37.0, 81.0, 91.6, 97.5, 82.2, 30, 2),
(35, 91.7, 94.4, 78.1, 67.3, 17.4, 10.5, 77.7, 31, 2),
(24, 80.7, 60.7, 94.6, 84.9, 94.7, 59.8, 75.8, 32, 2),
(20, 100.0, 100.0, 82.7, 52.0, 31.3, 10.4, 84.7, 33, 2),
(16, 99.3, 97.0, 95.8, 34.6, 93.9, 43.1, 90.7, 34, 2),
(31, 95.3, 93.4, 45.9, 43.6, 95.6, 98.1, 74.0, 35, 2),
(41, 83.4, 55.6, 61.6, 96.9, 88.3, 100.0, 76.2, 36, 2),
(47, 84.4, 81.9, 10.0, 94.7, 98.2, 100.0, 84.5, 37, 2),
(34, 96.0, 96.9, 35.9, 60.5, 77.9, 98.2, 79.2, 38, 2),
(47, 86.8, 91.4, 71.5, 87.8, 34.6, 88.0, 73.3, 39, 2),
(46, 96.1, 88.8, 59.5, 53.7, 96.7, 100.0, 84.6, 40, 2),
(36, 91.3, 75.7, 22.6, 92.4, 100.0, 94.9, 80.7, 41, 2),
(30, 97.2, 98.6, 33.2, 77.0, 23.7, 42.1, 77.2, 42, 2),
(41, 87.2, 98.3, 84.6, 27.9, 97.9, 31.9, 79.6, 43, 2),
(29, 92.7, 83.3, 83.6, 45.8, 38.8, 64.9, 77.0, 44, 2),
(50, 85.9, 80.7, 59.9, 100.0, 20.3, 22.4, 76.1, 45, 2),
(36, 45.3, 94.1, 100.0, 92.2, 97.2, 96.4, 83.8, 46, 2),
(66, 85.3, 48.5, 52.1, 96.8, 100.0, 89.8, 77.8, 47, 2),
(46, 73.8, 100.0, 51.1, 96.3, 15.8, 96.0, 71.0, 48, 2),
(54, 69.0, 86.8, 35.6, 74.1, 96.1, 100.0, 75.6, 49, 2),
(53, 95.6, 89.4, 97.7, 37.9, 19.2, 16.1, 77.5, 50, 2);

-- ---------------------------------------------------------------------------
-- SCORE_QS — Édition 2024
-- ---------------------------------------------------------------------------
INSERT INTO SCORE_QS (rang, score_rep_acad, score_employeur, score_ratio, score_citations, score_intl_etu, score_intl_ens, score_global, id_univ, id_edition) VALUES
(1, 100.0, 96.2, 98.2, 97.8, 88.4, 100.0, 100.0, 1, 3),
(6, 97.2, 96.8, 100.0, 92.7, 96.4, 96.8, 100.0, 2, 3),
(3, 100.0, 100.0, 99.0, 84.5, 100.0, 95.5, 95.8, 3, 3),
(4, 96.3, 98.5, 94.5, 97.7, 71.4, 76.3, 95.4, 4, 3),
(2, 100.0, 97.2, 97.3, 85.8, 95.5, 99.1, 97.4, 5, 3),
(5, 100.0, 100.0, 100.0, 95.9, 61.4, 72.2, 96.2, 6, 3),
(7, 95.5, 88.8, 67.6, 100.0, 98.9, 97.0, 95.1, 7, 3),
(8, 95.7, 91.5, 69.3, 89.4, 90.4, 96.5, 90.5, 8, 3),
(9, 98.8, 100.0, 92.5, 73.1, 100.0, 95.5, 93.3, 9, 3),
(15, 98.5, 93.3, 99.3, 99.9, 82.1, 100.0, 88.5, 10, 3),
(12, 94.9, 88.6, 99.6, 76.5, 66.4, 87.7, 90.4, 11, 3),
(10, 97.9, 99.2, 23.8, 96.6, 60.1, 93.3, 87.0, 12, 3),
(14, 99.7, 90.2, 15.8, 91.5, 100.0, 98.4, 86.3, 13, 3),
(17, 98.2, 93.7, 96.2, 95.1, 23.5, 51.1, 88.9, 14, 3),
(26, 93.1, 72.4, 81.0, 95.2, 86.7, 100.0, 91.4, 15, 3),
(13, 100.0, 91.0, 54.0, 97.2, 62.4, 55.5, 86.0, 16, 3),
(26, 95.6, 57.5, 80.8, 88.0, 97.8, 100.0, 87.7, 17, 3),
(19, 93.5, 92.9, 10.9, 95.0, 98.6, 100.0, 87.7, 18, 3),
(19, 90.6, 92.5, 21.4, 96.7, 100.0, 98.3, 88.2, 19, 3),
(25, 98.4, 94.9, 96.4, 100.0, 13.8, 18.7, 88.4, 20, 3),
(11, 99.1, 98.9, 94.6, 62.7, 88.7, 78.8, 84.5, 21, 3),
(17, 96.3, 97.6, 57.2, 99.3, 55.3, 9.5, 88.3, 22, 3),
(16, 99.0, 100.0, 100.0, 39.3, 65.0, 94.6, 88.5, 23, 3),
(24, 72.9, 94.3, 94.3, 87.9, 67.6, 61.6, 85.7, 24, 3),
(21, 100.0, 94.3, 44.2, 51.1, 99.4, 97.5, 86.8, 25, 3),
(36, 84.2, 66.3, 93.8, 96.0, 96.8, 100.0, 84.4, 26, 3),
(22, 97.9, 99.1, 65.9, 48.3, 98.5, 98.7, 82.1, 27, 3),
(37, 84.9, 95.4, 75.1, 77.1, 97.1, 80.9, 83.0, 28, 3),
(30, 96.0, 90.9, 62.4, 56.9, 86.7, 81.7, 81.2, 29, 3),
(34, 90.4, 78.1, 34.5, 86.8, 95.4, 96.6, 83.3, 30, 3),
(41, 97.0, 99.6, 83.8, 73.1, 17.5, 10.3, 80.4, 31, 3),
(28, 86.6, 64.1, 97.6, 81.6, 93.7, 61.9, 80.6, 32, 3),
(28, 100.0, 100.0, 85.9, 56.1, 30.1, 10.4, 82.7, 33, 3),
(23, 100.0, 100.0, 98.6, 32.2, 95.4, 43.0, 84.1, 34, 3),
(32, 92.2, 96.3, 49.8, 44.3, 100.0, 93.0, 78.9, 35, 3),
(47, 86.5, 53.1, 62.4, 90.6, 88.2, 100.0, 78.9, 36, 3),
(42, 91.1, 78.8, 9.7, 90.2, 100.0, 97.9, 80.4, 37, 3),
(34, 98.5, 94.0, 35.1, 57.3, 73.7, 92.9, 80.8, 38, 3),
(50, 84.2, 85.9, 77.1, 83.8, 35.8, 89.8, 77.7, 39, 3),
(40, 90.3, 86.3, 62.9, 54.2, 99.2, 100.0, 82.8, 40, 3),
(43, 85.7, 72.8, 21.5, 86.8, 100.0, 99.2, 77.0, 41, 3),
(29, 100.0, 100.0, 34.9, 73.6, 22.4, 43.0, 77.2, 42, 3),
(38, 93.3, 100.0, 87.9, 29.7, 100.0, 30.7, 82.7, 43, 3),
(33, 100.0, 90.0, 77.5, 48.8, 40.5, 64.5, 78.4, 44, 3),
(51, 81.3, 86.2, 58.2, 100.0, 18.9, 22.9, 77.4, 45, 3),
(38, 43.1, 98.3, 99.7, 88.1, 95.7, 100.0, 77.8, 46, 3),
(60, 81.7, 48.4, 55.6, 96.7, 94.8, 96.9, 74.4, 47, 3),
(44, 75.2, 98.5, 52.8, 96.2, 16.0, 91.7, 75.7, 48, 3),
(47, 74.1, 82.4, 38.1, 79.2, 92.6, 99.0, 74.9, 49, 3),
(46, 100.0, 95.4, 92.6, 38.7, 18.4, 15.9, 73.9, 50, 3);

-- ---------------------------------------------------------------------------
-- SCORE_QS — Édition 2025
-- ---------------------------------------------------------------------------
INSERT INTO SCORE_QS (rang, score_rep_acad, score_employeur, score_ratio, score_citations, score_intl_etu, score_intl_ens, score_global, id_univ, id_edition) VALUES
(1, 100.0, 100.0, 100.0, 100.0, 86.8, 99.3, 100.0, 1, 4),
(2, 98.5, 99.5, 98.2, 93.9, 99.6, 100.0, 98.5, 2, 4),
(3, 100.0, 100.0, 100.0, 84.8, 97.7, 98.1, 96.9, 3, 4),
(4, 100.0, 100.0, 96.3, 100.0, 69.0, 74.1, 96.8, 4, 4),
(5, 100.0, 100.0, 100.0, 84.6, 94.8, 100.0, 96.7, 5, 4),
(6, 100.0, 100.0, 100.0, 99.0, 60.8, 70.3, 96.1, 6, 4),
(7, 98.8, 87.2, 65.9, 97.9, 98.6, 100.0, 93.9, 7, 4),
(8, 99.5, 91.1, 68.8, 93.1, 88.9, 100.0, 93.7, 8, 4),
(9, 99.5, 98.3, 95.9, 72.2, 100.0, 99.0, 91.6, 9, 4),
(10, 96.5, 95.3, 100.0, 100.0, 79.8, 100.0, 90.9, 10, 4),
(11, 96.3, 91.9, 99.8, 74.0, 66.2, 90.9, 90.3, 11, 4),
(12, 100.0, 100.0, 23.5, 98.2, 61.0, 91.5, 90.1, 12, 4),
(13, 98.5, 93.9, 15.4, 93.0, 99.8, 95.1, 88.9, 13, 4),
(14, 99.5, 96.6, 92.6, 97.7, 23.6, 50.3, 88.5, 14, 4),
(15, 91.9, 73.3, 80.6, 92.4, 83.5, 100.0, 88.4, 15, 4),
(16, 98.3, 93.1, 52.7, 97.5, 63.4, 54.2, 87.9, 16, 4),
(17, 97.4, 59.4, 81.2, 86.4, 99.3, 100.0, 87.6, 17, 4),
(18, 96.4, 90.0, 10.9, 93.7, 100.0, 99.9, 87.3, 18, 4),
(19, 90.5, 90.4, 20.6, 94.9, 99.4, 100.0, 87.1, 19, 4),
(20, 99.2, 97.7, 95.0, 99.1, 13.4, 18.1, 86.5, 20, 4),
(21, 99.1, 96.4, 94.2, 60.8, 87.0, 79.0, 86.2, 21, 4),
(22, 99.8, 98.3, 57.0, 100.0, 56.6, 9.6, 85.5, 22, 4),
(23, 99.9, 99.9, 100.0, 38.6, 63.3, 91.5, 85.2, 23, 4),
(24, 74.4, 97.6, 98.1, 87.6, 65.0, 62.3, 84.7, 24, 4),
(25, 99.7, 96.9, 44.9, 50.8, 96.1, 96.9, 84.1, 25, 4),
(26, 84.2, 67.2, 91.2, 93.6, 100.0, 100.0, 83.5, 26, 4),
(27, 98.3, 97.2, 65.5, 47.7, 99.8, 98.7, 83.3, 27, 4),
(28, 83.0, 98.6, 76.8, 75.9, 98.6, 80.4, 83.2, 28, 4),
(29, 94.3, 87.6, 62.3, 57.9, 89.6, 83.7, 83.0, 29, 4),
(30, 93.8, 75.4, 34.6, 84.6, 96.2, 100.0, 82.4, 30, 4),
(31, 98.5, 98.6, 83.1, 71.7, 16.9, 10.5, 82.3, 31, 4),
(32, 86.0, 62.6, 100.0, 84.2, 95.8, 63.7, 82.1, 32, 4),
(32, 100.0, 99.8, 89.3, 57.3, 29.7, 10.1, 82.1, 33, 4),
(34, 99.6, 98.8, 100.0, 31.7, 97.0, 41.5, 82.0, 34, 4),
(34, 95.6, 98.1, 51.3, 45.1, 99.2, 93.1, 82.0, 35, 4),
(36, 86.7, 53.3, 64.2, 92.9, 87.5, 100.0, 81.3, 36, 4),
(37, 89.2, 79.6, 9.4, 87.6, 100.0, 100.0, 81.2, 37, 4),
(38, 98.3, 94.3, 34.5, 57.7, 72.8, 95.5, 81.0, 38, 4),
(39, 85.7, 87.8, 79.7, 80.7, 35.1, 88.4, 80.3, 39, 4),
(40, 90.3, 85.7, 64.3, 53.6, 99.5, 99.1, 80.2, 40, 4),
(40, 86.7, 74.0, 21.2, 90.2, 100.0, 100.0, 80.2, 41, 4),
(42, 100.0, 99.8, 35.4, 74.0, 22.3, 42.2, 79.8, 42, 4),
(43, 96.3, 98.8, 90.5, 28.6, 98.1, 30.2, 79.6, 43, 4),
(44, 97.9, 92.1, 80.3, 47.6, 39.2, 65.5, 79.0, 44, 4),
(45, 84.0, 86.3, 58.6, 99.6, 19.6, 23.1, 77.8, 45, 4),
(46, 44.7, 99.6, 95.9, 86.1, 98.0, 99.1, 77.5, 46, 4),
(47, 81.1, 50.3, 56.7, 99.7, 95.4, 100.0, 77.1, 47, 4),
(47, 75.3, 95.4, 54.7, 99.5, 15.9, 95.0, 77.1, 48, 4),
(49, 74.4, 83.0, 39.4, 79.7, 91.4, 100.0, 77.0, 49, 4),
(50, 98.8, 99.0, 94.2, 39.3, 19.0, 15.9, 76.0, 50, 4);

SET FOREIGN_KEY_CHECKS = 1;
