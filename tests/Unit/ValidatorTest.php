<?php

namespace WCEFP\Tests\Unit;

use Brain\Monkey\Functions;

// Load stub implementations of plugin classes
require_once WCEFP_TESTS_DIR . '/stubs/plugin-stubs.php';

/**
 * Tests for WCEFP_Validator class
 */
class ValidatorTest extends WCEFPTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Mock WordPress functions used by validator
        Functions\when('__')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('sanitize_email')->returnArg();
        Functions\when('is_email')->justReturn(true);
        Functions\when('get_post_type')->justReturn('product');
        Functions\when('wc_get_product')->justReturn(new class {
            public function get_id()
            {
                return 123;
            }
            public function get_type()
            {
                return 'wcefp_event';
            }
        });
    }

    public function test_validate_product_id_with_valid_id()
    {
        $result = \WCEFP_Validator::validate_product_id('123');
        $this->assertEquals(123, $result);
    }

    public function test_validate_product_id_with_invalid_id()
    {
        Functions\when('wc_get_product')->justReturn(null);

        $result = \WCEFP_Validator::validate_product_id('invalid');
        $this->assertFalse($result);
    }

    public function test_validate_capacity_within_range()
    {
        $result = \WCEFP_Validator::validate_capacity(50, 0, 100);
        $this->assertEquals(50, $result);
    }

    public function test_validate_capacity_outside_range()
    {
        $result = \WCEFP_Validator::validate_capacity(150, 0, 100);
        $this->assertFalse($result);
    }

    public function test_validate_email_valid()
    {
        $result = \WCEFP_Validator::validate_email('test@example.com');
        $this->assertEquals('test@example.com', $result);
    }

    public function test_validate_email_invalid()
    {
        Functions\when('is_email')->justReturn(false);

        $result = \WCEFP_Validator::validate_email('invalid-email');
        $this->assertFalse($result);
    }

    public function test_validate_bulk_success()
    {
        $data = [
            'product_id' => '123',
            'capacity' => '50',
            'email' => 'test@example.com'
        ];

        $rules = [
            'product_id' => ['method' => 'validate_product_id', 'required' => true],
            'capacity' => ['method' => 'validate_capacity', 'args' => [0, 100], 'required' => true],
            'email' => ['method' => 'validate_email', 'required' => false]
        ];

        $result = \WCEFP_Validator::validate_bulk($data, $rules);

        $this->assertIsArray($result);
        $this->assertEquals(123, $result['product_id']);
        $this->assertEquals(50, $result['capacity']);
        $this->assertEquals('test@example.com', $result['email']);
    }

    public function test_validate_bulk_missing_required_field()
    {
        $data = [
            'capacity' => '50'
        ];

        $rules = [
            'product_id' => ['method' => 'validate_product_id', 'required' => true],
            'capacity' => ['method' => 'validate_capacity', 'args' => [0, 100], 'required' => true]
        ];

        $result = \WCEFP_Validator::validate_bulk($data, $rules);

        $this->assertFalse($result);
    }
}
