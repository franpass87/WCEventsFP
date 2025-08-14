(function($){
  $(function(){

    // GENERA OCCORRENZE nel tab prodotto
    $('#wcefp-generate').on('click', function(){
      const pid = $(this).data('product');
      const from = $('#wcefp_generate_from').val();
      const to = $('#wcefp_generate_to').val();
      const $out = $('#wcefp-generate-result').html('<em>Generazione in corso…</em>');
      $.post(ajaxurl, {action:'wcefp_generate_occurrences', nonce: WCEFPAdmin.nonce, product_id: pid, from, to}, function(r){
        if(r && r.success){
          $out.html('<span>Occorrenze create: <strong>'+r.data.created+'</strong></span>');
        } else {
          $out.html('<span style="color:#b32d2e">Errore: '+(r && r.data && r.data.msg ? r.data.msg : 'unknown')+'</span>');
        }
      });
    });

    // PAGINA CALENDARIO/LISTA
    const $view = $('#wcefp-view');
    $('#wcefp-switch-calendar').on('click', function(){
      $view.empty();
      const cal = $('<div id="wcefp-calendar"></div>').appendTo($view);
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth()-1, 1).toISOString().slice(0,10);
      const to   = new Date(now.getFullYear(), now.getMonth()+2, 0).toISOString().slice(0,10);

      $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_get_calendar', nonce: WCEFPAdmin.nonce, from, to}, function(r){
        const events = (r && r.success) ? r.data.events : [];
        const calendar = new FullCalendar.Calendar(cal[0], {
          initialView: 'dayGridMonth',
          height: 650,
          events: events,
          eventClick: function(info){
            const e = info.event;
            const occ = e.id;
            const ep = e.extendedProps || {};
            const currentCap = parseInt(ep.capacity || 0, 10);
            const currentStatus = ep.status || 'active';

            const newCapStr = prompt('Nuova capienza per questo slot:', currentCap);
            if (newCapStr === null) return; // cancel
            const newCap = parseInt(newCapStr, 10);
            if (Number.isNaN(newCap) || newCap < 0) { alert('Valore non valido'); return; }

            const toggle = confirm('Vuoi alternare lo stato (attivo/disattivato)?\nOK = alterna, Annulla = lascia invariato');
            const nextStatus = toggle ? (currentStatus === 'active' ? 'cancelled' : 'active') : currentStatus;

            $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_update_occurrence', nonce: WCEFPAdmin.nonce, occ: occ, capacity: newCap, status: nextStatus}, function(res){
              if(res && res.success){
                alert('Aggiornato.');
                $('#wcefp-switch-calendar').trigger('click'); // reload
              } else {
                alert('Errore aggiornamento.');
              }
            });
          }
        });
        calendar.render();
      });
    }).trigger('click');

    $('#wcefp-switch-list').on('click', function(){
      $view.html('<p>Carico lista…</p>');
      $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_get_bookings', nonce: WCEFPAdmin.nonce}, function(r){
        if(r.success){
          const rows = r.data.rows || [];
          if(!rows.length){ $view.html('<p>Nessuna prenotazione.</p>'); return; }
          let html = '<table class="widefat striped"><thead><tr><th>Ordine</th><th>Data</th><th>Prodotto</th><th>Q.tà</th><th>Totale</th></tr></thead><tbody>';
          rows.forEach(x=>{
            html += `<tr><td>${x.order}</td><td>${x.date}</td><td>${x.product}</td><td>${x.qty}</td><td>€ ${Number(x.total).toFixed(2)}</td></tr>`;
          });
          html += '</tbody></table>';
          $view.html(html);
        } else {
          $view.html('<p>Errore nel caricamento.</p>');
        }
      });
    });

  });
})(jQuery);
