<?php

class WCEFP_Cache
{
    public static function set(string $key, mixed $value, int $expire): bool
    {
        return wp_cache_set($key, $value, '', $expire);
    }
    public static function get(string $key): mixed
    {
        return wp_cache_get($key);
    }
    public static function delete(string $key): bool
    {
        return wp_cache_delete($key);
    }
    /**
     * @return array<string, mixed>
     */
    public static function get_kpi_data(int $days): array
    {
        return (array) wp_cache_get('kpi_data_' . $days);
    }
    public static function invalidate_product_cache(int $product_id): void
    {
        wp_cache_delete($product_id, 'products');
        delete_transient('wcefp_product_' . $product_id);
    }
    public static function clear_all(): void
    {
        wp_cache_flush();
    }
}
class WCEFP_Validator
{
    /**
     * @param string|int $id
     */
    public static function validate_product_id($id): int|false
    {
        $product = wc_get_product($id);
        if ($product && get_post_type($product->get_id()) === 'product') {
            return (int) $product->get_id();
        }
        return false;
    }
    public static function validate_capacity(int $capacity, int $min, int $max): int|false
    {
        return ($capacity >= $min && $capacity <= $max) ? $capacity : false;
    }
    public static function validate_email(string $email): string|false
    {
        $sanitized = sanitize_email($email);
        return is_email($sanitized) ? $sanitized : false;
    }
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @return array<string, mixed>|false
     */
    public static function validate_bulk(array $data, array $rules): array|false
    {
        $result = [];
        foreach ($rules as $field => $rule) {
            $required = ! empty($rule['required']);
            $value = $data[$field] ?? null;
            if ($required && null === $value) {
                return false;
            }
            if (null !== $value) {
                $method = $rule['method'];
                $args = $rule['args'] ?? [];
                $validated = self::$method($value, ...$args);
                if ($validated === false) {
                    return false;
                }
                $result[$field] = $validated;
            }
        }
        return $result;
    }
}
