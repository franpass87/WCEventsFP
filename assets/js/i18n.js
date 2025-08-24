/**
 * WCEventsFP Internationalization JavaScript
 * Handles frontend localization and language switching
 */
(function($) {
    'use strict';
    
    // Global i18n object
    window.WCEventsFP_I18n = {
        
        currentLocale: wcefp_i18n.current_locale || 'en_US',
        supportedLocales: wcefp_i18n.supported_locales || {},
        strings: wcefp_i18n.strings || {},
        textDomain: wcefp_i18n.text_domain || 'wceventsfp',
        
        /**
         * Initialize i18n system
         */
        init: function() {
            this.detectUserLocale();
            this.setupLanguageSwitcher();
            this.formatElements();
            this.bindEvents();
        },
        
        /**
         * Auto-detect user's preferred language
         */
        detectUserLocale: function() {
            if (!navigator.language) return;
            
            var browserLang = navigator.language.replace('-', '_');
            var fallbackLang = browserLang.split('_')[0];
            
            // Check if browser language is supported
            if (this.supportedLocales[browserLang]) {
                this.switchLocale(browserLang);
            } else if (this.supportedLocales[fallbackLang]) {
                this.switchLocale(fallbackLang);
            }
        },
        
        /**
         * Setup language switcher if present
         */
        setupLanguageSwitcher: function() {
            var self = this;
            
            // Create language switcher if not exists
            if ($('.wcefp-language-switcher').length === 0) {
                this.createLanguageSwitcher();
            }
            
            // Handle language switching
            $(document).on('click', '.wcefp-language-switcher [data-locale]', function(e) {
                e.preventDefault();
                var locale = $(this).data('locale');
                self.switchLocale(locale);
            });
        },
        
        /**
         * Create language switcher HTML
         */
        createLanguageSwitcher: function() {
            var switcher = '<div class="wcefp-language-switcher">';
            switcher += '<button class="wcefp-current-language" aria-expanded="false">';
            switcher += '<span class="wcefp-flag">' + this.supportedLocales[this.currentLocale].flag + '</span>';
            switcher += '<span class="wcefp-language-name">' + this.supportedLocales[this.currentLocale].native + '</span>';
            switcher += '<span class="wcefp-dropdown-arrow">▼</span>';
            switcher += '</button>';
            switcher += '<div class="wcefp-language-menu" role="menu">';
            
            Object.keys(this.supportedLocales).forEach(function(code) {
                var locale = this.supportedLocales[code];
                switcher += '<a href="#" class="wcefp-language-option" data-locale="' + code + '" role="menuitem">';
                switcher += '<span class="wcefp-flag">' + locale.flag + '</span>';
                switcher += '<span class="wcefp-language-name">' + locale.native + '</span>';
                if (locale.completion && locale.completion < 100) {
                    switcher += '<span class="wcefp-translation-status">(' + locale.completion + '%)</span>';
                }
                switcher += '</a>';
            }.bind(this));
            
            switcher += '</div></div>';
            
            // Add to booking forms
            $('.wcefp-booking-form .wcefp-form-header').prepend(switcher);
        },
        
        /**
         * Switch to different locale
         */
        switchLocale: function(locale) {
            if (!this.supportedLocales[locale]) {
                console.warn('Unsupported locale:', locale);
                return;
            }
            
            this.currentLocale = locale;
            this.loadStrings(locale);
            this.updateElements();
            this.updateFormats(locale);
            
            // Store preference
            localStorage.setItem('wcefp_preferred_locale', locale);
            
            // Trigger custom event
            $(document).trigger('wcefp:locale-changed', [locale]);
        },
        
        /**
         * Load strings for specific locale
         */
        loadStrings: function(locale) {
            var self = this;
            
            $.post(wcefp_i18n.ajax_url, {
                action: 'wcefp_get_translations',
                locale: locale,
                nonce: wcefp_i18n.nonce
            }, function(response) {
                if (response.success) {
                    self.strings = response.data.strings;
                    self.updateElements();
                }
            });
        },
        
        /**
         * Update translatable elements
         */
        updateElements: function() {
            var self = this;
            
            // Update elements with data-i18n attribute
            $('[data-i18n]').each(function() {
                var key = $(this).data('i18n');
                var translation = self.getString(key);
                
                if ($(this).is('input[type="submit"], button')) {
                    $(this).val(translation);
                } else if ($(this).is('input[placeholder]')) {
                    $(this).attr('placeholder', translation);
                } else {
                    $(this).text(translation);
                }
            });
            
            // Update form labels
            this.updateFormLabels();
        },
        
        /**
         * Update form labels and placeholders
         */
        updateFormLabels: function() {
            var bookingStrings = this.strings.booking || {};
            
            // Common booking form elements
            var mappings = {
                'select-date': bookingStrings.select_date,
                'select-time': bookingStrings.select_time,
                'participants': bookingStrings.participants,
                'adults': bookingStrings.adults,
                'children': bookingStrings.children,
                'total-price': bookingStrings.total_price,
                'book-now': bookingStrings.book_now,
                'confirm-booking': bookingStrings.confirm_booking
            };
            
            Object.keys(mappings).forEach(function(key) {
                var elements = $('.wcefp-' + key + ', [data-wcefp="' + key + '"]');
                if (elements.length && mappings[key]) {
                    elements.text(mappings[key]);
                }
            });
        },
        
        /**
         * Update formats (dates, numbers, currency)
         */
        updateFormats: function(locale) {
            var localeInfo = this.supportedLocales[locale];
            if (!localeInfo) return;
            
            // Update date formats
            $('.wcefp-date').each(function() {
                var date = $(this).data('date');
                if (date) {
                    $(this).text(this.formatDate(date, locale));
                }
            }.bind(this));
            
            // Update price formats
            $('.wcefp-price').each(function() {
                var amount = $(this).data('amount');
                if (amount) {
                    $(this).text(this.formatPrice(amount, locale));
                }
            }.bind(this));
        },
        
        /**
         * Format elements based on locale
         */
        formatElements: function() {
            this.updateFormats(this.currentLocale);
        },
        
        /**
         * Bind events for i18n functionality
         */
        bindEvents: function() {
            var self = this;
            
            // Language switcher dropdown
            $(document).on('click', '.wcefp-current-language', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $menu = $(this).siblings('.wcefp-language-menu');
                var isOpen = $(this).attr('aria-expanded') === 'true';
                
                // Close all other dropdowns
                $('.wcefp-language-switcher .wcefp-current-language').attr('aria-expanded', 'false');
                $('.wcefp-language-menu').hide();
                
                if (!isOpen) {
                    $(this).attr('aria-expanded', 'true');
                    $menu.show();
                }
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wcefp-language-switcher').length) {
                    $('.wcefp-language-switcher .wcefp-current-language').attr('aria-expanded', 'false');
                    $('.wcefp-language-menu').hide();
                }
            });
            
            // Keyboard navigation for language switcher
            $(document).on('keydown', '.wcefp-language-switcher', function(e) {
                var $options = $(this).find('.wcefp-language-option');
                var currentIndex = $options.index(document.activeElement);
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        var nextIndex = currentIndex < $options.length - 1 ? currentIndex + 1 : 0;
                        $options.eq(nextIndex).focus();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        var prevIndex = currentIndex > 0 ? currentIndex - 1 : $options.length - 1;
                        $options.eq(prevIndex).focus();
                        break;
                    case 'Escape':
                        $(this).find('.wcefp-current-language').focus();
                        $(this).find('.wcefp-language-menu').hide();
                        break;
                }
            });
        },
        
        /**
         * Get translated string
         */
        getString: function(key, category = null) {
            if (category && this.strings[category] && this.strings[category][key]) {
                return this.strings[category][key];
            }
            
            // Search in all categories
            for (var cat in this.strings) {
                if (this.strings[cat] && this.strings[cat][key]) {
                    return this.strings[cat][key];
                }
            }
            
            return key; // Return key if translation not found
        },
        
        /**
         * Format date according to locale
         */
        formatDate: function(date, locale = null) {
            if (!locale) locale = this.currentLocale;
            
            var localeInfo = this.supportedLocales[locale];
            if (!localeInfo) return date;
            
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            if (!(date instanceof Date) || isNaN(date)) return '';
            
            // Use Intl.DateTimeFormat if available
            if (window.Intl && window.Intl.DateTimeFormat) {
                try {
                    return new Intl.DateTimeFormat(locale.replace('_', '-')).format(date);
                } catch (e) {
                    // Fallback to manual formatting
                }
            }
            
            // Manual formatting based on locale
            var day = date.getDate().toString().padStart(2, '0');
            var month = (date.getMonth() + 1).toString().padStart(2, '0');
            var year = date.getFullYear();
            
            switch (localeInfo.date_format) {
                case 'd/m/Y':
                    return day + '/' + month + '/' + year;
                case 'd.m.Y':
                    return day + '.' + month + '.' + year;
                case 'Y年m月d日':
                    return year + '年' + month + '月' + day + '日';
                default: // M d, Y
                    var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    return monthNames[date.getMonth()] + ' ' + day + ', ' + year;
            }
        },
        
        /**
         * Format price according to locale
         */
        formatPrice: function(amount, locale = null) {
            if (!locale) locale = this.currentLocale;
            
            var localeInfo = this.supportedLocales[locale];
            if (!localeInfo) return amount;
            
            // Format number
            var parts = amount.toFixed(2).split('.');
            var integerPart = parts[0];
            var decimalPart = parts[1];
            
            // Add thousands separators
            if (localeInfo.thousands_separator) {
                integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, localeInfo.thousands_separator);
            }
            
            // Join with decimal separator
            var formatted = integerPart;
            if (decimalPart && decimalPart !== '00') {
                formatted += localeInfo.decimal_separator + decimalPart;
            }
            
            // Add currency symbol
            return localeInfo.currency + ' ' + formatted;
        },
        
        /**
         * Format time according to locale
         */
        formatTime: function(time, locale = null) {
            if (!locale) locale = this.currentLocale;
            
            var localeInfo = this.supportedLocales[locale];
            if (!localeInfo) return time;
            
            if (typeof time === 'string') {
                var parts = time.split(':');
                var hour = parseInt(parts[0]);
                var minute = parts[1] || '00';
                
                if (localeInfo.time_format === 'g:i A') {
                    // 12-hour format
                    var ampm = hour >= 12 ? 'PM' : 'AM';
                    if (hour > 12) hour -= 12;
                    if (hour === 0) hour = 12;
                    return hour + ':' + minute + ' ' + ampm;
                } else {
                    // 24-hour format
                    return hour.toString().padStart(2, '0') + ':' + minute;
                }
            }
            
            return time;
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Check for stored locale preference
        var storedLocale = localStorage.getItem('wcefp_preferred_locale');
        if (storedLocale && WCEventsFP_I18n.supportedLocales[storedLocale]) {
            WCEventsFP_I18n.currentLocale = storedLocale;
        }
        
        WCEventsFP_I18n.init();
    });
    
    // Expose globally for other scripts
    window.wcefp_i18n = WCEventsFP_I18n;
    
})(jQuery);