/**
 * WCEventsFP Dark Theme Controller
 * Handles theme switching and persistence
 */

class WCEFPDarkTheme {
    constructor() {
        this.init();
    }

    init() {
        this.createToggleButton();
        this.loadSavedTheme();
        this.bindEvents();
        this.observeSystemTheme();
    }

    createToggleButton() {
        if (document.querySelector('.wcefp-theme-toggle')) return;

        const toggle = document.createElement('button');
        toggle.className = 'wcefp-theme-toggle';
        toggle.setAttribute('aria-label', 'Toggle dark theme');
        toggle.setAttribute('title', 'Toggle dark/light theme');
        toggle.innerHTML = this.getThemeIcon();
        
        document.body.appendChild(toggle);
        
        // Add to admin if we're in admin area
        if (document.body.classList.contains('wp-admin')) {
            toggle.style.top = '32px'; // Account for admin bar
        }
    }

    getThemeIcon() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        return currentTheme === 'dark' ? '☀️' : '🌙';
    }

    bindEvents() {
        const toggle = document.querySelector('.wcefp-theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleTheme();
        });

        // Keyboard accessibility
        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        this.setTheme(newTheme);
        this.saveTheme(newTheme);
        this.updateToggleButton();
        this.animateToggle();
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Update meta theme-color for mobile browsers
        let metaTheme = document.querySelector('meta[name="theme-color"]');
        if (!metaTheme) {
            metaTheme = document.createElement('meta');
            metaTheme.name = 'theme-color';
            document.head.appendChild(metaTheme);
        }
        
        metaTheme.content = theme === 'dark' ? '#0f172a' : '#ffffff';
        
        // Dispatch custom event for other components
        window.dispatchEvent(new CustomEvent('wcefp-theme-changed', {
            detail: { theme }
        }));
    }

    saveTheme(theme) {
        localStorage.setItem('wcefp-theme', theme);
        
        // Also save to user meta if logged in
        if (window.wp && wp.ajax) {
            wp.ajax.post('wcefp_save_user_theme', {
                theme: theme,
                nonce: window.wcefp_ajax?.nonce || ''
            }).catch(() => {
                // Silently fail if AJAX is not available
            });
        }
    }

    loadSavedTheme() {
        // Priority: 1. URL parameter, 2. localStorage, 3. user preference, 4. system preference
        const urlParams = new URLSearchParams(window.location.search);
        const urlTheme = urlParams.get('theme');
        
        if (urlTheme && ['light', 'dark'].includes(urlTheme)) {
            this.setTheme(urlTheme);
            return;
        }

        const savedTheme = localStorage.getItem('wcefp-theme');
        if (savedTheme && ['light', 'dark'].includes(savedTheme)) {
            this.setTheme(savedTheme);
            return;
        }

        // Check user preference from server (if available)
        if (window.wcefp_user_theme) {
            this.setTheme(window.wcefp_user_theme);
            return;
        }

        // Fall back to system preference
        this.setTheme(this.getSystemTheme());
    }

    getSystemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches 
            ? 'dark' 
            : 'light';
    }

    observeSystemTheme() {
        if (!window.matchMedia) return;

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        const handleSystemThemeChange = (e) => {
            // Only auto-switch if user hasn't explicitly set a preference
            if (!localStorage.getItem('wcefp-theme')) {
                this.setTheme(e.matches ? 'dark' : 'light');
                this.updateToggleButton();
            }
        };

        // Modern browsers
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleSystemThemeChange);
        } else {
            // Legacy support
            mediaQuery.addListener(handleSystemThemeChange);
        }
    }

    updateToggleButton() {
        const toggle = document.querySelector('.wcefp-theme-toggle');
        if (!toggle) return;

        toggle.innerHTML = this.getThemeIcon();
        
        const currentTheme = document.documentElement.getAttribute('data-theme');
        toggle.setAttribute('title', 
            currentTheme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'
        );
    }

    animateToggle() {
        const toggle = document.querySelector('.wcefp-theme-toggle');
        if (!toggle) return;

        // Add rotation animation
        toggle.style.transform = 'rotate(360deg)';
        setTimeout(() => {
            toggle.style.transform = '';
        }, 300);

        // Add bounce effect
        toggle.classList.add('wcefp-theme-toggle-active');
        setTimeout(() => {
            toggle.classList.remove('wcefp-theme-toggle-active');
        }, 200);
    }

    // Public API for other components
    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    isDarkTheme() {
        return this.getCurrentTheme() === 'dark';
    }

    // Method for developers to programmatically set theme
    setThemeManually(theme) {
        if (!['light', 'dark'].includes(theme)) {
            console.warn('Invalid theme:', theme);
            return;
        }
        this.setTheme(theme);
        this.saveTheme(theme);
        this.updateToggleButton();
    }
}

// Enhanced toggle animations
const additionalStyles = `
.wcefp-theme-toggle-active {
    transform: scale(1.1) !important;
}

.wcefp-theme-toggle {
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
}

/* Theme transition enhancement for specific elements */
.wcefp-enhanced-transition {
    transition: background-color 0.5s ease, color 0.5s ease, border-color 0.5s ease !important;
}

/* Improve leaflet map theme transitions */
.leaflet-container {
    transition: background-color 0.3s ease;
}

/* Enhance calendar transitions */
.fc-theme-standard .fc-scrollgrid {
    transition: border-color 0.3s ease;
}

/* Better form transitions */
input, select, textarea {
    transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
}
`;

// Create and inject additional styles
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Initialize theme system when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.wcefpDarkTheme = new WCEFPDarkTheme();
    });
} else {
    window.wcefpDarkTheme = new WCEFPDarkTheme();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WCEFPDarkTheme;
}

// Enhance existing WCEFP components with dark theme awareness
document.addEventListener('wcefp-theme-changed', (e) => {
    const theme = e.detail.theme;
    
    // Update any charts or visualizations
    if (window.wcefpCharts) {
        window.wcefpCharts.updateTheme(theme);
    }
    
    // Update maps
    if (window.wcefpMaps) {
        window.wcefpMaps.updateTheme(theme);
    }
    
    // Update any third-party components
    if (window.FullCalendar && document.querySelector('.fc')) {
        // Trigger calendar re-render for theme change
        const calendarEl = document.querySelector('.fc');
        if (calendarEl && calendarEl._calendar) {
            calendarEl._calendar.render();
        }
    }
    
    // Announce theme change to screen readers
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', 'polite');
    announcement.setAttribute('aria-atomic', 'true');
    announcement.style.position = 'absolute';
    announcement.style.left = '-10000px';
    announcement.style.width = '1px';
    announcement.style.height = '1px';
    announcement.style.overflow = 'hidden';
    announcement.textContent = `Theme changed to ${theme} mode`;
    document.body.appendChild(announcement);
    
    setTimeout(() => {
        document.body.removeChild(announcement);
    }, 1000);
});

// Expose global utility functions
window.wcefpTheme = {
    toggle: () => window.wcefpDarkTheme?.toggleTheme(),
    set: (theme) => window.wcefpDarkTheme?.setThemeManually(theme),
    get: () => window.wcefpDarkTheme?.getCurrentTheme(),
    isDark: () => window.wcefpDarkTheme?.isDarkTheme()
};