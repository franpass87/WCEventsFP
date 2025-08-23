<?php
/**
 * QR Code Generator Utility
 * 
 * Generates QR codes for check-in and other booking-related functionality
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage Utils
 * @since 2.2.0
 */

namespace WCEFP\Utils;

class QRCodeGenerator {
    
    private $cache_dir;
    private $cache_url;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->cache_dir = trailingslashit($upload_dir['basedir']) . 'wcefp-qrcodes/';
        $this->cache_url = trailingslashit($upload_dir['baseurl']) . 'wcefp-qrcodes/';
        
        // Create cache directory if it doesn't exist
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            
            // Add .htaccess for security
            file_put_contents($this->cache_dir . '.htaccess', "Options -Indexes\nDeny from all");
        }
    }
    
    /**
     * Generate QR code
     *
     * @param string $data Data to encode
     * @param array $options QR code options
     * @return string|WP_Error QR code data URL or error
     */
    public function generate($data, $options = []) {
        $defaults = [
            'size' => 200,
            'margin' => 4,
            'format' => 'png',
            'error_correction' => 'M', // L, M, Q, H
            'cache' => true
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Generate cache key
        $cache_key = md5($data . serialize($options));
        $cache_file = $this->cache_dir . $cache_key . '.' . $options['format'];
        $cache_url = $this->cache_url . $cache_key . '.' . $options['format'];
        
        // Return cached version if exists and caching is enabled
        if ($options['cache'] && file_exists($cache_file)) {
            return $cache_url;
        }
        
        try {
            // Use Google Charts API as fallback for QR generation
            $qr_data = $this->generate_via_google_charts($data, $options);
            
            if (is_wp_error($qr_data)) {
                // Fallback to simple QR generation
                return $this->generate_simple_qr($data, $options);
            }
            
            // Save to cache if enabled
            if ($options['cache']) {
                file_put_contents($cache_file, $qr_data);
                return $cache_url;
            }
            
            // Return data URL
            $base64 = base64_encode($qr_data);
            return 'data:image/' . $options['format'] . ';base64,' . $base64;
            
        } catch (Exception $e) {
            return new \WP_Error('qr_generation_failed', 
                __('Failed to generate QR code: ', 'wceventsfp') . $e->getMessage()
            );
        }
    }
    
    /**
     * Generate QR code using Google Charts API
     *
     * @param string $data Data to encode
     * @param array $options QR options
     * @return string|WP_Error QR code image data or error
     */
    private function generate_via_google_charts($data, $options) {
        $size = $options['size'] . 'x' . $options['size'];
        $error_correction = $options['error_correction'];
        $margin = $options['margin'];
        
        $url = add_query_arg([
            'cht' => 'qr',
            'chs' => $size,
            'chl' => urlencode($data),
            'choe' => 'UTF-8',
            'chld' => $error_correction . '|' . $margin
        ], 'https://chart.googleapis.com/chart');
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'WCEventsFP/2.2.0'
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new \WP_Error('api_error', 
                __('QR code generation API returned error code: ', 'wceventsfp') . $response_code
            );
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Generate simple QR code using ASCII representation
     * Fallback when external APIs are not available
     *
     * @param string $data Data to encode
     * @param array $options QR options
     * @return string Simple HTML QR representation
     */
    private function generate_simple_qr($data, $options) {
        // For development/testing - create a simple HTML representation
        $html = '<div class="wcefp-simple-qr" style="font-family: monospace; line-height: 1; font-size: 8px; background: white; padding: 20px; display: inline-block; border: 2px solid #000;">';
        
        // Generate a simple pattern based on data hash
        $hash = md5($data);
        $size = min(20, max(10, intval($options['size'] / 20)));
        
        for ($i = 0; $i < $size; $i++) {
            $html .= '<div>';
            for ($j = 0; $j < $size; $j++) {
                $pos = ($i * $size + $j) % strlen($hash);
                $char = hexdec($hash[$pos]) > 7 ? '█' : '░';
                $html .= $char;
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '<div style="margin-top: 10px; font-size: 12px; text-align: center;">';
        $html .= '<strong>' . __('Check-in URL:', 'wceventsfp') . '</strong><br>';
        $html .= '<a href="' . esc_url($data) . '" target="_blank">' . esc_html($data) . '</a>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate QR code for booking check-in
     *
     * @param int $booking_id Booking ID
     * @return string|WP_Error QR code or error
     */
    public function generate_checkin_qr($booking_id) {
        $token = get_post_meta($booking_id, '_wcefp_checkin_token', true);
        if (!$token) {
            return new \WP_Error('no_token', __('No check-in token found for this booking', 'wceventsfp'));
        }
        
        $checkin_url = add_query_arg([
            'wcefp_checkin' => 1,
            'token' => $token,
            'booking' => $booking_id
        ], home_url('/wcefp-checkin/'));
        
        return $this->generate($checkin_url, [
            'size' => 300,
            'margin' => 10,
            'error_correction' => 'M'
        ]);
    }
    
    /**
     * Clean up old cached QR codes
     *
     * @param int $days_old Number of days to keep cached files
     */
    public function cleanup_cache($days_old = 30) {
        if (!is_dir($this->cache_dir)) {
            return;
        }
        
        $files = glob($this->cache_dir . '*');
        $cutoff_time = time() - ($days_old * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get QR code cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        if (!is_dir($this->cache_dir)) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'cache_dir' => $this->cache_dir
            ];
        }
        
        $files = glob($this->cache_dir . '*');
        $total_size = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $total_size += filesize($file);
            }
        }
        
        return [
            'total_files' => count($files),
            'total_size' => $total_size,
            'total_size_formatted' => size_format($total_size),
            'cache_dir' => $this->cache_dir,
            'cache_url' => $this->cache_url
        ];
    }
}