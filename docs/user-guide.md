# WCEventsFP - User Guide v2

> **Plugin Version**: 2.2.0+  
> **WordPress**: 5.0+ required (6.0+ recommended)
> **WooCommerce**: 5.0+ required (7.0+ recommended)
> **PHP**: 7.4+ required (8.0+ recommended)

---

## 📚 Table of Contents

1. [Quick Start](#-quick-start)
2. [Installation](#-installation)
3. [Initial Setup](#-initial-setup)
4. [Event/Experience Product Editor v2](#-evenexperience-product-editor-v2)
5. [Advanced Scheduling System](#-advanced-scheduling-system)
6. [Dynamic Pricing & Tickets](#-dynamic-pricing--tickets)
7. [Capacity Management](#-capacity-management)
8. [Extras & Add-ons](#-extras--add-ons)
9. [Meeting Points System](#-meeting-points-system)
10. [Policies & Notifications](#-policies--notifications)
11. [Frontend Booking Widget v2](#-frontend-booking-widget-v2)
12. [Managing Bookings](#-managing-bookings)
13. [Analytics & Reports](#-analytics--reports)
14. [Integrations & Compatibility](#-integrations--compatibility)
15. [Performance & Optimization](#-performance--optimization)
16. [Troubleshooting](#-troubleshooting)
17. [FAQ](#-frequently-asked-questions)

---

## 🚀 Quick Start

### What is WCEventsFP v2?
WCEventsFP v2 is a complete reimplementation that transforms your WordPress site into an enterprise-level booking platform. It provides advanced scheduling, dynamic pricing, and professional customer experience features that compete directly with industry leaders like Bokun, RegionDo, and GetYourGuide.

### New v2 Features
- **🎯 Enterprise Product Editor** - Tabbed interface with dedicated sections for each aspect
- **📅 Advanced Scheduling** - Multiple recurrence patterns, timezone support, exception handling  
- **🎫 Dynamic Pricing** - Early-bird, seasonal, demand-based pricing with visual badges
- **👥 Capacity Management** - TTL-based stock holds with race condition protection
- **🎁 Flexible Extras** - Product-specific and reusable add-ons with stock management
- **📍 Meeting Points** - Geographic location management with geocoding
- **📧 Email Automation** - Professional templates with scheduled reminders
- **🏪 Professional Booking Widget** - Complete customer interface with real-time interactions
- **🚀 Performance Optimizations** - Conditional asset loading and advanced caching

### 5-Minute Setup v2
1. Install/update WCEventsFP to v2.2.0+
2. Run the database migration (automatic)
3. Create your first Event/Experience product using the new tabbed editor
4. Configure scheduling patterns and pricing rules
5. Add the booking widget to pages using `[wcefp_booking]` shortcode
6. Start accepting bookings with the professional customer experience!

---

## 💾 Installation

### System Requirements v2
- **WordPress**: 5.0+ (6.0+ recommended)
- **WooCommerce**: 5.0+ (7.0+ recommended)
- **PHP**: 8.0 or higher (8.2+ recommended)
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Memory**: 256MB minimum (512MB recommended)
- **Disk Space**: 50MB for plugin files

### Installation Methods

#### Method 1: WordPress Admin (Recommended)
1. Download the plugin ZIP from your purchase confirmation
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the `wceventsfp.zip` file
4. Click **Install Now** and then **Activate**

#### Method 2: FTP Upload
1. Extract the plugin ZIP file
2. Upload the `wceventsfp` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin and activate WCEventsFP

#### Method 3: WP-CLI
```bash
wp plugin install wceventsfp.zip --activate
```

### Post-Installation Verification
After activation, you should see:
- ✅ WCEventsFP menu in WordPress admin
- ✅ No error messages or warnings
- ✅ Setup wizard available
- ✅ Plugin version 2.1.4+ confirmed

---

## ⚙️ Initial Setup

### Setup Wizard
WCEventsFP includes a setup wizard to get you started quickly:

1. **Welcome Screen** - Overview and requirements check
2. **Basic Settings** - Currency, timezone, default settings
3. **Payment Methods** - Configure WooCommerce payment gateways
4. **Email Settings** - Setup automated emails and notifications
5. **Integration Options** - Connect external services (optional)
6. **Complete** - Review settings and finish setup

### Essential Settings

#### General Settings (`WCEventsFP → Settings → General`)
- **Default Event Duration**: Set standard event length
- **Booking Time Limit**: How long before event starts can customers book
- **Capacity Management**: Enable/disable capacity tracking
- **Time Zone**: Ensure matches your business location

#### Payment Settings (`WooCommerce → Settings → Payments`)
WCEventsFP integrates with all WooCommerce payment methods:
- **Credit Cards**: Stripe, PayPal, Square
- **Digital Wallets**: PayPal Express, Apple Pay, Google Pay  
- **Bank Transfers**: Direct bank transfer, check payments
- **Buy Now Pay Later**: Klarna, Afterpay (if supported in your region)

#### Email Notifications (`WCEventsFP → Settings → Emails`)
- **Booking Confirmation**: Sent when booking is completed
- **Event Reminders**: Automatic reminders before events
- **Cancellation Notices**: When bookings are cancelled
- **Admin Notifications**: New booking alerts for administrators

---

## 🎯 Creating Events

### Event Types
WCEventsFP supports various event types:
- **Single Events** - One-time events with specific date/time
- **Recurring Events** - Weekly, monthly, or custom patterns
- **Multi-session Events** - Events spanning multiple dates
- **Open-ended Bookings** - Services available anytime

### Creating Your First Event

#### Step 1: Basic Event Information
1. Go to **WCEventsFP → Events → Add New**
2. Enter **Event Title** (e.g., "Wine Tasting Experience")
3. Write **Event Description** with details about what's included
4. Set **Event Category** and **Tags** for organization

#### Step 2: Scheduling & Capacity
- **Event Date & Time**: Set when the event occurs
- **Duration**: How long the event lasts
- **Capacity**: Maximum number of participants
- **Minimum Bookings**: Optional minimum to confirm event

#### Step 3: Pricing Configuration
- **Base Price**: Standard ticket price
- **Group Discounts**: Reduced rates for multiple participants
- **Early Bird Pricing**: Time-limited discounts
- **Dynamic Pricing**: Seasonal or demand-based adjustments

#### Step 4: Additional Options
- **Location Details**: Address, meeting point, accessibility info
- **What's Included**: Equipment, meals, transportation
- **Requirements**: Age limits, physical requirements, what to bring
- **Cancellation Policy**: Refund terms and conditions

### Advanced Event Features

#### Recurring Events
Create events that repeat automatically:
```
Weekly Wine Tasting:
- Every Saturday at 3:00 PM
- Generate 12 weeks in advance
- Capacity: 20 people per session
- Same pricing structure for all dates
```

#### Event Variations
Offer different options within the same event:
- **Standard Experience**: Basic package
- **Premium Experience**: Includes extras
- **VIP Experience**: Full premium treatment

#### Resource Management
Assign resources to events:
- **Guides/Staff**: Assign specific team members
- **Equipment**: Track availability of items
- **Venues**: Manage multiple locations

---

## 🎨 Frontend v2 - Marketplace Experience (NEW)

Transform your website into a professional booking platform with GetYourGuide-style experiences catalog and individual experience pages.

### 🗂️ Experiences Catalog Shortcode

Display your experiences in a filterable, searchable catalog with professional marketplace styling.

#### Basic Usage
```html
[wcefp_experiences]
```

#### Advanced Configuration
```html
[wcefp_experiences 
    filters="location,duration,price,rating,date,category" 
    view="grid" 
    columns="3" 
    limit="12" 
    show_map="no"]
```

#### Available Parameters
| Parameter | Options | Default | Description |
|-----------|---------|---------|-------------|
| `filters` | Comma-separated list | `location,duration,price,rating,category` | Which filters to show |
| `view`/`layout` | `grid`, `list`, `masonry` | `grid` | Display layout |
| `columns` | `1`, `2`, `3`, `4` | `3` | Columns in grid mode |
| `limit`/`per_page` | Number | `12` | Experiences per page |
| `show_filters` | `yes`, `no` | `yes` | Show filter bar |
| `show_map`/`map` | `yes`/`on`, `no`/`off` | `no` | Display map (requires Google Maps API) |
| `orderby` | `date`, `popularity`, `rating`, `price`, `title` | `date` | Default sorting |
| `order` | `ASC`, `DESC` | `DESC` | Sort direction |
| `category` | Category slug | - | Filter by specific category |
| `ajax` | `yes`, `no` | `yes` | Enable AJAX pagination |
| `skeleton` | `yes`, `no` | `yes` | Show loading animation |

#### Filter Types Available
- **🔍 Search** - Text search across titles and descriptions
- **📂 Category** - Product categories dropdown
- **📍 Location** - Meeting point locations
- **⏰ Duration** - Time ranges (0-2h, 2-4h, 4-8h, 8h+)
- **💰 Price** - Price ranges with currency symbols
- **⭐ Rating** - Star rating minimums (2+, 3+, 4+)
- **📅 Date** - Available date selection

### 📄 Individual Experience Page

Create dedicated landing pages for each experience with complete booking functionality.

#### Complete Experience Page
```html
[wcefp_experience_page_v2 
    show_hero="yes" 
    show_gallery="yes"
    show_booking_widget="yes" 
    show_reviews="yes" 
    show_faq="yes" 
    show_schema="yes"]
```

#### Page Sections Available
- **🦸 Hero Section** - Title, rating, trust badges, social proof
- **🖼️ Gallery Slider** - Image carousel with thumbnails
- **✨ Highlights** - Key selling points with checkmarks
- **📝 Description** - Full product description
- **✅ Included/Excluded** - What's covered and what's not
- **🗺️ Itinerary** - Step-by-step timeline
- **📍 Meeting Point** - Location with map integration
- **💳 Booking Widget** - Sticky sidebar booking form
- **⭐ Reviews** - Combined WooCommerce + Google reviews
- **❓ FAQ** - Expandable questions and answers
- **📜 Policies** - Cancellation and terms
- **🏷️ Schema.org** - Structured data for SEO

### 🧩 Gutenberg Block

Use the visual block editor for easy catalog placement.

#### Using the Block
1. Edit your page/post in WordPress
2. Add new block → WCEventsFP → **Catalogo Esperienze**
3. Configure options in the block sidebar
4. See live preview in the editor
5. Publish the page

#### Block Features
- 📱 **Visual preview** in editor
- ⚙️ **Settings panel** with all shortcode options
- 📊 **Real-time data** from your experiences
- 🎨 **Style customization** options

### 🎯 Trust Elements Integration

All Frontend v2 components automatically display trust elements:

- **🏆 Best Seller** badges on popular experiences
- **✅ Free Cancellation** indicators
- **⚡ Instant Confirmation** badges
- **🔥 Limited Availability** warnings (only when genuine)
- **👥 Social Proof** ("X people booked yesterday" - real data only)
- **⭐ Google Reviews** integration with star ratings

### 📊 Analytics Integration

Frontend v2 includes comprehensive GA4 enhanced ecommerce tracking:

- **`view_item_list`** - Catalog page views
- **`select_item`** - Experience clicks with position data
- **`view_item`** - Individual experience page views
- **`add_to_cart`** - Booking button clicks
- **`begin_checkout`** - Checkout process starts

All events include proper ecommerce data (currency, value, items array) for accurate conversion tracking.

---

## 📅 Managing Bookings

### Booking Workflow
Understanding the complete booking process:

1. **Customer Selection**: Customer chooses event and options
2. **Information Gathering**: Contact details, special requirements
3. **Payment Processing**: Secure payment via WooCommerce
4. **Confirmation**: Automatic confirmation email sent
5. **Management**: Admin tools for tracking and modifications

### Booking Administration

#### Bookings Dashboard (`WCEventsFP → Bookings`)
Central hub for all booking management:
- **Recent Bookings**: Latest reservations
- **Upcoming Events**: Next events with booking details
- **Capacity Overview**: How full your events are
- **Quick Actions**: Common management tasks

#### Individual Booking Management
For each booking you can:
- **View Details**: Complete customer and event information
- **Modify Booking**: Change dates, participant count, options
- **Process Refunds**: Handle cancellations and refunds
- **Send Messages**: Communicate directly with customers
- **Add Notes**: Internal notes for staff

#### Bulk Operations
Handle multiple bookings simultaneously:
- **Export Data**: CSV export for external processing
- **Send Messages**: Mass communication to participants
- **Status Updates**: Bulk status changes
- **Reporting**: Generate reports for specific periods

### Customer Communication

#### Automated Emails
Pre-configured email templates:
- **Booking Confirmation**: Immediate confirmation with details
- **Event Reminders**: 24 hours, 1 week before event
- **Cancellation**: Confirmation of cancellation and refund status
- **Follow-up**: Post-event feedback requests

#### Manual Communication
When needed, send custom messages:
- **Event Updates**: Weather changes, location updates
- **Special Offers**: Upsells or related events
- **Personal Service**: Address specific customer needs

---

## 📊 Analytics & Reports

### Dashboard Overview
Get instant insights from your booking business:

#### Key Performance Indicators (KPIs)
- **Total Bookings**: Number of reservations this month
- **Revenue**: Total and average booking values
- **Occupancy Rate**: How full your events are running
- **Customer Satisfaction**: Based on feedback and reviews

#### Visual Analytics
- **Booking Trends**: Line charts showing booking patterns
- **Event Performance**: Which events are most popular
- **Revenue Breakdown**: Income by event type, month, etc.
- **Customer Demographics**: Age, location, repeat customers

### Detailed Reports

#### Financial Reports
- **Revenue Report**: Income by time period, event, or category
- **Tax Report**: Tax collected for compliance
- **Refund Report**: Cancellations and refund tracking
- **Commission Report**: For multi-vendor setups

#### Operational Reports  
- **Event Utilization**: Capacity vs. actual bookings
- **Staff Performance**: If using staff assignment features
- **Customer Behavior**: Booking patterns and preferences
- **Marketing Effectiveness**: Conversion rates by source

#### Exporting Data
All reports can be exported in multiple formats:
- **PDF**: Professional reports for stakeholders
- **CSV**: Data import into Excel or accounting software
- **JSON**: Technical integration with other systems

---

## 🔌 Integrations

### Payment Gateways
WCEventsFP works with all WooCommerce payment methods:

#### Credit Card Processing
- **Stripe**: Global payment processing with advanced features
- **PayPal**: Worldwide acceptance with buyer protection
- **Square**: In-person and online payments
- **Authorize.net**: Enterprise-grade payment processing

#### Alternative Payment Methods
- **Bank Transfer**: Direct bank deposits
- **Check Payments**: Hold bookings for check clearance
- **Cash on Delivery**: For in-person payment events

### Email Marketing

#### Brevo (Sendinblue) Integration
Automated email marketing and transactional emails:
- **List Segmentation**: Automatic customer categorization
- **Automated Campaigns**: Welcome series, event reminders
- **Transactional Emails**: Booking confirmations, receipts
- **Analytics**: Email performance tracking

#### Configuration Steps:
1. Go to **WCEventsFP → Settings → Integrations**
2. Enter your Brevo API key
3. Configure list settings and automation rules
4. Test the connection

### Analytics Integration

#### Google Analytics 4
Track booking performance with enhanced ecommerce:
- **Event Tracking**: View events, add to cart, purchases
- **Conversion Funnels**: See where customers drop off
- **Revenue Attribution**: Which marketing drives bookings
- **Custom Dimensions**: Event-specific data tracking

### 🌟 Google Reviews Integration (NEW in v2.2)

Display authentic Google Reviews on your experience pages to build trust and improve conversions.

#### Setup Google Places API
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable **Places API**
4. Create credentials (API Key) with Places API access
5. Configure API Key restrictions (recommended)

#### Configure in WordPress Admin
1. Navigate to **WCEventsFP → Settings → Integrations**
2. Scroll to **Google Reviews** section
3. Enter your **Google Places API Key**
4. Save settings

#### Add Place ID to Meeting Points
1. Go to **Meeting Points → Edit** (or create new)
2. Find **Google Place ID** field
3. Enter your business Place ID ([How to find Place ID](https://developers.google.com/maps/documentation/places/web-service/place-id))
4. Click **Test Place ID** to verify connection
5. Save meeting point

#### Display Reviews on Experience Pages
Reviews automatically display when:
- Meeting Point has valid Place ID
- Experience is linked to that Meeting Point
- Google Places API key is configured

**Features:**
- ⭐ **Aggregate rating** with star display
- 👤 **Recent reviews** with reviewer photos
- 🔄 **Smart caching** (rating: 12 hours, reviews: 6 hours)
- 📱 **Mobile responsive** design
- 🎨 **Tab system** (WooCommerce reviews + Google reviews)

#### Schema.org Integration
Google Reviews data automatically enhances your SEO with:
- `AggregateRating` markup
- `Review` objects with author and rating
- Enhanced search result snippets

### 🛡️ Trust Elements & Social Proof (NEW in v2.2)

Build credibility with ethical trust signals that boost conversions without misleading customers.

#### Available Trust Elements
- **🏆 Best Seller Badge** - Based on actual sales data
- **✅ Free Cancellation** - Configurable cancellation policy
- **⚡ Instant Confirmation** - Immediate booking confirmation
- **🔒 Secure Payment** - SSL and payment security indicators
- **💰 Money Back Guarantee** - Satisfaction guarantee display
- **🔥 Limited Availability** - Only when genuinely limited (stock <10)
- **👥 Social Proof** - Real booking counts ("X people booked yesterday")
- **🎯 Authority Badges** - Certifications and satisfied customer counts

#### Configuration
1. Go to **Products → Edit Experience**
2. Scroll to **Experience Details** meta box
3. Configure individual trust elements:
   - Enable **Best Seller** (if applicable)
   - Enable **Free Cancellation** 
   - Enable **Instant Confirmation**
   - Add **Certifications** list

#### Global Trust Settings
1. Navigate to **WCEventsFP → Settings → Trust & Social Proof**
2. Configure global defaults:
   - **Secure Payment** messaging
   - **Money Back Guarantee** policy
   - **Social Proof** display rules

#### Ethical Guidelines
WCEventsFP follows ethical practices:
- ✅ **Real data only** - Social proof based on actual bookings
- ✅ **Genuine scarcity** - Limited availability only when stock truly low
- ✅ **Honest claims** - All trust elements must be factually accurate
- ✅ **Graceful fallbacks** - No display when data unavailable
- ❌ **No fake urgency** - No artificial countdown timers
- ❌ **No false claims** - No exaggerated social proof

#### Meta Pixel (Facebook)
Optimize Facebook and Instagram advertising:
- **Conversion Tracking**: Track bookings from social ads
- **Audience Creation**: Retarget website visitors
- **Lookalike Audiences**: Find similar customers
- **Event Optimization**: Improve ad performance

### Calendar Integration

#### iCal Export/Import
- **Calendar Feeds**: Subscribe to event calendars
- **Import Events**: Bulk import from external calendars
- **Sync with Google Calendar**: Two-way synchronization
- **Outlook Integration**: Corporate calendar compatibility

### Distribution Channels (Advanced)

#### Online Travel Agencies (OTAs)
Distribute your events through major booking platforms:
- **Booking.com**: Experiences marketplace
- **Expedia**: Tours and activities
- **GetYourGuide**: Global experiences platform
- **Viator**: TripAdvisor's booking platform

*Note: OTA integrations require additional setup and may involve commission fees.*

---

## 🛠️ Troubleshooting

### Common Issues

#### Installation Problems

**Plugin won't activate**
- Check PHP version (8.0+ required)
- Verify WordPress version (6.5+ required)
- Ensure WooCommerce is installed and active
- Check file permissions (644 for files, 755 for directories)

**White screen after activation**
- Enable WordPress debug mode
- Check error logs in `/wp-content/debug.log`
- Run diagnostic tool: `tools/diagnostics/wcefp-health-check.php`

#### Booking Issues

**Customers can't complete bookings**
- Verify payment gateway configuration
- Check SSL certificate is valid
- Ensure cart and checkout pages exist
- Test booking process as a customer

**Email notifications not sending**
- Verify WordPress email configuration
- Check spam folders
- Test with WP Mail SMTP plugin
- Review email template settings

#### Performance Issues

**Slow loading pages**
- Enable WordPress object caching
- Optimize database (WP-Optimize plugin recommended)
- Check for plugin conflicts
- Review hosting resources

**High memory usage**
- Increase PHP memory limit (512MB recommended)
- Enable optimized autoloading
- Check for infinite loops in custom code

### Debug Mode

Enable debug mode for troubleshooting:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WCEFP_DEBUG', true);
```

### Getting Help

#### Self-Service Resources
1. **Plugin Documentation**: Complete guides and tutorials
2. **Video Tutorials**: Step-by-step setup and usage
3. **Knowledge Base**: Common questions and solutions
4. **Community Forum**: User discussions and tips

#### Professional Support
- **Email Support**: Technical assistance via support portal
- **Priority Support**: Faster response for premium customers
- **Custom Development**: Tailored modifications and integrations
- **Training Sessions**: One-on-one setup and training

---

## ❓ Frequently Asked Questions

### General Questions

**Q: Can I use WCEventsFP without WooCommerce?**
A: No, WCEventsFP requires WooCommerce for payment processing and order management. WooCommerce is free and integrates seamlessly.

**Q: Is there a limit to the number of events I can create?**
A: No, there are no artificial limits. You can create unlimited events, bookings, and manage unlimited customers.

**Q: Can I customize the booking forms and emails?**
A: Yes, all templates are customizable. You can modify booking forms, email templates, and styling to match your brand.

### Technical Questions

**Q: Is WCEventsFP compatible with my theme?**
A: WCEventsFP is designed to work with any well-coded WordPress theme. It uses standard WordPress and WooCommerce styling conventions.

**Q: Can I translate WCEventsFP to other languages?**
A: Yes, WCEventsFP is fully translatable using standard WordPress internationalization. Translation files are included for major languages.

**Q: Does WCEventsFP work with caching plugins?**
A: Yes, but you may need to exclude booking and checkout pages from caching. Popular caching plugins have WooCommerce compatibility modes.

### Business Questions

**Q: Can I offer group discounts or promotional codes?**
A: Yes, you can create group pricing tiers and WooCommerce coupon codes work seamlessly with event bookings.

**Q: How do refunds and cancellations work?**
A: You can configure automatic refund rules or handle refunds manually. The system tracks cancellation reasons and processes refunds through WooCommerce.

**Q: Can I manage multiple venues or staff members?**
A: Yes, WCEventsFP includes resource management features for staff assignment, equipment tracking, and multi-venue operations.

### Pricing & Licensing

**Q: Are there ongoing fees or transaction fees?**
A: WCEventsFP is a one-time purchase with no ongoing fees. Payment processing fees depend on your chosen payment gateway.

**Q: Can I use WCEventsFP on multiple websites?**
A: The license terms depend on your purchase. Check your license agreement for multi-site usage rights.

**Q: Do I get updates and support?**
A: Yes, purchases include 1 year of updates and support. Extended support can be purchased separately.

---

## 🎓 Best Practices

### Event Management
1. **Clear Descriptions**: Write detailed, engaging event descriptions
2. **High-Quality Images**: Use professional photos to showcase experiences
3. **Accurate Capacity**: Set realistic capacity limits to avoid overbooking
4. **Regular Updates**: Keep event information current and accurate

### Customer Experience
1. **Simple Booking Process**: Minimize form fields and steps
2. **Clear Communication**: Set expectations about what's included
3. **Responsive Support**: Respond quickly to customer inquiries
4. **Follow-up**: Send post-event surveys and thank you messages

### Business Operations
1. **Regular Backups**: Always backup your website and database
2. **Monitor Performance**: Track KPIs and booking trends
3. **Update Content**: Keep events fresh and seasonal
4. **Security**: Keep WordPress, plugins, and themes updated

---

*For additional support, visit our [support portal](mailto:support@example.com) or check the [documentation website](https://example.com/docs).*

---

**Last Updated**: August 24, 2024  
**Plugin Version**: 2.1.4+  
**Documentation Version**: 1.0