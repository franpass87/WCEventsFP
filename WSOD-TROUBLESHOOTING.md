# WSOD (White Screen of Death) - Risoluzione Problemi / Troubleshooting Guide

## 🆘 Problema: Schermata Bianca durante l'attivazione del plugin

Se il plugin WCEventsFP causa una **schermata bianca** (WSOD) durante l'attivazione, segui questa guida per risolvere il problema.

⚠️ **NOTA IMPORTANTE**: I problemi di WSOD durante l'attivazione sono stati risolti nella versione corrente. Questa guida è per situazioni residue o configurazioni particolari.

## ✅ Soluzioni Implementate (Versione Corrente)

Il plugin include già diverse protezioni contro il WSOD:

1. **Gestione Errori Completa**: Try-catch blocks proteggono tutte le operazioni critiche
2. **Controlli Database Sicuri**: Verifica esistenza tabelle prima delle query
3. **Inizializzazione Graduale**: Caricamento sicuro delle classi admin solo quando necessario
4. **Controlli di Memoria**: Avvisi quando la memoria disponibile è bassa
5. **🔧 Ordine Funzioni Corretto**: Funzioni definite prima dell'uso per evitare errori fatali
6. **🛡️ Caricamento Classi Robusto**: Controllo esistenza file e gestione errori
7. **⚡ Hook di Attivazione Sicuro**: Verifiche WooCommerce e database prima delle operazioni
8. **🆕 Controlli Ambiente PHP**: Verifica versione PHP 7.4+ e estensioni richieste
9. **🆕 Protezione plugins_loaded**: Hook principale protetto da try-catch comprehensivo
10. **🆕 Auto-disattivazione**: Il plugin si disattiva automaticamente in caso di errore fatale
11. **🆕 Validazione Memoria Robusta**: Gestione sicura di valori memory_limit non validi
12. **🔥 NUOVO: Traduzione Sicura**: Funzioni di traduzione che non causano WSOD se chiamate prima del caricamento textdomain
13. **🔥 NUOVO: Display Errori di Emergenza**: Sistema di visualizzazione errori che funziona anche quando le admin notices di WordPress falliscono
14. **🔥 NUOVO: Test di Attivazione Pre-Flight**: Script per testare la sicurezza del plugin prima dell'attivazione

## 🛠️ Strumenti Diagnostici

### 1. Health Check Base
```bash
php wcefp-health-check.php
```

### 2. Diagnostic Tool Avanzato
```bash
php wcefp-diagnostic-tool.php
```

### 3. **🆕 Diagnostic Tool Specifico per Attivazione**
```bash
php wcefp-activation-test.php
```
**Nuovo strumento v2.0.1:** Testa la sicurezza di attivazione prima di attivare il plugin

### 4. **🆕 Test di Caricamento Plugin**
```bash
php wcefp-load-test.php
```

**Nuovi strumenti diagnostici v2.0.1:**
- `wcefp-activation-test.php`: Verifica specifica per problemi di attivazione - **ESEGUI PRIMA DI ATTIVARE IL PLUGIN**
- `wcefp-load-test.php`: Testa il caricamento del plugin senza errori fatali

## 🔥 Miglioramenti v2.0.1 - Risoluzione WSOD Definitiva

### Problema Identificato
Il problema principale era che le funzioni di traduzione (`__()`, `sprintf()`) venivano chiamate prima che il textdomain fosse caricato, causando errori fatali in alcune configurazioni del server.

### Soluzioni Implementate
1. **Funzioni di Traduzione Sicure**: `wcefp_safe_translate()` e `wcefp_safe_sprintf()` che non causano WSOD
2. **Display di Emergenza**: Sistema di visualizzazione errori che bypassa le admin notices di WordPress
3. **Test Pre-Attivazione**: Script per verificare la compatibilità prima dell'attivazione
4. **Gestione Robusta Textdomain**: Caricamento sicuro delle traduzioni con fallback

## 🚨 Risoluzione Immediata

### Se il sito è inaccessibile:

1. **Via FTP/cPanel**:
   - Rinomina la cartella del plugin da `WCEventsFP` a `WCEventsFP-disabled`
   - Il sito tornerà accessibile immediatamente

2. **Via WordPress**:
   - Vai in "Plugin" → "Plugin Installati"
   - Disattiva "WCEventsFP"

## 🔧 Diagnosi e Risoluzione

### 1. Abilita Debug WordPress
Aggiungi a `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Controlla il file `wp-content/debug.log` per errori specifici.

### 2. Verifica Prerequisiti

#### WooCommerce Richiesto
- ✅ WooCommerce deve essere installato e attivo
- ⚠️ Versione minima WooCommerce: 7.0+

#### Requisiti Server
```
PHP: 7.4+
Memory Limit: 128M+ (raccomandato 256M)
Max Execution Time: 60s+
Max Input Vars: 1000+
```

### 3. Aumenta Limiti PHP

#### Via .htaccess (root del sito):
```apache
php_value memory_limit 256M
php_value max_execution_time 300
php_value max_input_vars 3000
```

#### Via php.ini:
```ini
memory_limit = 256M
max_execution_time = 300
max_input_vars = 3000
```

### 4. Controlla Conflitti Plugin

1. Disattiva **tutti** gli altri plugin
2. Attiva solo WCEventsFP
3. Se funziona, riattiva gli altri plugin uno alla volta

### 5. Testa con Tema Predefinito

1. Attiva un tema WordPress predefinito (Twenty Twenty-Three)
2. Testa l'attivazione del plugin
3. Se funziona, il problema è nel tema

## 📊 Controlli Database

Assicurati che l'utente database abbia le seguenti autorizzazioni:
- `CREATE` (per creare tabelle)
- `ALTER` (per modificare struttura tabelle)
- `INDEX` (per creare indici)
- `SELECT`, `INSERT`, `UPDATE`, `DELETE`

## 🔍 Log Errori Comuni

### "Fatal error: Allowed memory size exhausted"
**Soluzione**: Aumenta `memory_limit` a 256M o superiore

### "Maximum execution time exceeded"
**Soluzione**: Aumenta `max_execution_time` a 300s

### "Class 'WooCommerce' not found"
**Soluzione**: Installa e attiva WooCommerce prima di WCEventsFP

### "Cannot modify header information"
**Soluzione**: Controlla spazi/caratteri prima di `<?php` nei file del plugin

### **🆕 "Call to undefined function wcefp_convert_memory_to_bytes"** 
**Causa**: Ordine di definizione funzioni errato
**Soluzione**: ✅ RISOLTO - Funzione spostata prima dell'utilizzo nel codice

### **🆕 "Class 'WCEFP_Admin' not found"**
**Causa**: Caricamento classi fallito
**Soluzione**: ✅ RISOLTO - Aggiunto controllo esistenza classi prima dell'inizializzazione

### **🆕 "Cannot create table" / Database errors**
**Causa**: Problemi durante la creazione tabelle in attivazione
**Soluzione**: ✅ RISOLTO - Aggiunta verifica connessione database prima delle operazioni

## 🔧 **Correzioni Implementate v2.0.1**

### Problema Risolto #1: Ordine Definizione Funzioni
**Prima**: La funzione `wcefp_convert_memory_to_bytes()` era chiamata nella riga 120 ma definita nella riga 1621
**Dopo**: Funzione spostata dopo le costanti (riga 17) prima del primo utilizzo
**Impatto**: Elimina errore fatale "Call to undefined function"

### Problema Risolto #2: Caricamento Classi Non Sicuro  
**Prima**: Utilizzo di `require_once` diretto senza controlli
**Dopo**: Loop con controllo esistenza file e gestione eccezioni
**Impatto**: Il plugin continua a funzionare anche se alcune classi mancano

### Problema Risolto #3: Hook di Attivazione Fragile
**Prima**: Operazioni database senza controlli preliminari
**Dopo**: Verifica WooCommerce, connessione database e funzioni WordPress
**Impatto**: Attivazione sicura anche in ambienti non ottimali

### 🆕 **Nuove Correzioni v2.0.1+**

### Problema Risolto #4: Controlli Ambiente PHP Mancanti
**Prima**: Nessun controllo versione PHP o estensioni richieste
**Dopo**: Verifica PHP 7.4+ e estensioni critiche (mysqli, json, mbstring) in activation hook
**Impatto**: Prevenzione WSOD da incompatibilità ambiente

### Problema Risolto #5: plugins_loaded Non Protetto
**Prima**: Hook plugins_loaded senza gestione errori comprehensiva
**Dopo**: Wrapping completo con try-catch per Error e Exception
**Impatto**: Recupero automatico da errori fatali durante inizializzazione

### Problema Risolto #6: Funzione Memoria Fragile
**Prima**: `wcefp_convert_memory_to_bytes()` falliva con valori non validi
**Dopo**: Validazione robusta con gestione edge cases (null, stringhe vuote, valori negativi)
**Impatto**: Elimina crashes da configurazioni memory_limit non standard

### Problema Risolto #7: Mancanza Auto-Recovery
**Prima**: Errori fatali causavano WSOD permanente
**Dopo**: Auto-disattivazione plugin in caso di errore critico + messaggio utente
**Impatto**: Il sito rimane accessibile anche con plugin problematico

## 🆘 Supporto Avanzato

### Genera Report Diagnostico
```bash
cd /wp-content/plugins/WCEventsFP/
php wcefp-diagnostic-tool.php > diagnostic-report.txt
```

Invia il file `diagnostic-report.txt` al supporto tecnico.

### Informazioni da Fornire al Supporto

1. **Versioni**:
   - WordPress
   - WooCommerce  
   - PHP
   - Plugin attivi

2. **Configurazione Server**:
   - Memory limit
   - Execution time
   - Error logs

3. **Messaggio Errore Specifico** dal debug.log

## ⚡ Procedure di Emergenza

### Ripristino Rapido
```bash
# Via SSH
cd /var/www/html/wp-content/plugins/
mv WCEventsFP WCEventsFP-backup
# Sito ora accessibile
```

### Test Sicuro
```bash
# Crea ambiente di test
cp -r WCEventsFP WCEventsFP-test
# Modifica e testa prima di applicare in produzione
```

## 📞 Contatti Supporto

- **Issue GitHub**: [Segnala problema](https://github.com/franpass87/WCEventsFP/issues)
- **Email**: [inserire email di supporto]
- **Documentazione**: README.md completo del plugin

---

**Nota**: Questo plugin include protezioni avanzate contro il WSOD. Se continui a riscontrare problemi, molto probabilmente sono legati a configurazioni server o conflitti con altri plugin/temi.