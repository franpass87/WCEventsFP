/**
 * Meeting Points Admin JavaScript
 * 
 * Handles geolocation, geocoding, and map integration for meeting points
 */
(function($) {
    'use strict';
    
    let meetingPointsAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initMap();
        },
        
        bindEvents: function() {
            $('#wcefp-geocode-address').on('click', this.geocodeAddress.bind(this));
            $('#wcefp-locate-me').on('click', this.getCurrentLocation.bind(this));
            
            // Update map when coordinates change
            $('#wcefp_latitude, #wcefp_longitude').on('change', this.updateMapPreview.bind(this));
            
            // Auto-update coordinates when address changes
            $('#wcefp_address, #wcefp_city, #wcefp_country').on('blur', this.maybeGeocodeAddress.bind(this));
        },
        
        initMap: function() {
            const lat = $('#wcefp_latitude').val();
            const lng = $('#wcefp_longitude').val();
            
            if (lat && lng && window.google) {
                this.renderMap(parseFloat(lat), parseFloat(lng));
            }
        },
        
        geocodeAddress: function(e) {
            e.preventDefault();
            
            const address = this.buildAddressString();
            if (!address) {
                alert(wcefp_mp_admin.i18n.geocoding_error + ': ' + 'Indirizzo mancante');
                return;
            }
            
            // Use browser's geocoding API if available
            if (navigator.geolocation && window.fetch) {
                this.geocodeWithFetch(address);
            } else {
                // Fallback to Google Geocoding (requires API key)
                this.geocodeWithGoogle(address);
            }
        },
        
        geocodeWithFetch: function(address) {
            const button = $('#wcefp-geocode-address');
            button.prop('disabled', true).text('Geocodificando...');
            
            // Use OpenStreetMap Nominatim (free alternative to Google)
            const url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=1&q=${encodeURIComponent(address)}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const result = data[0];
                        const lat = parseFloat(result.lat);
                        const lng = parseFloat(result.lon);
                        
                        $('#wcefp_latitude').val(lat);
                        $('#wcefp_longitude').val(lng);
                        
                        this.updateMapPreview();
                        this.showSuccess(wcefp_mp_admin.i18n.location_success);
                    } else {
                        this.showError(wcefp_mp_admin.i18n.geocoding_error);
                    }
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    this.showError(wcefp_mp_admin.i18n.geocoding_error);
                })
                .finally(() => {
                    button.prop('disabled', false).text('📍 Geocodifica Indirizzo');
                });
        },
        
        geocodeWithGoogle: function(address) {
            // This would require Google Maps API key
            // For now, show info message
            alert('Google Geocoding richiede una chiave API. Usa "Usa la Mia Posizione" o inserisci manualmente le coordinate.');
        },
        
        getCurrentLocation: function(e) {
            e.preventDefault();
            
            if (!navigator.geolocation) {
                alert('Geolocalizzazione non supportata dal browser');
                return;
            }
            
            const button = $('#wcefp-locate-me');
            button.prop('disabled', true).text('Ottenendo posizione...');
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    $('#wcefp_latitude').val(lat);
                    $('#wcefp_longitude').val(lng);
                    
                    this.updateMapPreview();
                    this.showSuccess(wcefp_mp_admin.i18n.location_success);
                    
                    button.prop('disabled', false).text('📍 Usa la Mia Posizione');
                },
                (error) => {
                    console.error('Geolocation error:', error);
                    this.showError(wcefp_mp_admin.i18n.location_error + ': ' + error.message);
                    button.prop('disabled', false).text('📍 Usa la Mia Posizione');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        },
        
        maybeGeocodeAddress: function() {
            // Only auto-geocode if coordinates are empty
            const lat = $('#wcefp_latitude').val();
            const lng = $('#wcefp_longitude').val();
            
            if (!lat || !lng) {
                const address = this.buildAddressString();
                if (address && address.length > 10) { // Reasonable address length
                    setTimeout(() => {
                        this.geocodeWithFetch(address);
                    }, 1000); // Debounce
                }
            }
        },
        
        updateMapPreview: function() {
            const lat = parseFloat($('#wcefp_latitude').val());
            const lng = parseFloat($('#wcefp_longitude').val());
            const zoom = parseInt($('#wcefp_map_zoom').val()) || 15;
            
            if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
                $('#wcefp-map-preview').html('<p>Inserisci coordinate valide per visualizzare la mappa</p>');
                return;
            }
            
            // Simple map preview using OpenStreetMap tiles
            this.renderSimpleMap(lat, lng, zoom);
        },
        
        renderSimpleMap: function(lat, lng, zoom) {
            const mapContainer = $('#wcefp-map-preview');
            
            // Create simple map preview with tile image
            const tileUrl = `https://tile.openstreetmap.org/${zoom}/${this.lng2tile(lng, zoom)}/${this.lat2tile(lat, zoom)}.png`;
            
            mapContainer.html(`
                <div style="position: relative; width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                    <div style="margin-bottom: 10px;">
                        <strong>📍 Posizione: ${lat.toFixed(6)}, ${lng.toFixed(6)}</strong>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        Zoom: ${zoom} | 
                        <a href="https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}&zoom=${zoom}" target="_blank" style="color: #0073aa;">Visualizza su OpenStreetMap</a>
                    </div>
                    <div style="margin-top: 10px; font-size: 11px; color: #999;">
                        Per una mappa interattiva completa, considera l'integrazione con Google Maps API
                    </div>
                </div>
            `);
        },
        
        renderMap: function(lat, lng) {
            // This would render Google Maps if API is available
            this.renderSimpleMap(lat, lng, parseInt($('#wcefp_map_zoom').val()) || 15);
        },
        
        buildAddressString: function() {
            const parts = [
                $('#wcefp_address').val(),
                $('#wcefp_city').val(), 
                $('#wcefp_country').val()
            ].filter(part => part && part.trim());
            
            return parts.join(', ');
        },
        
        // Helper functions for tile coordinates
        lng2tile: function(lng, zoom) {
            return Math.floor((lng + 180) / 360 * Math.pow(2, zoom));
        },
        
        lat2tile: function(lat, zoom) {
            return Math.floor((1 - Math.log(Math.tan(lat * Math.PI / 180) + 1 / Math.cos(lat * Math.PI / 180)) / Math.PI) / 2 * Math.pow(2, zoom));
        },
        
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            // Remove existing notices
            $('.wcefp-notice').remove();
            
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $(`
                <div class="notice ${noticeClass} wcefp-notice is-dismissible" style="margin: 10px 0;">
                    <p>${message}</p>
                </div>
            `);
            
            $('#wcefp-map-preview').before(notice);
            
            // Auto-dismiss after 3 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 3000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        meetingPointsAdmin.init();
    });
    
})(jQuery);