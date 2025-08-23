/**
 * Google Reviews Integration
 * Displays Google Reviews for wine experiences and tastings
 */

document.addEventListener( 'DOMContentLoaded', function () {
	const WCEFPGoogleReviews = {
		init() {
			this.loadReviews();
		},

		async loadReviews() {
			const reviewsContainer = document.querySelector(
				'.wcefp-google-reviews'
			);
			if ( ! reviewsContainer ) return;

			const placeId = reviewsContainer.dataset.placeId;
			if ( ! placeId ) {
				console.warn( 'Google Place ID not found' );
				return;
			}

			try {
				const response = await fetch( WCEFPPublic.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( {
						action: 'wcefp_get_google_reviews',
						place_id: placeId,
						nonce: WCEFPPublic.nonce,
					} ),
				} );

				const data = await response.json();

				if ( data.success ) {
					this.renderReviews( data.data, reviewsContainer );
				} else {
					console.error( 'Failed to load reviews:', data.data.msg );
					this.showError( reviewsContainer );
				}
			} catch ( error ) {
				console.error( 'Error loading reviews:', error );
				this.showError( reviewsContainer );
			}
		},

		renderReviews( reviewsData, container ) {
			const { overall_rating, reviews } = reviewsData;

			const html = `
                <div class="google-reviews-header">
                    <h3>Recensioni Google</h3>
                    <div class="overall-rating">
                        <div class="stars">${ this.renderStars(
							overall_rating
						) }</div>
                        <span class="rating-text">${ overall_rating }/5 su Google</span>
                    </div>
                </div>
                <div class="reviews-list">
                    ${ reviews
						.map( ( review ) => this.renderSingleReview( review ) )
						.join( '' ) }
                </div>
                <div class="google-reviews-footer">
                    <p><em>Le recensioni sono mostrate direttamente da Google e riflettono le opinioni autentiche dei nostri ospiti.</em></p>
                </div>
            `;

			container.innerHTML = html;
		},

		renderSingleReview( review ) {
			const timeago = this.getTimeAgo( review.time );
			return `
                <div class="single-review">
                    <div class="review-header">
                        <div class="author-info">
                            ${
								review.profile_photo_url
									? `<img src="${ review.profile_photo_url }" alt="${ review.author_name }" class="author-avatar">`
									: `<div class="author-avatar-placeholder">${ review.author_name.charAt(
											0
									  ) }</div>`
							}
                            <div class="author-details">
                                <span class="author-name">${
									review.author_name
								}</span>
                                <span class="review-date">${
									review.relative_time_description || timeago
								}</span>
                            </div>
                        </div>
                        <div class="review-rating">
                            ${ this.renderStars( review.rating ) }
                        </div>
                    </div>
                    ${
						review.text
							? `<div class="review-text">${ review.text }</div>`
							: ''
					}
                </div>
            `;
		},

		renderStars( rating ) {
			const fullStars = Math.floor( rating );
			const hasHalfStar = rating % 1 !== 0;
			let starsHtml = '';

			for ( let i = 0; i < fullStars; i++ ) {
				starsHtml += '<span class="star filled">★</span>';
			}

			if ( hasHalfStar ) {
				starsHtml += '<span class="star half">★</span>';
			}

			const emptyStars = 5 - Math.ceil( rating );
			for ( let i = 0; i < emptyStars; i++ ) {
				starsHtml += '<span class="star">★</span>';
			}

			return starsHtml;
		},

		getTimeAgo( timestamp ) {
			const now = Date.now() / 1000;
			const diff = now - timestamp;

			const intervals = [
				{ label: 'anno', seconds: 31536000 },
				{ label: 'mese', seconds: 2592000 },
				{ label: 'settimana', seconds: 604800 },
				{ label: 'giorno', seconds: 86400 },
				{ label: 'ora', seconds: 3600 },
				{ label: 'minuto', seconds: 60 },
			];

			for ( const interval of intervals ) {
				const count = Math.floor( diff / interval.seconds );
				if ( count > 0 ) {
					return count === 1
						? `1 ${ interval.label } fa`
						: `${ count } ${ interval.label }${
								count > 1 && interval.label !== 'mese'
									? 'i'
									: ''
						  } fa`;
				}
			}

			return 'ora';
		},

		showError( container ) {
			container.innerHTML = `
                <div class="reviews-error">
                    <p>Le recensioni non sono al momento disponibili. Vi invitiamo a visitare la nostra pagina Google per vedere le recensioni dei nostri ospiti.</p>
                </div>
            `;
		},
	};

	// Initialize Google Reviews
	WCEFPGoogleReviews.init();
} );
