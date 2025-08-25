## 🎯 Event/Experience Product Editor v2

The new v2 product editor features a comprehensive tabbed interface that organizes all event management features into logical sections.

### Product Types
- **Evento** - Traditional events (conferences, workshops, classes)
- **Esperienza** - Experience-based products (tours, activities, services)

### Tabbed Interface Overview

#### 📅 Scheduling Tab
Configure when and how often your events occur:

**Recurrence Patterns:**
- **Weekly**: Select specific days of the week (Mon, Tue, Wed, etc.)
- **Daily**: Events that occur every day within a date range
- **Monthly**: Events on specific dates of each month
- **Seasonal**: Events that occur during specific seasons/periods
- **Specific Dates**: Individual event dates with exact times

**Advanced Options:**
- **Timezone Support**: Full timezone handling with DST conversion
- **Booking Windows**: Control how far in advance bookings can be made
- **Auto-release Settings**: Automatically release unsold inventory
- **Exception Management**: Handle closures, holidays, and blackout dates

**Example Configuration:**
```
Recurrence: Weekly
Days: Monday, Wednesday, Friday  
Time Slots: 10:00 AM, 2:00 PM, 6:00 PM
Duration: 2 hours
Advance Booking: 30 days
Auto-release: 24 hours before
```

#### 🎫 Tickets & Pricing Tab
Create multiple ticket types with advanced pricing rules:

**Ticket Types:**
- Adult, Child, Student, Senior, Group, Private, etc.
- Custom ticket types with individual pricing
- Minimum/maximum quantities per order
- Individual capacity limits per ticket type

**Dynamic Pricing Rules:**
- **Early Bird**: Discounts for bookings made X days in advance
- **Last Minute**: Special rates for bookings made within Y hours
- **Seasonal Pricing**: Peak/low season adjustments with custom date ranges
- **Weekend/Weekday**: Different pricing for different days
- **Demand-based**: Automatic price adjustments based on booking velocity
- **Group Discounts**: Multi-tier discounts (5+ people, 10+ people, etc.)

**Visual Pricing System:**
- Automatic badges for deals ("Early Bird 20% Off!")
- Clear savings indicators ("Save €25")
- Dynamic price calculations in real-time

**Example Pricing Setup:**
```
Adult Ticket: €45 base price
- Early Bird (30+ days): €35 (-€10)
- Weekend Surcharge: +€5  
- Peak Season (July-August): +€10
- Group Discount (5+ people): -15%
```

#### 👥 Capacity Tab
Manage availability and prevent overbooking:

**Capacity Settings:**
- **Global Capacity**: Total participants per event occurrence
- **Per-ticket Limits**: Specific limits for each ticket type
- **Overbooking Buffer**: Allow slight overselling with automatic management
- **Stock Hold Duration**: How long cart items are reserved (default: 15 minutes)

**Advanced Features:**
- **TTL-based Holds**: Automatic expiration of reserved spots
- **Race Condition Protection**: Database-level locking for concurrent bookings
- **Automated Cleanup**: Background processes to release expired holds
- **Capacity Alerts**: Admin notifications when thresholds are reached

#### 🎁 Extras Tab
Create additional products and services:

**Extra Types:**
- **Per Person**: Price multiplied by number of participants
- **Per Order**: Fixed price regardless of group size
- **Optional/Required**: Control customer choice
- **Stock-managed**: Limited quantities with inventory tracking

**Reusable Extras:**
Create a library of extras that can be used across multiple products:
- Photography service
- Transportation
- Equipment rental  
- Meal upgrades
- Insurance coverage

**Example Extra Setup:**
```
Professional Photos: €25 per order (optional)
Equipment Rental: €15 per person (optional)
Lunch Package: €12 per person (required)
Insurance: €3 per person (optional)
```

#### 📍 Meeting Points Tab
Manage pickup/meeting locations:

**Meeting Point Features:**
- **Geographic Data**: Full address with latitude/longitude
- **Accessibility Information**: Wheelchair access, parking, facilities
- **Alternative Locations**: Backup meeting points for weather/conditions
- **Admin Interface**: Easy creation and management of reusable locations
- **Geocoding Integration**: Automatic coordinate lookup from addresses

**Location Management:**
- Create reusable meeting point library
- Per-event override capability
- Integration with mapping services
- Detailed instructions and notes

#### 📋 Policies Tab
Configure cancellation rules and customer communications:

**Policy Types:**
- **Cancellation Policy**: Time-based cancellation rules with refund percentages
- **Rescheduling Policy**: Rules for date changes and fees
- **Weather Policy**: Automatic handling for weather-dependent events
- **Health & Safety**: COVID-19 and health requirement information
- **Terms & Conditions**: Event-specific terms and legal information

**Email Template System:**
- **Confirmation Emails**: Branded booking confirmations with calendar files
- **Reminder Emails**: Automated 24h and 2h reminders
- **Follow-up Emails**: Post-event feedback requests and reviews
- **Custom Templates**: HTML email templates with dynamic content

---

## 🏪 Frontend Booking Widget v2

The new booking widget provides a complete, professional customer experience.

### Widget Implementation
Multiple ways to add the booking interface:

**Shortcode Usage:**
```
[wcefp_booking product_id="123"]
[wcefp_booking product_id="123" show_calendar="true"]
[wcefp_booking product_id="123" default_participants="2"]
```

**Gutenberg Block:**
- Native WordPress block with live preview
- Server-side rendering for performance
- Visual editor with all customization options

### Customer Experience Flow
Complete booking process with professional UI:

**Step 1: Date & Time Selection**
- Interactive calendar with availability indicators
- Time slot selection with capacity information
- Clear pricing display for each option
- Mobile-responsive calendar interface

**Step 2: Ticket Selection**  
- Visual ticket type cards with descriptions
- Quantity selectors with min/max validation
- Real-time price calculation with breakdown
- Group discount application and display

**Step 3: Extras Selection**
- Optional add-on services with clear pricing
- Per-person vs. per-order distinction
- Stock availability for limited extras
- Visual presentation with images/descriptions

**Step 4: Meeting Point Information**
- Map integration showing exact location
- Detailed instructions and accessibility info
- Contact information and alternative options
- Transportation and parking details

**Step 5: Booking Summary**
- Complete breakdown of all selections
- Final price with all discounts applied
- Terms and conditions acceptance
- Professional "Add to Cart" action

### Accessibility & Performance
Enterprise-grade technical implementation:

**Accessibility (WCAG Compliance):**
- Full keyboard navigation support
- Screen reader optimization  
- High contrast mode compatibility
- Focus management and ARIA labels

**Performance Features:**
- Lazy loading of non-critical components
- Conditional asset loading (only on pages with bookings)
- Optimized API calls with caching
- Mobile-first responsive design

**Error Handling:**
- Graceful degradation for JavaScript issues
- Clear error messages with resolution steps
- Automatic retry for temporary failures
- Loading states for all async operations

---

## 🔧 Performance & Optimization v2

Enterprise-grade performance with intelligent optimization.

### Asset Optimization
Conditional loading system that only loads what's needed:

**Frontend Optimization:**
- Assets only loaded on pages with booking widgets
- Combined and minified CSS/JS files
- Progressive enhancement approach
- Critical CSS inlining

**Admin Optimization:**  
- WCEFP assets only on relevant admin pages
- Lazy loading of complex components
- Optimized database queries with proper indexing
- Memory usage monitoring and optimization

### Caching System
Multi-layer caching for optimal performance:

**Query Caching:**
- Event availability cached for 15 minutes
- Pricing calculations cached per configuration
- Meeting point data cached for 1 hour
- Category/taxonomy data cached for 6 hours

**Object Caching:**
- WordPress object cache integration
- Custom cache groups for WCEFP data
- Automatic cache invalidation on updates
- Redis/Memcached support

### Database Optimization
Comprehensive database performance:

**Indexing Strategy:**
- Optimized indexes for all query patterns
- Composite indexes for complex queries
- Regular index maintenance and monitoring
- Query performance analysis tools

**Data Management:**
- Automatic cleanup of expired data
- Archive system for old bookings
- Optimized table structures
- Regular database maintenance

---

## 📞 Support & Advanced Help

### Getting Professional Support

**Documentation Resources:**
- Complete User Guide (this document)
- Developer API Documentation
- Video Tutorial Library
- Community Forums

**Professional Support Channels:**
- Email Support: Available for licensed users
- Priority Support: Enterprise license holders
- Custom Development: Available on request
- Training Sessions: Group training available

### Community Resources

**Forums & Community:**
- WordPress.org Plugin Support Forum
- GitHub Issues for Bug Reports
- Community Facebook Group
- Developer Slack Channel

**Contributing:**
- GitHub Repository for Code Contributions
- Documentation Improvements Welcome
- Translation Projects via WordPress.org
- Beta Testing Program

---

This comprehensive v2 user guide covers all the new enterprise-grade features and provides detailed instructions for users to maximize their booking system potential.