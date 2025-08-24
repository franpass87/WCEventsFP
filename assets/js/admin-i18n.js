/**
 * WCEventsFP Admin Internationalization JavaScript
 * Handles backend localization and translation management
 */
(function($) {
    'use strict';
    
    // Global admin i18n object
    window.WCEventsFP_Admin_I18n = {
        
        strings: wcefp_admin_i18n.admin_strings || {},
        currentLocale: wcefp_admin_i18n.current_locale || 'en_US',
        supportedLocales: wcefp_admin_i18n.supported_locales || {},
        
        /**
         * Initialize admin i18n
         */
        init: function() {
            this.setupTranslationInterface();
            this.bindEvents();
            this.enhanceFormValidation();
        },
        
        /**
         * Setup translation management interface
         */
        setupTranslationInterface: function() {
            this.createTranslationHelpers();
            this.enhanceStringInputs();
        },
        
        /**
         * Create translation helper tools
         */
        createTranslationHelpers: function() {
            // Add translation status indicators
            $('.wcefp-settings-page input[type="text"], .wcefp-settings-page textarea').each(function() {
                if ($(this).val() && $(this).attr('name')) {
                    var $indicator = $('<span class="wcefp-translation-indicator" title="Translation available"></span>');
                    $(this).after($indicator);
                }
            });
            
            // Add bulk translation actions
            if ($('.wcefp-settings-section').length) {
                this.addBulkTranslationActions();
            }
        },
        
        /**
         * Add bulk translation actions to settings
         */
        addBulkTranslationActions: function() {
            var $toolbar = $('<div class="wcefp-translation-toolbar"></div>');
            
            $toolbar.append('<button type="button" class="button wcefp-export-strings">' + 
                          this.getString('export_strings', 'Export Strings') + '</button>');
            $toolbar.append('<button type="button" class="button wcefp-import-strings">' + 
                          this.getString('import_strings', 'Import Strings') + '</button>');
            $toolbar.append('<input type="file" id="wcefp-translation-file" accept=".po,.json" style="display:none;">');
            
            $('.wcefp-settings-section:first').prepend($toolbar);
        },
        
        /**
         * Enhance string inputs with translation features
         */
        enhanceStringInputs: function() {
            var self = this;
            
            // Add translation helper to string inputs
            $('.wcefp-translatable-string').each(function() {
                var $input = $(this);
                var $wrapper = $('<div class="wcefp-translation-wrapper"></div>');
                
                $input.wrap($wrapper);
                
                // Add language selector
                var $langSelect = self.createLanguageSelector($input);
                $input.after($langSelect);
                
                // Add quick translation buttons
                var $quickActions = $('<div class="wcefp-quick-translations"></div>');
                $quickActions.append('<button type="button" class="button-link wcefp-auto-translate" data-input-id="' + $input.attr('id') + '">Auto Translate</button>');
                $quickActions.append('<button type="button" class="button-link wcefp-reset-translation">Reset</button>');
                
                $langSelect.after($quickActions);
            });
        },
        
        /**
         * Create language selector for input
         */
        createLanguageSelector: function($input) {
            var $select = $('<select class="wcefp-translation-language"></select>');
            
            Object.keys(this.supportedLocales).forEach(function(code) {
                var locale = this.supportedLocales[code];
                var $option = $('<option value="' + code + '">' + locale.flag + ' ' + locale.native + '</option>');
                
                if (code === this.currentLocale) {
                    $option.prop('selected', true);
                }
                
                $select.append($option);
            }.bind(this));
            
            return $select;
        },
        
        /**
         * Bind admin events
         */
        bindEvents: function() {
            var self = this;
            
            // Export strings
            $(document).on('click', '.wcefp-export-strings', function() {
                self.exportStrings();
            });
            
            // Import strings
            $(document).on('click', '.wcefp-import-strings', function() {
                $('#wcefp-translation-file').click();
            });
            
            $(document).on('change', '#wcefp-translation-file', function() {
                self.importStrings(this.files[0]);
            });
            
            // Auto translate
            $(document).on('click', '.wcefp-auto-translate', function() {
                var inputId = $(this).data('input-id');
                var $input = $('#' + inputId);
                var text = $input.val();
                
                if (text) {
                    self.autoTranslate(text, $input);
                }
            });
            
            // Reset translation
            $(document).on('click', '.wcefp-reset-translation', function() {
                var $input = $(this).closest('.wcefp-translation-wrapper').find('input, textarea');
                $input.val($input.data('original-value') || '');
            });
            
            // Language change
            $(document).on('change', '.wcefp-translation-language', function() {
                var locale = $(this).val();
                var $input = $(this).siblings('input, textarea');
                
                self.loadTranslationForInput($input, locale);
            });
            
            // String validation
            $(document).on('blur', '.wcefp-translatable-string', function() {
                self.validateTranslatableString($(this));
            });
        },
        
        /**
         * Export translatable strings
         */
        exportStrings: function() {
            var strings = {};
            
            $('.wcefp-translatable-string').each(function() {
                var key = $(this).attr('name') || $(this).attr('id');
                var value = $(this).val();
                
                if (key && value) {
                    strings[key] = value;
                }
            });
            
            // Add system strings
            strings = Object.assign(strings, this.strings);
            
            // Create download
            var jsonData = JSON.stringify(strings, null, 2);
            var blob = new Blob([jsonData], { type: 'application/json' });
            var url = URL.createObjectURL(blob);
            
            var a = document.createElement('a');
            a.href = url;
            a.download = 'wcefp-strings-' + this.currentLocale + '.json';
            a.click();
            
            URL.revokeObjectURL(url);
            
            this.showNotice('Strings exported successfully', 'success');
        },
        
        /**
         * Import strings from file
         */
        importStrings: function(file) {
            if (!file) return;
            
            var self = this;
            var reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    var data = JSON.parse(e.target.result);
                    self.applyImportedStrings(data);
                    self.showNotice('Strings imported successfully', 'success');
                } catch (error) {
                    self.showNotice('Error importing strings: ' + error.message, 'error');
                }
            };
            
            reader.readAsText(file);
        },
        
        /**
         * Apply imported strings to form
         */
        applyImportedStrings: function(strings) {
            Object.keys(strings).forEach(function(key) {
                var $input = $('[name="' + key + '"], #' + key);
                if ($input.length) {
                    $input.val(strings[key]);
                    $input.trigger('change');
                }
            });
        },
        
        /**
         * Auto translate text using browser API or simple substitutions
         */
        autoTranslate: function(text, $input) {
            var targetLang = $input.siblings('.wcefp-translation-language').val();
            
            // Simple keyword translations for common booking terms
            var translations = this.getQuickTranslations(text, targetLang);
            
            if (translations) {
                $input.val(translations);
                this.showNotice('Translation applied', 'success');
            } else {
                this.showNotice('No quick translation available', 'info');
            }
        },
        
        /**
         * Get quick translations for common terms
         */
        getQuickTranslations: function(text, targetLang) {
            var quickTranslations = {
                'en_US': {
                    'Book Now': 'Book Now',
                    'Check Availability': 'Check Availability',
                    'Select Date': 'Select Date',
                    'Total Price': 'Total Price'
                },
                'it_IT': {
                    'Book Now': 'Prenota Ora',
                    'Check Availability': 'Verifica Disponibilità', 
                    'Select Date': 'Seleziona Data',
                    'Total Price': 'Prezzo Totale'
                },
                'es_ES': {
                    'Book Now': 'Reservar Ahora',
                    'Check Availability': 'Verificar Disponibilidad',
                    'Select Date': 'Seleccionar Fecha', 
                    'Total Price': 'Precio Total'
                },
                'fr_FR': {
                    'Book Now': 'Réserver Maintenant',
                    'Check Availability': 'Vérifier la Disponibilité',
                    'Select Date': 'Sélectionner une Date',
                    'Total Price': 'Prix Total'
                },
                'de_DE': {
                    'Book Now': 'Jetzt Buchen',
                    'Check Availability': 'Verfügbarkeit Prüfen',
                    'Select Date': 'Datum Auswählen',
                    'Total Price': 'Gesamtpreis'
                }
            };
            
            return quickTranslations[targetLang] ? quickTranslations[targetLang][text] : null;
        },
        
        /**
         * Load translation for specific input
         */
        loadTranslationForInput: function($input, locale) {
            var key = $input.attr('name') || $input.attr('id');
            
            // Here you would typically load from database/API
            // For now, just show placeholder
            this.showNotice('Loading translation for ' + locale, 'info');
        },
        
        /**
         * Validate translatable string
         */
        validateTranslatableString: function($input) {
            var value = $input.val();
            var $indicator = $input.siblings('.wcefp-translation-indicator');
            
            if (!value) {
                $indicator.removeClass('has-translation').addClass('missing-translation');
                return false;
            }
            
            // Check for common translation issues
            var issues = [];
            
            // Check for HTML tags in translations
            if (/<[^>]*>/.test(value)) {
                issues.push('Contains HTML tags');
            }
            
            // Check for placeholder variables
            if (/%s|%d|\{\{.*\}\}/.test(value)) {
                issues.push('Contains placeholder variables');
            }
            
            // Check length (very long translations might have issues)
            if (value.length > 200) {
                issues.push('Very long translation');
            }
            
            if (issues.length > 0) {
                $indicator.removeClass('has-translation').addClass('has-issues');
                $input.attr('title', 'Issues: ' + issues.join(', '));
            } else {
                $indicator.removeClass('missing-translation has-issues').addClass('has-translation');
                $input.removeAttr('title');
            }
            
            return issues.length === 0;
        },
        
        /**
         * Enhance form validation with i18n considerations
         */
        enhanceFormValidation: function() {
            var self = this;
            
            // Add validation for translatable fields
            $('form').on('submit', function(e) {
                var hasErrors = false;
                
                $(this).find('.wcefp-translatable-string[required]').each(function() {
                    if (!self.validateTranslatableString($(this))) {
                        hasErrors = true;
                    }
                });
                
                if (hasErrors) {
                    e.preventDefault();
                    self.showNotice('Please fix translation issues before saving', 'error');
                    return false;
                }
            });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            if ($('.wcefp-notices').length) {
                $('.wcefp-notices').append($notice);
            } else {
                $('.wrap h1').after($notice);
            }
            
            // Auto dismiss after 3 seconds for success/info notices
            if (type === 'success' || type === 'info') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
        },
        
        /**
         * Get admin string with fallback
         */
        getString: function(key, fallback) {
            for (var category in this.strings) {
                if (this.strings[category][key]) {
                    return this.strings[category][key];
                }
            }
            return fallback || key;
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if (typeof wcefp_admin_i18n !== 'undefined') {
            WCEventsFP_Admin_I18n.init();
        }
    });
    
    // Expose globally
    window.wcefp_admin_i18n = WCEventsFP_Admin_I18n;
    
})(jQuery);