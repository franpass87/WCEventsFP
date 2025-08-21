<?php
if (!defined('ABSPATH')) exit;

class WCEFP_Gift_PDF {
    public static function init(){
        add_action('init', [__CLASS__,'route']);
    }

    public static function route(){
        if (empty($_GET['wcefp_gift_pdf'])) return;
        $order_id = absint($_GET['order'] ?? 0);
        $item_id = absint($_GET['item'] ?? 0);
        $voucher_id = absint($_GET['voucher'] ?? 0);
        $key = sanitize_text_field($_GET['key'] ?? '');
        if(!$order_id || !$item_id || !$voucher_id || !$key) wp_die(__('Richiesta non valida','wceventsfp'));

        $order = wc_get_order($order_id);
        if(!$order) wp_die(__('Ordine non trovato','wceventsfp'));
        $allowed = hash_equals($order->get_order_key(), $key);
        if(!$allowed && is_user_logged_in() && $order->get_user_id() && get_current_user_id() === $order->get_user_id()){
            $allowed = true;
        }
        if(!$allowed) wp_die(__('Permesso negato','wceventsfp'));

        $item = $order->get_item($item_id);
        if(!$item) wp_die(__('Articolo non valido','wceventsfp'));

        global $wpdb; $tbl = $wpdb->prefix.'wcefp_vouchers';
        $voucher = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d AND order_id=%d", $voucher_id, $order_id));
        if(!$voucher) wp_die(__('Voucher non trovato','wceventsfp'));
        $product = $item->get_product();
        $product_name = $product ? $product->get_name() : '';

        try {
            if(class_exists('TCPDF')){
                $pdf = new TCPDF();
                $pdf->AddPage();
            } elseif(class_exists('FPDF')){
                $pdf = new FPDF();
                $pdf->AddPage();
            } else {
                require_once WCEFP_PLUGIN_DIR.'vendor/wcefp-fpdf.php';
                $pdf = new FPDF();
                $pdf->AddPage();
            }
            if(method_exists($pdf,'SetFont')){
                $pdf->SetFont('Arial','B',16);
                if($logo = get_option('wcefp_gift_logo','')){
                    $pdf->Image($logo,10,10,40);
                    $pdf->Ln(30);
                }
                $pdf->Cell(0,10,utf8_decode($product_name),0,1,'C');
                $pdf->Ln(5);
                $pdf->SetFont('Arial','',12);
                $pdf->Cell(0,8,utf8_decode(__('Codice','wceventsfp').': '.$voucher->code),0,1);
                $pdf->Cell(0,8,utf8_decode(__('Per','wceventsfp').': '.$voucher->recipient_name),0,1);
                if($voucher->message_text){
                    $pdf->MultiCell(0,8,utf8_decode($voucher->message_text));
                }
                $pdf->Cell(0,8,utf8_decode(__('Data','wceventsfp').': '.date_i18n('d/m/Y', strtotime($voucher->created_at))),0,1);
                $pdf->Cell(0,8,utf8_decode(__('Stato','wceventsfp').': '.$voucher->status),0,1);
            }
            $data = method_exists($pdf,'Output') ? $pdf->Output('S') : '';
            if($data){
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="voucher-'.$voucher->code.'.pdf"');
                echo $data;
                exit;
            }
        } catch (\Throwable $e) {}

        $html = '<div style="font-family:sans-serif"><h1>'.esc_html($product_name).'</h1>';
        $html .= '<p>'.esc_html__('Codice','wceventsfp').': '.esc_html($voucher->code).'</p>';
        $html .= '<p>'.esc_html__('Per','wceventsfp').': '.esc_html($voucher->recipient_name).'</p>';
        if($voucher->message_text){
            $html .= '<p>'.esc_html($voucher->message_text).'</p>';
        }
        $html .= '</div>';
        wp_die($html);
    }
}
