-- =============================================================================
-- APPALTI PUBBLICI SAAS - DATABASE SCHEMA
-- Versione: 1.0.1 (FIXED)
USE `my_softchi`;
-- Compatibile con: MySQL 5.7+ / MariaDB 10.3+
-- Normativa: D.Lgs. 36/2023 (Codice dei Contratti Pubblici)
--
-- FIX APPLICATI:
--   1. pm_sal_voci.importo_periodo: rimossa subquery da colonna GENERATED
--      (MySQL non supporta subquery in colonne generate).
--      Sostituita con colonna normale DECIMAL + FOREIGN KEY esplicita.
--      Il valore va calcolato lato applicazione o tramite la vista v_sal_voci.
--   2. v_commesse_riepilogo: corretta subquery importo_liquidato
--      (ORDER BY + LIMIT non ammessi dentro SUM/COALESCE aggregati).
--      Riscritta come subquery correlata corretta.
--   3. Aggiunta vista v_sal_voci per calcolo automatico importo_periodo
--      tramite JOIN con pm_categorie_lavoro.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- SEZIONE 1: SISTEMA UTENTI, RUOLI, PERMESSI
-- =============================================================================

CREATE TABLE `pm_ruoli` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice`      VARCHAR(50)  NOT NULL COMMENT 'es: RUP, PM, DL, CSE, IMPRESA, TECNICO, ADMIN',
  `nome`        VARCHAR(100) NOT NULL,
  `descrizione` TEXT,
  `livello`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=base, 10=superadmin',
  `attivo`      TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ruoli_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ruoli sistema RBAC';

CREATE TABLE `pm_permessi` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `modulo`      VARCHAR(50)  NOT NULL COMMENT 'es: pm_commesse, pm_tasks, pm_sal, pm_documenti',
  `azione`      VARCHAR(50)  NOT NULL COMMENT 'es: read, create, update, delete, approve',
  `codice`      VARCHAR(100) NOT NULL COMMENT 'es: pm_commesse.read',
  `descrizione` VARCHAR(255),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permessi_codice` (`codice`),
  KEY `idx_permessi_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permessi granulari per modulo';

CREATE TABLE `pm_ruoli_permessi` (
  `ruolo_id`    INT UNSIGNED NOT NULL,
  `permesso_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`ruolo_id`, `permesso_id`),
  CONSTRAINT `fk_rp_ruolo` FOREIGN KEY (`ruolo_id`) REFERENCES `pm_ruoli` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permesso` FOREIGN KEY (`permesso_id`) REFERENCES `pm_permessi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pm_utenti` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`               CHAR(36)     NOT NULL COMMENT 'UUID v4 per riferimenti esterni',
  `ruolo_id`           INT UNSIGNED NOT NULL,
  `nome`               VARCHAR(100) NOT NULL,
  `cognome`            VARCHAR(100) NOT NULL,
  `email`              VARCHAR(255) NOT NULL,
  `password_hash`      VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
  `telefono`           VARCHAR(30),
  `matricola`          VARCHAR(50)  COMMENT 'matricola/codice fiscale',
  `qualifica`          VARCHAR(150) COMMENT 'es: Ingegnere Civile, Architetto',
  `ordine_professionale` VARCHAR(100),
  `numero_iscrizione`  VARCHAR(50)  COMMENT 'N. iscrizione albo professionale',
  `avatar_path`        VARCHAR(500),
  `firma_digitale_path` VARCHAR(500),
  `attivo`             TINYINT(1) NOT NULL DEFAULT 1,
  `email_verificata`   TINYINT(1) NOT NULL DEFAULT 0,
  `notifiche_email`    TINYINT(1) NOT NULL DEFAULT 1,
  `notifiche_push`     TINYINT(1) NOT NULL DEFAULT 1,
  `ultimo_accesso`     TIMESTAMP NULL,
  `token_reset`        VARCHAR(255) NULL,
  `token_reset_scade`  TIMESTAMP NULL,
  `token_verifica`     VARCHAR(255) NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utenti_email` (`email`),
  UNIQUE KEY `uk_utenti_uuid` (`uuid`),
  KEY `idx_utenti_ruolo` (`ruolo_id`),
  KEY `idx_utenti_cognome_nome` (`cognome`, `nome`),
  CONSTRAINT `fk_utenti_ruolo` FOREIGN KEY (`ruolo_id`) REFERENCES `pm_ruoli` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Anagrafica pm_utenti piattaforma';

CREATE TABLE `pm_sessioni` (
  `id`          CHAR(64)     NOT NULL COMMENT 'Token sessione SHA-256',
  `utente_id`   INT UNSIGNED NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL COMMENT 'IPv4/IPv6',
  `user_agent`  VARCHAR(500),
  `payload`     JSON,
  `csrf_token`  CHAR(64)     NOT NULL,
  `scade_il`    TIMESTAMP    NOT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessioni_utente` (`utente_id`),
  KEY `idx_sessioni_scadenza` (`scade_il`),
  CONSTRAINT `fk_sessioni_utente` FOREIGN KEY (`utente_id`) REFERENCES `pm_utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sessioni server-side';

-- =============================================================================
-- SEZIONE 2: STAZIONI APPALTANTI E IMPRESE
-- =============================================================================

CREATE TABLE `pm_stazioni_appaltanti` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice_fiscale`  VARCHAR(16)  NOT NULL,
  `denominazione`   VARCHAR(255) NOT NULL,
  `tipo`            ENUM('COMUNE','PROVINCIA','REGIONE','MINISTERO','ASL','UNIVERSITA','ALTRO') NOT NULL DEFAULT 'ALTRO',
  `indirizzo`       VARCHAR(300),
  `citta`           VARCHAR(100),
  `cap`             VARCHAR(10),
  `provincia`       CHAR(2),
  `telefono`        VARCHAR(30),
  `pec`             VARCHAR(255),
  `sito_web`        VARCHAR(255),
  `codice_belfiore` VARCHAR(10) COMMENT 'Codice ANAC/IPA',
  `attivo`          TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sa_cf` (`codice_fiscale`),
  KEY `idx_sa_tipo` (`tipo`),
  KEY `idx_sa_provincia` (`provincia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stazioni appaltanti (enti committenti)';

CREATE TABLE `pm_imprese` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice_fiscale`      VARCHAR(16)  NOT NULL,
  `partita_iva`         VARCHAR(13),
  `ragione_sociale`     VARCHAR(255) NOT NULL,
  `forma_giuridica`     VARCHAR(50)  COMMENT 'es: SRL, SPA, SCARL, ATI',
  `indirizzo`           VARCHAR(300),
  `citta`               VARCHAR(100),
  `cap`                 VARCHAR(10),
  `provincia`           CHAR(2),
  `telefono`            VARCHAR(30),
  `email`               VARCHAR(255),
  `pec`                 VARCHAR(255),
  `sito_web`            VARCHAR(255),
  `iban`                VARCHAR(34) COMMENT 'Per pagamenti',
  `soa_categorie`       TEXT COMMENT 'Categorie SOA JSON',
  `durc_scadenza`       DATE COMMENT 'Scadenza DURC',
  `durc_path`           VARCHAR(500),
  `rating_legalita`     DECIMAL(3,2) COMMENT 'Rating legalità ANAC',
  `attivo`              TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_imprese_cf` (`codice_fiscale`),
  KEY `idx_imprese_rag_soc` (`ragione_sociale`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Imprese esecutrici e subappaltatrici';

-- =============================================================================
-- SEZIONE 3: APPALTI E COMMESSE
-- =============================================================================

CREATE TABLE `pm_appalti` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                 CHAR(36)     NOT NULL,
  `stazione_appaltante_id` INT UNSIGNED NOT NULL,
  `codice_cig`           VARCHAR(20)  NOT NULL COMMENT 'CIG ANAC',
  `codice_cup`           VARCHAR(20)  COMMENT 'CUP progetto',
  `oggetto`              VARCHAR(500) NOT NULL,
  `descrizione`          TEXT,
  `tipo_appalto`         ENUM('LAVORI','SERVIZI','FORNITURE','MISTO') NOT NULL DEFAULT 'LAVORI',
  `procedura`            ENUM('APERTA','RISTRETTA','NEGOZIATA','AFFIDAMENTO_DIRETTO','ACCORDO_QUADRO','ALTRO') NOT NULL,
  `criterio_aggiudicazione` ENUM('PREZZO_PIU_BASSO','OFFERTA_ECONOMICAMENTE_VANTAGGIOSA') NOT NULL DEFAULT 'OFFERTA_ECONOMICAMENTE_VANTAGGIOSA',
  `importo_base_asta`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_sicurezza`    DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Oneri sicurezza non soggetti a ribasso',
  `importo_aggiudicato`  DECIMAL(15,2) COMMENT 'Importo dopo aggiudicazione',
  `ribasso_percentuale`  DECIMAL(6,4) COMMENT 'Ribasso offerto (es: 0.1250 = 12.50%)',
  `data_pubblicazione`   DATE,
  `data_aggiudicazione`  DATE,
  `data_stipula_contratto` DATE,
  `data_consegna_lavori` DATE,
  `rup_id`               INT UNSIGNED COMMENT 'Responsabile Unico del Procedimento',
  `stato`                ENUM('BOZZA','PUBBLICATO','AGGIUDICATO','CONTRATTO','IN_ESECUZIONE','COMPLETATO','ANNULLATO','SOSPESO') NOT NULL DEFAULT 'BOZZA',
  `cat_prevalente`       VARCHAR(10) COMMENT 'Categoria SOA prevalente (es: OG1)',
  `classi_importo`       VARCHAR(10) COMMENT 'Classifica SOA (es: II)',
  `codice_nuts`          VARCHAR(10) COMMENT 'Codice NUTS area geografica',
  `note`                 TEXT,
  `created_by`           INT UNSIGNED NOT NULL,
  `updated_by`           INT UNSIGNED,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_appalti_uuid` (`uuid`),
  UNIQUE KEY `uk_appalti_cig` (`codice_cig`),
  KEY `idx_appalti_sa` (`stazione_appaltante_id`),
  KEY `idx_appalti_stato` (`stato`),
  KEY `idx_appalti_rup` (`rup_id`),
  KEY `idx_appalti_tipo` (`tipo_appalto`),
  FULLTEXT KEY `ft_appalti_oggetto` (`oggetto`, `descrizione`),
  CONSTRAINT `fk_appalti_sa` FOREIGN KEY (`stazione_appaltante_id`) REFERENCES `pm_stazioni_appaltanti` (`id`),
  CONSTRAINT `fk_appalti_rup` FOREIGN KEY (`rup_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_appalti_created` FOREIGN KEY (`created_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Appalti/gare - procedura di affidamento';

CREATE TABLE `pm_commesse` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                 CHAR(36)     NOT NULL,
  `appalto_id`           INT UNSIGNED NOT NULL,
  `impresa_id`           INT UNSIGNED NOT NULL COMMENT 'Impresa aggiudicataria',
  `codice_commessa`      VARCHAR(50)  NOT NULL COMMENT 'Codice interno gestionale',
  `oggetto`              VARCHAR(500) NOT NULL,
  `descrizione`          TEXT,
  `luogo_esecuzione`     VARCHAR(300),
  `comune`               VARCHAR(100),
  `provincia`            CHAR(2),
  `coordinate_lat`       DECIMAL(10,8),
  `coordinate_lng`       DECIMAL(11,8),
  `rup_id`               INT UNSIGNED,
  `pm_id`                INT UNSIGNED COMMENT 'Project Manager',
  `dl_id`                INT UNSIGNED COMMENT 'Direttore dei Lavori',
  `cse_id`               INT UNSIGNED COMMENT 'Coordinatore Sicurezza in Esecuzione',
  `importo_contrattuale` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_sicurezza`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_varianti`     DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Totale pm_varianti approvate',
  `data_inizio_prevista` DATE,
  `data_fine_prevista`   DATE,
  `data_inizio_effettiva` DATE,
  `data_fine_effettiva`  DATE,
  `durata_contrattuale`  INT COMMENT 'Giorni naturali',
  `durata_effettiva`     INT GENERATED ALWAYS AS (
    CASE WHEN `data_inizio_effettiva` IS NOT NULL AND `data_fine_effettiva` IS NOT NULL
    THEN DATEDIFF(`data_fine_effettiva`, `data_inizio_effettiva`)
    ELSE NULL END
  ) STORED,
  `scostamento_giorni`   INT GENERATED ALWAYS AS (
    CASE WHEN `data_fine_prevista` IS NOT NULL AND `data_fine_effettiva` IS NOT NULL
    THEN DATEDIFF(`data_fine_effettiva`, `data_fine_prevista`)
    ELSE NULL END
  ) STORED COMMENT 'Positivo = ritardo, negativo = anticipo',
  `percentuale_avanzamento` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '0.00-100.00',
  `stato`                ENUM('BOZZA','PIANIFICAZIONE','IN_ESECUZIONE','SOSPESA','COMPLETATA','COLLAUDATA','CHIUSA','ANNULLATA') NOT NULL DEFAULT 'BOZZA',
  `priorita`             ENUM('BASSA','NORMALE','ALTA','CRITICA') NOT NULL DEFAULT 'NORMALE',
  `colore`               CHAR(7) NOT NULL DEFAULT '#0d6efd' COMMENT 'Colore HEX per UI',
  `note`                 TEXT,
  `created_by`           INT UNSIGNED NOT NULL,
  `updated_by`           INT UNSIGNED,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_commesse_uuid` (`uuid`),
  UNIQUE KEY `uk_commesse_codice` (`codice_commessa`),
  KEY `idx_commesse_appalto` (`appalto_id`),
  KEY `idx_commesse_impresa` (`impresa_id`),
  KEY `idx_commesse_stato` (`stato`),
  KEY `idx_commesse_rup` (`rup_id`),
  KEY `idx_commesse_pm` (`pm_id`),
  KEY `idx_commesse_dl` (`dl_id`),
  KEY `idx_commesse_date` (`data_inizio_prevista`, `data_fine_prevista`),
  FULLTEXT KEY `ft_commesse` (`oggetto`, `descrizione`),
  CONSTRAINT `fk_commesse_appalto` FOREIGN KEY (`appalto_id`) REFERENCES `pm_appalti` (`id`),
  CONSTRAINT `fk_commesse_impresa` FOREIGN KEY (`impresa_id`) REFERENCES `pm_imprese` (`id`),
  CONSTRAINT `fk_commesse_rup` FOREIGN KEY (`rup_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_commesse_pm` FOREIGN KEY (`pm_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_commesse_dl` FOREIGN KEY (`dl_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_commesse_cse` FOREIGN KEY (`cse_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_commesse_created` FOREIGN KEY (`created_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Commesse - unità operative di lavoro';

CREATE TABLE `pm_commesse_utenti` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commessa_id` INT UNSIGNED NOT NULL,
  `utente_id`   INT UNSIGNED NOT NULL,
  `ruolo_progetto` VARCHAR(50) NOT NULL COMMENT 'Ruolo specifico nel progetto',
  `data_inizio` DATE,
  `data_fine`   DATE,
  `percentuale_allocazione` DECIMAL(5,2) DEFAULT 100.00 COMMENT '% di tempo allocato',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cu_commessa_utente` (`commessa_id`, `utente_id`),
  CONSTRAINT `fk_cu_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cu_utente` FOREIGN KEY (`utente_id`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEZIONE 4: CRONOPROGRAMMA - TASK E MILESTONE
-- =============================================================================

CREATE TABLE `pm_fasi_lavoro` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commessa_id` INT UNSIGNED NOT NULL,
  `codice`      VARCHAR(20)  NOT NULL COMMENT 'es: F1, F2, F3',
  `nome`        VARCHAR(200) NOT NULL,
  `descrizione` TEXT,
  `ordine`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `colore`      CHAR(7) NOT NULL DEFAULT '#198754',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fl_commessa` (`commessa_id`),
  CONSTRAINT `fk_fl_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pm_tasks` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                 CHAR(36)     NOT NULL,
  `commessa_id`          INT UNSIGNED NOT NULL,
  `fase_id`              INT UNSIGNED COMMENT 'Fase/WBS di appartenenza',
  `parent_id`            INT UNSIGNED NULL COMMENT 'Task padre (struttura gerarchica WBS)',
  `codice_wbs`           VARCHAR(30)  NOT NULL COMMENT 'es: 1.2.3',
  `nome`                 VARCHAR(300) NOT NULL,
  `descrizione`          TEXT,
  `tipo`                 ENUM('TASK','MILESTONE','FASE','SOMMARIO') NOT NULL DEFAULT 'TASK',
  `assegnato_a`          INT UNSIGNED COMMENT 'Utente responsabile',
  `data_inizio_prevista` DATE,
  `data_fine_prevista`   DATE,
  `data_inizio_effettiva` DATE,
  `data_fine_effettiva`  DATE,
  `durata_prevista`      SMALLINT UNSIGNED COMMENT 'Giorni lavorativi',
  `durata_effettiva`     SMALLINT UNSIGNED,
  `percentuale_completamento` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `importo_previsto`     DECIMAL(15,2) DEFAULT 0.00,
  `importo_effettivo`    DECIMAL(15,2) DEFAULT 0.00,
  `stato`                ENUM('NON_INIZIATO','IN_CORSO','COMPLETATO','IN_RITARDO','SOSPESO','ANNULLATO') NOT NULL DEFAULT 'NON_INIZIATO',
  `priorita`             ENUM('BASSA','NORMALE','ALTA','CRITICA') NOT NULL DEFAULT 'NORMALE',
  `ordine`               INT UNSIGNED NOT NULL DEFAULT 0,
  `note`                 TEXT,
  `created_by`           INT UNSIGNED NOT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tasks_uuid` (`uuid`),
  KEY `idx_tasks_commessa` (`commessa_id`),
  KEY `idx_tasks_fase` (`fase_id`),
  KEY `idx_tasks_parent` (`parent_id`),
  KEY `idx_tasks_assegnato` (`assegnato_a`),
  KEY `idx_tasks_stato` (`stato`),
  KEY `idx_tasks_date` (`data_inizio_prevista`, `data_fine_prevista`),
  CONSTRAINT `fk_tasks_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_fase` FOREIGN KEY (`fase_id`) REFERENCES `pm_fasi_lavoro` (`id`),
  CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_id`) REFERENCES `pm_tasks` (`id`),
  CONSTRAINT `fk_tasks_assegnato` FOREIGN KEY (`assegnato_a`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_tasks_created` FOREIGN KEY (`created_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Attività cronoprogramma (diagramma di Gantt)';

CREATE TABLE `pm_dipendenze_tasks` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`        INT UNSIGNED NOT NULL COMMENT 'Task successore',
  `task_pred_id`   INT UNSIGNED NOT NULL COMMENT 'Task predecessore',
  `tipo`           ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS' COMMENT 'Finish-Start, Start-Start, Finish-Finish, Start-Finish',
  `lag_giorni`     SMALLINT NOT NULL DEFAULT 0 COMMENT 'Lag/Lead time in giorni (negativo=lead)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dep_tasks` (`task_id`, `task_pred_id`),
  CONSTRAINT `fk_dep_task` FOREIGN KEY (`task_id`) REFERENCES `pm_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dep_pred` FOREIGN KEY (`task_pred_id`) REFERENCES `pm_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEZIONE 5: CONTABILITÀ LAVORI E SAL
-- =============================================================================

CREATE TABLE `pm_categorie_lavoro` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commessa_id` INT UNSIGNED NOT NULL,
  `codice`      VARCHAR(30)  NOT NULL,
  `descrizione` VARCHAR(300) NOT NULL,
  `unita_misura` VARCHAR(20),
  `prezzo_unitario` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `quantita_contrattuale` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `importo_contrattuale` DECIMAL(15,2) GENERATED ALWAYS AS (`prezzo_unitario` * `quantita_contrattuale`) STORED,
  `categoria_soa` VARCHAR(10),
  `ordine`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `note`        TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_cl_commessa` (`commessa_id`),
  CONSTRAINT `fk_cl_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorie lavoro / Elenco Prezzi';

CREATE TABLE `pm_sal` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`                CHAR(36)     NOT NULL,
  `commessa_id`         INT UNSIGNED NOT NULL,
  `numero_sal`          SMALLINT UNSIGNED NOT NULL COMMENT 'Numero progressivo SAL',
  `data_inizio`         DATE NOT NULL,
  `data_fine`           DATE NOT NULL,
  `data_emissione`      DATE COMMENT 'Data emissione certificato',
  `importo_lavori`      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_sicurezza`   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_varianti`    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `importo_totale`      DECIMAL(15,2) GENERATED ALWAYS AS (`importo_lavori` + `importo_sicurezza` + `importo_varianti`) STORED,
  `importo_cumulato`    DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Importo cumulato da inizio lavori',
  `ritenuta_garanzia`   DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '5% ritenuta contrattuale',
  `importo_netto`       DECIMAL(15,2) GENERATED ALWAYS AS (`importo_lavori` + `importo_sicurezza` + `importo_varianti` - `ritenuta_garanzia`) STORED,
  `percentuale_avanzamento` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `stato`               ENUM('BOZZA','EMESSO','APPROVATO','PAGATO','CONTESTATO') NOT NULL DEFAULT 'BOZZA',
  `dl_id`               INT UNSIGNED COMMENT 'DL che firma il SAL',
  `rup_id`              INT UNSIGNED COMMENT 'RUP che approva',
  `note_dl`             TEXT,
  `note_rup`            TEXT,
  `data_approvazione`   DATE,
  `data_pagamento`      DATE,
  `created_by`          INT UNSIGNED NOT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sal_uuid` (`uuid`),
  UNIQUE KEY `uk_sal_numero` (`commessa_id`, `numero_sal`),
  KEY `idx_sal_commessa` (`commessa_id`),
  KEY `idx_sal_stato` (`stato`),
  CONSTRAINT `fk_sal_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`),
  CONSTRAINT `fk_sal_dl` FOREIGN KEY (`dl_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_sal_rup` FOREIGN KEY (`rup_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_sal_created` FOREIGN KEY (`created_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stati Avanzamento Lavori (SAL)';

-- -----------------------------------------------------------------------------
-- Tabella: pm_sal_voci  [FIX #1]
-- importo_periodo era GENERATED con subquery -> non supportato da MySQL.
-- Ora è colonna normale DECIMAL: calcolare lato applicazione oppure
-- usare la vista v_sal_voci per il valore calcolato automaticamente.
-- -----------------------------------------------------------------------------
CREATE TABLE `pm_sal_voci` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sal_id`            INT UNSIGNED  NOT NULL,
  `categoria_id`      INT UNSIGNED  NOT NULL,
  `quantita_periodo`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Quantità nel periodo SAL',
  `quantita_cumulata` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Quantità cumulata',
  -- FIX: rimossa GENERATED ALWAYS AS con subquery (non ammessa in MySQL).
  -- Il valore è calcolato automaticamente dalla vista v_sal_voci (quantita_periodo * prezzo_unitario).
  -- In INSERT/UPDATE calcolare: importo_periodo = quantita_periodo * prezzo_unitario di pm_categorie_lavoro.
  `importo_periodo`   DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Calcolato: quantita_periodo * prezzo_unitario (aggiornare lato app)',
  `note`              TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_sv_sal`       (`sal_id`),
  KEY `idx_sv_categoria` (`categoria_id`),
  CONSTRAINT `fk_sv_sal`       FOREIGN KEY (`sal_id`)       REFERENCES `pm_sal` (`id`)               ON DELETE CASCADE,
  CONSTRAINT `fk_sv_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `pm_categorie_lavoro` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Voci SAL - importo_periodo calcolato via vista v_sal_voci';

CREATE TABLE `pm_varianti` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commessa_id`    INT UNSIGNED NOT NULL,
  `numero`         SMALLINT UNSIGNED NOT NULL,
  `tipo`           ENUM('TECNICA','ECONOMICA','TECNICA_ECONOMICA','PERIZIA_SUPPLETIVA') NOT NULL,
  `motivo`         ENUM('ART_120_C1_A','ART_120_C1_B','ART_120_C1_C','ART_120_C1_D','ERRORI_OMISSIONI','ALTRO') NOT NULL COMMENT 'Art. 120 D.Lgs. 36/2023',
  `oggetto`        VARCHAR(500) NOT NULL,
  `descrizione`    TEXT,
  `importo`        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `stato`          ENUM('BOZZA','RICHIESTA','APPROVATA','RIFIUTATA') NOT NULL DEFAULT 'BOZZA',
  `data_richiesta` DATE,
  `data_approvazione` DATE,
  `approvata_da`   INT UNSIGNED,
  `note`           TEXT,
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_varianti_commessa` (`commessa_id`),
  CONSTRAINT `fk_varianti_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`),
  CONSTRAINT `fk_varianti_approvata` FOREIGN KEY (`approvata_da`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_varianti_created` FOREIGN KEY (`created_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Varianti contrattuali (art. 120 D.Lgs. 36/2023)';

-- =============================================================================
-- SEZIONE 6: GESTIONE DOCUMENTALE
-- =============================================================================

CREATE TABLE `pm_categorie_documento` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codice`      VARCHAR(30)  NOT NULL,
  `nome`        VARCHAR(100) NOT NULL,
  `descrizione` VARCHAR(300),
  `icona`       VARCHAR(50)  DEFAULT 'bi-file-earmark',
  `colore`      CHAR(7)      DEFAULT '#6c757d',
  `obbligatorio` TINYINT(1)  NOT NULL DEFAULT 0,
  `ordine`      SMALLINT UNSIGNED DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cd_codice` (`codice`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pm_documenti` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`           CHAR(36)     NOT NULL,
  `commessa_id`    INT UNSIGNED NOT NULL,
  `categoria_id`   INT UNSIGNED,
  `titolo`         VARCHAR(300) NOT NULL,
  `descrizione`    TEXT,
  `nome_file`      VARCHAR(255) NOT NULL COMMENT 'Nome file originale',
  `path_file`      VARCHAR(500) NOT NULL COMMENT 'Path relativo su server',
  `mime_type`      VARCHAR(100),
  `dimensione`     INT UNSIGNED COMMENT 'Dimensione in bytes',
  `hash_md5`       CHAR(32)     COMMENT 'MD5 per integrità',
  `versione`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `doc_padre_id`   INT UNSIGNED NULL COMMENT 'Documento precedente (versioning)',
  `stato`          ENUM('BOZZA','PUBBLICATO','OBSOLETO','ARCHIVIATO') NOT NULL DEFAULT 'BOZZA',
  `riservato`      TINYINT(1) NOT NULL DEFAULT 0,
  `data_documento` DATE COMMENT 'Data del documento (non upload)',
  `data_scadenza`  DATE COMMENT 'Data scadenza validità',
  `tags`           JSON,
  `uploaded_by`    INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_doc_uuid` (`uuid`),
  KEY `idx_doc_commessa` (`commessa_id`),
  KEY `idx_doc_categoria` (`categoria_id`),
  KEY `idx_doc_stato` (`stato`),
  KEY `idx_doc_padre` (`doc_padre_id`),
  FULLTEXT KEY `ft_doc` (`titolo`, `descrizione`),
  CONSTRAINT `fk_doc_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`),
  CONSTRAINT `fk_doc_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `pm_categorie_documento` (`id`),
  CONSTRAINT `fk_doc_padre` FOREIGN KEY (`doc_padre_id`) REFERENCES `pm_documenti` (`id`),
  CONSTRAINT `fk_doc_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Archivio documentale pm_commesse';

CREATE TABLE `pm_verbali` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`           CHAR(36)     NOT NULL,
  `commessa_id`    INT UNSIGNED NOT NULL,
  `tipo`           ENUM('CONSEGNA_LAVORI','RIPRESA_LAVORI','SOSPENSIONE_LAVORI','STATO_AVANZAMENTO','VISITA_CANTIERE','COLLAUDO','CONTABILITA','RIUNIONE','SICUREZZA','ALTRO') NOT NULL,
  `numero`         VARCHAR(20)  NOT NULL COMMENT 'Numero verbale (es: 001/2024)',
  `oggetto`        VARCHAR(300) NOT NULL,
  `contenuto`      LONGTEXT,
  `luogo`          VARCHAR(200),
  `data_verbale`   DATETIME NOT NULL,
  `presenti`       JSON COMMENT 'Array di partecipanti',
  `allegati`       JSON COMMENT 'Array di ID pm_documenti allegati',
  `stato`          ENUM('BOZZA','FIRMATO','ARCHIVIATO') NOT NULL DEFAULT 'BOZZA',
  `redatto_da`     INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_verbali_uuid` (`uuid`),
  KEY `idx_verbali_commessa` (`commessa_id`),
  KEY `idx_verbali_tipo` (`tipo`),
  CONSTRAINT `fk_verbali_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`),
  CONSTRAINT `fk_verbali_redattore` FOREIGN KEY (`redatto_da`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Verbali di cantiere e riunioni';

-- =============================================================================
-- SEZIONE 7: SCADENZARIO E NOTIFICHE
-- =============================================================================

CREATE TABLE `pm_scadenze` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commessa_id`    INT UNSIGNED,
  `appalto_id`     INT UNSIGNED,
  `tipo`           ENUM('CONTRATTUALE','NORMATIVA','DOCUMENTALE','PAGAMENTO','COMUNICAZIONE','COLLAUDO','ALTRO') NOT NULL,
  `titolo`         VARCHAR(300) NOT NULL,
  `descrizione`    TEXT,
  `data_scadenza`  DATE NOT NULL,
  `ora_scadenza`   TIME,
  `giorni_preavviso` SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  `responsabile_id` INT UNSIGNED,
  `stato`          ENUM('ATTIVA','COMPLETATA','SCADUTA','ANNULLATA','PROROGATA') NOT NULL DEFAULT 'ATTIVA',
  `priorita`       ENUM('BASSA','NORMALE','ALTA','CRITICA') NOT NULL DEFAULT 'NORMALE',
  `ricorrente`     TINYINT(1) NOT NULL DEFAULT 0,
  `frequenza_ricorrenza` ENUM('SETTIMANALE','MENSILE','BIMESTRALE','TRIMESTRALE','SEMESTRALE','ANNUALE') NULL,
  `notifica_inviata` TINYINT(1) NOT NULL DEFAULT 0,
  `data_completamento` DATE,
  `note`           TEXT,
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scadenze_commessa` (`commessa_id`),
  KEY `idx_scadenze_data` (`data_scadenza`),
  KEY `idx_scadenze_stato` (`stato`),
  KEY `idx_scadenze_responsabile` (`responsabile_id`),
  CONSTRAINT `fk_sc_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`),
  CONSTRAINT `fk_sc_appalto` FOREIGN KEY (`appalto_id`) REFERENCES `pm_appalti` (`id`),
  CONSTRAINT `fk_sc_responsabile` FOREIGN KEY (`responsabile_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_sc_created` FOREIGN KEY (`created_by`) REFERENCES `pm_utenti` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Scadenzario adempimenti';

CREATE TABLE `pm_notifiche` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id`   INT UNSIGNED NOT NULL,
  `tipo`        ENUM('INFO','AVVISO','SCADENZA','APPROVAZIONE','DOCUMENTO','TASK','SAL','SISTEMA') NOT NULL DEFAULT 'INFO',
  `titolo`      VARCHAR(200) NOT NULL,
  `messaggio`   TEXT NOT NULL,
  `link`        VARCHAR(500) COMMENT 'URL relativo per navigazione',
  `entita_tipo` VARCHAR(50) COMMENT 'Tipo entità correlata (commessa, pm_sal, task)',
  `entita_id`   INT UNSIGNED COMMENT 'ID entità correlata',
  `letta`       TINYINT(1) NOT NULL DEFAULT 0,
  `data_lettura` TIMESTAMP NULL,
  `inviata_email` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_not_utente` (`utente_id`),
  KEY `idx_not_letta` (`letta`),
  KEY `idx_not_tipo` (`tipo`),
  KEY `idx_not_created` (`created_at`),
  CONSTRAINT `fk_not_utente` FOREIGN KEY (`utente_id`) REFERENCES `pm_utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notifiche in-app pm_utenti';

-- =============================================================================
-- SEZIONE 8: LOG E AUDIT
-- =============================================================================

CREATE TABLE `pm_audit_log` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id`   INT UNSIGNED,
  `azione`      VARCHAR(100) NOT NULL COMMENT 'es: CREATE, UPDATE, DELETE, LOGIN, APPROVE',
  `entita_tipo` VARCHAR(50)  NOT NULL,
  `entita_id`   INT UNSIGNED,
  `dati_prima`  JSON COMMENT 'Stato prima della modifica',
  `dati_dopo`   JSON COMMENT 'Stato dopo la modifica',
  `ip_address`  VARCHAR(45),
  `user_agent`  VARCHAR(500),
  `esito`       ENUM('OK','ERRORE','RIFIUTATO') NOT NULL DEFAULT 'OK',
  `messaggio`   VARCHAR(500),
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_utente` (`utente_id`),
  KEY `idx_al_entita` (`entita_tipo`, `entita_id`),
  KEY `idx_al_azione` (`azione`),
  KEY `idx_al_created` (`created_at`),
  CONSTRAINT `fk_al_utente` FOREIGN KEY (`utente_id`) REFERENCES `pm_utenti` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail completo azioni sistema';

CREATE TABLE `pm_report_salvati` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id`   INT UNSIGNED NOT NULL,
  `commessa_id` INT UNSIGNED,
  `tipo`        ENUM('AVANZAMENTO','SAL','COSTI','DOCUMENTI','SCADENZE','GANTT','PERSONALIZZATO') NOT NULL,
  `nome`        VARCHAR(200) NOT NULL,
  `parametri`   JSON NOT NULL COMMENT 'Parametri configurazione report',
  `schedulato`  TINYINT(1) NOT NULL DEFAULT 0,
  `frequenza`   VARCHAR(50) COMMENT 'cron expression',
  `destinatari` JSON COMMENT 'Array email destinatari',
  `ultimo_run`  TIMESTAMP NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rs_utente` (`utente_id`),
  CONSTRAINT `fk_rs_utente` FOREIGN KEY (`utente_id`) REFERENCES `pm_utenti` (`id`),
  CONSTRAINT `fk_rs_commessa` FOREIGN KEY (`commessa_id`) REFERENCES `pm_commesse` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEZIONE 9: DATI INIZIALI (SEED DATA)
-- =============================================================================

INSERT INTO `pm_ruoli` (`codice`, `nome`, `descrizione`, `livello`) VALUES
('SUPERADMIN', 'Super Amministratore', 'Accesso completo a tutte le funzionalità', 10),
('ADMIN', 'Amministratore', 'Gestione sistema e pm_utenti', 9),
('RUP', 'Responsabile Unico del Procedimento', 'RUP ai sensi D.Lgs. 36/2023', 7),
('PM', 'Project Manager', 'Gestione operativa del progetto', 6),
('DL', 'Direttore dei Lavori', 'Direzione lavori e contabilità', 6),
('CSE', 'Coordinatore Sicurezza in Esecuzione', 'Sicurezza cantiere in fase esecutiva', 5),
('IMPRESA', 'Impresa Esecutrice', 'Impresa aggiudicataria dei lavori', 4),
('TECNICO', 'Tecnico di Cantiere', 'Personale tecnico operativo', 3),
('AMMINISTRAZIONE', 'Ufficio Amministrativo', 'Gestione amministrativa e contabile', 5),
('READONLY', 'Sola Lettura', 'Accesso in sola lettura', 1);

INSERT INTO `pm_permessi` (`modulo`, `azione`, `codice`, `descrizione`) VALUES
('pm_commesse','read','pm_commesse.read','Visualizza pm_commesse'),
('pm_commesse','create','pm_commesse.create','Crea nuove pm_commesse'),
('pm_commesse','update','pm_commesse.update','Modifica pm_commesse'),
('pm_commesse','delete','pm_commesse.delete','Elimina pm_commesse'),
('pm_commesse','approve','pm_commesse.approve','Approva/chiudi pm_commesse'),
('pm_tasks','read','pm_tasks.read','Visualizza cronoprogramma'),
('pm_tasks','create','pm_tasks.create','Crea attività'),
('pm_tasks','update','pm_tasks.update','Modifica attività'),
('pm_tasks','delete','pm_tasks.delete','Elimina attività'),
('pm_sal','read','pm_sal.read','Visualizza SAL'),
('pm_sal','create','pm_sal.create','Crea SAL'),
('pm_sal','update','pm_sal.update','Modifica SAL'),
('pm_sal','approve','pm_sal.approve','Approva SAL'),
('pm_documenti','read','pm_documenti.read','Visualizza pm_documenti'),
('pm_documenti','upload','pm_documenti.upload','Carica pm_documenti'),
('pm_documenti','update','pm_documenti.update','Modifica pm_documenti'),
('pm_documenti','delete','pm_documenti.delete','Elimina pm_documenti'),
('pm_verbali','read','pm_verbali.read','Visualizza pm_verbali'),
('pm_verbali','create','pm_verbali.create','Crea pm_verbali'),
('pm_verbali','update','pm_verbali.update','Modifica pm_verbali'),
('pm_scadenze','read','pm_scadenze.read','Visualizza pm_scadenze'),
('pm_scadenze','create','pm_scadenze.create','Crea pm_scadenze'),
('pm_scadenze','update','pm_scadenze.update','Modifica pm_scadenze'),
('report','read','report.read','Visualizza report'),
('report','create','report.create','Genera report'),
('pm_utenti','read','pm_utenti.read','Visualizza pm_utenti'),
('pm_utenti','create','pm_utenti.create','Crea pm_utenti'),
('pm_utenti','update','pm_utenti.update','Modifica pm_utenti'),
('pm_utenti','delete','pm_utenti.delete','Elimina pm_utenti'),
('ai','use','ai.use','Utilizza assistente AI');

INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 1, id FROM `pm_permessi`;
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 2, id FROM `pm_permessi`;
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 3, id FROM `pm_permessi` WHERE `codice` NOT IN ('pm_utenti.delete','pm_utenti.create');
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 4, id FROM `pm_permessi` WHERE `modulo` IN ('pm_commesse','pm_tasks','pm_sal','pm_documenti','pm_verbali','pm_scadenze','report','ai')
  AND `azione` != 'delete' OR `codice` IN ('pm_commesse.read','pm_utenti.read');
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 5, id FROM `pm_permessi` WHERE `codice` IN (
  'pm_commesse.read','pm_tasks.read','pm_tasks.update',
  'pm_sal.read','pm_sal.create','pm_sal.update','pm_sal.approve',
  'pm_documenti.read','pm_documenti.upload','pm_documenti.update',
  'pm_verbali.read','pm_verbali.create','pm_verbali.update',
  'pm_scadenze.read','pm_scadenze.create','pm_scadenze.update',
  'report.read','report.create','ai.use'
);
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 6, id FROM `pm_permessi` WHERE `codice` IN (
  'pm_commesse.read','pm_tasks.read',
  'pm_documenti.read','pm_documenti.upload',
  'pm_verbali.read','pm_verbali.create','pm_verbali.update',
  'pm_scadenze.read','pm_scadenze.create',
  'report.read'
);
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 7, id FROM `pm_permessi` WHERE `codice` IN (
  'pm_commesse.read','pm_tasks.read','pm_tasks.update',
  'pm_sal.read','pm_documenti.read','pm_documenti.upload',
  'pm_verbali.read','pm_scadenze.read'
);
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 8, id FROM `pm_permessi` WHERE `codice` IN (
  'pm_commesse.read','pm_tasks.read','pm_tasks.update',
  'pm_documenti.read','pm_documenti.upload',
  'pm_verbali.read','pm_scadenze.read'
);
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 9, id FROM `pm_permessi` WHERE `codice` IN (
  'pm_commesse.read','pm_sal.read','pm_sal.approve',
  'pm_documenti.read','pm_documenti.upload',
  'pm_scadenze.read','pm_scadenze.create','pm_scadenze.update',
  'report.read','report.create','pm_utenti.read'
);
INSERT INTO `pm_ruoli_permessi` (`ruolo_id`, `permesso_id`) SELECT 10, id FROM `pm_permessi` WHERE `azione` = 'read';

INSERT INTO `pm_categorie_documento` (`codice`, `nome`, `descrizione`, `icona`, `colore`, `obbligatorio`, `ordine`) VALUES
('PROGETTO', 'Progetto Esecutivo', 'Elaborati progettuali definitivi/esecutivi', 'bi-file-earmark-ruled', '#0d6efd', 1, 1),
('CONTRATTO', 'Contratto e Capitolati', 'Contratto di appalto e capitolati speciali', 'bi-file-earmark-text', '#dc3545', 1, 2),
('PSC', 'Piano di Sicurezza e Coordinamento', 'PSC e POS cantiere', 'bi-shield-check', '#fd7e14', 1, 3),
('VERBALE', 'Verbali', 'Verbali di cantiere e riunioni', 'bi-file-earmark-check', '#198754', 0, 4),
('SAL_DOC', 'Documenti SAL', 'Certificati di pagamento e SAL', 'bi-currency-euro', '#6610f2', 1, 5),
('COLLAUDO', 'Collaudo', 'Certificato di collaudo e regolare esecuzione', 'bi-patch-check', '#20c997', 1, 6),
('AUTORIZZAZIONI', 'Autorizzazioni', 'Permessi, autorizzazioni, nulla osta', 'bi-card-checklist', '#e83e8c', 0, 7),
('CORRISPONDENZA', 'Corrispondenza', 'Lettere, PEC, comunicazioni ufficiali', 'bi-envelope-check', '#6c757d', 0, 8),
('FOTO', 'Documentazione Fotografica', 'Rilievi fotografici e video cantiere', 'bi-camera', '#17a2b8', 0, 9),
('ALTRO', 'Altro', 'Documentazione varia', 'bi-file-earmark', '#adb5bd', 0, 10);

INSERT INTO `pm_utenti` (`uuid`, `ruolo_id`, `nome`, `cognome`, `email`, `password_hash`, `attivo`, `email_verificata`) VALUES
(UUID(), 1, 'Super', 'Admin', 'admin@pm_appalti.local',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 1, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- VISTE RIMOSSE - non supportate da Altervista (privilegi insufficienti)
-- =============================================================================
-- Le seguenti query PHP sostituiscono le viste:
--
-- [v_commesse_riepilogo]
-- SELECT c.*, sa.denominazione AS stazione_appaltante, i.ragione_sociale AS impresa,
--   CONCAT(urup.cognome,' ',urup.nome) AS rup_nominativo,
--   CONCAT(upm.cognome,' ',upm.nome) AS pm_nominativo,
--   CONCAT(udl.cognome,' ',udl.nome) AS dl_nominativo,
--   a.codice_cig, a.codice_cup,
--   (SELECT COUNT(*) FROM pm_tasks t WHERE t.commessa_id=c.id AND t.stato='IN_RITARDO') AS tasks_in_ritardo,
--   (SELECT COUNT(*) FROM pm_sal s WHERE s.commessa_id=c.id AND s.stato='APPROVATO') AS sal_approvati,
--   (SELECT COALESCE(s2.importo_cumulato,0) FROM pm_sal s2
--     WHERE s2.commessa_id=c.id AND s2.stato IN ('APPROVATO','PAGATO')
--     AND s2.numero_sal=(SELECT MAX(s3.numero_sal) FROM pm_sal s3
--       WHERE s3.commessa_id=c.id AND s3.stato IN ('APPROVATO','PAGATO'))
--   ) AS importo_liquidato
-- FROM pm_commesse c
-- JOIN pm_appalti a ON a.id=c.appalto_id
-- JOIN pm_stazioni_appaltanti sa ON sa.id=a.stazione_appaltante_id
-- JOIN pm_imprese i ON i.id=c.impresa_id
-- LEFT JOIN pm_utenti urup ON urup.id=c.rup_id
-- LEFT JOIN pm_utenti upm ON upm.id=c.pm_id
-- LEFT JOIN pm_utenti udl ON udl.id=c.dl_id;
--
-- [v_sal_voci]
-- SELECT sv.*, sv.quantita_periodo * cl.prezzo_unitario AS importo_periodo,
--   cl.codice AS categoria_codice, cl.descrizione AS categoria_descrizione,
--   cl.unita_misura, cl.prezzo_unitario
-- FROM pm_sal_voci sv
-- JOIN pm_categorie_lavoro cl ON cl.id=sv.categoria_id;
--
-- [v_scadenze_prossime]
-- SELECT sc.*, c.codice_commessa, c.oggetto AS commessa_oggetto,
--   CONCAT(u.cognome,' ',u.nome) AS responsabile_nominativo,
--   DATEDIFF(sc.data_scadenza, CURDATE()) AS giorni_alla_scadenza
-- FROM pm_scadenze sc
-- LEFT JOIN pm_commesse c ON c.id=sc.commessa_id
-- LEFT JOIN pm_utenti u ON u.id=sc.responsabile_id
-- WHERE sc.stato='ATTIVA'
--   AND sc.data_scadenza >= CURDATE()
--   AND sc.data_scadenza <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
-- ORDER BY sc.data_scadenza ASC;
--
-- [v_kpi_commessa]
-- SELECT c.id AS commessa_id, c.codice_commessa, c.importo_contrattuale, c.percentuale_avanzamento,
--   (SELECT COUNT(*) FROM pm_tasks t WHERE t.commessa_id=c.id) AS totale_tasks,
--   (SELECT COUNT(*) FROM pm_tasks t WHERE t.commessa_id=c.id AND t.stato='COMPLETATO') AS tasks_completati,
--   (SELECT COUNT(*) FROM pm_tasks t WHERE t.commessa_id=c.id AND t.stato='IN_RITARDO') AS tasks_ritardo,
--   (SELECT COUNT(*) FROM pm_sal s WHERE s.commessa_id=c.id) AS numero_sal,
--   (SELECT COALESCE(MAX(s.importo_cumulato),0) FROM pm_sal s
--     WHERE s.commessa_id=c.id AND s.stato IN ('APPROVATO','PAGATO')) AS importo_liquidato,
--   (SELECT COUNT(*) FROM pm_documenti d WHERE d.commessa_id=c.id AND d.stato='PUBBLICATO') AS totale_documenti,
--   (SELECT COUNT(*) FROM pm_scadenze sc WHERE sc.commessa_id=c.id AND sc.stato='SCADUTA') AS scadenze_scadute,
--   DATEDIFF(c.data_fine_prevista, CURDATE()) AS giorni_alla_fine,
--   c.scostamento_giorni
-- FROM pm_commesse c;
-- =============================================================================
