<?php
/**
 * Image Optimizer
 * 
 * Handles image optimization, lazy loading, and responsive images
 * 
 * @package WCEFP
 * @subpackage Core\Performance
 * @since 2.2.0
 */

namespace WCEFP\Core\Performance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Image Optimizer Class
 */
class ImageOptimizer {
    
    /**
     * Default image sizes for experiences
     */
    const IMAGE_SIZES = [
        'wcefp_card' => [400, 300, true],
        'wcefp_hero' => [1200, 600, true],
        'wcefp_gallery_thumb' => [200, 150, true],
        'wcefp_gallery_full' => [800, 600, true]
    ];
    
    /**
     * Initialize image optimization
     */
    public static function init() {
        // Register custom image sizes
        add_action('after_setup_theme', [__CLASS__, 'register_image_sizes']);
        
        // Lazy loading filters
        add_filter('wp_get_attachment_image', [__CLASS__, 'add_lazy_loading'], 10, 5);
        add_filter('the_content', [__CLASS__, 'add_lazy_loading_to_content']);
        
        // Responsive images
        add_filter('wp_calculate_image_srcset', [__CLASS__, 'optimize_srcset'], 10, 5);
        
        // WebP support
        add_filter('wp_generate_attachment_metadata', [__CLASS__, 'generate_webp_versions'], 10, 2);
    }
    
    /**
     * Register custom image sizes
     */
    public static function register_image_sizes() {
        foreach (self::IMAGE_SIZES as $name => $size) {
            add_image_size($name, $size[0], $size[1], $size[2]);
        }
    }
    
    /**
     * Add lazy loading to attachment images
     * 
     * @param string $html Image HTML
     * @param int $attachment_id Attachment ID
     * @param string|array $size Image size
     * @param bool $icon Whether to use icon
     * @param array $attr Image attributes
     * @return string Modified HTML
     */
    public static function add_lazy_loading($html, $attachment_id, $size, $icon, $attr) {
        // Skip if lazy loading is disabled
        if (isset($attr['loading']) && $attr['loading'] === 'eager') {
            return $html;
        }
        
        // Skip for admin or if already has data-src
        if (is_admin() || strpos($html, 'data-src') !== false) {
            return $html;
        }
        
        // Extract src attribute
        if (preg_match('/src="([^"]+)"/', $html, $matches)) {
            $src = $matches[1];
            
            // Generate placeholder (1x1 transparent pixel or low-quality image)
            $placeholder = self::get_placeholder_image($attachment_id, $size);
            
            // Replace src with placeholder and add data-src
            $html = str_replace('src="' . $src . '"', 'src="' . $placeholder . '" data-src="' . $src . '"', $html);
            
            // Add lazy class
            if (strpos($html, 'class="') !== false) {
                $html = str_replace('class="', 'class="lazy ', $html);
            } else {
                $html = str_replace('<img ', '<img class="lazy" ', $html);
            }
            
            // Add loading attribute for browser-native lazy loading fallback
            $html = str_replace('<img ', '<img loading="lazy" ', $html);
        }
        
        return $html;
    }
    
    /**
     * Add lazy loading to content images
     * 
     * @param string $content Post content
     * @return string Modified content
     */
    public static function add_lazy_loading_to_content($content) {
        // Skip if not needed
        if (is_admin() || is_feed() || empty($content)) {
            return $content;
        }
        
        // Process all img tags
        $content = preg_replace_callback(
            '/<img\s+([^>]+)>/i',
            function($matches) {
                $img_tag = $matches[0];
                $attributes = $matches[1];
                
                // Skip if already has data-src or loading=eager
                if (strpos($attributes, 'data-src') !== false || 
                    strpos($attributes, 'loading="eager"') !== false) {
                    return $img_tag;
                }
                
                // Extract src
                if (preg_match('/src="([^"]+)"/', $attributes, $src_matches)) {
                    $src = $src_matches[1];
                    $placeholder = self::get_generic_placeholder();
                    
                    // Replace src with placeholder
                    $new_attributes = str_replace('src="' . $src . '"', 'src="' . $placeholder . '" data-src="' . $src . '"', $attributes);
                    
                    // Add lazy class
                    if (strpos($new_attributes, 'class="') !== false) {
                        $new_attributes = preg_replace('/class="([^"]*)"/', 'class="$1 lazy"', $new_attributes);
                    } else {
                        $new_attributes .= ' class="lazy"';
                    }
                    
                    // Add loading attribute
                    if (strpos($new_attributes, 'loading=') === false) {
                        $new_attributes .= ' loading="lazy"';
                    }
                    
                    return '<img ' . $new_attributes . '>';
                }
                
                return $img_tag;
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Get optimized experience image
     * 
     * @param int $product_id Product ID
     * @param string $size Image size
     * @param bool $lazy_load Enable lazy loading
     * @param array $attributes Additional attributes
     * @return string Image HTML
     */
    public static function get_experience_image($product_id, $size = 'wcefp_card', $lazy_load = true, $attributes = []) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return self::get_fallback_image($size, $lazy_load, $attributes);
        }
        
        // Get featured image
        $image_id = $product->get_image_id();
        if (!$image_id) {
            // Try gallery images
            $gallery_ids = $product->get_gallery_image_ids();
            if (!empty($gallery_ids)) {
                $image_id = $gallery_ids[0];
            }
        }
        
        if (!$image_id) {
            return self::get_fallback_image($size, $lazy_load, $attributes);
        }
        
        // Prepare attributes
        $default_attributes = [
            'alt' => $product->get_name(),
            'title' => $product->get_name(),
            'class' => 'wcefp-experience-image'
        ];
        
        if ($lazy_load) {
            $default_attributes['loading'] = 'lazy';
        } else {
            $default_attributes['loading'] = 'eager';
        }
        
        $attributes = array_merge($default_attributes, $attributes);
        
        return wp_get_attachment_image($image_id, $size, false, $attributes);
    }
    
    /**
     * Get responsive image with multiple sources
     * 
     * @param int $image_id Image ID
     * @param string $size Image size
     * @param array $attributes Image attributes
     * @return string Picture element HTML
     */
    public static function get_responsive_image($image_id, $size, $attributes = []) {
        if (!$image_id) {
            return '';
        }
        
        $image_meta = wp_get_attachment_metadata($image_id);
        if (!$image_meta) {
            return wp_get_attachment_image($image_id, $size, false, $attributes);
        }
        
        $sizes = self::get_responsive_sizes($size);
        $sources = [];
        
        // Generate WebP sources if available
        foreach ($sizes as $breakpoint => $img_size) {
            $webp_url = self::get_webp_url($image_id, $img_size);
            if ($webp_url) {
                $sources[] = [
                    'srcset' => $webp_url,
                    'media' => $breakpoint,
                    'type' => 'image/webp'
                ];
            }
        }
        
        // Generate regular sources
        foreach ($sizes as $breakpoint => $img_size) {
            $img_url = wp_get_attachment_image_url($image_id, $img_size);
            if ($img_url) {
                $sources[] = [
                    'srcset' => $img_url,
                    'media' => $breakpoint,
                    'type' => 'image/jpeg'
                ];
            }
        }
        
        // Build picture element
        $picture_html = '<picture>';
        
        foreach ($sources as $source) {
            $picture_html .= '<source';
            foreach ($source as $attr => $value) {
                $picture_html .= ' ' . $attr . '="' . esc_attr($value) . '"';
            }
            $picture_html .= '>';
        }
        
        // Fallback image
        $picture_html .= wp_get_attachment_image($image_id, $size, false, $attributes);
        $picture_html .= '</picture>';
        
        return $picture_html;
    }
    
    /**
     * Get placeholder image for lazy loading
     * 
     * @param int $attachment_id Attachment ID
     * @param string $size Image size
     * @return string Placeholder image URL
     */
    private static function get_placeholder_image($attachment_id, $size) {
        // Try to generate a low-quality placeholder
        $image_meta = wp_get_attachment_metadata($attachment_id);
        
        if ($image_meta && isset($image_meta['width'], $image_meta['height'])) {
            $width = $image_meta['width'];
            $height = $image_meta['height'];
            
            // Calculate placeholder dimensions
            if ($size === 'wcefp_card') {
                $placeholder_width = 400;
                $placeholder_height = 300;
            } elseif ($size === 'wcefp_hero') {
                $placeholder_width = 1200;
                $placeholder_height = 600;
            } else {
                $placeholder_width = min(400, $width);
                $placeholder_height = round($placeholder_width * ($height / $width));
            }
            
            // Generate data URL for placeholder
            return self::generate_placeholder_data_url($placeholder_width, $placeholder_height);
        }
        
        return self::get_generic_placeholder();
    }
    
    /**
     * Get generic placeholder image
     * 
     * @return string Placeholder image URL
     */
    private static function get_generic_placeholder() {
        // 1x1 transparent pixel
        return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1" fill="transparent"/></svg>');
    }
    
    /**
     * Generate placeholder data URL
     * 
     * @param int $width Width
     * @param int $height Height
     * @return string Data URL
     */
    private static function generate_placeholder_data_url($width, $height) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '">';
        $svg .= '<rect width="100%" height="100%" fill="#f0f0f0"/>';
        $svg .= '<text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" fill="#ccc" font-family="Arial, sans-serif" font-size="14">Loading...</text>';
        $svg .= '</svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Get fallback image when no product image available
     * 
     * @param string $size Image size
     * @param bool $lazy_load Enable lazy loading
     * @param array $attributes Image attributes
     * @return string Fallback image HTML
     */
    private static function get_fallback_image($size, $lazy_load, $attributes) {
        $plugin_url = defined('WCEFP_PLUGIN_URL') ? WCEFP_PLUGIN_URL : plugin_dir_url(dirname(dirname(__DIR__)));
        $fallback_url = $plugin_url . 'assets/images/placeholder-experience.jpg';
        
        $default_attributes = [
            'alt' => __('Experience Image', 'wceventsfp'),
            'class' => 'wcefp-experience-image wcefp-fallback-image'
        ];
        
        if ($lazy_load) {
            $placeholder = self::get_generic_placeholder();
            $default_attributes['src'] = $placeholder;
            $default_attributes['data-src'] = $fallback_url;
            $default_attributes['class'] .= ' lazy';
            $default_attributes['loading'] = 'lazy';
        } else {
            $default_attributes['src'] = $fallback_url;
            $default_attributes['loading'] = 'eager';
        }
        
        $attributes = array_merge($default_attributes, $attributes);
        
        $html = '<img';
        foreach ($attributes as $attr => $value) {
            $html .= ' ' . $attr . '="' . esc_attr($value) . '"';
        }
        $html .= '>';
        
        return $html;
    }
    
    /**
     * Get responsive sizes configuration
     * 
     * @param string $base_size Base image size
     * @return array Responsive sizes
     */
    private static function get_responsive_sizes($base_size) {
        $sizes = [
            '(max-width: 768px)' => 'wcefp_card',
            '(max-width: 1200px)' => 'wcefp_hero',
            '(min-width: 1201px)' => 'full'
        ];
        
        if ($base_size === 'wcefp_card') {
            $sizes = [
                '(max-width: 480px)' => 'wcefp_card',
                '(min-width: 481px)' => 'wcefp_hero'
            ];
        }
        
        return $sizes;
    }
    
    /**
     * Get WebP version URL if available
     * 
     * @param int $image_id Image ID
     * @param string $size Image size
     * @return string|false WebP URL or false
     */
    private static function get_webp_url($image_id, $size) {
        // This would integrate with WebP generation plugins
        // For now, return false to use regular images
        return false;
    }
    
    /**
     * Optimize srcset for better performance
     * 
     * @param array $sources Srcset sources
     * @param array $size_array Size array
     * @param string $image_url Image URL
     * @param array $image_meta Image metadata
     * @param int $attachment_id Attachment ID
     * @return array Optimized sources
     */
    public static function optimize_srcset($sources, $size_array, $image_url, $image_meta, $attachment_id) {
        // Remove excessive srcset entries for better performance
        if (count($sources) > 4) {
            // Keep only the most important sizes
            $important_widths = [400, 800, 1200, 1600];
            $filtered_sources = [];
            
            foreach ($sources as $width => $source) {
                $closest_important = null;
                $min_diff = PHP_INT_MAX;
                
                foreach ($important_widths as $important_width) {
                    $diff = abs($width - $important_width);
                    if ($diff < $min_diff) {
                        $min_diff = $diff;
                        $closest_important = $important_width;
                    }
                }
                
                if (!isset($filtered_sources[$closest_important]) || $min_diff < 50) {
                    $filtered_sources[$closest_important] = $source;
                }
            }
            
            return $filtered_sources;
        }
        
        return $sources;
    }
    
    /**
     * Generate WebP versions of images
     * 
     * @param array $metadata Image metadata
     * @param int $attachment_id Attachment ID
     * @return array Modified metadata
     */
    public static function generate_webp_versions($metadata, $attachment_id) {
        // This would integrate with image optimization plugins
        // Implementation depends on server capabilities and available plugins
        return $metadata;
    }
    
    /**
     * Preload critical images
     * 
     * @param array $image_urls Image URLs to preload
     */
    public static function preload_critical_images($image_urls) {
        foreach ($image_urls as $url) {
            echo '<link rel="preload" as="image" href="' . esc_url($url) . '">' . "\n";
        }
    }
}