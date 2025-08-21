(function($){
  $(function(){
    
    // Enhanced Event Grid with Search and Filter
    class WCEFPEventGrid {
      constructor($container) {
        this.$container = $container;
        this.$grid = $container.find('.wcefp-grid');
        this.originalCards = this.$grid.find('.wcefp-card').clone();
        this.init();
      }
      
      init() {
        this.addFilterBar();
        this.bindEvents();
        this.addSocialSharing();
        this.addImageGallery();
      }
      
      addFilterBar() {
        const filterHTML = `
          <div class="wcefp-filter-bar">
            <div class="wcefp-filter-row">
              <div class="wcefp-search-field">
                <span class="wcefp-search-icon">🔍</span>
                <input type="text" class="wcefp-search-input" placeholder="Cerca eventi o esperienze...">
              </div>
              <select class="wcefp-filter-select wcefp-filter-type">
                <option value="">Tutti i tipi</option>
                <option value="wcefp_event">Eventi</option>
                <option value="wcefp_experience">Esperienze</option>
              </select>
              <select class="wcefp-filter-select wcefp-filter-price">
                <option value="">Tutti i prezzi</option>
                <option value="0-50">€0 - €50</option>
                <option value="50-100">€50 - €100</option>
                <option value="100-200">€100 - €200</option>
                <option value="200+">€200+</option>
              </select>
              <button class="wcefp-clear-filters">Cancella filtri</button>
            </div>
          </div>
        `;
        this.$container.prepend(filterHTML);
      }
      
      bindEvents() {
        const $searchInput = this.$container.find('.wcefp-search-input');
        const $typeFilter = this.$container.find('.wcefp-filter-type');
        const $priceFilter = this.$container.find('.wcefp-filter-price');
        const $clearBtn = this.$container.find('.wcefp-clear-filters');
        
        // Debounced search
        let searchTimeout;
        $searchInput.on('input', () => {
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(() => {
            this.filterEvents();
          }, 300);
        });
        
        $typeFilter.on('change', () => this.filterEvents());
        $priceFilter.on('change', () => this.filterEvents());
        
        $clearBtn.on('click', () => {
          $searchInput.val('');
          $typeFilter.val('');
          $priceFilter.val('');
          this.filterEvents();
        });
      }
      
      filterEvents() {
        const searchTerm = this.$container.find('.wcefp-search-input').val().toLowerCase();
        const typeFilter = this.$container.find('.wcefp-filter-type').val();
        const priceFilter = this.$container.find('.wcefp-filter-price').val();
        
        this.showLoading();
        
        setTimeout(() => {
          let filteredCards = this.originalCards.filter((index, card) => {
            const $card = $(card);
            const title = $card.find('.wcefp-card-title').text().toLowerCase();
            const description = $card.find('.wcefp-card-meta').text().toLowerCase();
            const type = $card.data('type') || '';
            const price = this.extractPrice($card);
            
            // Search filter
            if (searchTerm && !title.includes(searchTerm) && !description.includes(searchTerm)) {
              return false;
            }
            
            // Type filter
            if (typeFilter && type !== typeFilter) {
              return false;
            }
            
            // Price filter
            if (priceFilter && !this.matchesPriceRange(price, priceFilter)) {
              return false;
            }
            
            return true;
          });
          
          this.updateGrid(filteredCards);
          this.hideLoading();
        }, 300);
      }
      
      extractPrice($card) {
        const priceText = $card.find('.price').text();
        const match = priceText.match(/€(\d+)/);
        return match ? parseInt(match[1]) : 0;
      }
      
      matchesPriceRange(price, range) {
        switch(range) {
          case '0-50': return price >= 0 && price <= 50;
          case '50-100': return price > 50 && price <= 100;
          case '100-200': return price > 100 && price <= 200;
          case '200+': return price > 200;
          default: return true;
        }
      }
      
      updateGrid(filteredCards) {
        this.$grid.empty();
        
        if (filteredCards.length === 0) {
          this.$grid.append(`
            <div class="wcefp-empty-state">
              <h3>Nessun risultato trovato</h3>
              <p>Prova a modificare i filtri di ricerca</p>
            </div>
          `);
        } else {
          filteredCards.each((index, card) => {
            $(card).hide().appendTo(this.$grid).fadeIn(300);
          });
        }
      }
      
      showLoading() {
        if (!this.$container.find('.wcefp-loading-spinner').length) {
          this.$container.append(`
            <div class="wcefp-loading-spinner">
              <div class="wcefp-spinner"></div>
            </div>
          `);
        }
      }
      
      hideLoading() {
        this.$container.find('.wcefp-loading-spinner').remove();
      }
      
      addSocialSharing() {
        this.$container.on('mouseenter', '.wcefp-card', function() {
          const $card = $(this);
          if (!$card.find('.wcefp-social-share').length) {
            const title = encodeURIComponent($card.find('.wcefp-card-title').text());
            const url = encodeURIComponent(window.location.href);
            
            const shareHTML = `
              <div class="wcefp-social-share">
                <span class="wcefp-share-label">Condividi:</span>
                <a href="https://www.facebook.com/sharer/sharer.php?u=${url}" 
                   class="wcefp-share-btn wcefp-share-facebook" target="_blank" aria-label="Condividi su Facebook">📘</a>
                <a href="https://twitter.com/intent/tweet?text=${title}&url=${url}" 
                   class="wcefp-share-btn wcefp-share-twitter" target="_blank" aria-label="Condividi su Twitter">🐦</a>
                <a href="https://wa.me/?text=${title}%20${url}" 
                   class="wcefp-share-btn wcefp-share-whatsapp" target="_blank" aria-label="Condividi su WhatsApp">💬</a>
                <a href="mailto:?subject=${title}&body=${url}" 
                   class="wcefp-share-btn wcefp-share-email" aria-label="Condividi via Email">✉️</a>
              </div>
            `;
            
            $card.find('.wcefp-card-body').append(shareHTML);
          }
        });
      }
      
      addImageGallery() {
        this.$container.on('click', '.wcefp-card-media img', function() {
          const $img = $(this);
          const src = $img.attr('src');
          const alt = $img.attr('alt') || 'Immagine evento';
          
          // Simple lightbox overlay
          const overlay = $(`
            <div class="wcefp-lightbox" style="
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0,0,0,0.9);
              z-index: 10000;
              display: flex;
              align-items: center;
              justify-content: center;
              cursor: pointer;
            ">
              <img src="${src}" alt="${alt}" style="
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
                border-radius: 8px;
              ">
              <span style="
                position: absolute;
                top: 20px;
                right: 30px;
                color: white;
                font-size: 30px;
                cursor: pointer;
              ">&times;</span>
            </div>
          `);
          
          $('body').append(overlay);
          
          overlay.on('click', function() {
            overlay.remove();
          });
        });
      }
    }
    
    // Initialize enhanced grids
    $('.wcefp-grid').each(function() {
      const $container = $(this).closest('.wcefp-event-grid-container, [class*="wcefp-"]').length ? 
                        $(this).closest('.wcefp-event-grid-container, [class*="wcefp-"]') : 
                        $(this).parent();
      new WCEFPEventGrid($container);
    });
    
    // Add smooth scroll animations
    if (typeof IntersectionObserver !== 'undefined') {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            $(entry.target).addClass('wcefp-animate-in');
          }
        });
      }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
      });
      
      $('.wcefp-card').each(function() {
        observer.observe(this);
      });
    }
    
    // Enhanced card interactions
    $('.wcefp-card').hover(
      function() {
        $(this).addClass('wcefp-card-hover');
      },
      function() {
        $(this).removeClass('wcefp-card-hover');
      }
    );
    
    // Countdown Timer Functionality
    $('.wcefp-countdown').each(function() {
      const $countdown = $(this);
      const eventTime = parseInt($countdown.data('event-time')) * 1000;
      
      function updateCountdown() {
        const now = new Date().getTime();
        const distance = eventTime - now;
        
        if (distance < 0) {
          $countdown.find('.wcefp-countdown-timer').html('<div class="wcefp-countdown-expired">' + WCEFPTpl.strings.expired + '</div>');
          return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        $countdown.find('#days').text(days);
        $countdown.find('#hours').text(hours);
        $countdown.find('#minutes').text(minutes);
        $countdown.find('#seconds').text(seconds);
      }
      
      updateCountdown();
      setInterval(updateCountdown, 1000);
    });
    
    // Performance: Lazy load images
    if ('IntersectionObserver' in window) {
      const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
              img.src = img.dataset.src;
              img.removeAttribute('data-src');
              imageObserver.unobserve(img);
            }
          }
        });
      });
      
      $('.wcefp-card img[data-src]').each(function() {
        imageObserver.observe(this);
      });
    }
    
  });
})(jQuery);

// Add dynamic CSS animation class
$(document).ready(function() {
  if (!$('#wcefp-dynamic-styles').length) {
    $('<style id="wcefp-dynamic-styles">').appendTo('head').text(`
      .wcefp-animate-in {
        animation: wcefpSlideInUp 0.6s ease-out forwards;
      }
      
      @keyframes wcefpSlideInUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      .wcefp-lightbox {
        animation: wcefpFadeIn 0.3s ease-out;
      }
    `);
  }
});
