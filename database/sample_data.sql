-- =============================================================================
-- DATI DI ESEMPIO — Appalti SaaS v5
-- Generato: 2026-03-09
--
-- PREREQUISITO: eseguire prima database/schema.sql
-- UTENTI: tutti hanno password "Password123!"
--   Hash bcrypt: $2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
--
-- STRUTTURA DATI:
--   2 Stazioni Appaltanti | 3 Imprese | 3 Appalti | 3 Commesse
--   7 Utenti aggiuntivi   | Fasi, Task, SAL, Verbali, Scadenze, Documenti
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- =============================================================================
-- UTENTI (il superadmin id=1 è già inserito dallo schema)
-- Ruoli: 1=SUPERADMIN 2=ADMIN 3=RUP 4=PM 5=DL 6=CSE 7=IMPRESA 8=TECNICO 9=AMMINISTRAZIONE
-- =============================================================================

INSERT INTO `pm_utenti`
  (id, uuid, ruolo_id, nome, cognome, email, password_hash,
   telefono, matricola, qualifica, ordine_professionale, numero_iscrizione,
   attivo, email_verificata, notifiche_email)
VALUES
(2, '11111111-1111-1111-1111-000000000002', 3,
 'Mario', 'Rossi', 'mario.rossi@comune-bergamo.example.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '035 123456', 'MAT001', 'Ingegnere Civile – RUP',
 'Ordine Ingegneri Bergamo', 'BG-1234', 1, 1, 1),

(3, '11111111-1111-1111-1111-000000000003', 4,
 'Laura', 'Bianchi', 'laura.bianchi@comune-bergamo.example.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '035 234567', 'MAT002', 'Architetto – Project Manager',
 'Ordine Architetti Bergamo', 'BG-5678', 1, 1, 1),

(4, '11111111-1111-1111-1111-000000000004', 5,
 'Giuseppe', 'Verdi', 'g.verdi@studio-verdi-ingegneria.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '030 345678', 'EXT001', 'Ingegnere – Direttore Lavori',
 'Ordine Ingegneri Brescia', 'BS-2345', 1, 1, 1),

(5, '11111111-1111-1111-1111-000000000005', 6,
 'Anna', 'Ferrari', 'a.ferrari@sicurezza-cantieri.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '030 456789', 'EXT002', 'Geometra – Coordinatore Sicurezza CSE',
 'Collegio Geometri Brescia', 'BS-6789', 1, 1, 1),

(6, '11111111-1111-1111-1111-000000000006', 7,
 'Roberto', 'Martinelli', 'r.martinelli@costruzionialfa.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '02 567890', 'IMP001', 'Responsabile Tecnico – Costruzioni Alfa',
 NULL, NULL, 1, 1, 1),

(7, '11111111-1111-1111-1111-000000000007', 8,
 'Carlo', 'Esposito', 'carlo.esposito@comune-bergamo.example.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '035 678901', 'MAT003', 'Geometra Tecnico',
 'Collegio Geometri Bergamo', 'BG-9012', 1, 1, 1),

(8, '11111111-1111-1111-1111-000000000008', 9,
 'Federica', 'Conti', 'f.conti@comune-bergamo.example.it',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 '035 789012', 'MAT004', 'Funzionario Amministrativo',
 NULL, NULL, 1, 1, 1);

-- =============================================================================
-- STAZIONI APPALTANTI
-- =============================================================================

INSERT INTO `pm_stazioni_appaltanti`
  (id, codice_fiscale, denominazione, tipo,
   indirizzo, citta, cap, provincia, telefono, pec, sito_web, codice_belfiore, attivo)
VALUES
(1, '00691530164', 'Comune di Bergamo', 'COMUNE',
 'Piazza Vittorio Veneto, 1', 'Bergamo', '24122', 'BG',
 '035 399111', 'protocollo@pec.comune.bergamo.it',
 'https://www.comune.bergamo.it', 'A794', 1),

(2, '00382520172', 'Provincia di Brescia', 'PROVINCIA',
 'Corso Zanardelli, 1', 'Brescia', '25121', 'BS',
 '030 37411', 'protocollo@pec.provincia.brescia.it',
 'https://www.provincia.brescia.it', 'B157', 1);

-- =============================================================================
-- IMPRESE
-- =============================================================================

INSERT INTO `pm_imprese`
  (id, codice_fiscale, partita_iva, ragione_sociale, forma_giuridica,
   indirizzo, citta, cap, provincia, telefono, email, pec, iban,
   soa_categorie, durc_scadenza, rating_legalita, attivo)
VALUES
(1, '02345678901', '02345678901', 'Costruzioni Alfa S.r.l.', 'S.r.l.',
 'Via dell''Industria, 12', 'Seriate', '24068', 'BG',
 '035 292000', 'info@costruzionialfa.it', 'costruzionialfa@pec.it',
 'IT60 X054 2811 1010 0000 0123 456',
 'OG1 Classifica V, OS6 Classifica III',
 '2026-09-30', 2.50, 1),

(2, '03456789012', '03456789012', 'Edilizia Beta S.p.A.', 'S.p.A.',
 'Via Roma, 45', 'Brescia', '25126', 'BS',
 '030 345678', 'info@ediliziabeta.it', 'ediliziabeta@pec.it',
 'IT60 X054 2811 2020 0000 0234 567',
 'OG1 Classifica VI, OG6 Classifica IV',
 '2026-06-30', 2.80, 1),

(3, '04567890123', '04567890123', 'Infrastrutture Gamma S.n.c.', 'S.n.c.',
 'Via Po, 78', 'Brescia', '25123', 'BS',
 '030 456789', 'info@infragamma.it', 'infragamma@pec.it',
 'IT60 X054 2811 3030 0000 0345 678',
 'OG6 Classifica V, OG8 Classifica III',
 '2026-12-31', 2.20, 1);

-- =============================================================================
-- APPALTI
-- =============================================================================

INSERT INTO `pm_appalti`
  (id, uuid, stazione_appaltante_id, codice_cig, codice_cup,
   oggetto, tipo_appalto, procedura, criterio_aggiudicazione,
   importo_base_asta, importo_sicurezza, importo_aggiudicato, ribasso_percentuale,
   data_pubblicazione, data_aggiudicazione, data_stipula_contratto, data_consegna_lavori,
   rup_id, stato, cat_prevalente, codice_nuts, created_by)
VALUES
(1, '22222222-2222-2222-2222-000000000001',
 1, 'ZA1234567890', 'F11B24001230007',
 'Manutenzione ordinaria e straordinaria strade comunali – Lotto 1 zona centro storico',
 'LAVORI', 'NEGOZIATA', 'OFFERTA_ECONOMICAMENTE_VANTAGGIOSA',
 465000.00, 15000.00, 451200.00, 0.0296,
 '2024-10-15', '2024-12-20', '2025-01-15', '2025-03-01',
 2, 'IN_ESECUZIONE', 'OG3', 'ITC46', 2),

(2, '22222222-2222-2222-2222-000000000002',
 1, 'ZB2345678901', 'F11B24001240008',
 'Ristrutturazione e adeguamento sismico Scuola Elementare G. Garibaldi – Via Dante 15',
 'LAVORI', 'APERTA', 'OFFERTA_ECONOMICAMENTE_VANTAGGIOSA',
 820000.00, 30000.00, 798500.00, 0.0262,
 '2024-07-01', '2024-11-10', '2025-01-30', '2025-06-01',
 2, 'IN_ESECUZIONE', 'OG1', 'ITC46', 2),

(3, '22222222-2222-2222-2222-000000000003',
 2, 'ZC3456789012', 'G21B25000010009',
 'Ampliamento e rifacimento rete fognaria zona nord – Via Industriale e limitrofe',
 'LAVORI', 'APERTA', 'PREZZO_PIU_BASSO',
 1150000.00, 50000.00, 1098000.00, 0.0452,
 '2025-09-01', '2026-01-15', '2026-02-20', '2026-04-01',
 2, 'CONTRATTO', 'OG6', 'ITC47', 2);

-- =============================================================================
-- COMMESSE
-- Colonne GENERATED escluse: durata_effettiva, scostamento_giorni
-- =============================================================================

INSERT INTO `pm_commesse`
  (id, uuid, appalto_id, impresa_id, codice_commessa, oggetto,
   descrizione, luogo_esecuzione, comune, provincia,
   rup_id, pm_id, dl_id, cse_id,
   importo_contrattuale, importo_sicurezza,
   data_inizio_prevista, data_fine_prevista, data_inizio_effettiva,
   durata_contrattuale, percentuale_avanzamento,
   stato, priorita, colore, created_by)
VALUES
(1, '33333333-3333-3333-3333-000000000001',
 1, 1, '2025-BG-001',
 'Manutenzione strade comunali – Lotto 1 centro storico',
 'Interventi di manutenzione su marciapiedi, asfalto e segnaletica verticale nel centro storico di Bergamo. '
 'Comprende rifacimento manto stradale via Gombito, via Colleoni e piazza Vecchia.',
 'Centro Storico – Città Alta', 'Bergamo', 'BG',
 2, 3, 4, 5,
 451200.00, 15000.00,
 '2025-03-01', '2026-02-28', '2025-03-01',
 365, 68.50,
 'IN_ESECUZIONE', 'ALTA', '#0d6efd', 2),

(2, '33333333-3333-3333-3333-000000000002',
 2, 2, '2025-BG-002',
 'Ristrutturazione Scuola Elementare G. Garibaldi',
 'Lavori di ristrutturazione, adeguamento sismico e messa a norma antincendio della scuola elementare. '
 'Previsti interventi su struttura portante, impianti elettrici e termici, facciate e coperture.',
 'Via Dante, 15', 'Bergamo', 'BG',
 2, 3, 4, 5,
 798500.00, 30000.00,
 '2025-06-01', '2026-11-30', '2025-06-10',
 540, 32.00,
 'IN_ESECUZIONE', 'CRITICA', '#dc3545', 2),

(3, '33333333-3333-3333-3333-000000000003',
 3, 3, '2026-BS-001',
 'Ampliamento rete fognaria zona nord',
 'Posa di nuova rete fognaria mista e separata in via Industriale, via dell''Artigianato e strade limitrofe. '
 'Realizzazione di 2 vasche di prima pioggia e collegamento al depuratore consortile.',
 'Zona Industriale Nord', 'Brescia', 'BS',
 2, 3, 4, 5,
 1098000.00, 50000.00,
 '2026-04-01', '2027-09-30', NULL,
 548, 0.00,
 'PIANIFICAZIONE', 'NORMALE', '#198754', 2);

-- =============================================================================
-- TEAM COMMESSE
-- =============================================================================

INSERT INTO `pm_commesse_utenti`
  (commessa_id, utente_id, ruolo_progetto, data_inizio, percentuale_allocazione)
VALUES
(1, 2, 'RUP',    '2025-03-01', 20.00),
(1, 3, 'PM',     '2025-03-01', 60.00),
(1, 4, 'DL',     '2025-03-01', 40.00),
(1, 5, 'CSE',    '2025-03-01', 30.00),
(1, 6, 'IMPRESA','2025-03-01', 100.00),
(1, 7, 'TECNICO','2025-03-01', 50.00),

(2, 2, 'RUP',    '2025-06-01', 20.00),
(2, 3, 'PM',     '2025-06-01', 70.00),
(2, 4, 'DL',     '2025-06-01', 60.00),
(2, 5, 'CSE',    '2025-06-01', 50.00),
(2, 6, 'IMPRESA','2025-06-01', 100.00),

(3, 2, 'RUP',    '2026-04-01', 15.00),
(3, 3, 'PM',     '2026-04-01', 50.00),
(3, 4, 'DL',     '2026-04-01', 50.00),
(3, 5, 'CSE',    '2026-04-01', 40.00);

-- =============================================================================
-- FASI DI LAVORO
-- =============================================================================

INSERT INTO `pm_fasi_lavoro`
  (id, commessa_id, codice, nome, ordine, colore)
VALUES
-- Commessa 1 – Manutenzione strade
(1,  1, 'C1-F1', 'Cantierizzazione e opere provvisorie', 1, '#fd7e14'),
(2,  1, 'C1-F2', 'Rifacimento manto stradale',           2, '#0d6efd'),
(3,  1, 'C1-F3', 'Marciapiedi e segnaletica',            3, '#198754'),
-- Commessa 2 – Scuola
(4,  2, 'C2-F1', 'Indagini diagnostiche e demolizioni',  1, '#6c757d'),
(5,  2, 'C2-F2', 'Strutture e adeguamento sismico',      2, '#dc3545'),
(6,  2, 'C2-F3', 'Impianti, finiture e arredi',          3, '#6610f2'),
-- Commessa 3 – Fognatura
(7,  3, 'C3-F1', 'Scavi e allestimento cantiere',        1, '#e83e8c'),
(8,  3, 'C3-F2', 'Posa tubazioni e pozzetti',            2, '#fd7e14'),
(9,  3, 'C3-F3', 'Vasche prima pioggia e collaudi',      3, '#20c997');

-- =============================================================================
-- TASK (cronoprogramma)
-- Colonne GENERATED escluse. Colonne obbligatorie: codice_wbs, nome, created_by
-- =============================================================================

INSERT INTO `pm_tasks`
  (id, uuid, commessa_id, fase_id, codice_wbs, nome, tipo,
   assegnato_a, data_inizio_prevista, data_fine_prevista,
   data_inizio_effettiva, data_fine_effettiva,
   durata_prevista, percentuale_completamento, importo_previsto,
   stato, priorita, ordine, created_by)
VALUES
-- Commessa 1
(1,  'aaaaaaaa-0001-0001-0001-000000000001', 1, 1, '1.1',
 'Installazione cantiere e segnaletica temporanea', 'TASK',
 7, '2025-03-01', '2025-03-15', '2025-03-01', '2025-03-14',
 10, 100.00, 8500.00, 'COMPLETATO', 'ALTA', 1, 3),

(2,  'aaaaaaaa-0001-0001-0001-000000000002', 1, 2, '1.2',
 'Fresatura e rifacimento manto – Via Gombito', 'TASK',
 7, '2025-03-16', '2025-05-31', '2025-03-17', NULL,
 55, 100.00, 120000.00, 'COMPLETATO', 'ALTA', 2, 3),

(3,  'aaaaaaaa-0001-0001-0001-000000000003', 1, 2, '1.3',
 'Rifacimento manto – Via Colleoni e Piazza Vecchia', 'TASK',
 7, '2025-06-01', '2025-09-30', '2025-06-05', NULL,
 87, 85.00, 185000.00, 'IN_CORSO', 'ALTA', 3, 3),

(4,  'aaaaaaaa-0001-0001-0001-000000000004', 1, 3, '1.4',
 'Rifacimento marciapiedi e posa segnaletica definitiva', 'TASK',
 7, '2025-10-01', '2026-02-28', NULL, NULL,
 121, 0.00, 137700.00, 'NON_INIZIATO', 'NORMALE', 4, 3),

-- Commessa 2
(5,  'aaaaaaaa-0002-0002-0002-000000000005', 2, 4, '2.1',
 'Indagini strutturali e rilievo geometrico', 'TASK',
 7, '2025-06-01', '2025-07-15', '2025-06-10', '2025-07-20',
 30, 100.00, 18000.00, 'COMPLETATO', 'ALTA', 1, 3),

(6,  'aaaaaaaa-0002-0002-0002-000000000006', 2, 4, '2.2',
 'Demolizioni selettive e sgombero macerie', 'TASK',
 6, '2025-07-16', '2025-08-31', '2025-07-22', '2025-09-05',
 35, 100.00, 45000.00, 'COMPLETATO', 'ALTA', 2, 3),

(7,  'aaaaaaaa-0002-0002-0002-000000000007', 2, 5, '2.3',
 'Rinforzi strutturali e cerchiatura sismica', 'TASK',
 4, '2025-09-01', '2025-12-31', '2025-09-08', NULL,
 87, 70.00, 320000.00, 'IN_CORSO', 'CRITICA', 3, 3),

(8,  'aaaaaaaa-0002-0002-0002-000000000008', 2, 6, '2.4',
 'Impianto elettrico, termico e antincendio', 'TASK',
 4, '2026-01-01', '2026-08-31', NULL, NULL,
 152, 0.00, 215500.00, 'NON_INIZIATO', 'ALTA', 4, 3),

(9,  'aaaaaaaa-0002-0002-0002-000000000009', 2, 6, '2.5',
 'Finiture, tinteggiatura e arredi scolastici', 'MILESTONE',
 3, '2026-09-01', '2026-11-30', NULL, NULL,
 60, 0.00, 200000.00, 'NON_INIZIATO', 'NORMALE', 5, 3),

-- Commessa 3
(10, 'aaaaaaaa-0003-0003-0003-000000000010', 3, 7, '3.1',
 'Allestimento cantiere e deviazioni traffico', 'TASK',
 7, '2026-04-01', '2026-04-20', NULL, NULL,
 14, 0.00, 25000.00, 'NON_INIZIATO', 'ALTA', 1, 3),

(11, 'aaaaaaaa-0003-0003-0003-000000000011', 3, 8, '3.2',
 'Scavi e posa rete fognaria via Industriale', 'TASK',
 4, '2026-04-21', '2026-09-30', NULL, NULL,
 123, 0.00, 450000.00, 'NON_INIZIATO', 'ALTA', 2, 3),

(12, 'aaaaaaaa-0003-0003-0003-000000000012', 3, 8, '3.3',
 'Posa rete fognaria strade limitrofe', 'TASK',
 4, '2026-10-01', '2027-03-31', NULL, NULL,
 121, 0.00, 380000.00, 'NON_INIZIATO', 'NORMALE', 3, 3),

(13, 'aaaaaaaa-0003-0003-0003-000000000013', 3, 9, '3.4',
 'Vasche di prima pioggia e collegamento depuratore', 'TASK',
 4, '2027-04-01', '2027-08-31', NULL, NULL,
 91, 0.00, 243000.00, 'NON_INIZIATO', 'ALTA', 4, 3);

-- =============================================================================
-- DIPENDENZE TASK (Finish-to-Start)
-- =============================================================================

INSERT INTO `pm_dipendenze_tasks` (task_id, task_pred_id, tipo, lag_giorni)
VALUES
(2, 1, 'FS', 1),   -- via Gombito dopo cantiere
(3, 2, 'FS', 0),   -- via Colleoni dopo via Gombito
(4, 3, 'FS', 0),   -- marciapiedi dopo asfalto
(6, 5, 'FS', 0),   -- demolizioni dopo indagini
(7, 6, 'FS', 5),   -- strutture dopo demolizioni (5gg lag)
(8, 7, 'FS', 0),   -- impianti dopo strutture
(9, 8, 'FS', 0),   -- finiture dopo impianti
(11,10, 'FS', 1),  -- scavi dopo allestimento
(12,11, 'FS', 0),
(13,12, 'FS', 0);

-- =============================================================================
-- CATEGORIE LAVORO (Elenco Prezzi)
-- Colonne GENERATED escluse: importo_contrattuale
-- =============================================================================

INSERT INTO `pm_categorie_lavoro`
  (id, commessa_id, codice, descrizione, unita_misura,
   prezzo_unitario, quantita_contrattuale, categoria_soa, ordine)
VALUES
-- Commessa 1 – Manutenzione strade
(1, 1, 'S.01', 'Fresatura manto bituminoso esistente sp.4 cm', 'm²',
  3.50, 18000.0000, 'OG3', 1),
(2, 1, 'S.02', 'Fornitura e posa conglomerato bituminoso usura sp.4 cm', 'm²',
 12.80, 14500.0000, 'OG3', 2),
(3, 1, 'S.03', 'Rifacimento marciapiede in porfido', 'm²',
 95.00, 1200.0000, 'OG3', 3),
(4, 1, 'S.04', 'Segnaletica orizzontale vernice bianca rifrangente', 'm²',
 18.50, 650.0000, 'OG3', 4),

-- Commessa 2 – Scuola
(5, 2, 'E.01', 'Demolizione solaio cls armato sp. 20 cm', 'm³',
 85.00, 180.0000, 'OG1', 1),
(6, 2, 'E.02', 'Rinforzo strutturale con placcaggio FRP su pilastri', 'ml',
 320.00, 480.0000, 'OG1', 2),
(7, 2, 'E.03', 'Solaio prefabbricato a lastre predalles sp.22+5', 'm²',
 68.00, 1800.0000, 'OG1', 3),
(8, 2, 'E.04', 'Impianto elettrico a norma CEI 64-8 incluso quadri', 'corpo',
 95000.00, 1.0000, 'OG1', 4),
(9, 2, 'E.05', 'Intonaco civile rasato e tinteggiatura murale', 'm²',
 28.50, 4200.0000, 'OG1', 5),

-- Commessa 3 – Fognatura
(10, 3, 'F.01', 'Scavo a sezione obbligata h<3m in terreno sciolto', 'm³',
 22.00, 8500.0000, 'OG6', 1),
(11, 3, 'F.02', 'Fornitura e posa tubo gres DN400 per rete mista', 'ml',
 185.00, 2200.0000, 'OG6', 2),
(12, 3, 'F.03', 'Pozzetto in cls prefabbricato diam. 120 cm', 'cad',
 480.00, 185.0000, 'OG6', 3),
(13, 3, 'F.04', 'Vasca prima pioggia cls armato V=200 m³', 'cad',
 65000.00, 2.0000, 'OG6', 4),
(14, 3, 'F.05', 'Rinterro e ripristino piano viabile', 'm³',
 38.00, 6800.0000, 'OG6', 5);

-- =============================================================================
-- SAL – Stati Avanzamento Lavori
-- Colonne GENERATED escluse: importo_totale, importo_netto
-- =============================================================================

INSERT INTO `pm_sal`
  (id, uuid, commessa_id, numero_sal,
   data_inizio, data_fine, data_emissione,
   importo_lavori, importo_sicurezza, importo_varianti,
   importo_cumulato, ritenuta_garanzia, percentuale_avanzamento,
   stato, dl_id, rup_id, note_dl, data_approvazione, data_pagamento, created_by)
VALUES
-- SAL 1 commessa 1 (PAGATO)
(1, '44444444-0001-0001-0001-000000000001',
 1, 1, '2025-03-01', '2025-06-30', '2025-07-10',
 148560.00, 4950.00, 0.00,
 153510.00, 7675.50, 33.90,
 'PAGATO', 4, 2,
 'SAL regolare. Lavori via Gombito completati al 100%. Avanzamento complessivo 33,9%.',
 '2025-07-25', '2025-08-20', 3),

-- SAL 2 commessa 1 (APPROVATO, in attesa pagamento)
(2, '44444444-0001-0001-0001-000000000002',
 1, 2, '2025-07-01', '2025-12-31', '2026-01-15',
 161784.00, 5400.00, 0.00,
 321294.00, 16064.70, 71.20,
 'APPROVATO', 4, 2,
 'Via Colleoni avanzata all''85%. Alcune interferenze con cantiere privato adiacente risolte.',
 '2026-02-01', NULL, 3),

-- SAL 1 commessa 2 (BOZZA)
(3, '44444444-0002-0002-0002-000000000003',
 2, 1, '2025-06-01', '2025-12-31', NULL,
 255520.00, 9600.00, 0.00,
 265120.00, 13256.00, 32.00,
 'BOZZA', 4, 2, NULL, NULL, NULL, 3);

-- =============================================================================
-- SAL VOCI
-- =============================================================================

INSERT INTO `pm_sal_voci`
  (sal_id, categoria_id, quantita_periodo, quantita_cumulata, importo_periodo)
VALUES
-- SAL 1, commessa 1
(1, 1, 18000.0000, 18000.0000,  63000.00),  -- fresatura 100%
(1, 2, 6000.0000,  6000.0000,   76800.00),  -- asfalto 41%
(1, 3, 0.0000,     0.0000,          0.00),  -- marciapiedi 0%
(1, 4, 0.0000,     0.0000,          0.00),  -- segnaletica 0%
-- SAL 2, commessa 1
(2, 2, 8500.0000,  14500.0000, 108800.00),  -- asfalto restante 59%
(2, 3, 550.0000,   550.0000,   52250.00),   -- marciapiedi 46%
(2, 4, 0.0000,     0.0000,         0.00),
-- SAL 1, commessa 2
(3, 5, 180.0000,   180.0000,   15300.00),   -- demolizioni 100%
(3, 6, 336.0000,   336.0000,  107520.00),   -- FRP 70%
(3, 7, 1260.0000,  1260.0000,  85680.00),   -- solaio 70%
(3, 8, 0.0000,     0.0000,         0.00),
(3, 9, 0.0000,     0.0000,         0.00);

-- =============================================================================
-- VARIANTI
-- =============================================================================

INSERT INTO `pm_varianti`
  (commessa_id, numero, tipo, motivo, oggetto, descrizione,
   importo, stato, data_richiesta, data_approvazione, approvata_da, created_by)
VALUES
(1, 1,
 'TECNICA', 'ERRORI_OMISSIONI',
 'Aggiunta tratto via S. Lorenzo non previsto in progetto',
 'Durante l''esecuzione è emersa la necessità di intervenire su un tratto di 120 ml di via S. Lorenzo '
 'non incluso nel progetto originario ma urgente per garantire la continuità viaria.',
 14200.00, 'APPROVATA', '2025-05-10', '2025-06-05', 2, 4),

(2, 1,
 'TECNICA_ECONOMICA', 'ART_120_C1_B',
 'Miglioramento classe energetica – sostituzione infissi e cappotto termico',
 'Su richiesta della SA, vengono aggiunti interventi di efficientamento energetico non previsti: '
 'sostituzione di tutti gli infissi con telaio in alluminio a taglio termico e posa di cappotto esterno.',
 38000.00, 'APPROVATA', '2025-10-01', '2025-11-10', 2, 4);

-- =============================================================================
-- DOCUMENTI
-- Categorie (da schema): 1=PROGETTO 2=CONTRATTO 3=PSC 4=VERBALE 5=SAL_DOC
--   6=COLLAUDO 7=AUTORIZZAZIONI 8=CORRISPONDENZA 9=FOTO 10=ALTRO
-- =============================================================================

INSERT INTO `pm_documenti`
  (id, uuid, commessa_id, categoria_id, titolo, descrizione,
   nome_file, path_file, mime_type, dimensione, versione,
   stato, data_documento, uploaded_by)
VALUES
-- Commessa 1
(1, '55555555-0001-0001-0001-000000000001',
 1, 2, 'Contratto d''appalto – Costruzioni Alfa S.r.l.',
 'Contratto stipulato in data 15/01/2025 con Costruzioni Alfa S.r.l.',
 'contratto_2025-BG-001.pdf', 'uploads/commesse/1/contratto_2025-BG-001.pdf',
 'application/pdf', 1245184, 1, 'PUBBLICATO', '2025-01-15', 3),

(2, '55555555-0001-0001-0001-000000000002',
 1, 3, 'Piano di Sicurezza e Coordinamento – Rev.0',
 'PSC redatto dal CSE per i lavori di manutenzione strade.',
 'PSC_2025-BG-001_rev0.pdf', 'uploads/commesse/1/PSC_2025-BG-001_rev0.pdf',
 'application/pdf', 892416, 1, 'PUBBLICATO', '2025-02-10', 5),

(3, '55555555-0001-0001-0001-000000000003',
 1, 9, 'Rilievo fotografico pre-cantiere – Via Gombito',
 'Documentazione fotografica dello stato dei luoghi prima dell''inizio lavori.',
 'foto_precantiere_via_gombito.zip', 'uploads/commesse/1/foto_precantiere_via_gombito.zip',
 'application/zip', 45088768, 1, 'PUBBLICATO', '2025-02-28', 7),

-- Commessa 2
(4, '55555555-0002-0002-0002-000000000004',
 2, 2, 'Contratto d''appalto – Edilizia Beta S.p.A.',
 'Contratto stipulato in data 30/01/2025 con Edilizia Beta S.p.A.',
 'contratto_2025-BG-002.pdf', 'uploads/commesse/2/contratto_2025-BG-002.pdf',
 'application/pdf', 1548288, 1, 'PUBBLICATO', '2025-01-30', 3),

(5, '55555555-0002-0002-0002-000000000005',
 2, 1, 'Progetto Esecutivo – Relazione strutturale',
 'Relazione di calcolo strutturale e adeguamento sismico ai sensi del DM 17/01/2018.',
 'relazione_strutturale_esecutivo.pdf',
 'uploads/commesse/2/relazione_strutturale_esecutivo.pdf',
 'application/pdf', 3276800, 1, 'PUBBLICATO', '2025-01-10', 4),

(6, '55555555-0002-0002-0002-000000000006',
 2, 7, 'Autorizzazione sismica – Regione Lombardia',
 'Autorizzazione per l''esecuzione di opere in zona sismica 3 rilasciata dalla Regione.',
 'autorizzazione_sismica_RL_2025.pdf',
 'uploads/commesse/2/autorizzazione_sismica_RL_2025.pdf',
 'application/pdf', 512000, 1, 'PUBBLICATO', '2025-04-15', 3),

-- Commessa 3
(7, '55555555-0003-0003-0003-000000000007',
 3, 2, 'Contratto d''appalto – Infrastrutture Gamma S.n.c.',
 'Contratto stipulato in data 20/02/2026 con Infrastrutture Gamma S.n.c.',
 'contratto_2026-BS-001.pdf', 'uploads/commesse/3/contratto_2026-BS-001.pdf',
 'application/pdf', 1048576, 1, 'PUBBLICATO', '2026-02-20', 3),

(8, '55555555-0003-0003-0003-000000000008',
 3, 3, 'Piano di Sicurezza e Coordinamento – Rete fognaria',
 'PSC completo per cantiere stradale con interferenze con sottoservizi.',
 'PSC_2026-BS-001.pdf', 'uploads/commesse/3/PSC_2026-BS-001.pdf',
 'application/pdf', 1204224, 1, 'PUBBLICATO', '2026-03-01', 5);

-- =============================================================================
-- VERBALI
-- =============================================================================

INSERT INTO `pm_verbali`
  (id, uuid, commessa_id, tipo, numero, oggetto, contenuto,
   luogo, data_verbale, presenti, stato, redatto_da)
VALUES
-- Commessa 1
(1, '66666666-0001-0001-0001-000000000001',
 1, 'CONSEGNA_LAVORI', 'VCL-2025-001',
 'Verbale di consegna lavori – 2025-BG-001',
 'L''anno 2025 il giorno 1 del mese di marzo, il sottoscritto Direttore Lavori ing. Giuseppe Verdi, '
 'munito di mandato del RUP ing. Mario Rossi, ha consegnato i lavori a Costruzioni Alfa S.r.l., '
 'nella persona del responsabile tecnico sig. Roberto Martinelli. '
 'I lavori dovranno essere ultimati entro il 28/02/2026.',
 'Via Gombito, Bergamo (Città Alta)', '2025-03-01 10:00:00',
 '[{"nome":"Mario Rossi","ruolo":"RUP"},{"nome":"Giuseppe Verdi","ruolo":"DL"},'
 '{"nome":"Anna Ferrari","ruolo":"CSE"},{"nome":"Roberto Martinelli","ruolo":"Appaltatore"}]',
 'FIRMATO', 4),

(2, '66666666-0001-0001-0001-000000000002',
 1, 'STATO_AVANZAMENTO', 'VSA-2025-003',
 'Verbale di visita cantiere e SAL n.1 – ottobre 2025',
 'Sopralluogo congiunto per verifica avanzamento lavori. Riscontrato completamento al 68,5% del '
 'programma lavori. Via Gombito ultimata. Via Colleoni in corso all''85%. '
 'Confermata data ultimazione prevista. Nessuna criticità rilevante.',
 'Cantiere Via Colleoni, Bergamo', '2025-10-15 09:30:00',
 '[{"nome":"Giuseppe Verdi","ruolo":"DL"},{"nome":"Anna Ferrari","ruolo":"CSE"},'
 '{"nome":"Roberto Martinelli","ruolo":"Appaltatore"},{"nome":"Carlo Esposito","ruolo":"Tecnico SA"}]',
 'FIRMATO', 4),

-- Commessa 2
(3, '66666666-0002-0002-0002-000000000003',
 2, 'CONSEGNA_LAVORI', 'VCL-2025-002',
 'Verbale di consegna lavori – 2025-BG-002 Scuola Garibaldi',
 'Si procede alla consegna lavori di ristrutturazione della scuola elementare G. Garibaldi. '
 'L''edificio viene consegnato libero da persone e cose. I lavori dovranno essere condotti '
 'in modo da limitare le interferenze con le attività scolastiche dell''istituto adiacente.',
 'Scuola Elementare G. Garibaldi – Via Dante 15, Bergamo', '2025-06-10 09:00:00',
 '[{"nome":"Mario Rossi","ruolo":"RUP"},{"nome":"Giuseppe Verdi","ruolo":"DL"},'
 '{"nome":"Anna Ferrari","ruolo":"CSE"},{"nome":"Laura Bianchi","ruolo":"PM"}]',
 'FIRMATO', 4),

(4, '66666666-0002-0002-0002-000000000004',
 2, 'SICUREZZA', 'VSIC-2025-007',
 'Verbale riunione di coordinamento sicurezza – dicembre 2025',
 'Riunione di coordinamento mensile ex art. 92 D.Lgs. 81/2008. '
 'Argomenti: aggiornamento POS impresa, verifica DPI in cantiere, '
 'programma lavori mese di gennaio 2026, rischi interferenziali con cantiere fibra adiacente. '
 'Prescrizioni: installare segnaletica aggiuntiva zona nord del cantiere entro 7 giorni.',
 'Ufficio DL – Via Dante 15, Bergamo', '2025-12-10 14:00:00',
 '[{"nome":"Giuseppe Verdi","ruolo":"DL"},{"nome":"Anna Ferrari","ruolo":"CSE"},'
 '{"nome":"Laura Bianchi","ruolo":"PM"}]',
 'FIRMATO', 5),

-- Commessa 3
(5, '66666666-0003-0003-0003-000000000005',
 3, 'VISITA_CANTIERE', 'VPRE-2026-001',
 'Sopralluogo pre-cantiere – ricognizione sottoservizi esistenti',
 'Sopralluogo preliminare all''inizio lavori per verifica presenza di sottoservizi '
 '(gas, acquedotto, fibra ottica, elettrodotto). '
 'Rilevate interferenze con rete gas in via dell''Artigianato: necessaria segnalazione a BresciaGas. '
 'Programmata riunione con gestori reti entro il 20/03/2026.',
 'Zona Industriale Nord, Brescia', '2026-03-05 10:30:00',
 '[{"nome":"Giuseppe Verdi","ruolo":"DL"},{"nome":"Anna Ferrari","ruolo":"CSE"},'
 '{"nome":"Carlo Esposito","ruolo":"Tecnico SA"}]',
 'BOZZA', 4);

-- =============================================================================
-- SCADENZE
-- =============================================================================

INSERT INTO `pm_scadenze`
  (commessa_id, appalto_id, tipo, titolo, descrizione,
   data_scadenza, giorni_preavviso, responsabile_id,
   stato, priorita, ricorrente, created_by)
VALUES
-- Commessa 1
(1, 1, 'CONTRATTUALE',
 'Ultimazione lavori – Commessa 2025-BG-001',
 'Data contrattuale di ultimazione di tutti i lavori previsti dal contratto d''appalto, '
 'inclusa posa segnaletica definitiva.',
 '2026-02-28', 30, 3, 'ATTIVA', 'CRITICA', 0, 2),

(1, 1, 'DOCUMENTALE',
 'Scadenza DURC Costruzioni Alfa S.r.l.',
 'Il Documento Unico di Regolarità Contributiva dell''impresa scade. '
 'Richiedere il nuovo DURC prima del pagamento del SAL n.2.',
 '2026-09-30', 60, 8, 'ATTIVA', 'ALTA', 0, 2),

(1, 1, 'PAGAMENTO',
 'Pagamento SAL n.2 – entro termini D.Lgs. 231/2002',
 'Il certificato di pagamento del SAL n.2, approvato il 01/02/2026, '
 'deve essere liquidato entro 30 giorni dall''approvazione.',
 '2026-03-03', 7, 8, 'SCADUTA', 'CRITICA', 0, 2),

-- Commessa 2
(2, 2, 'NORMATIVA',
 'Rinnovo piano operativo di sicurezza (POS) impresa',
 'Il POS dell''impresa esecutrice deve essere aggiornato in caso di variazioni '
 'nelle lavorazioni o nell''organico del cantiere.',
 '2026-06-01', 30, 5, 'ATTIVA', 'ALTA', 1, 2),

(2, 2, 'CONTRATTUALE',
 'Ultimazione lavori – Scuola Garibaldi',
 'Data contrattuale di ultimazione dei lavori di ristrutturazione della scuola. '
 'Richiesta disponibilità edificio per anno scolastico 2026/2027.',
 '2026-11-30', 60, 3, 'ATTIVA', 'CRITICA', 0, 2),

(2, 2, 'COLLAUDO',
 'Richiesta nomina Collaudatore in corso d''opera',
 'Per importo superiore a 750.000€ è obbligatorio il collaudo tecnico-amministrativo '
 'in corso d''opera ex art. 116 D.Lgs. 36/2023.',
 '2026-01-30', 20, 2, 'COMPLETATA', 'ALTA', 0, 2),

-- Commessa 3
(3, 3, 'COMUNICAZIONE',
 'Comunicazione avvio lavori a BresciaGas e A2A',
 'Prima dell''apertura del cantiere in zona industriale, comunicare l''avvio '
 'lavori ai gestori delle reti interferenti (gas, elettrico) per concordare assistenza.',
 '2026-03-25', 14, 4, 'ATTIVA', 'ALTA', 0, 2),

(3, 3, 'CONTRATTUALE',
 'Ultimazione lavori – Rete fognaria zona nord',
 'Data contrattuale di ultimazione di tutti i lavori della rete fognaria '
 'e delle vasche di prima pioggia.',
 '2027-09-30', 60, 3, 'ATTIVA', 'NORMALE', 0, 2);

-- =============================================================================
-- NOTIFICHE IN-APP
-- =============================================================================

INSERT INTO `pm_notifiche`
  (utente_id, tipo, titolo, messaggio, link, entita_tipo, entita_id, letta)
VALUES
(2, 'SCADENZA',
 'Scadenza pagamento SAL n.2 superata',
 'Il pagamento del SAL n.2 della commessa 2025-BG-001 risulta scaduto il 03/03/2026. '
 'Contattare l''ufficio ragioneria per l''emissione immediata del mandato.',
 '/pages/contabilita.php?commessa_id=1', 'pm_sal', 2, 0),

(3, 'SAL',
 'SAL n.2 commessa 2025-BG-001 approvato',
 'Il Direttore dei Lavori ha approvato il SAL n.2 in data 01/02/2026. '
 'L''importo netto liquidabile è di € 151.119,30.',
 '/pages/sal.php?commessa_id=1', 'pm_sal', 2, 1),

(3, 'TASK',
 'Task "Rinforzi strutturali" al 70% – aggiornare avanzamento',
 'Il task 2.3 della commessa Scuola Garibaldi risulta al 70% di avanzamento. '
 'Verificare il cronoprogramma e aggiornare la percentuale di completamento.',
 '/pages/commessa-detail.php?id=2', 'pm_tasks', 7, 0),

(4, 'DOCUMENTO',
 'Nuovo documento caricato – Autorizzazione sismica',
 'È stato caricato il documento "Autorizzazione sismica – Regione Lombardia" '
 'sulla commessa 2025-BG-002. Verificare e archiviare.',
 '/pages/documenti.php?commessa_id=2', 'pm_documenti', 6, 1),

(5, 'AVVISO',
 'Verbale sicurezza in bozza da firmare',
 'Il verbale VPRE-2026-001 "Sopralluogo pre-cantiere" della commessa 2026-BS-001 '
 'è ancora in stato BOZZA. Completare la firma digitale.',
 '/pages/verbali.php?commessa_id=3', 'pm_verbali', 5, 0),

(8, 'SCADENZA',
 'DURC Costruzioni Alfa in scadenza tra 6 mesi',
 'Il DURC di Costruzioni Alfa S.r.l. scade il 30/09/2026. '
 'Richiedere il documento aggiornato prima di procedere con i pagamenti.',
 '/pages/scadenze.php?commessa_id=1', 'pm_scadenze', 2, 0);

-- =============================================================================
-- REPORT SALVATI
-- =============================================================================

INSERT INTO `pm_report_salvati`
  (utente_id, commessa_id, tipo, nome, parametri, schedulato)
VALUES
(2, NULL, 'AVANZAMENTO',
 'Cruscotto avanzamento tutte le commesse attive',
 '{"commesse":"tutte","stato":["IN_ESECUZIONE","PIANIFICAZIONE"],"formato":"PDF"}',
 0),

(3, 1, 'SAL',
 'Riepilogo SAL commessa 2025-BG-001',
 '{"commessa_id":1,"include_voci":true,"formato":"PDF"}',
 0),

(3, 2, 'GANTT',
 'Gantt aggiornato – Scuola Garibaldi',
 '{"commessa_id":2,"mostra_completati":true,"formato":"PDF"}',
 0),

(8, NULL, 'SCADENZE',
 'Scadenzario mensile tutte le commesse',
 '{"giorni_preavviso":30,"stato":["ATTIVA"],"formato":"PDF","destinatari":["mario.rossi@comune-bergamo.example.it"]}',
 1);

-- =============================================================================
-- AUDIT LOG (campione di eventi)
-- =============================================================================

INSERT INTO `pm_audit_log`
  (utente_id, azione, entita_tipo, entita_id, esito, messaggio, ip_address)
VALUES
(2, 'CREATE', 'pm_appalti',  1, 'OK', 'Creato appalto CIG ZA1234567890', '10.0.0.1'),
(2, 'CREATE', 'pm_commesse', 1, 'OK', 'Creata commessa 2025-BG-001',     '10.0.0.1'),
(3, 'UPDATE', 'pm_tasks',    3, 'OK', 'Avanzamento task 1.3 aggiornato al 85%', '10.0.0.2'),
(3, 'CREATE', 'pm_sal',      1, 'OK', 'Emesso SAL n.1 commessa 1 – € 153.510,00', '10.0.0.2'),
(4, 'UPDATE', 'pm_sal',      1, 'OK', 'SAL n.1 approvato da DL', '10.0.0.3'),
(2, 'UPDATE', 'pm_sal',      1, 'OK', 'SAL n.1 approvato da RUP e inoltrato ad Amministrazione', '10.0.0.1'),
(8, 'UPDATE', 'pm_sal',      1, 'OK', 'SAL n.1 – mandato di pagamento emesso', '10.0.0.4'),
(4, 'CREATE', 'pm_verbali',  2, 'OK', 'Redatto verbale VSA-2025-003', '10.0.0.3'),
(3, 'CREATE', 'pm_documenti',6, 'OK', 'Caricato documento: Autorizzazione sismica', '10.0.0.2'),
(2, 'CREATE', 'pm_varianti', 1, 'OK', 'Approvata variante n.1 commessa 1 – € 14.200,00', '10.0.0.1');

-- =============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- RIEPILOGO DATI INSERITI
-- =============================================================================
-- pm_utenti            : 7 (id 2-8)  + 1 superadmin da schema
-- pm_stazioni_appaltanti: 2
-- pm_imprese           : 3
-- pm_appalti           : 3
-- pm_commesse          : 3  (1 IN_ESECUZIONE avanz. 68,5% | 1 IN_ESECUZIONE 32% | 1 PIANIFICAZIONE)
-- pm_commesse_utenti   : 15
-- pm_fasi_lavoro       : 9  (3 per commessa)
-- pm_tasks             : 13
-- pm_dipendenze_tasks  : 10
-- pm_categorie_lavoro  : 14
-- pm_sal               : 3  (SAL1=PAGATO SAL2=APPROVATO SAL3=BOZZA)
-- pm_sal_voci          : 11
-- pm_varianti          : 2
-- pm_documenti         : 8
-- pm_verbali           : 5
-- pm_scadenze          : 8  (1 SCADUTA 1 COMPLETATA 6 ATTIVE)
-- pm_notifiche         : 6
-- pm_report_salvati    : 4
-- pm_audit_log         : 10
-- =============================================================================
