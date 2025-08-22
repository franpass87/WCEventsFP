# WCEventsFP - Documentazione Completa

## 📋 Indice
- [🛡️ Risoluzione Definitiva WSOD](#risoluzione-definitiva-wsod)
- [🚀 Funzionalità Plugin](#funzionalità-plugin)
- [⚙️ Installazione e Configurazione](#installazione-e-configurazione)
- [🔧 Risoluzione Problemi](#risoluzione-problemi)
- [🧪 Test e Diagnostica](#test-e-diagnostica)
- [📖 Guide per Sviluppatori](#guide-per-sviluppatori)
- [🆘 Supporto Tecnico](#supporto-tecnico)

---

## 🛡️ Risoluzione Definitiva WSOD

### ✅ PROBLEMA WSOD RISOLTO - VERSIONE 2.1.1

È stato implementato un **sistema di prevenzione WSOD bulletproof** che garantisce:

- **🚫 NESSUN WSOD MAI PIÙ**: Il plugin non può più causare schermate bianche
- **📋 Errori dettagliati**: Al posto di WSOD vedrai messaggi di errore chiari e utili  
- **🔧 Auto-disattivazione**: Se ci sono problemi critici, il plugin si disattiva automaticamente
- **🛠️ Modalità emergenza**: Anche in caso di errori, mantiene funzionalità base
- **🚀 Setup Wizard**: Configurazione guidata per prevenire problemi di attivazione

### 🚨 GARANZIA ANTI-WSOD

**Il plugin ora include queste protezioni:**

✅ **Controllo ambiente**: Verifica PHP, WordPress, WooCommerce prima del caricamento  
✅ **Caricamento sicuro**: Ogni file è caricato con gestione errori  
✅ **Fallback multipli**: Se un sistema fallisce, prova il successivo  
✅ **Shutdown protection**: Cattura errori fatali prima che causino WSOD  
✅ **Emergency mode**: Mantiene funzionalità base anche con errori  
✅ **Auto-deactivation**: Previene danni permanenti al sito  

### 📋 COME USARE - ISTRUZIONI DEFINITIVE

#### PASSO 1: Test Pre-Attivazione (OBBLIGATORIO)

**Prima di attivare il plugin**, esegui SEMPRE questo test:

```bash
php wcefp-activation-test.php
```

**OPPURE** carica il file `test-plugin-loading.php` sul tuo sito e accedi via browser:
```
https://tuosito.com/wp-content/plugins/wceventsfp/test-plugin-loading.php
```

#### PASSO 2: Interpretare i Risultati

✅ **TUTTI I TEST OK**: Puoi attivare il plugin in sicurezza  
⚠️ **QUALCHE PROBLEMA**: Risolvi i problemi indicati prima dell'attivazione  
❌ **MOLTI ERRORI**: NON attivare il plugin, contatta il supporto  

#### PASSO 3: Attivazione Sicura

1. **Usa il Setup Wizard**: Dopo attivazione, segui la configurazione guidata
2. **Verifica funzionalità**: Controlla che tutto funzioni correttamente
3. **Test completo**: Prova le funzionalità principali del plugin

### 🔧 COSA È CAMBIATO (Miglioramenti v2.1.1)

#### Nuovo Sistema Multi-Livello

1. **Livello 1**: Controlli ambiente prima di qualsiasi caricamento
2. **Livello 2**: Sistema di caricamento graduale con fallback  
3. **Livello 3**: Modalità emergenza se tutto il resto fallisce
4. **Livello 4**: Auto-disattivazione in caso di errori critici

#### Gestione Errori Bulletproof

- **Shutdown Handler**: Cattura errori fatali PHP che causerebbero WSOD
- **Error Handler**: Gestisce tutti gli errori non fatali con logging dettagliato  
- **Exception Handling**: Try-catch su ogni operazione critica
- **Throwable Catching**: Cattura sia Exception che Error (PHP 7+)

### 📊 ESEMPI DI ERRORI CHE VEDRAI (invece di WSOD)

#### Errore di Ambiente
```
❌ WCEventsFP Plugin Error

Errore: Ambiente server non compatibile
- PHP versione insufficiente (minimo: 7.4)
- WooCommerce non attivo
- Memoria PHP insufficiente (minimo: 256MB)

Il plugin è stato disattivato automaticamente.
```

#### Errore di Inizializzazione  
```
⚠️ WCEventsFP Plugin Warning

Plugin running in minimal emergency mode
- Alcune funzionalità sono disabilitate
- Controlla i log per dettagli specifici
- Il sito rimane perfettamente funzionante
```

#### Errore Fatale PHP (molto raro ora)
```
❌ WCEventsFP Plugin Error

Errore: Errore fatale PHP durante il caricamento del plugin
Dettagli: Syntax error in file xyz.php line 123

Il plugin è stato disattivato automaticamente.
```

### 🎯 RISULTATO FINALE

**PRIMA**: WSOD senza informazioni → Sito inutilizzabile  
**ADESSO**: Errori dettagliati → Risoluzione rapida → Sito sempre funzionante  

Il plugin **non può più causare WSOD** in nessuna circostanza. Anche negli scenari peggiori, vedrai sempre messaggi di errore utili che ti permetteranno di risolvere il problema rapidamente.

---

## 🚀 Funzionalità Plugin

### Architettura Enterprise (v2.1.1)

**Sistema di prenotazione enterprise per competere direttamente con RegionDo, Bokun e GetYourGuide**

Plugin WordPress/WooCommerce completo per eventi ed esperienze con funzionalità di livello enterprise per massimizzare le conversioni, gestire risorse operative e automatizzare la distribuzione multi-canale.

### 🛡️ Bulletproof WSOD Prevention System (v2.1.1)

- **Complete Architecture Simplification**: Removed complex multi-layer bootstrap system, replaced with single `WCEFP_Simple_Plugin` class
- **Enhanced WSOD Preventer**: Bulletproof error handling with comprehensive memory conversion and safety checks
- **Graceful Degradation**: Plugin no longer fails completely if WooCommerce is missing - shows user-friendly messages instead
- **Emergency Error System**: Comprehensive error tracking and recovery mechanisms for critical situations

### 🏗️ Simplified Plugin Architecture

- **Single Plugin Class**: `WCEFP_Simple_Plugin` with singleton pattern eliminates complex dependencies and loading chains
- **Bulletproof Memory Handling**: Enhanced `wcefp_convert_memory_to_bytes()` function handles all edge cases
- **Streamlined Loading**: Direct class instantiation instead of multi-layer fallback systems
- **Reduced Complexity**: Removed unused Bootstrap classes and complex service providers

### 🔧 Enhanced Error Handling & Recovery

- **Unified Error Management**: Consistent error handling approach throughout the plugin
- **Safe Activation/Deactivation**: Removed complex dependency chains, prevents activation errors
- **Memory Safety**: Comprehensive memory limit detection and conversion with overflow protection
- **Critical File Checks**: Validates essential plugin files before initialization

### 🏗️ Sistema di Gestione Risorse

- **Guide e Staff**: Gestione completa delle guide con competenze, disponibilità e costi
- **Attrezzature**: Inventario attrezzature con tracking utilizzo e manutenzione
- **Veicoli**: Fleet management per tour e trasferimenti
- **Location**: Gestione venues e meeting points con capacità
- **Calendario Risorse**: Pianificazione e allocazione automatica risorse
- **Conflitti**: Prevenzione automatica overbooking risorse

### 🌐 Distribuzione Multi-Canale

- **OTA Integration**: Distribuzione automatica su Booking.com, Expedia, GetYourGuide, Viator, Klook, Tiqets
- **Inventory Sync**: Sincronizzazione real-time disponibilità e prezzi
- **Commission Management**: Gestione automatica commissioni per canale
- **Markup dinamico**: Pricing specifico per canale con buffer disponibilità
- **Error Handling**: Sistema robusto di gestione errori sync
- **Analytics per Canale**: Performance tracking per ogni canale di distribuzione

### 💰 Sistema Commissioni e Reseller

- **Affiliate Program**: Sistema completo affiliazione con codici referral
- **Tier System**: Livelli Bronze/Silver/Gold/Platinum con commissioni progressive
- **Reseller Dashboard**: Dashboard dedicata per partner e affiliati
- **Payout Automation**: Gestione automatica pagamenti commissioni
- **Performance Tracking**: Analytics dettagliati per ogni reseller
- **White Label Options**: Opzioni personalizzazione per top reseller

### ⭐ Google Reviews Integration

- **API Integration**: Connessione diretta con Google Places API
- **Reviews Display**: Visualizzazione elegante recensioni Google genuine
- **Overall Rating**: Mostra rating complessivo e numero recensioni
- **Caching**: Sistema cache per performance ottimali
- **Fallback**: Recensioni di esempio quando API non disponibile
- **Shortcode**: `[wcefp_google_reviews place_id="..." limit="5"]`

---

## ⚙️ Installazione e Configurazione

### Requisiti di Sistema

- **WordPress**: 6+
- **WooCommerce**: 7+
- **PHP**: 7.4+
- **Browser**: Chrome, Firefox, Edge, Safari (ultime versioni)
- **Memoria**: Minimo 256MB raccomandati

### Procedura di Installazione

1. **Carica il plugin**
   - Copia la cartella `WCEventsFP` in `wp-content/plugins/`
   - OPPURE carica il file ZIP dal pannello WordPress

2. **Test Pre-Attivazione** (OBBLIGATORIO)
   ```bash
   cd wp-content/plugins/WCEventsFP
   php wcefp-activation-test.php
   ```

3. **Attivazione Sicura**
   - Vai su **Plugin** → **Plugin Installati**
   - Clicca **Attiva** su WCEventsFP
   - Segui il Setup Wizard automatico

4. **Configurazione Iniziale**
   - Vai su **Eventi & Degustazioni → Setup Wizard**
   - Seleziona le funzionalità desiderate
   - Configura le impostazioni base

### Setup Wizard

Il Setup Wizard ti guida attraverso:

- ✅ **Test compatibilità**: Verifica requisiti di sistema
- ✅ **Selezione funzionalità**: Scegli cosa abilitare
- ✅ **Configurazione database**: Crea tabelle necessarie  
- ✅ **Impostazioni base**: Configura tracking, email, integrazioni
- ✅ **Test finale**: Verifica che tutto funzioni

### Configurazione Avanzata

#### Tab Generali
- **Capienza Default**: Numero predefinito di posti per nuove occorrenze
- **Email WooCommerce**: Opzione per disabilitare email WC per ordini solo-eventi
- **Regole Prezzo Dinamiche**: JSON per sconti/ricarichi per date e giorni specifici

#### Tab Visualizzazione  
- Opzioni per personalizzare l'aspetto degli widget e template

#### Tab Integrazioni
- **Brevo/Sendinblue**: API Key, template email, mittente, liste IT/EN, tag contatti
- Configurazione completa per email transazionali automatiche

#### Tab Avanzate
- **Google Analytics 4**: ID misurazione, eventi custom dataLayer
- **Google Tag Manager**: Container ID (preferito rispetto a GA4 diretto)  
- **Meta Pixel**: ID per tracking Facebook/Instagram

---

## 🔧 Risoluzione Problemi

### Problemi Comuni e Soluzioni

#### Plugin Non Si Attiva

**Sintomi**: Errore durante attivazione o WSOD
**Soluzioni**:
1. Esegui il test pre-attivazione: `php wcefp-activation-test.php`
2. Verifica requisiti di sistema (PHP 7.4+, WooCommerce attivo)
3. Controlla memoria PHP (minimo 256MB)
4. Attiva debug WordPress: `define('WP_DEBUG', true);`

#### Funzionalità Mancanti

**Sintomi**: Alcune funzioni non sono disponibili
**Soluzioni**:
1. Controlla Setup Wizard: **Eventi & Degustazioni → Setup**
2. Verifica funzionalità abilitate nelle impostazioni
3. Controlla log errori per problemi specifici
4. Riavvia il Setup Wizard se necessario

#### Problemi di Performance

**Sintomi**: Sito lento dopo attivazione
**Soluzioni**:
1. Usa modalità "Minimal" nel Setup Wizard
2. Disabilita funzionalità non necessarie
3. Abilita cache nelle impostazioni avanzate
4. Controlla conflitti con altri plugin

#### Errori di Integrazione

**Sintomi**: Problemi con Brevo, GA4, Meta Pixel
**Soluzioni**:
1. Verifica API key e credenziali
2. Controlla configurazione nelle impostazioni
3. Testa connessioni singolarmente
4. Consulta log dettagliati

### Log e Debug

#### File di Log Principali
- `wp-content/debug.log` - Log generali WordPress
- `wp-content/uploads/wcefp-logs/` - Log specifici plugin
- Log server web (chiedi al tuo hosting provider)

#### Abilitare Debug Avanzato
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WCEFP_DEBUG', true);
```

### Strumenti di Diagnostica

#### Health Check Base
```bash
php wcefp-health-check.php
```

#### Diagnostic Tool Avanzato
```bash
php wcefp-diagnostic-tool.php
```

#### Test Caricamento Plugin
```bash
php wcefp-activation-test.php
```

---

## 🧪 Test e Diagnostica

### Test Pre-Attivazione

**Prima di attivare il plugin, esegui SEMPRE questi test:**

#### Test Ambiente
```bash
cd wp-content/plugins/WCEventsFP
php wcefp-health-check.php
```

**Verifica**:
- ✅ PHP 7.4+
- ✅ WordPress 6+
- ✅ WooCommerce 7+
- ✅ Memoria 256MB+
- ✅ Estensioni PHP necessarie

#### Test Caricamento
```bash
php test-plugin-loading.php
```

**Oppure via browser**:
```
https://tuosito.com/wp-content/plugins/wceventsfp/test-plugin-loading.php
```

**Controlla**:
- ✅ File plugin esistenti
- ✅ Sintassi PHP corretta
- ✅ Dipendenze soddisfatte
- ✅ Istanza plugin creabile

#### Test Attivazione Sicura
```bash
php wcefp-activation-test.php
```

**Simula l'attivazione senza attivare realmente il plugin**

### Interpretazione Risultati

#### 🟢 Tutti i Test Superati
```
🎉 Tutti i test superati! (6/6)
Il plugin dovrebbe attivarsi senza causare WSOD. 
È possibile procedere con l'attivazione dalla dashboard WordPress.
```
**→ Procedi con l'attivazione**

#### 🟡 Test Quasi Superati  
```
⚠️ Test quasi tutti superati (5/6)
Il plugin dovrebbe essere relativamente sicuro, ma ci sono alcuni problemi minori.
```
**→ Verifica i dettagli e risolvi i problemi minori**

#### 🔴 Molti Test Falliti
```
❌ Diversi test falliti (2/6)
NON attivare il plugin finché i problemi non sono risolti.
```
**→ NON attivare, risolvi tutti i problemi**

### Procedure di Emergenza

#### Se il Sito Va in WSOD

1. **Accesso FTP/SSH immediato**
2. **Disattiva il plugin**:
   ```bash
   cd wp-content/plugins
   mv WCEventsFP WCEventsFP-disabled
   ```
3. **Controlla i log**:
   ```bash
   tail -f wp-content/debug.log
   ```
4. **Esegui diagnostica**:
   ```bash
   cd WCEventsFP-disabled
   php wcefp-diagnostic-tool.php
   ```

#### Recovery Automatico

Il plugin include un sistema di auto-recovery:
- **Shutdown Handler**: Cattura errori fatali
- **Emergency Mode**: Modalità ridotta se fallisce caricamento normale
- **Auto-Deactivation**: Disattivazione automatica se troppi errori

---

## 📖 Guide per Sviluppatori

### Architettura Plugin

#### Struttura File Principali
```
WCEventsFP/
├── wceventsfp.php              # File principale plugin
├── wcefp-wsod-preventer.php    # Sistema prevenzione WSOD  
├── includes/
│   ├── Core/                   # Classi core
│   │   ├── InstallationManager.php
│   │   └── ActivationHandler.php
│   ├── Admin/                  # Pannello amministratore
│   ├── Frontend/               # Interfaccia frontend
│   └── Utils/                  # Utilità varie
└── admin/                      # Legacy admin files
```

#### Classe Plugin Principale
```php
class WCEFP_Simple_Plugin {
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function init() {
        // Inizializzazione sicura con try-catch
    }
}
```

### Hooks e Filtri Disponibili

#### Hook di Azione
```php
// Dopo creazione occorrenze
add_action('wcefp_occurrences_created', function($product_id, $count) {
    // La tua logica custom
});

// Prima dell'inizializzazione plugin
add_action('wcefp_before_init', function() {
    // Preparazione custom
});

// Dopo inizializzazione completata
add_action('wcefp_after_init', function($plugin_instance) {
    // Post-init logic
});
```

#### Filtri Disponibili
```php
// Modifica payload Brevo prima invio
add_filter('wcefp_brevo_order_payload', function($payload, $order) {
    // Personalizza dati inviati a Brevo
    return $payload;
}, 10, 2);

// Validazione voucher custom
add_filter('wcefp_validate_voucher', function($is_valid, $code, $voucher_data) {
    // Logica validazione personalizzata
    return $is_valid;
}, 10, 3);

// Personalizza prezzi dinamici
add_filter('wcefp_dynamic_pricing', function($price, $product_id, $date) {
    // Calcolo prezzo personalizzato
    return $price;
}, 10, 3);
```

### API Endpoints

#### REST API Endpoints
```
GET  /wp-json/wcefp/v1/events          # Lista eventi
GET  /wp-json/wcefp/v1/events/{id}     # Dettaglio evento  
POST /wp-json/wcefp/v1/booking         # Crea prenotazione
GET  /wp-json/wcefp/v1/availability    # Controlla disponibilità
```

#### AJAX Endpoints
```javascript
// Frontend AJAX
wp.ajax.post('wcefp_check_availability', {
    product_id: 123,
    date: '2024-01-15',
    adults: 2,
    children: 1
});

// Admin AJAX  
wp.ajax.post('wcefp_update_occurrence', {
    occurrence_id: 456,
    capacity: 20,
    status: 'active'
});
```

### Database Schema

#### Tabelle Plugin
- `wp_wcefp_occurrences` - Occorrenze eventi
- `wp_wcefp_closures` - Chiusure straordinarie
- `wp_wcefp_resources` - Gestione risorse
- `wp_wcefp_bookings` - Prenotazioni dettagliate
- `wp_wcefp_vouchers` - Voucher regalo

### Funzioni di Utilità

#### Sicurezza e Validazione
```php
// Conversione memoria sicura
$bytes = wcefp_convert_memory_to_bytes('256M');

// Log sicuro
wcefp_debug_log('Messaggio debug');

// Gestione errori di emergenza
wcefp_emergency_error('Errore critico', 'error');

// Display errori admin
wcefp_display_emergency_errors();
```

#### Helper Functions
```php
// Ottieni etichette giorni settimana
$labels = wcefp_get_weekday_labels();

// Controlla se funzionalità abilitata
if (wcefp_feature_enabled('google_reviews')) {
    // Logica reviews
}

// Ottieni istanza plugin
$plugin = WCEFP();
```

---

## 🆘 Supporto Tecnico

### Prima di Contattare il Supporto

1. **Esegui tutti i test diagnostici**
2. **Raccogli informazioni sistema**:
   - Versione PHP
   - Versione WordPress  
   - Versione WooCommerce
   - Tema attivo
   - Plugin attivi
   - Log errori

3. **Prova le soluzioni standard**:
   - Riavvio Setup Wizard
   - Modalità minimal
   - Disattivazione plugin conflittuali

### Informazioni da Fornire

#### Dettagli Ambiente
```
PHP: [versione]
WordPress: [versione]
WooCommerce: [versione]
WCEventsFP: [versione]
Tema: [nome tema]
Hosting: [provider]
```

#### Log Errori
```bash
# Raccogli log recenti
tail -n 100 wp-content/debug.log

# Log specifici plugin
ls -la wp-content/uploads/wcefp-logs/
```

#### Risultati Test
```bash
# Esegui e allega risultati
php wcefp-health-check.php > health-check-results.txt
php wcefp-diagnostic-tool.php > diagnostic-results.txt
```

### Contatti Supporto

📧 **Email**: supporto-wcefp@example.com
📞 **Telefono**: Disponibile per clienti enterprise
💬 **Chat**: Disponibile nel pannello admin plugin
📖 **Documentazione**: https://docs.wcefp.com
🐛 **Bug Reports**: https://github.com/wcefp/issues

### Livelli di Supporto

#### 🟢 Supporto Standard (Gratuito)
- Email support
- Documentazione completa
- Community forum
- Bug fixes

#### 🟡 Supporto Priority (Premium)
- Supporto telefonico
- Chat 1-on-1
- Setup personalizzato
- Configurazione avanzata

#### 🔴 Supporto Enterprise
- Supporto dedicato
- Sviluppo custom
- Integrazioni personalizzate
- SLA garantito

---

**Documentazione aggiornata al**: Gennaio 2024  
**Versione plugin**: 2.1.1  
**Autore**: Francesco Passeri  