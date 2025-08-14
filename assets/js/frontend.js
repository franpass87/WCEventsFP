(function($){
  $(function(){

    function formatEuro(x){
      return '€ ' + Number(x).toFixed(2).replace('.', ',');
    }

    $('.wcefp-widget').each(function(){
      const $w = $(this);
      const pid = $w.data('product');
      const priceAdult = parseFloat($w.data('price-adult') || '0');
      const priceChild = parseFloat($w.data('price-child') || '0');

      const $date = $w.find('.wcefp-date');
      const $slot = $w.find('.wcefp-slot');
      const $ad   = $w.find('.wcefp-adults');
      const $ch   = $w.find('.wcefp-children');
      const $btn  = $w.find('.wcefp-add');
      const $fb   = $w.find('.wcefp-feedback');
      const $tot  = $w.find('.wcefp-total');

      function updateTotal(){
        const ad = parseInt($ad.val()||'0',10);
        const ch = parseInt($ch.val()||'0',10);
        let extras = 0;
        $w.find('.wcefp-extra:checked').each(function(){
          const p = parseFloat($(this).data('price') || '0');
          extras += p;
        });
        const total = (ad*priceAdult) + (ch*priceChild) + extras;
        $tot.text(formatEuro(total));
      }

      function loadSlots(){
        const d = $date.val();
        $slot.html('<option value="">Seleziona orario</option>');
        if(!d) return;
        $.post(WCEFPData.ajaxUrl, {action:'wcefp_public_occurrences', nonce: WCEFPData.nonce, product_id: pid, date: d}, function(r){
          if(r && r.success){
            (r.data.slots || []).forEach(s=>{
              const disabled = s.available<=0 ? 'disabled' : '';
              $slot.append(`<option value="${s.id}" ${disabled}>${s.time} (${s.available} posti)</option>`);
            });
          }
        });
      }

      $date.on('change', function(){ loadSlots(); });
      $ad.on('input', updateTotal);
      $ch.on('input', updateTotal);
      $
