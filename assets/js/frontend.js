(function($){
  $(function(){

    const $giftToggle = $('#wcefp-gift-toggle');
    const $giftFields = $('#wcefp-gift-fields');
    $giftToggle.on('change', function(){
      $giftFields.toggle(this.checked);
    });

    const priceFormatter = new Intl.NumberFormat(WCEFPData.locale, {
      style: 'currency',
      currency: WCEFPData.currency,
    });
    function formatCurrency(x){
      return priceFormatter.format(Number(x));
    }

    $('.wcefp-widget').each(function(){
      const $w = $(this);
      const pid = $w.data('product');
      const priceAdult = parseFloat($w.data('price-adult') || '0');
      const priceChild = parseFloat($w.data('price-child') || '0');
      const hasVoucher = String($w.data('voucher') || '0') === '1';

      const $date = $w.find('.wcefp-date');
      const $slot = $w.find('.wcefp-slot');
      const $ad   = $w.find('.wcefp-adults');
      const $ch   = $w.find('.wcefp-children');
      const $btn  = $w.find('.wcefp-add');
      const $fb   = $w.find('.wcefp-feedback');
      const $tot  = $w.find('.wcefp-total');

      function getExtras(){
        const extras = [];
        $w.find('.wcefp-extra-row').each(function(){
          const id = $(this).data('id');
          const name = $(this).data('name');
          const price = parseFloat($(this).data('price') || '0');
          const pricing = $(this).data('pricing');
          const $qty = $(this).find('.wcefp-extra-qty');
          const $tg  = $(this).find('.wcefp-extra-toggle');
          let qty = 0;
          if($qty.length) qty = parseInt($qty.val()||'0',10);
          if($tg.length) qty = $tg.is(':checked') ? 1 : 0;
          if(qty>0){
            extras.push({id:id, name:name, price:price, qty:qty, pricing:pricing});
          }
        });
        return extras;
      }

      function updateTotal(){
        const ad = parseInt($ad.val()||'0',10);
        const ch = parseInt($ch.val()||'0',10);
        const extras = getExtras();
        let extrasCost = 0;
        extras.forEach(ex=>{
          let mult = ex.qty;
          if(ex.pricing === 'per_person') mult *= (ad+ch);
          else if(ex.pricing === 'per_child') mult *= ch;
          else if(ex.pricing === 'per_adult') mult *= ad;
          extrasCost += ex.price * mult;
        });
        const total = hasVoucher ? 0 : ((ad*priceAdult) + (ch*priceChild) + extrasCost);
        $tot.text(formatCurrency(total));
      }

      function loadSlots(){
        const d = $date.val();
        $slot.html('<option value="">Seleziona orario</option>');
        if(!d) return;
        $.post(WCEFPData.ajaxUrl, {action:'wcefp_public_occurrences', nonce: WCEFPData.nonce, product_id: pid, date: d}, function(r){
          if(r && r.success){
            const slots = r.data.slots || [];
            if(slots.length === 0){
              $slot.append('<option value="" disabled>Nessuno slot disponibile</option>');
            }
            slots.forEach(s=>{
              const disabled = s.soldout ? 'disabled' : '';
              const label = s.soldout ? `${s.time} (sold-out)` : `${s.time} (${s.available} posti)`;
              $slot.append(`<option value="${s.id}" ${disabled}>${label}</option>`);
            });
          }
        });
      }

      $date.on('change', loadSlots);
      $ad.on('input', updateTotal);
      $ch.on('input', updateTotal);
      function handleExtrasChange(){
        updateTotal();
        if (WCEFPData.ga4_enabled) {
          const extras = getExtras();
          window.dataLayer = window.dataLayer || [];
          window.dataLayer.push({event:'select_extras', item_id: pid, extras: extras});
        }
      }
      $w.on('input', '.wcefp-extra-qty', handleExtrasChange);
      $w.on('change', '.wcefp-extra-toggle', handleExtrasChange);
      updateTotal();

      $btn.on('click', function(e){
        e.preventDefault();
        $fb.text('');
        const occ = $slot.val();
        const ad = parseInt($ad.val()||'0',10);
        const ch = parseInt($ch.val()||'0',10);
        const extras = getExtras();

        if(!occ){ $fb.text('Seleziona uno slot.'); return; }
        if((ad+ch) <= 0){ $fb.text('Indica almeno 1 partecipante.'); return; }

        const giftEnabled = $giftToggle.is(':checked');
        let giftName = '', giftEmail = '', giftMsg = '';
        if(giftEnabled){
          giftName = $.trim($('input[name="gift_recipient_name"]').val());
          giftEmail = $.trim($('input[name="gift_recipient_email"]').val());
          giftMsg = $.trim($('textarea[name="gift_message"]').val());
          if(!giftName){ $fb.text('Inserisci il nome del destinatario.'); return; }
        }

        $.post(WCEFPData.ajaxUrl, {
          action:'wcefp_add_to_cart', nonce: WCEFPData.nonce,
          product_id: pid, occurrence_id: occ, adults: ad, children: ch, extras: extras,
          wcefp_gift_toggle: giftEnabled ? 1 : 0,
          gift_recipient_name: giftName,
          gift_recipient_email: giftEmail,
          gift_message: giftMsg
        }, function(r){
          if(r && r.success){
            // GA4 begin_checkout
            if (WCEFPData.ga4_enabled) {
              window.dataLayer = window.dataLayer || [];
              window.dataLayer.push({event:'begin_checkout'});
            }
            window.location.href = r.data.cart_url;
          } else {
            $fb.text(r && r.data && r.data.msg ? r.data.msg : 'Errore.');
          }
        });
      });
    });

  });
})(jQuery);
