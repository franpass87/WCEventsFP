(function($){
  $(function(){
    $('.wcefp-map').each(function(){
      var $div = $(this);
      var addr = $div.data('address');
      if(!addr) return;
      fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(addr))
        .then(function(r){return r.json();})
        .then(function(data){
          if(!data || !data[0]) return;
          var lat = parseFloat(data[0].lat);
          var lon = parseFloat(data[0].lon);
          var map = L.map($div[0]).setView([lat, lon], 15);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
          }).addTo(map);
          L.marker([lat, lon]).addTo(map);
        });
    });
  });
})(jQuery);
