# WCEventsFP (v2.0.0) - Enterprise Booking Platform

**Sistema di prenotazione enterprise per competere direttamente con RegionDo, Bokun e GetYourGuide**

Plugin WordPress/WooCommerce completo per eventi ed esperienze con funzionalità di livello enterprise per massimizzare le conversioni, gestire risorse operative e automatizzare la distribuzione multi-canale.

## 🚀 Nuove Funzionalità v2.0.1 - Competitive Edge

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
