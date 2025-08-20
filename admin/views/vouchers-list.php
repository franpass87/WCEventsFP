<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php _e('Voucher Regalo','wceventsfp'); ?></h1>
    <form method="post">
        <?php wp_nonce_field('bulk-vouchers'); ?>
        <?php $table->display(); ?>
    </form>
</div>
