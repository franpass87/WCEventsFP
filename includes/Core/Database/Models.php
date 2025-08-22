<?php
/**
 * Database model stubs for missing classes
 * 
 * @package WCEFP
 * @subpackage Core\Database\Models
 * @since 2.0.1
 */

namespace WCEFP\Core\Database\Models;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Model class
 */
abstract class BaseModel {
    
    protected $table_name;
    protected $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
}

/**
 * Occurrence Model stub
 */
class OccurrenceModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_occurrences';
    }
}

/**
 * Booking Model stub
 */
class BookingModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_bookings';
    }
}

/**
 * Voucher Model stub
 */
class VoucherModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_vouchers';
    }
}

/**
 * Analytics Model stub
 */
class AnalyticsModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->table_name = $this->wpdb->prefix . 'wcefp_analytics';
    }
}