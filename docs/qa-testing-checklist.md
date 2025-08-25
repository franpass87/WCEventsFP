# Frontend v2 QA & Testing Checklist

## 🧪 E2E Testing - Complete Booking Flow

### Prerequisites
- [ ] WooCommerce configured with products
- [ ] At least one experience product with `_wcefp_is_experience = 1`
- [ ] Google Places API key configured (optional)
- [ ] GA4/GTM configured for analytics testing (optional)

### Catalog Testing (`[wcefp_experiences]`)

#### Basic Functionality
- [ ] **Shortcode renders** without errors
- [ ] **Experience cards display** with image, title, price
- [ ] **Trust badges show** (if configured: best seller, free cancellation, instant confirmation)
- [ ] **Rating stars display** (if reviews exist)
- [ ] **Duration and location** show (if configured)

#### Filtering System
- [ ] **Search filter** works with debounced input
- [ ] **Category filter** filters correctly
- [ ] **Price filter** works with ranges
- [ ] **Rating filter** shows only rated experiences
- [ ] **Date filter** affects availability
- [ ] **Clear filters** resets all filters
- [ ] **Filtering updates URL** parameters

#### Sorting & Display
- [ ] **Sort by date** works (newest first)
- [ ] **Sort by popularity** works
- [ ] **Sort by rating** works (highest first)
- [ ] **Sort by price** works (low to high, high to low)
- [ ] **Layout switches** work (grid/list)
- [ ] **Column changes** work (1-4 columns)

#### Responsive & Performance
- [ ] **Mobile responsive** (single column, stacked filters)
- [ ] **Images lazy load** after first viewport
- [ ] **Skeleton loader** shows during AJAX
- [ ] **AJAX pagination** works (if enabled)
- [ ] **Loading states** provide feedback

### Experience Page v2 (`[wcefp_experience_page_v2]`)

#### Hero Section
- [ ] **Title, rating, trust badges** display correctly
- [ ] **Social proof** shows real data only
- [ ] **Hero meta** (duration, location, availability) accurate

#### Content Sections
- [ ] **Gallery slider** works with thumbnails
- [ ] **Highlights list** displays with checkmarks
- [ ] **What's included/excluded** sections clear
- [ ] **Itinerary timeline** displays steps
- [ ] **Meeting point** with address/instructions

#### Reviews Section
- [ ] **WooCommerce reviews** tab works
- [ ] **Google Reviews tab** works (if Place ID configured)
- [ ] **Rating summary** displays correctly
- [ ] **Review cards** show author, date, content

#### Booking Widget v2 (Sticky Sidebar)
- [ ] **Price display** matches WooCommerce price
- [ ] **Date picker** shows available dates
- [ ] **Quantity controls** work (+/- buttons)
- [ ] **Book Now button** enables after selections
- [ ] **Trust elements** display in widget
- [ ] **Widget stays sticky** on desktop
- [ ] **Widget anchors bottom** on mobile

### WooCommerce Gating

#### Archive Exclusion
- [ ] **Experiences hidden** from shop page
- [ ] **Experiences hidden** from category archives
- [ ] **Experiences hidden** from search results
- [ ] **Still accessible** via direct URL
- [ ] **Admin settings** work (toggle on/off)

#### SEO & Technical
- [ ] **Excluded from feeds** (RSS)
- [ ] **Excluded from sitemaps** (XML)
- [ ] **Excluded from REST API** product listings
- [ ] **Not in related products**
- [ ] **Not in cross-sells**

### Google Reviews Integration

#### Setup & Configuration  
- [ ] **Place ID field** saves in Meeting Point CPT
- [ ] **Test connection** button works
- [ ] **API key setting** in admin works
- [ ] **Error handling** shows proper messages

#### Display & Caching
- [ ] **Rating summary** displays (stars + count)
- [ ] **Recent reviews** show with photos
- [ ] **Caching works** (12h rating, 6h reviews)
- [ ] **Fallback graceful** without API key
- [ ] **Schema.org markup** includes Google rating

### Trust Elements & Social Proof

#### Ethical Implementation
- [ ] **Social proof** only shows real booking counts
- [ ] **Scarcity** only when stock genuinely limited (<10)
- [ ] **Trust badges** match actual product settings
- [ ] **No fake/misleading** claims displayed
- [ ] **Fallback graceful** when no data available

#### Configuration
- [ ] **Admin settings** control trust elements
- [ ] **Meta fields** control individual badges
- [ ] **Real bookings count** queries work
- [ ] **Stock integration** accurate

### Performance & Accessibility

#### Performance Metrics
- [ ] **Page load < 3s** (desktop)
- [ ] **Lighthouse score >90** (desktop)
- [ ] **Mobile load < 5s**
- [ ] **Images optimized** with lazy loading
- [ ] **CSS/JS conditional** loading works

#### Accessibility (WCAG AA)
- [ ] **Keyboard navigation** works throughout
- [ ] **Focus states** visible (3px outline)
- [ ] **Screen reader** text present
- [ ] **ARIA labels** on interactive elements
- [ ] **Color contrast** ≥4.5:1 ratio

#### Internationalization
- [ ] **Numbers localized** (format_i18n)
- [ ] **Currency symbols** dynamic
- [ ] **Plurals work** (_n() function)
- [ ] **Date formatting** follows locale

### Analytics & Tracking

#### GA4 Events (with Network Tab verification)
- [ ] **view_item_list** fires on catalog load
- [ ] **select_item** fires on experience click
- [ ] **view_item** fires on experience page load
- [ ] **add_to_cart** fires on booking button click
- [ ] **begin_checkout** fires on redirect
- [ ] **Enhanced ecommerce** data included (items, currency, value)

#### Configuration & Consent
- [ ] **Admin toggle** disables tracking when off
- [ ] **GTM DataLayer** pushes events
- [ ] **Console debugging** works in debug mode
- [ ] **Consent mode** compatible (if privacy plugin active)

### Error Handling & Edge Cases

#### Data Edge Cases
- [ ] **No experiences** found shows proper message
- [ ] **Missing images** don't break layout
- [ ] **Empty meta fields** handled gracefully
- [ ] **API failures** (Google) don't crash
- [ ] **Cache misses** handled properly

#### User Experience
- [ ] **Loading states** show during waits
- [ ] **Error messages** clear and helpful  
- [ ] **Fallback content** when features disabled
- [ ] **Mobile UX** maintains functionality
- [ ] **Cross-browser** compatibility (Chrome, Firefox, Safari, Edge)

## 🐛 Common Issues & Solutions

### Shortcode Not Working
1. Check if `_wcefp_is_experience = 1` on products
2. Verify WooCommerce products published
3. Check for PHP errors in debug log
4. Ensure assets loading (check Network tab)

### No Google Reviews
1. Verify Google Places API key configured
2. Check Place ID format and validity
3. Review API quotas and billing
4. Check transient cache clearing

### Analytics Not Tracking  
1. Verify GA4 enabled in admin settings
2. Check gtag library loaded
3. Review console for JavaScript errors
4. Test in incognito mode (avoid ad blockers)

### Performance Issues
1. Check image optimization and lazy loading
2. Review caching (page cache + transients)
3. Verify conditional asset loading
4. Check for N+1 query issues

---

## ✅ Testing Sign-Off

**Tested by**: ________________  
**Date**: ________________  
**Environment**: Production / Staging / Local  
**Browser(s)**: ________________  

**Overall Assessment**:
- [ ] ✅ Ready for production
- [ ] ⚠️ Minor issues noted (see notes)
- [ ] ❌ Major issues require fixes

**Notes**: _________________________________