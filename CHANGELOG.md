# 📜 Changelog – WCEventsFP

[2.0.1] – 2025-08-22

## Added

🛠️ **Enhanced Error Handling System**: Comprehensive error management with user-friendly messages, developer debugging tools, and detailed logging for better troubleshooting.

🌍 **Advanced Internationalization (i18n)**: Enhanced multi-language support for global markets with 10 supported locales, automatic translations, dynamic language switching, and locale-specific formatting for dates, prices, and numbers.

🔧 **Developer Debug Tools**: Advanced debugging utilities including performance monitoring, SQL query logging, system information display, admin bar integration, and real-time debug panel with tabbed interface.

📡 **Enhanced Webhook System**: Robust webhook management for third-party integrations with retry logic, queue processing, comprehensive event coverage (booking lifecycle, payments, reviews), signature verification, and detailed logging.

🧪 **Improved Testing Infrastructure**: Fixed JavaScript test framework with proper Jest configuration, jQuery mocking, and comprehensive test coverage for advanced features.

## Enhanced

🔍 **Error Logging**: Database-backed error logging with admin interface for reviewing and managing system errors.

🌐 **Global Market Ready**: Support for multiple currencies (USD, EUR, GBP, JPY, KRW, CNY, BRL), RTL languages, and locale-specific date/time formats.

⚡ **Performance Monitoring**: Real-time performance tracking with Core Web Vitals monitoring, memory usage tracking, and query performance analysis.

🔌 **API Integration**: Enhanced webhook system supports all major booking events with reliable delivery, retry mechanisms, and comprehensive error handling.

## Developer Experience

🎯 **Debug Panel**: Floating debug panel accessible via Alt+D with tabs for logs, performance metrics, system info, and database queries.

📊 **System Monitoring**: Real-time monitoring of memory usage, query counts, loading times, and system health metrics.

🧹 **Code Quality**: Improved test coverage, better error handling, and enhanced logging for easier debugging and maintenance.

## Fixed

✅ **JavaScript Tests**: Fixed test infrastructure issues and improved test reliability.

🔧 **Class Loading**: Proper loading order for new enhancement classes in main plugin file.

🌍 **Language Support**: Enhanced language detection and fallback mechanisms for better international user experience.

---

[1.8.1] – 2025-08-21

## Added

🎨 **Design Moderno e UX**: Interfaccia completamente ridisegnata con gradients moderni, animazioni fluide e micro-interazioni.

🔍 **Filtri Avanzati**: Sistema di ricerca e filtri in tempo reale con debounce, filtro per tipo evento/esperienza e fasce di prezzo.

🗺️ **Mappe Interactive**: Integrazione Leaflet con marker personalizzati, popup informativi e fallback per Google Maps.

⭐ **Sistema Recensioni**: Shortcode [wcefp_reviews] e [wcefp_testimonials] con rating stelle, avatars e slider automatico touch-friendly.

🎯 **Social Proof**: Indicatori dinamici di attività, badge di urgenza (Ultimi posti!, Popolare, Bestseller), contatori disponibilità.

📱 **Mobile Experience**: Design completamente responsive con touch gestures, interfaccia mobile-friendly e ottimizzazioni prestazioni.

🚀 **Widget Multi-Step**: Sistema di prenotazione a step multipli con progress indicator e validazione avanzata.

🔄 **Animazioni Avanzate**: Loading states, success animations, micro-interazioni e feedback visivo migliorato.

## Changed

Frontend CSS e JavaScript completamente riscritti per prestazioni e UX moderne.

Sistema di template cards migliorato con hover effects, gradient backgrounds e typography moderna.

Filtri e ricerca ottimizzati con debouncing e animazioni fluide.

Mobile responsiveness migliorata su tutti i componenti.

## Improved

Accessibilità migliorata con ARIA labels e supporto screen reader.

Performance ottimizzate con lazy loading immagini e caching intelligente.

Cross-browser compatibility migliorata.

---
[1.7.1] – 2025-08-19
Added

Widget di prenotazione con selezione quantità/toggle e calcolo dinamico del prezzo.
Shortcode aggiuntivi: [wcefp_booking_widget], [wcefp_redeem].
Sezione compatibilità e screenshot UI nella documentazione.
📜 Changelog – WCEventsFP
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
