<?php
/**
 * DateTime Helper Utility
 * 
 * Handles date/time operations, timezone conversions, and formatting
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage Utils
 * @since 2.2.0
 */

namespace WCEFP\Utils;

class DateTimeHelper {
    
    private $site_timezone;
    private $date_format;
    private $time_format;
    
    public function __construct() {
        $this->site_timezone = wp_timezone();
        $this->date_format = get_option('date_format');
        $this->time_format = get_option('time_format');
    }
    
    /**
     * Convert datetime to site timezone
     *
     * @param string|DateTime $datetime DateTime to convert
     * @param string $from_timezone Source timezone
     * @return DateTime Converted datetime
     */
    public function to_site_timezone($datetime, $from_timezone = 'UTC') {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime, new \DateTimeZone($from_timezone));
        }
        
        $datetime->setTimezone($this->site_timezone);
        return $datetime;
    }
    
    /**
     * Convert datetime to UTC
     *
     * @param string|DateTime $datetime DateTime to convert
     * @param string $from_timezone Source timezone
     * @return DateTime UTC datetime
     */
    public function to_utc($datetime, $from_timezone = null) {
        if (is_string($datetime)) {
            $from_timezone = $from_timezone ?: $this->site_timezone->getName();
            $datetime = new \DateTime($datetime, new \DateTimeZone($from_timezone));
        }
        
        $datetime->setTimezone(new \DateTimeZone('UTC'));
        return $datetime;
    }
    
    /**
     * Format datetime for display
     *
     * @param string|DateTime $datetime DateTime to format
     * @param string $format Format string
     * @param bool $convert_timezone Whether to convert to site timezone
     * @return string Formatted datetime
     */
    public function format_datetime($datetime, $format = null, $convert_timezone = true) {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }
        
        if ($convert_timezone) {
            $datetime = $this->to_site_timezone($datetime);
        }
        
        $format = $format ?: $this->date_format . ' ' . $this->time_format;
        return $datetime->format($format);
    }
    
    /**
     * Get relative time string (e.g., "2 hours ago", "in 3 days")
     *
     * @param string|DateTime $datetime DateTime to compare
     * @param DateTime $reference_time Reference time (default: now)
     * @return string Relative time string
     */
    public function get_relative_time($datetime, $reference_time = null) {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }
        
        if (!$reference_time) {
            $reference_time = new \DateTime('now', $this->site_timezone);
        }
        
        $interval = $reference_time->diff($datetime);
        $is_future = $datetime > $reference_time;
        
        // Calculate total time difference
        if ($interval->y > 0) {
            $unit = $interval->y;
            $text = _n('year', 'years', $unit, 'wceventsfp');
        } elseif ($interval->m > 0) {
            $unit = $interval->m;
            $text = _n('month', 'months', $unit, 'wceventsfp');
        } elseif ($interval->d > 0) {
            $unit = $interval->d;
            $text = _n('day', 'days', $unit, 'wceventsfp');
        } elseif ($interval->h > 0) {
            $unit = $interval->h;
            $text = _n('hour', 'hours', $unit, 'wceventsfp');
        } elseif ($interval->i > 0) {
            $unit = $interval->i;
            $text = _n('minute', 'minutes', $unit, 'wceventsfp');
        } else {
            return $is_future ? 
                __('in a moment', 'wceventsfp') : 
                __('just now', 'wceventsfp');
        }
        
        if ($is_future) {
            return sprintf(__('in %d %s', 'wceventsfp'), $unit, $text);
        } else {
            return sprintf(__('%d %s ago', 'wceventsfp'), $unit, $text);
        }
    }
    
    /**
     * Parse various datetime formats
     *
     * @param string $datetime_string DateTime string
     * @param string $timezone Timezone for parsing
     * @return DateTime|false Parsed datetime or false on failure
     */
    public function parse_datetime($datetime_string, $timezone = null) {
        $timezone = $timezone ? new \DateTimeZone($timezone) : $this->site_timezone;
        
        // Common formats to try
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s\Z',
            'c', // ISO 8601
            'r', // RFC 2822
        ];
        
        foreach ($formats as $format) {
            $datetime = \DateTime::createFromFormat($format, $datetime_string, $timezone);
            if ($datetime !== false) {
                return $datetime;
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($datetime_string);
        if ($timestamp !== false) {
            $datetime = new \DateTime('@' . $timestamp);
            $datetime->setTimezone($timezone);
            return $datetime;
        }
        
        return false;
    }
    
    /**
     * Generate time slots for a given date range
     *
     * @param DateTime $start_date Start date
     * @param DateTime $end_date End date
     * @param int $duration_minutes Duration of each slot in minutes
     * @param array $restrictions Time restrictions
     * @return array Array of time slots
     */
    public function generate_time_slots($start_date, $end_date, $duration_minutes = 60, $restrictions = []) {
        $slots = [];
        $current = clone $start_date;
        $interval = new \DateInterval('PT' . $duration_minutes . 'M');
        
        // Default restrictions
        $default_restrictions = [
            'start_time' => '09:00',
            'end_time' => '18:00',
            'excluded_days' => [], // 0 = Sunday, 1 = Monday, etc.
            'excluded_dates' => [],
            'break_times' => [] // ['12:00-13:00'] lunch break
        ];
        
        $restrictions = wp_parse_args($restrictions, $default_restrictions);
        
        while ($current <= $end_date) {
            // Check if day is excluded
            if (in_array($current->format('w'), $restrictions['excluded_days'])) {
                $current->add(new \DateInterval('P1D'));
                $current->setTime(
                    intval(explode(':', $restrictions['start_time'])[0]),
                    intval(explode(':', $restrictions['start_time'])[1])
                );
                continue;
            }
            
            // Check if specific date is excluded
            if (in_array($current->format('Y-m-d'), $restrictions['excluded_dates'])) {
                $current->add(new \DateInterval('P1D'));
                $current->setTime(
                    intval(explode(':', $restrictions['start_time'])[0]),
                    intval(explode(':', $restrictions['start_time'])[1])
                );
                continue;
            }
            
            // Check if within working hours
            $current_time = $current->format('H:i');
            if ($current_time >= $restrictions['start_time'] && 
                $current_time < $restrictions['end_time']) {
                
                // Check if not in break time
                $in_break = false;
                foreach ($restrictions['break_times'] as $break) {
                    list($break_start, $break_end) = explode('-', $break);
                    if ($current_time >= $break_start && $current_time < $break_end) {
                        $in_break = true;
                        break;
                    }
                }
                
                if (!$in_break) {
                    $slot_end = clone $current;
                    $slot_end->add($interval);
                    
                    $slots[] = [
                        'start' => clone $current,
                        'end' => $slot_end,
                        'formatted_start' => $this->format_datetime($current, 'Y-m-d H:i:s', false),
                        'formatted_time' => $current->format('g:i A'),
                        'formatted_date' => $current->format($this->date_format),
                        'day_of_week' => $current->format('l'),
                        'is_weekend' => in_array($current->format('w'), [0, 6])
                    ];
                }
            }
            
            $current->add($interval);
            
            // If we've gone past end time for the day, move to next day
            if ($current->format('H:i') >= $restrictions['end_time']) {
                $current->add(new \DateInterval('P1D'));
                $current->setTime(
                    intval(explode(':', $restrictions['start_time'])[0]),
                    intval(explode(':', $restrictions['start_time'])[1])
                );
            }
        }
        
        return $slots;
    }
    
    /**
     * Calculate business hours between two dates
     *
     * @param DateTime $start_date Start date
     * @param DateTime $end_date End date
     * @param array $business_hours Business hours config
     * @return int Total business hours
     */
    public function calculate_business_hours($start_date, $end_date, $business_hours = []) {
        $default_hours = [
            'monday' => ['09:00', '18:00'],
            'tuesday' => ['09:00', '18:00'],
            'wednesday' => ['09:00', '18:00'],
            'thursday' => ['09:00', '18:00'],
            'friday' => ['09:00', '18:00'],
            'saturday' => ['09:00', '13:00'],
            'sunday' => null // Closed
        ];
        
        $business_hours = wp_parse_args($business_hours, $default_hours);
        
        $total_hours = 0;
        $current = clone $start_date;
        $day_names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        
        while ($current <= $end_date) {
            $day_name = $day_names[$current->format('w')];
            $day_hours = $business_hours[$day_name];
            
            if ($day_hours) {
                list($open_time, $close_time) = $day_hours;
                
                $day_start = clone $current;
                $day_start->setTimeFromTimeString($open_time);
                
                $day_end = clone $current;
                $day_end->setTimeFromTimeString($close_time);
                
                // Calculate overlap with our time period
                $period_start = max($start_date, $day_start);
                $period_end = min($end_date, $day_end);
                
                if ($period_start < $period_end) {
                    $interval = $period_start->diff($period_end);
                    $total_hours += $interval->h + ($interval->i / 60);
                }
            }
            
            $current->add(new \DateInterval('P1D'));
            $current->setTime(0, 0, 0);
        }
        
        return $total_hours;
    }
    
    /**
     * Add business days to a date
     *
     * @param DateTime $date Starting date
     * @param int $business_days Number of business days to add
     * @param array $excluded_dates Dates to exclude (holidays, etc.)
     * @return DateTime New date
     */
    public function add_business_days($date, $business_days, $excluded_dates = []) {
        $result = clone $date;
        $added_days = 0;
        
        while ($added_days < $business_days) {
            $result->add(new \DateInterval('P1D'));
            
            // Skip weekends
            $day_of_week = $result->format('w');
            if ($day_of_week == 0 || $day_of_week == 6) {
                continue;
            }
            
            // Skip excluded dates
            if (in_array($result->format('Y-m-d'), $excluded_dates)) {
                continue;
            }
            
            $added_days++;
        }
        
        return $result;
    }
    
    /**
     * Check if a date/time is in the past
     *
     * @param string|DateTime $datetime DateTime to check
     * @param int $buffer_minutes Buffer minutes (consider past if within this time)
     * @return bool True if in the past
     */
    public function is_past($datetime, $buffer_minutes = 0) {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }
        
        $now = new \DateTime('now', $this->site_timezone);
        
        if ($buffer_minutes > 0) {
            $now->add(new \DateInterval('PT' . $buffer_minutes . 'M'));
        }
        
        return $datetime < $now;
    }
    
    /**
     * Check if a date/time is within business hours
     *
     * @param DateTime $datetime DateTime to check
     * @param array $business_hours Business hours configuration
     * @return bool True if within business hours
     */
    public function is_business_hours($datetime, $business_hours = []) {
        $default_hours = [
            'monday' => ['09:00', '18:00'],
            'tuesday' => ['09:00', '18:00'],
            'wednesday' => ['09:00', '18:00'],
            'thursday' => ['09:00', '18:00'],
            'friday' => ['09:00', '18:00'],
            'saturday' => ['09:00', '13:00'],
            'sunday' => null
        ];
        
        $business_hours = wp_parse_args($business_hours, $default_hours);
        $day_names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $day_name = $day_names[$datetime->format('w')];
        
        $day_hours = $business_hours[$day_name];
        if (!$day_hours) {
            return false; // Day is closed
        }
        
        list($open_time, $close_time) = $day_hours;
        $current_time = $datetime->format('H:i');
        
        return $current_time >= $open_time && $current_time < $close_time;
    }
    
    /**
     * Format duration in human-readable format
     *
     * @param int $minutes Duration in minutes
     * @return string Formatted duration
     */
    public function format_duration($minutes) {
        if ($minutes < 60) {
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'wceventsfp'), $minutes);
        }
        
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        
        if ($remaining_minutes == 0) {
            return sprintf(_n('%d hour', '%d hours', $hours, 'wceventsfp'), $hours);
        }
        
        return sprintf(
            __('%d hours %d minutes', 'wceventsfp'),
            $hours,
            $remaining_minutes
        );
    }
    
    /**
     * Get timezone list for admin settings
     *
     * @return array Timezone options
     */
    public static function get_timezone_list() {
        $timezones = [];
        $timezone_identifiers = \DateTimeZone::listIdentifiers();
        
        foreach ($timezone_identifiers as $timezone) {
            $tz = new \DateTimeZone($timezone);
            $now = new \DateTime('now', $tz);
            $offset = $now->getOffset() / 3600;
            $offset_string = sprintf('%+03d:00', $offset);
            
            $timezones[$timezone] = sprintf(
                '(UTC%s) %s',
                $offset_string,
                str_replace('_', ' ', $timezone)
            );
        }
        
        return $timezones;
    }
}