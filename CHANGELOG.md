[1.7.1] – 2025-08-19
Added

Widget di prenotazione con selezione quantità/toggle e calcolo dinamico del prezzo.
Shortcode aggiuntivi: [wcefp_booking_widget], [wcefp_redeem].
Sezione compatibilità e screenshot UI nella documentazione.
📜 Changelog – WCEventsFP

[1.7.3] – 2025-08-21
Added

Frontend Quick Wins - Enhanced visual design and user experience:
- New `frontend-cards.css` with comprehensive design system using CSS custom properties
- CSS variables for colors, spacing, typography, and theming support
- Enhanced card components with hover states, improved shadows, and better spacing
- Responsive design with breakpoints for mobile (≤480px), tablet (≤768px), and desktop (≤1024px)
- Optional skeleton loader with `.wcefp-skeleton-enabled` flag for reduced CLS
- Enhanced widget styling with improved typography, padding, and form controls
- WCAG AA compliant contrast ratios and visible focus indicators
- Dark mode support (respects `prefers-color-scheme`)
- High contrast mode support
- Reduced motion accessibility support
- Print-friendly styles

Improved

Enhanced accessibility and user experience:
- Better hover and focus states for all interactive elements
- Improved keyboard navigation support
- Enhanced button and form control styling with better visual feedback
- More consistent spacing and typography throughout
- Smooth transitions and micro-interactions (with reduced motion respect)

[1.7.1] – 2025-08-19
Fixed

Corretto il checkbox del lunedì che non appariva nel pannello "Ricorrenze settimanali & Slot" del backend.

Changed

Admin UX: migliorato blocco “Info esperienza” con editor leggeri, microcopy e sanitizzazione HTML.

[1.7.0] – 2025-08-18
Added

Extra riutilizzabili con CPT dedicato e tabella ponte, supporto a tariffazione per ordine/persona/adulto/bambino, quantità massime, obbligatorietà e stock con allocazione automatica.

Changed

Widget di prenotazione aggiornato con selezione quantità/toggle e calcolo dinamico del prezzo.

Fixed

Pagina Meeting Points nel backend registrata dopo il menu principale del plugin, evitando il redirect alla homepage.

[1.6.1] – 2025-08-17
Changed

Extra opzionali gestiti con campi dedicati (nome e prezzo) invece del JSON manuale.

[1.6.0] – 2025-08-16
Added

Nuovi tipi prodotto: Evento ed Esperienza (non visibili negli archivi Woo standard).

Prezzi differenziati adulti/bambini.

Extra opzionali con prezzo.

Ricorrenze settimanali e slot orari.

Capacità per singolo slot con gestione prenotazioni.

Shortcode:

[wcefp_event_card id="123"]

[wcefp_event_grid]

[wcefp_booking_widget]

Dashboard KPI: prenotazioni 30gg, ricavi, riempimento medio, top esperienza.

Calendario backend con FullCalendar + inline edit.

Lista prenotazioni AJAX con ricerca live ed export CSV.

Chiusure straordinarie (giorni o periodi non prenotabili).

Tracking eventi personalizzati GA4 / GTM: view_item, add_to_cart, begin_checkout, purchase, extra_selected.

Integrazione Meta Pixel: PageView, ViewContent, Purchase.

Integrazione Brevo (Sendinblue) con segmentazione automatica ITA/ENG.

Gestione email: disattiva notifiche WooCommerce → invio da Brevo.

Regala un’esperienza: opzione checkout, voucher PDF con codice univoco, invio email al destinatario, shortcode [wcefp_redeem].

Link “Aggiungi al calendario” e generazione file ICS dinamici.

Improved

Struttura plugin modulare (includes/, admin/, public/).

File .pot per traduzioni multilingua.

File .gitignore ottimizzato per GitHub.

Sicurezza migliorata (nonce, sanitizzazione input).

Notes

Testato con WordPress 6.x e WooCommerce 7.x.

Richiede PHP 7.4+.

Compatibilità con plugin di cache (escludere checkout e AJAX).

Roadmap

Tariffe stagionali e dinamiche.

QR code biglietti e coupon partner.

Recensioni post-esperienza.

Gestione drag&drop disponibilità in calendario.
