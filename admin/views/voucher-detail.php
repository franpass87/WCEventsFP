<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1><?php _e('Dettaglio Voucher','wceventsfp'); ?></h1>
    <table class="form-table">
        <tr>
            <th><?php _e('Codice','wceventsfp'); ?></th>
            <td><?php echo esc_html($voucher['code']); ?></td>
        </tr>
        <tr>
            <th><?php _e('Data','wceventsfp'); ?></th>
            <td><?php echo esc_html(mysql2date(get_option('date_format').' '.get_option('time_format'), $voucher['created_at'])); ?></td>
        </tr>
        <tr>
            <th><?php _e('Valore','wceventsfp'); ?></th>
            <td>€ <?php echo esc_html(number_format((float)$voucher['value'],2,',','.')); ?></td>
        </tr>
        <tr>
            <th><?php _e('Stato','wceventsfp'); ?></th>
            <td><?php echo esc_html(($voucher['status']=='used')?__('Usato','wceventsfp'):__('Non usato','wceventsfp')); ?></td>
        </tr>
        <tr>
            <th><?php _e('Ordine','wceventsfp'); ?></th>
            <td><?php $link = admin_url('post.php?post='.$voucher['order_id'].'&action=edit'); echo '<a href="'.esc_url($link).'">#'.esc_html($voucher['order_id']).'</a>'; ?></td>
        </tr>
    </table>
    <?php if ($voucher['status'] !== 'used'): ?>
    <form method="post" style="margin-top:20px;">
        <?php wp_nonce_field('wcefp_mark_used'); ?>
        <input type="hidden" name="wcefp_mark_used" value="1" />
        <button type="submit" class="button button-primary"><?php _e('Segna come usato','wceventsfp'); ?></button>
    </form>
    <?php endif; ?>
</div>
