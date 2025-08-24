module.exports = {
	env: {
		browser: true,
		es6: true,
		node: true,
		jquery: true,
	},
	extends: ['eslint:recommended'],
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module',
	},
	globals: {
		// WordPress globals
		wp: 'readonly',
		jQuery: 'readonly',
		$: 'readonly',
		ajaxurl: 'readonly',
		// WooCommerce globals
		wc_checkout_params: 'readonly',
		woocommerce_params: 'readonly',
		// WCEventsFP globals
		WCEFPModals: 'writable',
		WCEFPWidgets: 'writable',
		WCEFPAccessibility: 'writable',
		WCEFPData: 'readonly',
		WCEFPVoucherManager: 'writable',
		WCEFPAdmin: 'writable',
		WCEFPRealtime: 'writable',
		WCEFPTpl: 'writable',
		wcefpExport: 'readonly',
		wcefpVoucherManager: 'readonly',
		wcefpApiTesting: 'readonly',
		wcefpAnalytics: 'readonly',
		wcefpCheckin: 'readonly',
		wcefpBooking: 'readonly',
		wcefp_checkin: 'readonly',
		wcefp_i18n: 'readonly',
		// Missing globals from ESLint errors
		wcefp_admin_i18n: 'readonly',
		wcefpSettings: 'readonly', 
		wcefp_analytics: 'readonly',
		wcefp_shortcodes: 'readonly',
		WCEventsFP_Admin_I18n: 'readonly',
		// Additional globals  
		WCEFPPublic: 'readonly',
		dataLayer: 'writable',
		fbq: 'readonly',
		// Third-party globals
		FullCalendar: 'readonly',
		Chart: 'readonly',
		moment: 'readonly',
	},
	rules: {
		// Allow console for debugging in development
		'no-console': 'warn',
		// Allow alert/confirm/prompt for modals system
		'no-alert': 'off',
		// Allow unused vars with underscore prefix
		'no-unused-vars': ['error', { 
			'argsIgnorePattern': '^_',
			'varsIgnorePattern': '^_'
		}],
		// Allow camelCase exceptions for WordPress/WooCommerce naming
		camelcase: ['error', {
			allow: [
				'product_id', 'booking_id', 'event_id', 'user_id',
				'date_from', 'date_to', 'date_range',
				'session_id', 'start_time', 'end_time',
				'page_url', 'user_agent', 'screen_resolution', 'viewport_size',
				'utm_source', 'utm_medium', 'utm_campaign',
				'device_type', 'connection_type',
				'dom_content_loaded', 'load_complete', 'total_load_time',
				'dns_lookup', 'tcp_connection', 'server_response', 'dom_processing',
				'time_range', 'include_predictions', 'include_segments',
				'x_pred', 'action_type', 'voucher_code',
				'current_locale', 'supported_locales',
				// Analytics and tracking
				'page_view', 'product_view', 'date_selected', 'participants_selected',
				'extras_viewed', 'add_to_cart_attempted', 'add_to_cart_completed',
				'checkout_initiated', 'purchase_completed', 'user_fingerprint',
				'participants_total', 'selected_date', 'selected_time', 'funnel_completion',
				'days_from_now', 'selected_slot', 'slot_text', 'extra_id', 'extra_name',
				'extra_price', 'item_id', 'item_name', 'item_category', 'item_category2',
				'booking_type', 'content_ids', 'content_type', 'content_name',
				'content_category', 'num_items', 'filter_type', 'content_id',
				'place_id', 'overall_rating',
				'wcefp_*'
			],
		}],
		// Relax some rules for WordPress development
		'no-mixed-spaces-and-tabs': 'warn',
		'no-inner-declarations': 'warn',
		'no-dupe-class-members': 'error',
	},
};