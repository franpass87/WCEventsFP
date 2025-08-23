# WCEventsFP (v2.1.4) - Enterprise Booking Platform<!-- x-release-please-version -->

> **📦 COME SCARICARE IL PLUGIN** - [Guida Completa per Utenti](DOWNLOAD-INSTALL-GUIDE.md)  
> **⚠️ NON usare "Code → Download ZIP"** - Scarica dalle [GitHub Releases](https://github.com/franpass87/WCEventsFP/releases)
> 
> **🛡️ WSOD ISSUES COMPLETELY RESOLVED** - See [WSOD Solution Guide](WSOD-GUIDE.md)

**Sistema di prenotazione enterprise per competere direttamente con RegionDo, Bokun e GetYourGuide**

Plugin WordPress/WooCommerce completo per eventi ed esperienze con funzionalità di livello enterprise per massimizzare le conversioni, gestire risorse operative e automatizzare la distribuzione multi-canale.

## 🚀 Nuove Funzionalità v2.1.4 - Comprehensive Code Consolidation & Admin Menu Rationalization

### 🔧 Admin Menu System Rationalization
- **Menu Duplication Eliminated**: Risolti completamente i problemi di duplicazione del menu admin
- **Single Source of Truth**: Consolidato tutto il sistema menu in `includes/Admin/MenuManager.php`
- **Legacy Class Cleanup**: Rimossa l'auto-inizializzazione da 5 classi Legacy che causavano duplicazioni
- **Architecture Consistency**: Implementata architettura moderna uniforme per la gestione admin

### ⚡ Performance & Security Improvements
- **Conditional Asset Loading**: Caricamento Chart.js solo nelle pagine che necessitano di analytics
- **Input Sanitization**: Migliorata sicurezza con `absint()` invece di cast `(int)`
- **Memory Optimization**: Ridotto caricamento di risorse non necessarie nelle pagine admin

### 📚 Documentation & Code Quality
- **Version Consistency**: Allineati tutti i riferimenti di versione a v2.1.4
- **Code Documentation**: Aggiunti commenti esplicativi per sviluppatori futuri
- **Architecture Documentation**: Documentate le modifiche architetturali per manutenzione

---

## 🎨 Feature Pack Roadmap - UI/UX & Advanced Features (v2.2.0-dev)

**Development Branch**: `feature/uiux-feature-pack`  
**Target Release**: Q1 2024  
**Focus**: Modern UI/UX, Enterprise Features, Developer Experience

### 📋 Implementation Phases

#### **Phase 1: UI/UX Foundation** ✅ COMPLETED
- [x] **Admin Interface Modernization**
  - [x] Move inline CSS/JS to dedicated asset files for better maintainability
  - [x] Standardize settings interface using WordPress Settings API ✅ (Already implemented)
  - [x] Implement WP_List_Table for consistent data presentation
  - [x] Enhanced accessibility (ARIA labels, keyboard navigation, screen reader support)
  - [x] Replace browser alerts with WordPress-native modals and notices
  
- [x] **Frontend Experience Polish**
  - [x] Modular CSS architecture with `.wcefp-widget` wrapper
  - [x] Responsive design optimization for mobile booking flow
  - [x] Conditional asset enqueuing (load only when shortcode/block is used)
  - [x] Theme font inheritance option for seamless integration
  - [x] Consistent loading/empty/error state management

#### **Phase 2: Communication & Automation** ✅ COMPLETED
- [x] **Email Notification System**
  - [x] Modern HTML email templates with responsive design and brand consistency
  - [x] Template engine with variable replacement system and fallback support
  - [x] Automated reminder system via WP-Cron with conditional sending logic
  - [x] Email analytics with delivery tracking and performance monitoring
  - [x] Event-driven email sequences with booking confirmations and follow-ups
  
- [x] **Advanced Voucher System**  
  - [x] Enhanced voucher management with WordPress-native modal interfaces
  - [x] Real-time admin dashboard with analytics, search, and bulk operations
  - [x] Frontend widget system for voucher redemption with responsive design
  - [x] Comprehensive lifecycle management with status tracking and expiry warnings
  - [x] Email delivery automation with voucher notifications and reminders

#### **Phase 3: Data & Integration** ✅ COMPLETED
- [x] **Advanced Export Capabilities**
  - [x] CSV export for bookings with filtering (date range, status, event)
  - [x] ICS calendar export for individual events and global feed
  - [x] Capability-based download permissions and audit logging
  
- [x] **Modern Content Management**
  - [x] Gutenberg block for booking forms with live preview
  - [x] Server-side rendering for performance and SEO
  - [x] Block editor UI with event/product selector and configuration options

#### **Phase 4: API & Developer Experience** ✅ COMPLETED
- [x] **Enhanced REST API**  
  - [x] Secure `wcefp/v2` namespace with comprehensive endpoints for bookings, events, analytics
  - [x] Rate limiting and throttling with configurable limits per client type
  - [x] Advanced authentication (API keys, Bearer tokens, WordPress sessions)
  - [x] OpenAPI 3.0 documentation generation with interactive Swagger UI
  - [x] Input validation, error handling, and comprehensive logging
  
- [x] **Role-Based Access Control**
  - [x] Custom `event_manager` role with granular capabilities  
  - [x] Advanced Event Manager role with enterprise features access
  - [x] Event Viewer role for read-only access with API permissions
  - [x] Capability mapping for existing WordPress roles (Admin, Shop Manager, Editor)
  - [x] Menu visibility and action authorization based on user permissions

#### **Phase 5: Advanced Booking Features**
- [ ] **Digital Check-in System**
  - QR code generation for each booking with unique tokens
  - Mobile-optimized check-in interface for event staff
  - Real-time check-in status tracking and duplicate prevention
  
- [ ] **Calendar Integration**
  - Public ICS feeds for events with caching and performance optimization
  - Google Calendar "Add Event" integration buttons  
  - Authenticated feeds for admin calendar synchronization

#### **Phase 6: Analytics & Automation**  
- [ ] **Interactive Analytics Dashboard**
  - Chart.js powered visualizations for booking trends and KPIs
  - Occupancy rates, revenue analytics, and top-performing events
  - Cached data aggregation with WordPress transients for performance
  
- [ ] **Intelligent Occurrence Management**
  - Auto-generation of future occurrences with rolling window (60+ days)
  - WP-Cron daily automation with duplicate prevention
  - Execution logging and error handling for reliability

### 🔧 Technical Implementation Guidelines

- **Security First**: All features include proper nonce verification, capability checks, and output escaping
- **Performance Optimized**: Conditional loading, efficient caching, minimal database impact  
- **Backward Compatible**: No breaking changes to existing APIs, hooks, or URL structures
- **WordPress Standards**: Full compliance with WPCS, accessibility guidelines, and coding standards
- **Internationalization**: All user-facing strings translatable with proper text domains

### 🧪 Quality Assurance Process

- **Automated Testing**: PHPUnit for backend, Jest for frontend components
- **Code Quality**: PHPCS Level 8.0+, PHPStan analysis, ESLint compliance
- **User Testing**: Accessibility validation with screen readers and keyboard navigation  
- **Performance**: Core Web Vitals optimization and database query analysis
- **Documentation**: Complete API documentation, usage examples, and migration guides

---

## 🚀 Previous Features v2.1.3 - Advanced Bug Fixes & Code Optimization

### 🐛 Bug Fixes Risolti
- **Deprecated Functions**: Sostituita funzione WooCommerce deprecata `get_woocommerce_currency()` con `get_option('woocommerce_currency')`
- **Legacy Logger Redundancy**: Eliminata inizializzazione ridondante del sistema di logging legacy in wceventsfp.php
- **Code Quality**: Rimossi riferimenti non necessari che causavano duplicazione di codice
- **Compatibility**: Migliorata compatibilità con versioni più recenti di WordPress/WooCommerce

### 🧹 Code Cleanup & Optimization
- **Redundancy Elimination**: Rimossa inizializzazione del logger legacy deprecato dal bootstrap principale
- **Function Modernization**: Aggiornate chiamate a funzioni WordPress/WooCommerce per utilizzare le versioni più recenti
- **Performance**: Ridotte operazioni non necessarie durante l'inizializzazione del plugin
- **Code Standards**: Migliorata aderenza agli standard di codifica WordPress moderni

### 📝 Documentation Updates
- **Version Consistency**: Aggiornati tutti i riferimenti di versione a v2.1.3
- **Installation Guide**: Aggiornate le istruzioni di installazione con la nuova versione
- **Diagnostic Tools**: Aggiornati gli strumenti di diagnosi per riflettere la versione corrente

## Previous Releases

### v2.1.2 - Bug Fixes & Code Cleanup

### 🐛 Bug Fixes Risolti
- **Logger Deprecato**: Rimossi tutti i riferimenti al vecchio sistema WCEFP_Logger, ora usa WCEFP\Utils\Logger
- **Test JavaScript**: Risolto test Jest per auto-dismiss delle notifiche utilizzando fake timers
- **Compatibilità**: Aggiornata compatibilità con WordPress 6.7+ e WooCommerce 9.3+
- **Sicurezza**: Risolte vulnerabilità minori nelle dipendenze di sviluppo

### 🧹 Code Cleanup
- **Redundancy Removal**: Eliminata ridondanza nell'uso del sistema di logging legacy
- **Code Quality**: Migliorata qualità del codice rimuovendo duplicazioni
- **Performance**: Ottimizzazioni minori nelle performance

### 🛡️ Technical Improvements
- **Product Type System Unified**: Eliminated duplicate product classes, standardized to wcefp_event/wcefp_experience
- **WSOD Documentation Consolidated**: Single comprehensive guide replaces 5 redundant files  
- **Security Updates**: Fixed npm vulnerabilities, updated @wordpress/scripts to v30.22.0
- **Code Quality**: Removed ~1300 lines of redundant code and documentation
- **Enhanced Product Classes**: Improved virtual product handling and shipping logic

### 🛡️ Complete WSOD (White Screen of Death) Resolution
- **Advanced Autoloading System**: Bulletproof PSR-4 autoloading without Composer dependency, with intelligent fallback and error handling
- **Server Resource Monitor**: Real-time server analysis with adaptive modes (ultra_minimal → minimal → progressive → standard → full) based on available resources  
- **Resource-Aware Initialization**: Plugin automatically adapts to server capabilities, ensuring functionality even on limited hosting environments
- **Emergency Recovery System**: Comprehensive error tracking and automatic recovery mechanisms for critical situations

### 🔧 Enhanced System Stability  
- **Smart Feature Loading**: Dynamic feature activation based on server resources to prevent overload
- **Memory Safety**: Bulletproof memory management with overflow prevention and intelligent allocation
- **Graceful Degradation**: Plugin continues to function even when server resources are limited
- **Universal Compatibility**: Guaranteed functionality across all hosting environments from shared hosting to dedicated servers

## 🚀 Previous Features v2.1.0 - Major WSOD Cleanup & Architecture Improvements

### 🛡️ Bulletproof WSOD Prevention System
- **Complete Architecture Simplification**: Removed complex multi-layer bootstrap system, replaced with single `WCEFP_Simple_Plugin` class
- **Enhanced WSOD Preventer**: Bulletproof error handling with comprehensive memory conversion and safety checks
- **Graceful Degradation**: Plugin no longer fails completely if WooCommerce is missing - shows user-friendly messages instead
- **Emergency Error System**: Comprehensive error tracking and recovery mechanisms for critical situations

### 🏗️ Simplified Plugin Architecture
- **Single Plugin Class**: `WCEFP_Simple_Plugin` with singleton pattern eliminates complex dependencies and loading chains
- **Bulletproof Memory Handling**: Enhanced `wcefp_convert_memory_to_bytes()` function handles all edge cases (null, numeric, string, overflow prevention)
- **Streamlined Loading**: Direct class instantiation instead of multi-layer fallback systems
- **Reduced Complexity**: Removed unused Bootstrap classes and complex service providers

### 🔧 Enhanced Error Handling & Recovery
- **Unified Error Management**: Consistent error handling approach throughout the plugin
- **Safe Activation/Deactivation**: Removed complex dependency chains, prevents activation errors
- **Memory Safety**: Comprehensive memory limit detection and conversion with overflow protection
- **Critical File Checks**: Validates essential plugin files before initialization

## 🚀 Previous Features (v2.0.1) - System Improvements

### 🛠️ Enhanced Error Handling & Debugging
- **Advanced Error System**: Comprehensive error management with user-friendly messages and detailed logging
- **Developer Debug Tools**: Real-time debugging panel with performance monitoring, SQL query logging, and system diagnostics
- **Admin Bar Integration**: Quick access to debug tools and system status from WordPress admin bar
- **Error Recovery**: Automatic error handling with fallback mechanisms and detailed error context

### 🌍 Advanced Internationalization (i18n)
- **Global Market Support**: 10 supported locales (EN, IT, ES, FR, DE, PT-BR, JP, KO, ZH-CN)
- **Dynamic Language Switching**: Client-side language switching with localStorage preference saving
- **Locale-Specific Formatting**: Automatic date, time, price, and number formatting based on user locale
- **RTL Language Support**: Full right-to-left language support for Arabic, Hebrew, and Persian
- **Emergency Translations**: Fallback translation system for critical booking terms

### 📡 Enhanced Webhook System
- **Comprehensive Event Coverage**: Webhooks for all booking lifecycle events, payments, and reviews
- **Reliable Delivery**: Queue-based processing with retry logic and exponential backoff
- **Signature Verification**: HMAC-SHA256 signature verification for security
- **Admin Interface**: Webhook management, testing, and monitoring through admin panel
- **Performance Optimized**: Asynchronous processing to avoid blocking user interactions

### 🧪 Improved Development Experience
- **Fixed Test Infrastructure**: Resolved Jest configuration issues with proper jQuery mocking
- **Performance Monitoring**: Core Web Vitals tracking and real-time performance metrics
- **System Health Checks**: Automated monitoring with alerts for system issues
- **Code Quality Tools**: Enhanced linting, testing, and debugging capabilities

## 🚀 Nuove Funzionalità v2.0.0 - Competitive Edge

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

### 🔒 Security & Monitoring Enhancements (v2.0.1)
- **Rate Limiting**: Protezione automatica contro spam e attacchi DDoS
- **Content Security Policy**: Headers CSP configurabili per maggiore sicurezza
- **Real-time Monitoring**: Sistema di monitoraggio avanzato con alert automatici
- **Health Checks**: Controlli automatici dello stato del sistema
- **Performance Metrics**: Tracciamento Core Web Vitals e metriche di performance
- **Alert System**: Notifiche via email, Slack, e webhook per problemi critici

### 🚀 Real-time Features (v2.0.1)  
- **Live Updates**: Aggiornamenti in tempo reale di prenotazioni e disponibilità
- **WebSocket-like Communication**: Sistema di polling avanzato per aggiornamenti live
- **Push Notifications**: Notifiche push per amministratori e utenti
- **Session Management**: Gestione sessioni real-time con riconnessione automatica
- **Live Dashboard**: Dashboard amministratore con metriche in tempo reale

### ♿ Accessibility Enhancements (v2.0.1)
- **WCAG 2.1 AA Compliance**: Conformità completa alle linee guida per l'accessibilità
- **Screen Reader Support**: Supporto completo per lettori di schermo
- **Keyboard Navigation**: Navigazione completa da tastiera
- **High Contrast Mode**: Modalità alto contrasto per utenti con disabilità visive  
- **Focus Management**: Gestione avanzata del focus per migliore usabilità
- **Accessibility Toolbar**: Barra strumenti accessibilità personalizzabile

### ⚡ Performance Optimization (v2.0.1)
- **Multi-tier Caching**: Sistema cache avanzato a più livelli (Memory, Object, File)
- **Image Optimization**: Conversione automatica WebP con fallback
- **Lazy Loading**: Caricamento pigro di immagini e contenuti
- **Critical CSS**: Inlining automatico CSS critico above-the-fold
- **Asset Optimization**: Minificazione e combinazione CSS/JS automatica
- **Database Optimization**: Query ottimizzate e caching intelligente

### 🧪 Testing & Quality Assurance (v2.0.1)
- **Unit Testing**: Framework PHPUnit completo con mocking WordPress
- **JavaScript Testing**: Test Jest per componenti frontend
- **Code Coverage**: Reporting copertura codice completo
- **Static Analysis**: PHPStan per analisi statica del codice
- **Continuous Integration**: Setup completo per CI/CD

### 📚 Developer Experience (v2.0.1)
- **Dependency Management**: Composer e npm per gestione dipendenze
- **API Documentation**: Documentazione API completa e esempi
- **Contributing Guidelines**: Linee guida dettagliate per contribuitori
- **Coding Standards**: Standard di codifica WordPress e PSR-12
- **Development Tools**: Strumenti di sviluppo e debugging avanzati

## 🏗️ Architettura Enterprise
Con:
- Ricorrenze settimanali, slot orari, prezzi Adulto/Bambino
- Extra riutilizzabili (CPT dedicato, tabella ponte, tariffazione per ordine/persona/adulto/bambino, quantità massime, obbligatorietà, stock con allocazione automatica)
- Chiusure straordinarie (globali/prodotto)
- Dashboard KPI (ordini, ricavi, riempimento medio, top esperienza)
- Calendario prenotazioni (FullCalendar, inline edit)
- Lista prenotazioni AJAX (ricerca live, export CSV)
- Tracciamento GA4/Tag Manager + Meta Pixel
- Integrazione Brevo (liste IT/EN, transactional)
- ICS, gift “Regala un’esperienza”
- Widget di prenotazione con selezione quantità/toggle e calcolo dinamico del prezzo
## Compatibilità

- **WordPress**: 6+
- **WooCommerce**: 7+
- **PHP**: 7.4+
- **Browser**: Chrome, Firefox, Edge, Safari (ultime versioni)
## Screenshot UI

Esempi interfaccia amministratore e frontend:

![Calendario backend](assets/screenshots/calendar-admin.png)
![Widget prenotazione](assets/screenshots/booking-widget.png)
![Dashboard KPI](assets/screenshots/kpi-dashboard.png)

Per aggiungere altri screenshot, inserire le immagini in `assets/screenshots/` e aggiornare questa sezione.

**Autore:** Francesco Passeri  
**Requisiti:** WordPress 6+, WooCommerce 7+, PHP 7.4+  
**Slug:** `wceventsfp`

---

## Installazione
1. Copia la cartella `WCEventsFP` in `wp-content/plugins/`.
2. Attiva **WCEventsFP** da **Plugin** in WordPress.
   - ✅ **Attivazione Immediata**: Tutte le funzionalità sono disponibili subito
   - ✅ **Nessun Setup**: Non sono richiesti passaggi di configurazione
3. Vai su **Eventi & Degustazioni → Impostazioni** e configura:
   - **Tracking**: abilita GA4/DTL, inserisci **Meta Pixel ID**.
   - **Brevo**: API Key, mittente, Template ID (opzionale), **Lista IT** e **Lista EN**.
   - Opzione per **disattivare le email WooCommerce** se l’ordine contiene **solo** eventi/esperienze (gestirà tutto Brevo).

> Se vuoi le card/griglie stile OTA, assicurati che nel main sia incluso:
> `require_once WCEFP_PLUGIN_DIR . 'includes/class-wcefp-templates.php';`

---

## Creazione prodotto Evento/Esperienza
1. Crea un **Prodotto** WooCommerce e scegli tipo **Evento** o **Esperienza**.
2. Nella **tab** “Eventi/Esperienze” imposta:
   - Prezzo **Adulto** e **Bambino**
   - **Capienza per slot** e **Durata** (minuti)
   - **Giorni** attivi e **slot orari** (es. `11:00, 15:00, 18:30`)
   - **Extra** riutilizzabili con tariffazione (per ordine/per persona/solo adulto/solo bambino), obbligatorietà, quantità massima e **stock**
   - Info: **lingue**, **meeting point**, **incluso**, **escluso**, **cancellazione**
3. Genera le **occorrenze** (da/a) con il pulsante nel pannello prodotto.

> Gli Eventi/Esperienze **non compaiono** negli archivi standard dello shop.

---

## Chiusure straordinarie
- **Eventi & Degustazioni → Chiusure straordinarie**
- Aggiungi intervalli **globali** o per **singolo prodotto**.
- Le date chiuse **bloccano** il calendario in frontend.

---

## Calendario & Lista prenotazioni
- **Eventi & Degustazioni → Calendario & Lista**
  - **Calendario** (FullCalendar): mostra capienza/prenotati; **click evento** per aggiornare capienza e stato (attivo/cancellato).
  - **Lista**: ricerca live e overview ultimi ordini Evento/Esperienza.

---

## Interfaccia Impostazioni Tabbed

La pagina **Eventi & Degustazioni → Impostazioni** è ora organizzata in tab per una migliore usabilità:

### 🔧 Tab Generali
- **Capienza Default**: Numero predefinito di posti per nuove occorrenze
- **Email WooCommerce**: Opzione per disabilitare email WC per ordini solo-eventi
- **Regole Prezzo Dinamiche**: JSON per sconti/ricarichi per date e giorni specifici

### 🎨 Tab Visualizzazione  
- Opzioni per personalizzare l'aspetto degli widget e template (disponibili in versioni future)

### 🔗 Tab Integrazioni
- **Brevo/Sendinblue**: API Key, template email, mittente, liste IT/EN, tag contatti
- Configurazione completa per email transazionali automatiche

### ⚙️ Tab Avanzate
- **Google Analytics 4**: ID misurazione, eventi custom dataLayer
- **Google Tag Manager**: Container ID (preferito rispetto a GA4 diretto)  
- **Meta Pixel**: ID per tracking Facebook/Instagram
- Tutte le configurazioni hanno descrizioni contestuali e validazione in tempo reale

---

## 🚀 Nuove Funzionalità v1.9.0 - Competitive Edge

### Advanced Tracking & Analytics
- **Google Analytics 4 Potenziato**: Eventi ecommerce completi, enhanced conversions, funnel tracking
- **Google Ads Integration**: Conversion tracking automatico con enhanced conversions
- **Meta Pixel Avanzato**: Eventi Facebook completi (ViewContent, AddToCart, InitiateCheckout, Purchase, CompleteRegistration)
- **Server-side Analytics**: Backup dati critici con analytics table personalizzata
- **Performance Monitoring**: Core Web Vitals, tempo caricamento, metriche UX
- **Cross-device Tracking**: Fingerprinting utenti e tracking cross-session
- **Real-time Dashboard**: Monitoraggio KPI, conversion funnel, insights automatici

### Conversion Optimization Engine
- **Urgency Indicators**: Timer countdown per offerte limitate nel tempo
- **Social Proof**: Notifiche prenotazioni recenti, badge "Molto Richiesto", recensioni simulate
- **Dynamic Pricing**: Confronto prezzi concorrenti, indicatori sconto, "Miglior Prezzo"
- **Scarcity Marketing**: Indicatori disponibilità limitata con colori di urgenza
- **Trust Badges**: Badge sicurezza, garanzie, certificazioni
- **Exit Intent Popup**: Recovery conversioni con codici sconto
- **Live Chat Simulation**: Widget supporto clienti con risposte automatiche

### Customer Journey Enhancement
- **Funnel Tracking**: Monitoraggio completo del percorso utente (9 step)
- **Engagement Analytics**: Scroll depth, tempo permanenza, interazioni
- **Session Analysis**: Durata sessioni, step completamento, abbandoni
- **User Behavior**: Heat mapping simulato, click tracking, form analytics

## Gift / Regala un’esperienza
- Nel **checkout**: flag “Regala un’esperienza” + campi **Nome**, **Email destinatario**, **Messaggio**.
- A **ordine completato**: invio PDF personalizzato via email al destinatario, con codice voucher e messaggio. Creazione voucher (uno per quantità) e invio link al destinatario via Brevo. Pagina voucher stampabile (`?wcefp_voucher_view=1&code=...`).
- Riscatto: crea una pagina “Riscatta voucher” con lo shortcode `[wcefp_redeem]`. Dopo redeem valido, l’utente prenota il relativo prodotto a 0€. Il voucher diventa used quando l’ordine è Completato.

---

## Shortcode disponibili

### Shortcode Base
- `[wcefp_event_card id="123"]` — Card evento singolo
- `[wcefp_event_grid]` — Griglia eventi/esperienze
- `[wcefp_booking_widget]` — Widget prenotazione
- `[wcefp_booking product_id="123"]` — Widget prenotazione manuale
- `[wcefp_redeem]` — Riscatta voucher

### 🆕 Nuovi Shortcode v1.8.1
- `[wcefp_reviews id="123" limit="5"]` — Sistema recensioni con rating
- `[wcefp_testimonials limit="3" style="carousel"]` — Testimonial slider
- `[wcefp_countdown id="123"]` — Timer countdown per eventi
- `[wcefp_featured_events limit="3"]` — Eventi in evidenza

### Esempi d'Uso
```html
<!-- Widget multi-step per prodotto specifico -->
<div class="wcefp-multistep">
  [wcefp_booking_widget product_id="123"]
</div>

<!-- Sezione recensioni completa -->
[wcefp_reviews id="123" limit="8"]

<!-- Testimonial rotativi -->
[wcefp_testimonials limit="5" style="carousel"]

<!-- Countdown per prossimo evento -->
[wcefp_countdown id="123" style="modern"]
```

---

## Tracking (GA4 / GTM)
Eventi pushati in **dataLayer**:
- `view_item` (su widget prodotto)
- `add_to_cart` (salvato in session e pushato lato carrello Woo)
- `begin_checkout` (al click “Aggiungi al carrello”, redirect)
- `purchase` (su thank-you Woo)
- `extra_selected` (toggle di un extra nel widget)

**Suggerimento GTM**  
- Trigger “Custom Event” per ciascun nome evento sopra.  
- Mappa `ecommerce.items` dove presente.  
- Per `purchase`, Woo invia: `transaction_id`, `value`, `currency`, `items`.

---

## Meta Pixel
- Base `PageView` in `<head>`.
- `Purchase` su thank-you (`value`, `currency`).

---

## Brevo (Sendinblue)
- **Impostazioni**:
  - API v3 key
  - Mittente email (nome+email)
  - (Opzionale) **Template ID** transazionale
  - **Lista IT** e **Lista EN**
- Alla **completazione ordine** con Eventi/Esperienze:
  - **Upsert contatto** + iscrizione a lista in base alla **lingua** (IT/EN).
  - Invio email riepilogo con link ICS (thank-you) e, se gift attivo, email al **destinatario** con link voucher.

---

## ICS (Calendario)
- Su **Thank-you**: link “Aggiungi al calendario” per ogni riga evento (ICS dinamico).
- Route pubblica: `/?wcefp_ics=1&order=...&item=...&pid=...&occ=...`

---

## Export CSV
- **Eventi & Degustazioni → Esporta**
  - **Occorrenze**
  - **Prenotazioni** (ultimo anno)

---

## Sicurezza & note
- Tutte le **AJAX** con **nonce** e **capabilities**.
- **Overbooking**: aggiornamenti posti **atomici** in SQL.
- Email Woo disattivate **solo** se l’ordine contiene **esclusivamente** eventi/esperienze (opzione).
- Se usi **cache** aggressiva, escludi le pagine di **checkout**, **carrello** e gli endpoint AJAX.

---

## Troubleshooting
- **Slot non visibili**: verifica che esista almeno un’occorrenza **attiva** e **non chiusa** (vedi “Chiusure straordinarie”).
- **Prezzo non cambia**: controlla che gli extra siano in JSON valido e che `apply_dynamic_price` non sia bloccato da altri plugin.
- **Brevo non invia**: ricontrolla API key, mittente, e che il server possa chiamare `api.brevo.com`.
- **Meta Pixel duplica acquisti**: accertati di non avere un altro plugin che invia `Purchase` su thank-you.

---

## Hooks utili (azioni/filtri)
```php
// Dopo creazione occorrenze (customizza logica pricing/capienza)
add_action('wcefp_occurrences_created', function($product_id, $count){});

// Prima di inviare Brevo ordine (modifica payload)
add_filter('wcefp_brevo_order_payload', function($payload, $order){ return $payload; }, 10, 2);

// Convalida voucher personalizzata
add_filter('wcefp_validate_voucher', function($ok, $code, $row){ return $ok; }, 10, 3);
