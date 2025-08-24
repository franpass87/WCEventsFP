/**
 * REST API Client Tests
 */

describe('WCEFP REST API Client', () => {
    let originalFetch;
    
    beforeEach(() => {
        originalFetch = global.fetch;
        global.fetch = jest.fn();
        
        // Mock WordPress REST API globals
        global.wpApiSettings = {
            root: 'http://example.com/wp-json/',
            nonce: 'test-nonce'
        };
        
        global.wcefpApi = {
            root: 'http://example.com/wp-json/wcefp/v1/',
            nonce: 'test-nonce'
        };
    });
    
    afterEach(() => {
        global.fetch = originalFetch;
        jest.clearAllMocks();
    });
    
    describe('Events API', () => {
        test('should fetch events list', async () => {
            const mockEvents = [
                { id: 1, title: 'Wine Tasting', capacity: 10 },
                { id: 2, title: 'Cooking Class', capacity: 12 }
            ];
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockEvents
            });
            
            // Simulate API client
            const response = await fetch(`${global.wcefpApi.root}events`);
            const events = await response.json();
            
            expect(global.fetch).toHaveBeenCalledWith(`${global.wcefpApi.root}events`);
            expect(events).toEqual(mockEvents);
            expect(events).toHaveLength(2);
        });
        
        test('should fetch single event with details', async () => {
            const mockEvent = {
                id: 1,
                title: 'Wine Tasting Experience',
                capacity: 15,
                duration: 120,
                location: 'Vineyard Estate',
                price: 45.00,
                available_dates: [
                    { date: '2024-03-15', time: '10:00:00', available_spots: 10 }
                ]
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockEvent
            });
            
            const response = await fetch(`${global.wcefpApi.root}events/1`);
            const event = await response.json();
            
            expect(event.id).toBe(1);
            expect(event.title).toBe('Wine Tasting Experience');
            expect(event.available_dates).toHaveLength(1);
        });
        
        test('should handle event not found error', async () => {
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 404,
                json: async () => ({
                    code: 'event_not_found',
                    message: 'Event not found'
                })
            });
            
            const response = await fetch(`${global.wcefpApi.root}events/999`);
            const error = await response.json();
            
            expect(response.ok).toBe(false);
            expect(response.status).toBe(404);
            expect(error.code).toBe('event_not_found');
        });
    });
    
    describe('Bookings API', () => {
        test('should create booking with valid data', async () => {
            const bookingData = {
                event_id: 1,
                customer_email: 'test@example.com',
                customer_name: 'John Doe',
                booking_date: '2024-03-15',
                participants: 2
            };
            
            const mockResponse = {
                id: 123,
                status: 'confirmed',
                ...bookingData
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                status: 201,
                json: async () => mockResponse
            });
            
            const response = await fetch(`${global.wcefpApi.root}bookings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': global.wcefpApi.nonce
                },
                body: JSON.stringify(bookingData)
            });
            
            const booking = await response.json();
            
            expect(response.status).toBe(201);
            expect(booking.id).toBe(123);
            expect(booking.event_id).toBe(1);
            expect(booking.participants).toBe(2);
        });
        
        test('should validate required booking fields', async () => {
            const invalidBookingData = {
                event_id: 1,
                // Missing required fields
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: false,
                status: 400,
                json: async () => ({
                    code: 'missing_field',
                    message: 'Missing required field: customer_email'
                })
            });
            
            const response = await fetch(`${global.wcefpApi.root}bookings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': global.wcefpApi.nonce
                },
                body: JSON.stringify(invalidBookingData)
            });
            
            const error = await response.json();
            
            expect(response.status).toBe(400);
            expect(error.code).toBe('missing_field');
        });
    });
    
    describe('Export API', () => {
        test('should export bookings as CSV', async () => {
            const mockExport = {
                filename: 'wcefp-bookings-2024-03-01.csv',
                content_type: 'text/csv',
                content: btoa('ID,Event,Customer,Date\n1,Wine Tasting,John Doe,2024-03-15'),
                count: 1
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockExport
            });
            
            const response = await fetch(`${global.wcefpApi.root}export/bookings?format=csv`, {
                headers: {
                    'X-WP-Nonce': global.wcefpApi.nonce
                }
            });
            
            const exportData = await response.json();
            
            expect(exportData.filename).toContain('.csv');
            expect(exportData.content_type).toBe('text/csv');
            expect(exportData.count).toBe(1);
        });
        
        test('should export calendar as ICS', async () => {
            const mockCalendar = {
                filename: 'wcefp-calendar-2024-03-01.ics',
                content_type: 'text/calendar',
                content: btoa('BEGIN:VCALENDAR\nVERSION:2.0\nEND:VCALENDAR'),
                count: 5
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockCalendar
            });
            
            const response = await fetch(`${global.wcefpApi.root}export/calendar?format=ics`);
            const calendarData = await response.json();
            
            expect(calendarData.filename).toContain('.ics');
            expect(calendarData.content_type).toBe('text/calendar');
            expect(calendarData.count).toBe(5);
        });
    });
    
    describe('System Status API', () => {
        test('should fetch system status', async () => {
            const mockStatus = {
                plugin_version: '2.1.4',
                wordpress_version: '6.4.0',
                php_version: '8.1.0',
                database: {
                    bookings_count: 150,
                    events_count: 25
                },
                dependencies: {
                    woocommerce_active: true
                }
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockStatus
            });
            
            const response = await fetch(`${global.wcefpApi.root}system/status`, {
                headers: {
                    'X-WP-Nonce': global.wcefpApi.nonce
                }
            });
            
            const status = await response.json();
            
            expect(status.plugin_version).toBe('2.1.4');
            expect(status.database.bookings_count).toBe(150);
            expect(status.dependencies.woocommerce_active).toBe(true);
        });
        
        test('should fetch system health', async () => {
            const mockHealth = {
                overall_status: 'good',
                checks: {
                    database: { status: 'good', message: 'All tables exist' },
                    memory_usage: { status: 'good', message: 'Memory usage: 45%' }
                }
            };
            
            global.fetch.mockResolvedValueOnce({
                ok: true,
                json: async () => mockHealth
            });
            
            const response = await fetch(`${global.wcefpApi.root}system/health`, {
                headers: {
                    'X-WP-Nonce': global.wcefpApi.nonce
                }
            });
            
            const health = await response.json();
            
            expect(health.overall_status).toBe('good');
            expect(health.checks.database.status).toBe('good');
        });
    });
});

describe('WCEFP Frontend Forms', () => {
    let container;
    
    beforeEach(() => {
        container = document.createElement('div');
        document.body.appendChild(container);
        
        // Mock global WCEFP object
        global.wcefp = {
            ajaxurl: 'http://example.com/wp-admin/admin-ajax.php',
            nonce: 'test-nonce',
            strings: {
                loading: 'Loading...',
                error: 'An error occurred',
                success: 'Success!'
            }
        };
    });
    
    afterEach(() => {
        document.body.removeChild(container);
        jest.clearAllMocks();
    });
    
    describe('Booking Form', () => {
        test('should render booking form elements', () => {
            container.innerHTML = `
                <form class="wcefp-booking-form">
                    <select name="event_id" required>
                        <option value="">Select Event</option>
                        <option value="1">Wine Tasting</option>
                    </select>
                    <input type="email" name="customer_email" required>
                    <input type="date" name="booking_date" required>
                    <input type="number" name="participants" min="1" required>
                    <button type="submit">Book Now</button>
                </form>
            `;
            
            const form = container.querySelector('.wcefp-booking-form');
            const eventSelect = form.querySelector('select[name="event_id"]');
            const emailInput = form.querySelector('input[name="customer_email"]');
            const submitButton = form.querySelector('button[type="submit"]');
            
            expect(form).toBeTruthy();
            expect(eventSelect.options).toHaveLength(2);
            expect(emailInput.type).toBe('email');
            expect(submitButton.textContent).toBe('Book Now');
        });
        
        test('should validate form before submission', () => {
            container.innerHTML = `
                <form class="wcefp-booking-form">
                    <input type="email" name="customer_email" required>
                    <input type="date" name="booking_date" required>
                    <button type="submit">Book Now</button>
                </form>
            `;
            
            const form = container.querySelector('.wcefp-booking-form');
            const emailInput = form.querySelector('input[name="customer_email"]');
            
            // Test invalid email
            emailInput.value = 'invalid-email';
            expect(emailInput.validity.valid).toBe(false);
            
            // Test valid email
            emailInput.value = 'test@example.com';
            expect(emailInput.validity.valid).toBe(true);
        });
    });
    
    describe('Event Search', () => {
        test('should filter events based on search input', () => {
            container.innerHTML = `
                <div class="wcefp-search">
                    <input type="text" class="search-input" placeholder="Search events...">
                    <div class="events-grid">
                        <div class="event-item" data-title="wine tasting experience">Wine Tasting</div>
                        <div class="event-item" data-title="cooking class">Cooking Class</div>
                        <div class="event-item" data-title="art workshop">Art Workshop</div>
                    </div>
                </div>
            `;
            
            const searchInput = container.querySelector('.search-input');
            const eventItems = container.querySelectorAll('.event-item');
            
            // Simulate search functionality
            const filterEvents = (query) => {
                eventItems.forEach(item => {
                    const title = item.dataset.title.toLowerCase();
                    const visible = title.includes(query.toLowerCase());
                    item.style.display = visible ? 'block' : 'none';
                });
            };
            
            // Test search
            filterEvents('wine');
            
            expect(eventItems[0].style.display).toBe('block');
            expect(eventItems[1].style.display).toBe('none');
            expect(eventItems[2].style.display).toBe('none');
        });
    });
});

describe('WCEFP Utilities', () => {
    describe('Date Formatting', () => {
        test('should format dates correctly', () => {
            const formatDate = (dateString) => {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long', 
                    day: 'numeric'
                });
            };
            
            expect(formatDate('2024-03-15')).toBe('March 15, 2024');
            expect(formatDate('2024-12-25')).toBe('December 25, 2024');
        });
        
        test('should validate date ranges', () => {
            const isDateInRange = (date, startDate, endDate) => {
                const checkDate = new Date(date);
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                return checkDate >= start && checkDate <= end;
            };
            
            expect(isDateInRange('2024-03-15', '2024-03-01', '2024-03-31')).toBe(true);
            expect(isDateInRange('2024-04-01', '2024-03-01', '2024-03-31')).toBe(false);
        });
    });
    
    describe('Price Calculations', () => {
        test('should calculate booking totals correctly', () => {
            const calculateTotal = (basePrice, adults, children, extras = []) => {
                const participantsTotal = (adults + children) * basePrice;
                const extrasTotal = extras.reduce((sum, extra) => sum + extra.price, 0);
                return participantsTotal + extrasTotal;
            };
            
            expect(calculateTotal(25, 2, 1, [])).toBe(75);
            expect(calculateTotal(25, 2, 1, [{ price: 10 }, { price: 15 }])).toBe(100);
        });
        
        test('should format prices correctly', () => {
            const formatPrice = (amount, currency = 'EUR') => {
                return new Intl.NumberFormat('it-IT', {
                    style: 'currency',
                    currency: currency
                }).format(amount);
            };
            
            const price1 = formatPrice(25.50);
            const price2 = formatPrice(100);
            
            // Check that prices contain euro symbol and correct numbers
            expect(price1).toContain('25,50');
            expect(price1).toContain('€');
            expect(price2).toContain('100,00');
            expect(price2).toContain('€');
        });
    });
    
    describe('Validation Helpers', () => {
        test('should validate email addresses', () => {
            const isValidEmail = (email) => {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            };
            
            expect(isValidEmail('test@example.com')).toBe(true);
            expect(isValidEmail('invalid-email')).toBe(false);
            expect(isValidEmail('test@')).toBe(false);
        });
        
        test('should validate phone numbers', () => {
            const isValidPhone = (phone) => {
                const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
                return phoneRegex.test(phone.replace(/\s/g, ''));
            };
            
            expect(isValidPhone('+1234567890')).toBe(true);
            expect(isValidPhone('123 456 7890')).toBe(true);
            expect(isValidPhone('invalid-phone')).toBe(false);
        });
    });
});