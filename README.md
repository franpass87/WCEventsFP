# WCEventsFP (v1.8.1) - Premium Booking Platform
Eventi & Esperienze per WooCommerce con funzionalità avanzate simili a Bokun, Regiondo e GetYourGuide.

## 🚀 Nuove Funzionalità v1.8.1

### 🎨 Design Moderno e UX Migliorata
- **Interfaccia completamente ridisegnata** con gradients moderni e animazioni fluide
- **Filtri avanzati** con ricerca in tempo reale, filtro per tipo e fascia prezzo
- **Social proof dinamico** con indicatori di prenotazioni recenti e disponibilità
- **Sistema di recensioni** integrato con stelle e testimonial rotativi
- **Widget di prenotazione multi-step** con progress indicator

### 🗺️ Mappe Interactive
- **Integrazione Leaflet** per visualizzare meeting points
- **Marker personalizzati** con popup informativi
- **Link diretti** a Google Maps per navigazione
- **Design responsive** ottimizzato per mobile

### ⭐ Sistema di Recensioni
- **Shortcode `[wcefp_reviews id="123"]`** per mostrare recensioni clienti
- **Shortcode `[wcefp_testimonials limit="3"]`** con slider automatico
- **Rating visivi** con stelle e statistiche
- **Testimonianze multilingue** con avatars

### 🎯 Social Proof & Urgency
- **Indicatori di attività** ("Marco ha prenotato 2 ore fa")
- **Badge di urgenza** (Ultimi posti!, Popolare, Bestseller)
- **Contatori disponibilità** in tempo reale
- **Animazioni di social proof** con effetti di comparsa

### 📱 Mobile Experience
- **Design completamente responsive**
- **Touch gestures** per gallery e slider
- **Interfaccia touch-friendly** per tutti i controlli
- **Ottimizzazione prestazioni** per dispositivi mobili

## 🏗️ Funzionalità Base Esistenti
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

## Dashboard KPI
- **Eventi & Degustazioni → Analisi KPI**
  - Ordini 30gg, Ricavi 30gg, Riempimento medio, Top esperienza.  
  *(Demo simple: personalizza la query secondo le tue metriche.)*

---

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
