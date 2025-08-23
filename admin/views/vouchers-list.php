<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php _e('Voucher Regalo','wceventsfp'); ?></h1>
    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="wcefp-vouchers" />
        <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter form, no data mutation
        if (!empty($_GET['order_id'])):
        ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($_GET['order_id'])); ?>" />
        <?php endif; ?>
        <label>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter form, no data mutation
            ?>
            <input type="checkbox" name="wcefp_only_order" value="1" <?php checked(!empty($_GET['wcefp_only_order'])); ?> />
            <?php _e('Solo con ordine','wceventsfp'); ?>
        </label>
        <?php submit_button(__('Filtra','wceventsfp'), 'secondary', '', false); ?>
    </form>
    <form method="post">
        <?php wp_nonce_field('bulk-vouchers'); ?>
        <input type="hidden" name="page" value="wcefp-vouchers" />
        <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter form, no data mutation
        if (!empty($_GET['order_id'])):
        ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr(absint($_GET['order_id'])); ?>" />
        <?php endif; ?>
        <?php
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter form, no data mutation
        ?>
        <input type="hidden" name="wcefp_only_order" value="<?php echo esc_attr(!empty($_GET['wcefp_only_order']) ? '1' : ''); ?>" />
        <?php $table->display(); ?>
    </form>
</div>
