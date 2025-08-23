<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php _e('Voucher Regalo','wceventsfp'); ?></h1>
    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="wcefp-vouchers" />
        <?php if (!empty($_GET['order_id'])): ?>
            <input type="hidden" name="order_id" value="<?php echo absint($_GET['order_id']); ?>" />
        <?php endif; ?>
        <label>
            <input type="checkbox" name="wcefp_only_order" value="1" <?php checked(!empty($_GET['wcefp_only_order'])); ?> />
            <?php _e('Solo con ordine','wceventsfp'); ?>
        </label>
        <?php submit_button(__('Filtra','wceventsfp'), 'secondary', '', false); ?>
    </form>
    <form method="post">
        <?php wp_nonce_field('bulk-vouchers'); ?>
        <input type="hidden" name="page" value="wcefp-vouchers" />
        <?php if (!empty($_GET['order_id'])): ?>
            <input type="hidden" name="order_id" value="<?php echo absint($_GET['order_id']); ?>" />
        <?php endif; ?>
        <input type="hidden" name="wcefp_only_order" value="<?php echo !empty($_GET['wcefp_only_order']) ? '1' : ''; ?>" />
        <?php $table->display(); ?>
    </form>
</div>
