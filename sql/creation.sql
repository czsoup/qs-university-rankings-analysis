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
