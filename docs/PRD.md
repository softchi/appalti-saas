# Product Requirements Document
## Appalti SaaS — Piattaforma Gestione Commesse Pubbliche
**Versione:** 1.0.0
**Data:** 2026-03-09
**Conformità:** D.Lgs. 36/2023 (Codice dei Contratti Pubblici)

---

## 1. Sommario Esecutivo

Appalti SaaS è una piattaforma web professionale per la gestione integrata di pm_appalti e pm_commesse di lavori pubblici, conforme al D.Lgs. 36/2023. Combina le funzionalità di Primavera P6, Monday.com, ClickUp e Microsoft Project in un'unica soluzione verticale per il mercato italiano della Pubblica Amministrazione.

**Obiettivo primario:** Digitalizzare e centralizzare la gestione di tutte le fasi di un appalto pubblico — dalla pubblicazione del bando al collaudo finale — riducendo errori, rispettando le pm_scadenze normative e fornendo visibilità in tempo reale agli stakeholder.

---

## 2. Utenti Target (Personas)

| Ruolo | Descrizione | Permessi Chiave |
|-------|-------------|-----------------|
| **RUP** (Responsabile Unico del Procedimento) | Supervisiona l'intero appalto; firma atti | Accesso completo, approvazione SAL |
| **Project Manager** | Coordina i lavori quotidianamente | Gestione pm_tasks, Gantt, SAL |
| **Direttore dei Lavori (DL)** | Dirige esecuzione tecnica, emette SAL | SAL, contabilità, pm_verbali |
| **CSE** (Coordinatore Sicurezza Esecuzione) | Gestisce sicurezza in cantiere | Tasks sicurezza, pm_verbali, pm_scadenze |
| **Impresa Esecutrice** | Aggiorna avanzamento lavori | Tasks assegnati, pm_documenti |
| **Tecnico di Cantiere** | Operativo in cantiere | Tasks, pm_verbali, pm_documenti |
| **Amministrazione** | Gestisce aspetti finanziari e normativi | SAL, pm_scadenze, report |
| **SuperAdmin** | Amministra la piattaforma | Accesso totale |

---

## 3. Requisiti Funzionali

### 3.1 Gestione Commesse
- **RF-01** Creazione commessa con CIG, CUP, importo contrattuale, dati SA e impresa
- **RF-02** Auto-generazione codice commessa (CO-YYYY-NNNN)
- **RF-03** Stati: IN_ATTESA → IN_ESECUZIONE → SOSPESA / COMPLETATA / ANNULLATA
- **RF-04** Campi D.Lgs. 36/2023: categoria SOA, classifica SOA, ribasso d'asta, tipo procedura
- **RF-05** Associazione team (RUP, PM, DL, CSE + pm_utenti aggiuntivi)
- **RF-06** Visibilità RBAC: ogni utente vede solo le pm_commesse a cui è assegnato (escluso admin)

### 3.2 Pianificazione Lavori (Cronoprogramma)
- **RF-07** Struttura WBS gerarchica (fasi → pm_tasks → subtasks)
- **RF-08** Auto-generazione codice WBS (es. 1.2.3)
- **RF-09** Tipi task: FASE, TASK, MILESTONE, SOMMARIO
- **RF-10** Dipendenze tra pm_tasks (Fine-Inizio)
- **RF-11** Gantt interattivo su canvas HTML5: zoom giorno/settimana/mese
- **RF-12** Aggiornamento percentuale completamento → aggiorna avanzamento commessa
- **RF-13** Visualizzazione ritardi (pm_tasks con data fine reale > prevista)

### 3.3 SAL — Stato Avanzamento Lavori
- **RF-14** Creazione SAL con periodo, importi lavori + sicurezza + pm_varianti
- **RF-15** Auto-calcolo importo cumulato e percentuale avanzamento
- **RF-16** Ritenuta garanzia 5% e importo netto
- **RF-17** Workflow approvazione: BOZZA → EMESSO → APPROVATO → PAGATO
- **RF-18** Solo RUP può approvare SAL
- **RF-19** Voci SAL di dettaglio per categoria di lavoro

### 3.4 Contabilità Lavori
- **RF-20** Elenco prezzi (categorie di lavoro) con U.M., Q.tà, Prezzo unitario
- **RF-21** Tracking quantità eseguite vs contrattuali
- **RF-22** Gestione pm_varianti (art. 120 D.Lgs. 36/2023): migliorative, tecniche, perizia suppletiva
- **RF-23** Cruscotto scostamenti (importo contrattuale vs eseguito)

### 3.5 Documenti
- **RF-24** Upload multi-formato: PDF, DOC/X, XLS/X, immagini, ZIP (max 50MB)
- **RF-25** Categorizzazione pm_documenti (Progetto, Contratto, SAL, Verbali, ecc.)
- **RF-26** Versioning pm_documenti con catena padre-figlio e stato OBSOLETO/CORRENTE
- **RF-27** Download sicuro con verifica integrità MD5
- **RF-28** Ricerca full-text su titolo, tag, descrizione
- **RF-29** Documenti riservati (flag) visibili solo a pm_utenti con permesso specifico

### 3.6 Verbali di Cantiere
- **RF-30** Tipi: Consegna Lavori, Sospensione, Ripresa, Visita Cantiere, Collaudo, Contabilità, Riunione
- **RF-31** Auto-numerazione verbale per commessa
- **RF-32** Campi: data, ora inizio/fine, luogo, partecipanti, contenuto, prescrizioni
- **RF-33** Visualizzazione e stampa verbale (print CSS)
- **RF-34** Stati: BOZZA → FIRMATO → ARCHIVIATO

### 3.7 Scadenzario
- **RF-35** Tipi scadenza: Contrattuale, Normativa, Documentale, Pagamento, Collaudo
- **RF-36** Classificazione urgenza: SCADUTA / CRITICA (≤7gg) / URGENTE (≤15gg) / NORMALE
- **RF-37** Notifiche automatiche al responsabile prima della scadenza
- **RF-38** Vista lista + vista timeline

### 3.8 Notifiche
- **RF-39** Notifiche in-app per: nuovi SAL, pm_scadenze imminenti, pm_tasks assegnati, approvazioni
- **RF-40** Badge contatore non lette in navbar
- **RF-41** Polling ogni 60 secondi per aggiornamento badge
- **RF-42** Marcatura letta/tutte lette

### 3.9 Report e Statistiche
- **RF-43** Report avanzamento per singola commessa (pm_tasks, fasi, scostamenti)
- **RF-44** Report SAL con andamento e liquidazione
- **RF-45** Report costi per categoria (contrattuale vs eseguito)
- **RF-46** Report pm_scadenze con classificazione urgenza
- **RF-47** Riepilogo portfolio pm_commesse (global)
- **RF-48** Export JSON Gantt (compatibile MS Project / Primavera)
- **RF-49** Export CSV per tutti i report

### 3.10 AI Assistant
- **RF-50** Integrazione Anthropic Claude API (claude-sonnet-4-6)
- **RF-51** Analisi completa commessa con raccolta dati real-time
- **RF-52** Calcolo SPI (Schedule Performance Index) e CPI (Cost Performance Index)
- **RF-53** Identificazione automatica rischi e proposte azioni correttive
- **RF-54** Generazione report narrativo in italiano
- **RF-55** Previsione data completamento e fabbisogno finanziario
- **RF-56** Fallback rule-based se API non disponibile
- **RF-57** Livelli semaforo: VERDE / GIALLO / ARANCIO / ROSSO

### 3.11 Amministrazione
- **RF-58** CRUD pm_utenti con RBAC (10 pm_ruoli predefiniti)
- **RF-59** Gestione stazioni appaltanti e pm_imprese
- **RF-60** Audit log completo di tutte le operazioni CREATE/UPDATE/DELETE
- **RF-61** Log di sicurezza: tentativi login falliti, violazioni CSRF
- **RF-62** Configurazione SMTP, AI, parametri sicurezza

---

## 4. Requisiti Non Funzionali

### 4.1 Performance
- **RNF-01** Pagina dashboard: caricamento < 2s con 50 pm_commesse
- **RNF-02** API list: < 500ms con 1000 record e indici corretti
- **RNF-03** Upload pm_documenti: supporto fino a 50MB, timeout 120s

### 4.2 Sicurezza
- **RNF-04** SQL Injection: 100% prepared statements con PDO
- **RNF-05** XSS: escaping output con `htmlspecialchars()` + CSP header
- **RNF-06** CSRF: token per sessione + meta tag + validazione su ogni POST/PUT/DELETE
- **RNF-07** Password: bcrypt con cost factor 12 (PHP `password_hash`)
- **RNF-08** Rate limiting login: 5 tentativi → blocco 15 minuti
- **RNF-09** Sessioni server-side in MySQL, ID rigenerato ogni 30 min
- **RNF-10** Upload: verifica MIME reale (finfo), whitelist estensioni, no PHP in uploads/
- **RNF-11** RBAC granulare: 30+ pm_permessi, verifica lato server su ogni endpoint

### 4.3 Compatibilità
- **RNF-12** PHP 7.4+ / PHP 8.x
- **RNF-13** MySQL 5.7+ / MariaDB 10.3+
- **RNF-14** Apache 2.4+ con mod_rewrite
- **RNF-15** Browser: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **RNF-16** Responsive: Mobile 375px+, Tablet 768px+, Desktop 1024px+

### 4.4 Deploy
- **RNF-17** Deploy su hosting condiviso Altervista (PHP + MySQL)
- **RNF-18** Nessuna dipendenza da Composer in produzione (tutte le librerie CDN o bundlate)
- **RNF-19** Migrabile a VPS/Cloud con Node.js/React/PostgreSQL tramite API REST già strutturate

---

## 5. Stack Tecnologico

### Frontend
| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| HTML5 / CSS3 | — | Struttura e stile |
| Bootstrap | 5.3 | UI component library |
| Bootstrap Icons | 1.11 | Iconografia |
| Chart.js | 4.x | Grafici (doughnut, line, bar, radar) |
| Canvas API (vanilla) | — | Gantt chart custom |
| Vanilla JS ES6+ | — | Logica frontend, API client |

### Backend
| Tecnologia | Versione | Ruolo |
|-----------|---------|-------|
| PHP | 8.0+ | Server-side, API REST |
| PDO | — | Database abstraction |
| MySQL / MariaDB | 5.7+ | Database relazionale |
| Apache / mod_rewrite | 2.4+ | Web server, URL rewriting |

### Terze Parti
| Servizio | Uso |
|---------|-----|
| Anthropic Claude API | AI Assistant (analisi pm_commesse) |
| CDN Bootstrap/Chart.js | Librerie frontend (opzionale offline) |

---

## 6. Architettura del Sistema

```
pm_appalti-saas/
├── index.php                    # Entry point (redirect)
├── login.php                    # Pagina di login
├── .htaccess                    # Apache config, security headers
├── php/
│   ├── config.php               # Costanti di configurazione
│   ├── db.php                   # Singleton PDO (Database class)
│   ├── auth.php                 # Autenticazione + RBAC (Auth class)
│   ├── functions.php            # Helpers, Validator, Logger, uploadFile
│   └── bootstrap.php            # Bootstrapper: carica tutto
├── api/
│   ├── login.php                # POST /api/login
│   ├── logout.php               # GET|POST /api/logout
│   ├── pm_commesse.php             # CRUD pm_commesse
│   ├── pm_tasks.php                # CRUD pm_tasks + tree + WBS
│   ├── pm_sal.php                  # CRUD SAL + approvazione
│   ├── pm_documenti.php            # Upload + download + versioning
│   ├── pm_scadenze.php             # CRUD pm_scadenze
│   ├── pm_verbali.php              # CRUD pm_verbali
│   ├── pm_appalti.php              # CRUD stazioni appaltanti + pm_imprese
│   ├── pm_utenti.php               # CRUD pm_utenti + pm_ruoli
│   ├── pm_notifiche.php            # GET pm_notifiche + mark read
│   ├── dashboard.php            # KPI aggregati
│   ├── reports.php              # Report avanzamento/SAL/costi/pm_scadenze/gantt
│   └── ai_assistant.php         # Analisi AI (Claude API + fallback)
├── pages/
│   ├── dashboard.php            # Dashboard principale
│   ├── pm_commesse.php             # Lista pm_commesse
│   ├── commessa-detail.php      # Dettaglio commessa
│   ├── cronoprogramma.php       # Gantt interattivo
│   ├── pm_sal.php                  # Gestione SAL
│   ├── contabilita.php          # Contabilità lavori
│   ├── pm_documenti.php            # Archivio pm_documenti
│   ├── pm_verbali.php              # Verbali di cantiere
│   ├── pm_scadenze.php             # Scadenzario
│   ├── report.php               # Report e statistiche
│   ├── ai-assistant.php         # AI Assistant chat
│   ├── pm_utenti.php               # Gestione pm_utenti (admin)
│   ├── profilo.php              # Profilo utente
│   └── impostazioni.php         # Impostazioni sistema (admin)
├── components/
│   ├── header.php               # HTML head + navbar
│   ├── sidebar.php              # Menu laterale RBAC
│   └── footer.php               # Bootstrap JS + modali globali
├── css/
│   ├── main.css                 # Stili globali + variabili CSS
│   └── gantt.css                # Stili Gantt chart
├── js/
│   ├── api.js                   # API client IIFE + CSRF auto
│   ├── main.js                  # UI helpers, sidebar, pm_notifiche
│   ├── charts.js                # Tutti i grafici Chart.js
│   └── gantt.js                 # GanttChart class (canvas)
├── database/
│   └── schema.sql               # Schema MySQL completo + seed
├── uploads/                     # File caricati (con .htaccess protettivo)
├── logs/                        # Log PHP (esclusi da git)
└── docs/
    ├── PRD.md                   # Questo documento
    └── DEPLOY.md                # Guida deploy Altervista
```

---

## 7. Modello Dati (Tabelle Principali)

| Tabella | Descrizione |
|---------|-------------|
| `pm_ruoli` | Ruoli utente (SUPERADMIN, ADMIN, RUP, PM, DL, CSE, ecc.) |
| `pm_permessi` | Permessi granulari (pm_commesse.create, pm_sal.approve, ecc.) |
| `pm_ruoli_permessi` | Associazione M:N pm_ruoli ↔ pm_permessi |
| `pm_utenti` | Utenti del sistema con hash bcrypt |
| `pm_sessioni` | Sessioni server-side in MySQL |
| `pm_stazioni_appaltanti` | Enti committenti |
| `pm_imprese` | Imprese esecutrici con dati SOA e DURC |
| `pm_appalti` | Procedura di gara (CIG, CUP, importi) |
| `pm_commesse` | Commessa operativa (figlio di appalto) |
| `pm_commesse_utenti` | Associazione team commessa |
| `pm_fasi_lavoro` | Macro-fasi del cronoprogramma |
| `pm_tasks` | Tasks gerarchici con WBS, dipendenze, % |
| `pm_dipendenze_tasks` | Dipendenze Fine-Inizio tra pm_tasks |
| `pm_categorie_lavoro` | Elenco prezzi con quantità e importi |
| `pm_sal` | Stato Avanzamento Lavori |
| `pm_sal_voci` | Voci di dettaglio SAL |
| `pm_varianti` | Varianti contrattuali art. 120 |
| `pm_categorie_documento` | Tassonomia pm_documenti |
| `pm_documenti` | File con versioning e metadati |
| `pm_verbali` | Verbali di cantiere |
| `pm_scadenze` | Scadenze con urgenza calcolata |
| `pm_notifiche` | Notifiche in-app |
| `pm_audit_log` | Log immutabile di tutte le operazioni |
| `pm_report_salvati` | Report generati e salvati |

---

## 8. Roadmap Future (v2.0)

- [ ] Migrazione frontend a React + TypeScript
- [ ] Migrazione backend a Node.js + Express + Prisma
- [ ] Migrazione database a PostgreSQL
- [ ] Deploy cloud (AWS/GCP) con Docker Compose
- [ ] App mobile React Native
- [ ] Firma digitale pm_documenti (integrazione SPID/CIE)
- [ ] Integrazione ANAC (pubblicazione bandi, comunicazioni obbligatorie)
- [ ] Integrazione MEPA / piattaforme e-procurement
- [ ] BIM viewer per pm_documenti progettuali
- [ ] OCR automatico per pm_documenti scansionati
- [ ] Workflow di approvazione personalizzabile
- [ ] Multi-tenant (una istanza per più enti)
