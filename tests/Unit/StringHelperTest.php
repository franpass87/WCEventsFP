<?php
/**
 * Tests for StringHelper utility
 * 
 * @package WCEFP\Tests\Unit
 * @since 2.2.1
 */

use WCEFP\Utils\StringHelper;
use PHPUnit\Framework\TestCase;

class StringHelperTest extends TestCase {
    
    /**
     * Test safe_str with various input types
     */
    public function test_safe_str_with_various_inputs() {
        // String input - should return as-is
        $this->assertEquals('hello', StringHelper::safe_str('hello'));
        
        // Numeric inputs - should convert to string
        $this->assertEquals('123', StringHelper::safe_str(123));
        $this->assertEquals('123.45', StringHelper::safe_str(123.45));
        
        // Null input - should return empty string
        $this->assertEquals('', StringHelper::safe_str(null));
        
        // Array input - should return empty string
        $this->assertEquals('', StringHelper::safe_str(['test']));
        
        // Object input - should return empty string
        $this->assertEquals('', StringHelper::safe_str(new stdClass()));
        
        // Boolean inputs - should return empty string
        $this->assertEquals('', StringHelper::safe_str(true));
        $this->assertEquals('', StringHelper::safe_str(false));
    }
    
    /**
     * Test safe_strlen with various input types
     */
    public function test_safe_strlen_with_various_inputs() {
        // String input
        $this->assertEquals(5, StringHelper::safe_strlen('hello'));
        
        // Numeric inputs
        $this->assertEquals(3, StringHelper::safe_strlen(123));
        $this->assertEquals(6, StringHelper::safe_strlen(123.45));
        
        // Null input - should return 0
        $this->assertEquals(0, StringHelper::safe_strlen(null));
        
        // Array input - should return 0
        $this->assertEquals(0, StringHelper::safe_strlen(['test']));
        
        // Object input - should return 0
        $this->assertEquals(0, StringHelper::safe_strlen(new stdClass()));
    }
    
    /**
     * Test that global helper functions work
     */
    public function test_global_helper_functions() {
        // Test wcefp_safe_str function
        $this->assertEquals('test', wcefp_safe_str('test'));
        $this->assertEquals('', wcefp_safe_str(null));
        
        // Test wcefp_safe_strlen function
        $this->assertEquals(4, wcefp_safe_strlen('test'));
        $this->assertEquals(0, wcefp_safe_strlen(null));
    }
    
    /**
     * Test safe_trim with various inputs
     */
    public function test_safe_trim_with_various_inputs() {
        // Normal string
        $this->assertEquals('hello', StringHelper::safe_trim('  hello  '));
        
        // Null input
        $this->assertEquals('', StringHelper::safe_trim(null));
        
        // Array input
        $this->assertEquals('', StringHelper::safe_trim(['test']));
    }
    
    /**
     * Test safe_strpos with various inputs
     */
    public function test_safe_strpos_with_various_inputs() {
        // Normal operation
        $this->assertEquals(2, StringHelper::safe_strpos('hello', 'llo'));
        $this->assertFalse(StringHelper::safe_strpos('hello', 'xyz'));
        
        // Null inputs
        $this->assertFalse(StringHelper::safe_strpos(null, 'test'));
        $this->assertFalse(StringHelper::safe_strpos('test', null));
        $this->assertFalse(StringHelper::safe_strpos(null, null));
    }
    
    /**
     * Test safe_preg_match with various inputs
     */
    public function test_safe_preg_match_with_various_inputs() {
        // Normal operation
        $this->assertEquals(1, StringHelper::safe_preg_match('/^[a-z]+$/', 'hello'));
        $this->assertEquals(0, StringHelper::safe_preg_match('/^[0-9]+$/', 'hello'));
        
        // Null input
        $this->assertEquals(1, StringHelper::safe_preg_match('/^$/', null)); // Empty string matches empty pattern
        
        // Array input
        $this->assertEquals(1, StringHelper::safe_preg_match('/^$/', ['test'])); // Converted to empty string
    }
}