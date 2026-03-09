# Guida al Deploy — Altervista (Shared Hosting)
## Appalti SaaS — Deploy Step-by-Step

---

## Prerequisiti

| Requisito | Versione minima | Note |
|-----------|----------------|------|
| PHP | 7.4 (consigliato 8.1+) | Altervista supporta PHP 7.x e 8.x |
| MySQL | 5.7+ | Altervista fornisce MySQL gratuito |
| Apache | 2.4+ | Con mod_rewrite abilitato |
| Spazio disco | > 200 MB | Per uploads, logs, database |
| Connessione HTTPS | Altervista fornisce SSL gratuito | |

---

## Fase 1 — Preparazione del Database

### 1.1 Accedere a phpMyAdmin
1. Accedi al pannello Altervista: `https://it.altervista.org`
2. Menu **Database** → **phpMyAdmin**
3. Seleziona il tuo database (già creato da Altervista, solitamente stesso nome utente)

### 1.2 Importare lo schema
1. Clicca su **SQL** (in alto) oppure su **Importa**
2. Carica il file `database/schema.sql` oppure incolla il contenuto
3. Clicca **Esegui** / **Go**
4. Verifica: devono comparire circa 25 tabelle nella lista sinistra

> **Nota:** Se ottieni errori sulle **GENERATED COLUMNS**, assicurati che la versione MySQL sia ≥ 5.7.

---

## Fase 2 — Configurazione PHP

### 2.1 Aprire `php/config.php`
Modifica le seguenti costanti:

```php
// ===== DATABASE =====
define('DB_HOST',     'localhost');     // Altervista usa sempre localhost
define('DB_NAME',     'tuo_database'); // Il nome del tuo DB Altervista
define('DB_USER',     'tuo_username'); // Username Altervista
define('DB_PASS',     'tua_password'); // Password MySQL

// ===== APP URL =====
// Sostituisci con il tuo dominio Altervista
define('APP_URL',     'https://tuonome.altervista.org');
define('APP_ENV',     'production');

// ===== SICUREZZA =====
// Genera una stringa casuale di 64 caratteri:
// php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET',  'CAMBIA_QUESTA_STRINGA_CON_VALORE_CASUALE_64_CHAR');

// ===== UPLOAD =====
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 52428800); // 50MB

// ===== AI ASSISTANT (opzionale) =====
define('AI_ENABLED',  false);           // true se hai una chiave API
define('AI_API_KEY',  'sk-ant-...');    // Chiave Anthropic (opzionale)
define('AI_MODEL',    'claude-sonnet-4-6');

// ===== EMAIL (opzionale) =====
define('MAIL_HOST',   'smtp.gmail.com');
define('MAIL_PORT',   587);
define('MAIL_USERNAME','tua@email.com');
define('MAIL_PASSWORD','password_app');
define('MAIL_FROM',   'tua@email.com');
define('MAIL_FROM_NAME', 'Appalti SaaS');
```

---

## Fase 3 — Upload dei File

### 3.1 Via FTP (consigliato)
Usa FileZilla o un client FTP simile:

```
Host:     ftp.altervista.org
Username: il tuo username Altervista
Password: la tua password Altervista
Porta:    21 (FTP) oppure 22 (SFTP se disponibile)
```

**Cartella di destinazione:** `/htdocs/` (o come configurata nel tuo pannello)

### 3.2 Struttura upload
Carica **tutta** la cartella `pm_appalti-saas/` nella root del tuo sito:
```
/htdocs/
├── index.php
├── login.php
├── .htaccess
├── php/
├── api/
├── pages/
├── components/
├── css/
├── js/
├── database/
├── uploads/        ← crea questa cartella manualmente
└── logs/           ← crea questa cartella manualmente
```

> **Attenzione:** Non caricare `.env` file o credenziali. Tutta la config è in `php/config.php`.

### 3.3 Creare cartelle mancanti
Se non esistono, crea via FTP o phpMyAdmin:
- `uploads/` — con pm_permessi **755**
- `logs/`    — con pm_permessi **755**

---

## Fase 4 — Configurazione Apache (.htaccess)

### 4.1 Verifica mod_rewrite
Altervista supporta `mod_rewrite`. Il file `.htaccess` già incluso gestisce:
- Redirect al login se non autenticato
- Security headers
- Blocco accesso diretto a `php/`
- Nessuna esecuzione PHP in `uploads/`

### 4.2 Se il sito è in una sottocartella
Se il sito non è nella root ma in `/pm_appalti/`, modifica `.htaccess`:
```apache
RewriteBase /pm_appalti/
```
E in `php/config.php`:
```php
define('APP_URL', 'https://tuonome.altervista.org/pm_appalti');
```

### 4.3 Abilitare HTTPS
Decommentare in `.htaccess`:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```
Altervista fornisce certificati SSL gratuiti tramite Let's Encrypt.

---

## Fase 5 — Primo Accesso

### 5.1 Credenziali admin predefinite
```
Email:    admin@pm_appalti.local
Password: password
```
> ⚠️ **CAMBIA LA PASSWORD IMMEDIATAMENTE** dopo il primo accesso!

### 5.2 Cambio password admin
1. Accedi con le credenziali predefinite
2. Clicca sull'avatar in alto a destra → **Il mio profilo**
3. Tab **Sicurezza** → Cambia password
4. Imposta una password sicura (min. 12 caratteri, maiuscole, numeri, simboli)

### 5.3 Configurazione iniziale
1. **Impostazioni → Generali**: imposta nome app e URL
2. **Impostazioni → Email**: configura SMTP per le pm_notifiche
3. **Gestione Utenti**: crea gli pm_utenti del team (RUP, PM, DL, CSE...)
4. **Appalti → Stazioni Appaltanti**: aggiungi i tuoi enti
5. **Appalti → Imprese**: aggiungi le pm_imprese esecutrici

---

## Fase 6 — Configurazione AI Assistant (Opzionale)

### 6.1 Ottenere una chiave API Anthropic
1. Vai su `https://console.anthropic.com`
2. Crea un account e aggiungi un metodo di pagamento
3. Vai su **API Keys** → **Create Key**
4. Copia la chiave `sk-ant-...`

### 6.2 Configurare in `php/config.php`
```php
define('AI_ENABLED', true);
define('AI_API_KEY', 'sk-ant-la-tua-chiave');
define('AI_MODEL',   'claude-sonnet-4-6');
define('AI_MAX_TOKENS', 4096);
```

> Se `AI_ENABLED` è `false`, l'assistente usa l'analisi rule-based automatica (nessun costo).

---

## Fase 7 — Verifica Finale

### Checklist pre-go-live

- [ ] `php/config.php` aggiornato con dati reali (DB, URL, secret)
- [ ] Database importato correttamente (25+ tabelle visibili)
- [ ] Login funzionante con credenziali admin
- [ ] Password admin cambiata
- [ ] HTTPS attivo e redirect funzionante
- [ ] Cartella `uploads/` accessibile in scrittura (chmod 755)
- [ ] Cartella `logs/` accessibile in scrittura (chmod 755)
- [ ] Upload di un documento di test funzionante
- [ ] Creazione di una commessa di test
- [ ] Notifiche in-app visibili
- [ ] Impostazioni email configurate (o disabilitate se non usate)

### Test funzionale rapido
1. Crea una **Stazione Appaltante** in Impostazioni
2. Crea un'**Impresa** in Impostazioni
3. Crea una **Commessa** con CIG, CUP, importo
4. Aggiungi un **Task** alla commessa dal Cronoprogramma
5. Crea un **SAL** e approvalo
6. Carica un **Documento**
7. Crea una **Scadenza**
8. Verifica che le pm_notifiche arrivino

---

## Troubleshooting Comune

### Errore 500 — Internal Server Error
1. Controlla `logs/php_errors.log`
2. Verifica che `php/config.php` abbia le credenziali DB corrette
3. Verifica che MySQL sia raggiungibile (`DB_HOST=localhost`)
4. Controlla i pm_permessi cartella `uploads/` e `logs/`

### Database connection failed
```
Error: SQLSTATE[HY000] [1045] Access denied
```
→ Verifica `DB_USER` e `DB_PASS` in `config.php`

### .htaccess non funziona
- Verifica che `mod_rewrite` sia abilitato (Altervista: sì per default)
- Verifica `RewriteBase /` (o `/sottocartella/` se necessario)
- Controlla che il file sia nominato `.htaccess` (con il punto iniziale)

### Upload file non funziona
- Verifica pm_permessi cartella `uploads/` (deve essere scrivibile: chmod 755 o 777)
- Verifica `upload_max_filesize` in `.htaccess` o `php.ini`
- Controlla che il file `.htaccess` nella cartella `uploads/` blocchi l'esecuzione PHP

### Sessioni scadono subito
- Verifica che la tabella `pm_sessioni` esista nel database
- Controlla `SESSION_LIFETIME` in `config.php` (default: 7200 secondi = 2 ore)

### AI Assistant non risponde
- Verifica `AI_ENABLED = true` e chiave API valida
- Controlla che `allow_url_fopen = On` nel php.ini di Altervista
- In alternativa, imposta `AI_ENABLED = false` per usare la modalità rule-based

---

## Aggiornamenti

Per aggiornare l'applicazione:
1. Scarica la nuova versione
2. Carica via FTP **sovrascrivendo** tutti i file (esclusa la cartella `uploads/`)
3. Se ci sono modifiche al DB, esegui gli script SQL aggiornamento in phpMyAdmin
4. Svuota la cache del browser (Ctrl+Shift+R)

---

## Migrazione a VPS/Cloud (Futuro)

L'applicazione è progettata per essere facilmente migrabile:

### Da Altervista a VPS Ubuntu
```bash
# 1. Installa stack LAMP
sudo apt update && sudo apt install -y apache2 php8.2 php8.2-pdo php8.2-mysql mariadb-server

# 2. Abilita mod_rewrite
sudo a2enmod rewrite && sudo systemctl restart apache2

# 3. Copia file
sudo cp -r pm_appalti-saas/ /var/www/html/

# 4. Importa DB
mysql -u root -p pm_appalti < database/schema.sql

# 5. Imposta pm_permessi
sudo chown -R www-data:www-data /var/www/html/pm_appalti-saas/
sudo chmod -R 755 /var/www/html/pm_appalti-saas/uploads/
```

### Da MySQL a PostgreSQL
Il layer PDO in `php/db.php` supporta PostgreSQL cambiando il DSN:
```php
// In php/config.php
define('DB_DSN', 'pgsql:host=localhost;port=5432;dbname=pm_appalti');
```
Saranno necessari adattamenti minori alle query (LIMIT/OFFSET, AUTO_INCREMENT → SERIAL, ecc.)

---

*Appalti SaaS — Documentazione Tecnica v1.0*
*Conforme a D.Lgs. 36/2023 (Codice dei Contratti Pubblici)*
