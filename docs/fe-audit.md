# Frontend Audit - Inventario Componenti Esperienze

## Shortcodes / Blocchi Esistenti

| Componente | Status | File | Funzione | Hook |
|-----------|---------|-------|----------|------|
| `[wcefp_experiences]` | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:39` | `experiences_catalog_shortcode()` | `init` |
| `[wcefp_events]` | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:38` | `events_list_shortcode()` | `init` |
| `[wcefp_event]` | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:40` | `single_event_shortcode()` | `init` |
| `[wcefp_booking_form]` | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:41` | `booking_form_shortcode()` | `init` |
| `[wcefp_google_reviews]` | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:53` | `google_reviews_shortcode()` | `init` |
| Gutenberg "Catalogo Esperienze" | ✅ **Presente** | `includes/Features/DataIntegration/GutenbergManager.php:112` | `render_experiences_catalog_block()` | `init` |
| Gutenberg "Booking Widget" | ✅ **Presente** | `includes/Features/DataIntegration/GutenbergManager.php` | `render_booking_form_block()` | `init` |

## Template Frontend

| Template | Status | File | Descrizione |
|----------|---------|-------|-------------|
| Experience Card v2 | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:1085` | `render_experience_card()` con hero image, trust badges, rating |
| Filtri Catalogo | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:299` | Filtri search, categoria, prezzo con UX |
| Griglia Esperienze | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:334` | Layout grid responsive 1-4 colonne |
| Mappa Placeholder | 🔄 **Parziale** | `includes/Frontend/ShortcodeManager.php:327` | Solo placeholder, manca Google Maps API |

## CSS/JS e Asset

| Asset | Status | File | Descrizione |
|-------|---------|-------|-------------|
| Experiences Catalog CSS | ✅ **Presente** | `assets/css/experiences-catalog.css` | 490+ righe, responsive, GYG-style |
| Experiences Catalog JS | ✅ **Presente** | `assets/js/experiences-catalog.js` | 500+ righe, filtri interattivi, AJAX |
| Conditional Loading | ✅ **Presente** | `includes/Core/Assets/AssetManager.php` | Enqueue solo quando shortcode presente |
| Blocks Editor CSS | ✅ **Presente** | `assets/css/blocks/editor.css` | Stili editor Gutenberg |
| Blocks Editor JS | ✅ **Presente** | `assets/js/blocks/editor.js` | Funzionalità editor Gutenberg |

## Hook WooCommerce & Filtri Loop

| Hook/Filtro | Status | File | Funzione |
|-------------|---------|-------|----------|
| `pre_get_posts` (Shop) | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:45` | `filter_shop_archives()` |
| `pre_get_posts` (Search) | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:48` | `filter_search_results()` |
| `pre_get_posts` (Feeds) | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:51` | `filter_feeds()` |
| `wp_sitemaps_posts_query_args` | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:54` | `filter_sitemap_args()` |
| `rest_product_query` | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:57` | `filter_rest_api_query()` |
| `woocommerce_output_related_products_args` | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:63` | `filter_related_products_args()` |
| `woocommerce_cart_crosssell_ids` | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:66` | `filter_crosssell_ids()` |

## Funzionalità Trust & Social Proof

| Funzionalità | Status | File | Implementazione |
|-------------|---------|-------|----------------|
| Trust Badges | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:1200` | `get_experience_trust_badges()` |
| Best Seller Badge | ✅ **Presente** | Meta: `_wcefp_is_best_seller` | Badge "🏆 Best Seller" |
| Cancellazione Gratuita | ✅ **Presente** | Meta: `_wcefp_free_cancellation` | Badge "✅ Cancellazione gratuita" |
| Conferma Immediata | ✅ **Presente** | Meta: `_wcefp_instant_confirmation` | Badge "⚡ Conferma immediata" |
| Posti Limitati | ✅ **Presente** | Meta: `_stock`, `_stock_status` | Badge "🔥 Solo X posti rimasti" |
| Rating WooCommerce | ✅ **Presente** | `includes/Frontend/ShortcodeManager.php:1104` | Rating stelle + count recensioni |
| Google Reviews | 🔄 **Parziale** | `includes/Frontend/ShortcodeManager.php:1007` | API presente, manca UI completa |

## API & REST Endpoint

| Endpoint | Status | File | Funzione |
|----------|---------|-------|----------|
| `/wcefp/v1/experiences` | ✅ **Presente** | `includes/Features/DataIntegration/GutenbergManager.php:269` | `get_experiences_for_block()` |
| Google Places API | 🔄 **Parziale** | `includes/Frontend/ShortcodeManager.php:1017` | `get_google_reviews()` implementato |

## Impostazioni Admin

| Impostazione | Status | File | Hook |
|-------------|---------|-------|------|
| Gating WooCommerce | ✅ **Presente** | `includes/Frontend/WooCommerceArchiveFilter.php:72` | `register_admin_settings()` |
| Hide from Shop | ✅ **Presente** | Setting: `wcefp_hide_from_shop` | Default: ON |
| Hide from Search | ✅ **Presente** | Setting: `wcefp_hide_from_search` | Default: ON |
| Hide from Feeds | ✅ **Presente** | Setting: `wcefp_hide_from_feeds` | Default: ON |
| Hide from Sitemaps | ✅ **Presente** | Setting: `wcefp_hide_from_sitemaps` | Default: ON |
| Hide from REST API | ✅ **Presente** | Setting: `wcefp_hide_from_rest` | Default: ON |
| Redirect Single Product | ✅ **Presente** | Setting: `wcefp_redirect_single_product` | Default: OFF |

## Cosa Manca (da Implementare)

| Componente | Status | Priorità | Note |
|-----------|---------|----------|------|
| Google Place ID in Meeting Point | ❌ **Mancante** | Alta | Campo `google_place_id` in CPT |
| GoogleReviewsService Class | ❌ **Mancante** | Alta | `get_rating_summary()`, `get_recent_reviews()` |
| Schema.org Product/Event | ❌ **Mancante** | Media | Markup strutturato per SEO |
| Mappa Google Maps | ❌ **Mancante** | Media | Integrazione API Maps con marker |
| Analytics GA4 | ❌ **Mancante** | Media | Eventi `view_item_list`, `select_item`, etc. |
| Accessibility ARIA | 🔄 **Parziale** | Media | Migliorare aria-live, focus, keyboard nav |
| i18n Completa | 🔄 **Parziale** | Bassa | Tradurre tutte le stringhe |
| E2E Testing | ❌ **Mancante** | Bassa | Test catalogo → scheda → prenotazione |

---

**Riepilogo Stato:**
- ✅ **Presente**: 20 componenti core implementati
- 🔄 **Parziale**: 4 componenti da completare  
- ❌ **Mancante**: 8 componenti da sviluppare

Il sistema base è funzionale con catalogo marketplace-style, gating WooCommerce completo, e trust badges. Mancano principalmente Google Reviews avanzate, schema.org, e analytics.