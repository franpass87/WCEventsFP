(function($){
  $(function(){

    // --------- Toolbar Calendario/Lista ----------
    const $filter = $('#wcefp-filter-product');
    if (window.WCEFPAdmin && Array.isArray(WCEFPAdmin.products)) {
      WCEFPAdmin.products.forEach(p=>{
        $filter.append(`<option value="${p.id}">${p.title}</option>`);
      });
    }

    const $view = $('#wcefp-view');

    function loadCalendar(){
      $view.empty();
      const cal = $('<div id="wcefp-calendar"></div>').appendTo($view);
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth()-1, 1).toISOString().slice(0,10);
      const to   = new Date(now.getFullYear(), now.getMonth()+2, 0).toISOString().slice(0,10);
      const product_id = parseInt($('#wcefp-filter-product').val() || '0', 10);

      $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_get_calendar', nonce: WCEFPAdmin.nonce, from, to, product_id}, function(r){
        const events = (r && r.success) ? r.data.events : [];
        // FullCalendar può non essere caricato sulle altre pagine del menu
        if (typeof FullCalendar === 'undefined' || !FullCalendar.Calendar){
          cal.html('<p>Calendario non disponibile su questa pagina.</p>');
          return;
        }
        const calendar = new FullCalendar.Calendar(cal[0], {
          initialView: 'dayGridMonth',
          height: 650,
          events: events,
          headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,listWeek' },
          eventClick: function(info){
            const e = info.event;
            const occ = e.id;
            const ep = e.extendedProps || {};
            const currentCap = parseInt(ep.capacity || 0, 10);
            const currentStatus = ep.status || 'active';

            const newCapStr = prompt('Nuova capienza per questo slot:', currentCap);
            if (newCapStr === null) return;
            const newCap = parseInt(newCapStr, 10);
            if (Number.isNaN(newCap) || newCap < 0) { alert('Valore non valido'); return; }

            const toggle = confirm('Vuoi alternare lo stato (attivo/disattivato)?\nOK = alterna, Annulla = lascia invariato');
            const nextStatus = toggle ? (currentStatus === 'active' ? 'cancelled' : 'active') : currentStatus;

            $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_update_occurrence', nonce: WCEFPAdmin.nonce, occ: occ, capacity: newCap, status: nextStatus}, function(res){
              if(res && res.success){
                alert('Aggiornato.');
                loadCalendar();
              } else {
                alert('Errore aggiornamento.');
              }
            });
          },
          eventDidMount: function(arg){
            // Tooltip semplice con capienza
            const ep = arg.event.extendedProps || {};
            const tip = `${ep.booked || 0}/${ep.capacity || 0}`;
            arg.el.setAttribute('title', tip);
          }
        });
        calendar.render();
      });
    }

    function loadList(){
      $view.html('<p>Carico lista…</p>');
      $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_get_bookings', nonce: WCEFPAdmin.nonce}, function(r){
        if(r.success){
          const rows = r.data.rows || [];
          if(!rows.length){ $view.html('<p>Nessuna prenotazione.</p>'); return; }
          let html = '<div class="wcefp-list-wrap"><input type="search" id="wcefp-list-search" placeholder="Cerca…" style="margin-bottom:8px;max-width:260px" /><table class="widefat striped wcefp-list-table"><thead><tr><th>Ordine</th><th>Status</th><th>Data</th><th>Prodotto</th><th>Q.tà</th><th>Totale</th></tr></thead><tbody>';
          rows.forEach(x=>{
            html += `<tr>
              <td>${x.order}</td>
              <td>${x.status}</td>
              <td>${x.date}</td>
              <td>${x.product}</td>
              <td>${x.qty}</td>
              <td>€ ${Number(x.total).toFixed(2)}</td>
            </tr>`;
          });
          html += '</tbody></table></div>';
          $view.html(html);

          // Ricerca live
          $('#wcefp-list-search').on('input', function(){
            const q = $(this).val().toLowerCase();
            $view.find('tbody tr').each(function(){
              const txt = $(this).text().toLowerCase();
              $(this).toggle(txt.indexOf(q) !== -1);
            });
          });
        } else {
          $view.html('<p>Errore nel caricamento.</p>');
        }
      });
    }

    $('#wcefp-switch-calendar').on('click', loadCalendar);
    $('#wcefp-switch-list').on('click', loadList);
    $filter.on('change', loadCalendar);

    // Avvio: calendario
    if ($view.length) $('#wcefp-switch-calendar').trigger('click');


    // --------- Tab prodotto: Genera occorrenze ----------
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

  });
})(jQuery);
