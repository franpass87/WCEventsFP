(function($){
  $(function(){
    // GENERA OCCORRENZE
    $('#wcefp-generate').on('click', function(){
      const pid = $(this).data('product');
      const from = $('#wcefp_generate_from').val();
      const to = $('#wcefp_generate_to').val();
      const $out = $('#wcefp-generate-result').html('<em>Generazione…</em>');
      $.post(ajaxurl, {action:'wcefp_generate_occurrences', nonce: WCEFPAdmin.nonce, product_id: pid, from, to}, function(r){
        if(r && r.success){ $out.html('<span>Create: <strong>'+r.data.created+'</strong></span>'); }
        else { $out.html('<span style="color:#b32d2e">Errore</span>'); }
      });
    });

    // CALENDARIO
    const $view = $('#wcefp-view');
    $('#wcefp-switch-calendar').on('click', function(){
      $view.empty();
      const $toolbar = $('<div style="margin-bottom:8px;"></div>').appendTo($view);
      const $csvExp = $('<button class="button">Export CSV</button>').appendTo($toolbar);
      const $csvImp = $('<button class="button" style="margin-left:8px;">Import CSV</button>').appendTo($toolbar);
      const cal = $('<div id="wcefp-calendar"></div>').appendTo($view);
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth()-1, 1).toISOString().slice(0,10);
      const to   = new Date(now.getFullYear(), now.getMonth()+2, 0).toISOString().slice(0,10);

      function renderCal(events){
        const calendar = new FullCalendar.Calendar(cal[0], {
          initialView: 'dayGridMonth',
          height: 650,
          events: events,
          eventClick: function(info){
            const id = info.event.id;
            const current = info.event.title.match(/\((\d+)\/(\d+)\)/);
            const booked = current ? parseInt(current[1],10) : 0;
            const cap    = current ? parseInt(current[2],10) : 0;
            const html = `
              <div id="wcefp-modal" style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:10000">
                <div style="background:#fff;padding:16px;border-radius:8px;min-width:320px">
                  <h3 style="margin-top:0">${info.event.title.replace(/\s\(\d+\/\d+\)$/,'')}</h3>
                  <p><label>Capienza <input id="wcefp-cap" type="number" min="0" value="${cap}" style="width:120px;margin-left:8px"/></label></p>
                  <p><label>Prenotati <input id="wcefp-booked" type="number" min="0" value="${booked}" style="width:120px;margin-left:8px"/></label></p>
                  <div style="text-align:right">
                    <button class="button" id="wcefp-close">Chiudi</button>
                    <button class="button button-primary" id="wcefp-save" data-id="${id}" style="margin-left:8px">Salva</button>
                  </div>
                </div>
              </div>`;
            $('body').append(html);
          }
        });
        calendar.render();

        $(document).on('click','#wcefp-close', function(){ $('#wcefp-modal').remove(); });
        $(document).on('click','#wcefp-save', function(){
          const id = $(this).data('id');
          const capacity = parseInt($('#wcefp-cap').val()||'0',10);
          const booked = parseInt($('#wcefp-booked').val()||'0',10);
          $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_update_occurrence', nonce: WCEFPAdmin.nonce, id, capacity, booked}, function(r){
            $('#wcefp-modal').remove();
            $('#wcefp-switch-calendar').trigger('click'); // ricarica
          });
        });
      }

      $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_get_calendar', nonce: WCEFPAdmin.nonce, from, to}, function(r){
        renderCal((r && r.success) ? r.data.events : []);
      });

      // Export CSV
      $csvExp.on('click', function(){
        $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_bulk_occurrences_csv', nonce: WCEFPAdmin.nonce, mode:'export'}, function(r){
          if(r && r.success){
            const blob = new Blob([r.data.csv], {type:'text/csv'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a'); a.href=url; a.download='occurrences.csv'; a.click(); URL.revokeObjectURL(url);
          }
        });
      });
      // Import CSV
      $csvImp.on('click', function(){
        const txt = prompt('Incolla CSV con header: id,product_id,start_datetime,end_datetime,capacity,booked');
        if(!txt) return;
        $.post(WCEFPAdmin.ajaxUrl, {action:'wcefp_bulk_occurrences_csv', nonce: WCEFPAdmin.nonce, mode:'import', csv:txt}, function(r){
          $('#wcefp-switch-calendar').trigger('click');
        });
      });
    }).trigger('click');

    // LISTA
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
        } else { $view.html('<p>Errore.</p>'); }
      });
    });
  });
})(jQuery);
