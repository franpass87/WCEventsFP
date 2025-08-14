(function($){
  $(function(){
    const $view = $('#wcefp-view');
    $('#wcefp-switch-calendar').on('click', function(){
      $view.html('<p>Carico calendario…</p>');
      $.post(ajaxurl, {action:'wcefp_get_calendar', nonce: WCEFPAdmin.nonce}, function(r){
        if(r.success){ $view.html('<pre>'+JSON.stringify(r.data,null,2)+'</pre>'); }
      });
    });
    $('#wcefp-switch-list').on('click', function(){
      $view.html('<p>Carico lista…</p>');
      $.post(ajaxurl, {action:'wcefp_get_bookings', nonce: WCEFPAdmin.nonce}, function(r){
        if(r.success){ $view.html('<pre>'+JSON.stringify(r.data,null,2)+'</pre>'); }
      });
    });
  });
})(jQuery);
