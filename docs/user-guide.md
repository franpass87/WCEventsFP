# WCEventsFP - User Guide

> **Plugin Version**: 2.1.4+  
> **WordPress**: 6.5+ required  
> **WooCommerce**: Latest stable required  
> **PHP**: 8.0+ required

---

## 📚 Table of Contents

1. [Quick Start](#-quick-start)
2. [Installation](#-installation)
3. [Initial Setup](#-initial-setup)
4. [Creating Events](#-creating-events)
5. [Managing Bookings](#-managing-bookings)
6. [Analytics & Reports](#-analytics--reports)
7. [Integrations](#-integrations)
8. [Troubleshooting](#-troubleshooting)
9. [FAQ](#-frequently-asked-questions)

---

## 🚀 Quick Start

### What is WCEventsFP?
WCEventsFP is an enterprise-grade booking platform for WordPress and WooCommerce. It transforms your online store into a powerful booking system for events, experiences, tours, and services.

### Key Features
- **Event Management** - Create and manage recurring events with capacity control
- **Booking System** - Complete booking workflow with payment processing
- **Analytics Dashboard** - KPI tracking and performance metrics
- **Multi-channel Distribution** - Integration ready for Booking.com, Expedia, GetYourGuide
- **Advanced Features** - Vouchers, calendar integration, email automation
- **Developer-Friendly** - Comprehensive API and customization hooks

### 5-Minute Setup
1. Install and activate WCEventsFP plugin
2. Complete the setup wizard
3. Create your first event
4. Configure payment methods
5. Start accepting bookings!

---

## 💾 Installation

### System Requirements
- **WordPress**: 6.5 or higher
- **WooCommerce**: Latest stable version
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