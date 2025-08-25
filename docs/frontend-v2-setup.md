# Frontend v2 Quick Setup Guide

## 🚀 Getting Started with WCEventsFP Frontend v2

### 1. Basic Setup (5 minutes)

#### Enable Archive Filtering
1. Go to **WordPress Admin > WCEventsFP Settings**
2. Navigate to **Archive & Search Filtering** section
3. Enable **"Hide from Shop Archives"** ✅
4. Enable **"Hide from Search Results"** ✅
5. Save settings

#### Configure Google Reviews (Optional)
1. Get a Google Places API Key from [Google Cloud Console](https://console.cloud.google.com)
2. Enable **Places API** for your project
3. In **WCEventsFP Settings > Google Integration**:
   - Enter your **API Key**
   - Add your **Default Place ID** (find it using [Place ID Finder](https://developers.google.com/maps/documentation/places/web-service/place-id))
4. Save settings

### 2. Using the New Booking Widget v2

#### Replace Existing Shortcode
**Old (v1):**
```
[wcefp_booking id="123"]
```

**New (v2):**
```
[wcefp_booking_widget_v2 id="123" layout="gyg-style" trust_nudges="moderate"]
```

#### Gutenberg Block
1. Add new block: **WCEventsFP > Booking Widget v2**
2. Select your product from dropdown
3. Configure layout and trust settings
4. Preview and publish

### 3. Trust Nudges Configuration

#### Recommended Settings for Ethical Implementation

**Navigate to: WCEventsFP Settings > Trust & Social Proof**

**Conservative Setup (Recommended):**
- Nudge Level: **Moderate**
- Show Availability Counter: ✅
- Show Recent Bookings: ✅  
- Show People Viewing: ❌ (disabled for ethical reasons)
- Best Seller Threshold: **10 bookings/month**

**Minimal Setup (Most Ethical):**
- Nudge Level: **Minimal**
- Show Availability Counter: ✅
- Show Recent Bookings: ❌
- Show People Viewing: ❌
- Only show policy badges (cancellation, confirmation, etc.)

### 4. Testing Your Setup

#### Checklist
- [ ] Create/edit a page with the new booking widget v2
- [ ] Test on mobile and desktop
- [ ] Verify events are hidden from shop page
- [ ] Check Google Reviews are loading (if configured)
- [ ] Test booking flow end-to-end
- [ ] Verify accessibility with screen reader

#### Debug Mode
Add to wp-config.php for troubleshooting:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 5. Customization Examples

#### Custom Color Scheme
```css
.wcefp-booking-widget-v2.custom-brand {
    --primary-color: #your-brand-color;
    --secondary-color: #your-secondary-color;
}

.wcefp-booking-widget-v2.custom-brand .wcefp-book-now-btn {
    background: var(--primary-color);
}
```

#### Hide Specific Trust Elements
```
[wcefp_booking_widget_v2 id="123" trust_nudges="minimal" show_social_proof="no"]
```

### 6. Migration Strategy

#### Phase 1: Parallel Testing
- Keep existing v1 widgets running
- Add v2 widgets to new/test pages
- Compare conversion rates and user feedback

#### Phase 2: Gradual Replacement  
- Replace v1 widgets on low-traffic pages first
- Monitor for any issues
- Update high-traffic pages last

#### Phase 3: Full Migration
- Replace all remaining v1 widgets
- Remove unused v1 customizations
- Clean up legacy CSS/JS

### 7. Common Configuration Patterns

#### Landing Page Style (Full Experience)
```
[wcefp_booking_widget_v2 
    id="123" 
    layout="gyg-style" 
    show_hero="yes"
    show_trust_badges="yes"
    show_google_reviews="yes"
    trust_nudges="moderate"
    color_scheme="default"]
```

#### Embedded Widget Style (In Content)
```
[wcefp_booking_widget_v2 
    id="123" 
    layout="compact" 
    show_hero="no"
    show_trust_badges="yes"
    trust_nudges="minimal"
    class="embedded-widget"]
```

#### Minimal Ethical Style
```
[wcefp_booking_widget_v2 
    id="123" 
    layout="minimal" 
    show_social_proof="no"
    trust_nudges="none"
    show_google_reviews="no"]
```

### 8. Performance Optimization

#### Recommended Caching Settings
- **Google Reviews Cache**: 4 hours (default)
- **Availability Cache**: 10 minutes
- **Trust Data Cache**: 30 minutes

#### WP Rocket / W3 Total Cache
Add these pages to cache exclusions:
- Booking confirmation pages
- Cart and checkout pages  
- User account pages

### 9. Accessibility Checklist

- [ ] All interactive elements are keyboard accessible
- [ ] Screen reader announcements work for dynamic content
- [ ] Color contrast meets WCAG AA standards
- [ ] Alt text provided for all images
- [ ] Form labels properly associated

### 10. SEO Optimization

#### Schema.org Markup
Frontend v2 automatically includes:
- Event structured data
- Offer markup with pricing
- Review markup from Google Reviews
- Location data for meeting points

#### Meta Tags
Ensure your experience pages have:
- Descriptive title tags
- Meta descriptions mentioning booking
- Open Graph tags for social sharing

## 🎯 Quick Wins

### Immediate Improvements
1. **Replace one shortcode** with v2 version - see instant UX improvement
2. **Enable archive filtering** - cleaner shop experience  
3. **Add Google Reviews** - instant social proof
4. **Configure trust nudges** - ethical conversion optimization

### Measuring Success
- Compare conversion rates before/after
- Monitor page load speeds
- Check accessibility scores  
- Gather user feedback

## 🆘 Need Help?

### Common Issues
1. **Widget not showing**: Check product ID and shortcode syntax
2. **Reviews not loading**: Verify API key and Place ID
3. **Styling issues**: Check for theme CSS conflicts
4. **Performance slow**: Review caching settings

### Support Resources
- Check `docs/frontend-v2-guide.md` for comprehensive documentation
- Enable debug mode for detailed error messages
- Test on staging site before production changes

---

**Remember**: Frontend v2 is designed to coexist with v1, so you can migrate gradually and test thoroughly. Start with one widget and expand from there!