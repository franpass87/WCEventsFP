/**
 * Gutenberg Block Editor
 *
 * Custom blocks for WCEventsFP booking forms and event listings.
 * Part of Phase 3: Data & Integration
 */
( function () {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { InspectorControls } = wp.blockEditor;
	const {
		PanelBody,
		SelectControl,
		ToggleControl,
		RangeControl,
		Spinner,
		Placeholder,
	} = wp.components;
	const { useState, useEffect } = wp.element;
	const { __ } = wp.i18n;
	const { apiFetch } = wp;

	/**
	 * Booking Form Block
	 */
	registerBlockType( 'wcefp/booking-form', {
		title: __( 'Event Booking Form', 'wceventsfp' ),
		description: __(
			'Display a booking form for a specific event',
			'wceventsfp'
		),
		icon: 'calendar-alt',
		category: 'wcefp',
		keywords: [
			__( 'booking', 'wceventsfp' ),
			__( 'event', 'wceventsfp' ),
			__( 'form', 'wceventsfp' ),
		],
		supports: {
			align: [ 'left', 'right', 'center', 'wide', 'full' ],
			className: true,
		},
		attributes: {
			productId: {
				type: 'number',
				default: 0,
			},
			showTitle: {
				type: 'boolean',
				default: true,
			},
			showDescription: {
				type: 'boolean',
				default: true,
			},
			showPrice: {
				type: 'boolean',
				default: true,
			},
			showImages: {
				type: 'boolean',
				default: false,
			},
		},

		edit( { attributes, setAttributes } ) {
			const {
				productId,
				showTitle,
				showDescription,
				showPrice,
				showImages,
			} = attributes;
			const [ events, setEvents ] = useState( [] );
			const [ selectedEvent, setSelectedEvent ] = useState( null );
			const [ loading, setLoading ] = useState( false );

			// Load events list
			useEffect( () => {
				setLoading( true );
				apiFetch( {
					path: '/wcefp/v1/events',
				} )
					.then( ( data ) => {
						setEvents( data );
						setLoading( false );
					} )
					.catch( () => {
						setLoading( false );
					} );
			}, [] );

			// Load selected event details
			useEffect( () => {
				if ( productId > 0 ) {
					apiFetch( {
						path: `/wcefp/v1/events/${ productId }`,
					} )
						.then( ( data ) => {
							setSelectedEvent( data );
						} )
						.catch( () => {
							setSelectedEvent( null );
						} );
				} else {
					setSelectedEvent( null );
				}
			}, [ productId ] );

			const eventOptions = [
				{ value: 0, label: __( 'Select an event', 'wceventsfp' ) },
			].concat(
				events.map( ( event ) => ( {
					value: event.id,
					label: event.title,
				} ) )
			);

			return (
				<div className="wcefp-booking-form-block-editor">
					<InspectorControls>
						<PanelBody
							title={ __( 'Event Settings', 'wceventsfp' ) }
						>
							<SelectControl
								label={ __( 'Select Event', 'wceventsfp' ) }
								value={ productId }
								options={ eventOptions }
								onChange={ ( value ) =>
									setAttributes( {
										productId: parseInt( value ),
									} )
								}
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Display Options', 'wceventsfp' ) }
						>
							<ToggleControl
								label={ __( 'Show Title', 'wceventsfp' ) }
								checked={ showTitle }
								onChange={ ( value ) =>
									setAttributes( { showTitle: value } )
								}
							/>
							<ToggleControl
								label={ __( 'Show Description', 'wceventsfp' ) }
								checked={ showDescription }
								onChange={ ( value ) =>
									setAttributes( { showDescription: value } )
								}
							/>
							<ToggleControl
								label={ __( 'Show Price', 'wceventsfp' ) }
								checked={ showPrice }
								onChange={ ( value ) =>
									setAttributes( { showPrice: value } )
								}
							/>
							<ToggleControl
								label={ __( 'Show Images', 'wceventsfp' ) }
								checked={ showImages }
								onChange={ ( value ) =>
									setAttributes( { showImages: value } )
								}
							/>
						</PanelBody>
					</InspectorControls>

					<div className="wcefp-block-preview">
						{ loading && (
							<Placeholder
								icon="calendar-alt"
								label={ __(
									'WCEventsFP Booking Form',
									'wceventsfp'
								) }
							>
								<Spinner />
								<p>{ __( 'Loading events…', 'wceventsfp' ) }</p>
							</Placeholder>
						) }

						{ ! loading && productId === 0 && (
							<Placeholder
								icon="calendar-alt"
								label={ __(
									'WCEventsFP Booking Form',
									'wceventsfp'
								) }
								instructions={ __(
									'Select an event to display the booking form.',
									'wceventsfp'
								) }
							>
								<SelectControl
									value={ productId }
									options={ eventOptions }
									onChange={ ( value ) =>
										setAttributes( {
											productId: parseInt( value ),
										} )
									}
								/>
							</Placeholder>
						) }

						{ ! loading && productId > 0 && selectedEvent && (
							<div className="wcefp-booking-form-preview">
								{ showTitle && (
									<h3 className="wcefp-block-title">
										{ selectedEvent.title }
									</h3>
								) }

								{ showImages && selectedEvent.featured_image && (
									<div className="wcefp-block-images">
										<img
											src={ selectedEvent.featured_image }
											alt={ selectedEvent.title }
										/>
									</div>
								) }

								{ showDescription && (
									<div className="wcefp-block-description">
										<p>{ selectedEvent.excerpt }</p>
									</div>
								) }

								{ showPrice && (
									<div className="wcefp-block-price">
										<strong>
											{ selectedEvent.currency }{ ' ' }
											{ selectedEvent.price }
										</strong>
									</div>
								) }

								<div className="wcefp-block-form-placeholder">
									<p>
										<em>
											{ __(
												'Booking form will appear here on the frontend.',
												'wceventsfp'
											) }
										</em>
									</p>
								</div>
							</div>
						) }

						{ ! loading && productId > 0 && ! selectedEvent && (
							<Placeholder
								icon="warning"
								label={ __( 'Event Not Found', 'wceventsfp' ) }
							>
								<p>
									{ __(
										'The selected event could not be found. Please select a different event.',
										'wceventsfp'
									) }
								</p>
							</Placeholder>
						) }
					</div>
				</div>
			);
		},

		save() {
			// Server-side rendering
			return null;
		},
	} );

	/**
	 * Event List Block
	 */
	registerBlockType( 'wcefp/event-list', {
		title: __( 'Event List', 'wceventsfp' ),
		description: __( 'Display a list of upcoming events', 'wceventsfp' ),
		icon: 'list-view',
		category: 'wcefp',
		keywords: [
			__( 'events', 'wceventsfp' ),
			__( 'list', 'wceventsfp' ),
			__( 'calendar', 'wceventsfp' ),
		],
		supports: {
			className: true,
		},
		attributes: {
			numberOfEvents: {
				type: 'number',
				default: 5,
			},
			showFeaturedImage: {
				type: 'boolean',
				default: true,
			},
			showExcerpt: {
				type: 'boolean',
				default: true,
			},
			showPrice: {
				type: 'boolean',
				default: true,
			},
			showBookButton: {
				type: 'boolean',
				default: true,
			},
		},

		edit( { attributes, setAttributes } ) {
			const {
				numberOfEvents,
				showFeaturedImage,
				showExcerpt,
				showPrice,
				showBookButton,
			} = attributes;
			const [ events, setEvents ] = useState( [] );
			const [ loading, setLoading ] = useState( false );

			// Load events list
			useEffect( () => {
				setLoading( true );
				apiFetch( {
					path: '/wcefp/v1/events',
				} )
					.then( ( data ) => {
						setEvents( data.slice( 0, numberOfEvents ) );
						setLoading( false );
					} )
					.catch( () => {
						setLoading( false );
					} );
			}, [ numberOfEvents ] );

			return (
				<div className="wcefp-event-list-block-editor">
					<InspectorControls>
						<PanelBody
							title={ __( 'List Settings', 'wceventsfp' ) }
						>
							<RangeControl
								label={ __( 'Number of Events', 'wceventsfp' ) }
								value={ numberOfEvents }
								onChange={ ( value ) =>
									setAttributes( { numberOfEvents: value } )
								}
								min={ 1 }
								max={ 20 }
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Display Options', 'wceventsfp' ) }
						>
							<ToggleControl
								label={ __(
									'Show Featured Image',
									'wceventsfp'
								) }
								checked={ showFeaturedImage }
								onChange={ ( value ) =>
									setAttributes( {
										showFeaturedImage: value,
									} )
								}
							/>
							<ToggleControl
								label={ __( 'Show Excerpt', 'wceventsfp' ) }
								checked={ showExcerpt }
								onChange={ ( value ) =>
									setAttributes( { showExcerpt: value } )
								}
							/>
							<ToggleControl
								label={ __( 'Show Price', 'wceventsfp' ) }
								checked={ showPrice }
								onChange={ ( value ) =>
									setAttributes( { showPrice: value } )
								}
							/>
							<ToggleControl
								label={ __( 'Show Book Button', 'wceventsfp' ) }
								checked={ showBookButton }
								onChange={ ( value ) =>
									setAttributes( { showBookButton: value } )
								}
							/>
						</PanelBody>
					</InspectorControls>

					<div className="wcefp-block-preview">
						{ loading && (
							<Placeholder
								icon="list-view"
								label={ __(
									'WCEventsFP Event List',
									'wceventsfp'
								) }
							>
								<Spinner />
								<p>{ __( 'Loading events…', 'wceventsfp' ) }</p>
							</Placeholder>
						) }

						{ ! loading && events.length === 0 && (
							<Placeholder
								icon="list-view"
								label={ __(
									'WCEventsFP Event List',
									'wceventsfp'
								) }
							>
								<p>
									{ __( 'No events found.', 'wceventsfp' ) }
								</p>
							</Placeholder>
						) }

						{ ! loading && events.length > 0 && (
							<div className="wcefp-event-list-preview">
								{ events.map( ( event, index ) => (
									<div
										key={ index }
										className="wcefp-event-item-preview"
									>
										{ showFeaturedImage &&
											event.featured_image && (
												<div className="wcefp-event-image">
													<img
														src={
															event.featured_image
														}
														alt={ event.title }
													/>
												</div>
											) }

										<div className="wcefp-event-content">
											<h3 className="wcefp-event-title">
												{ event.title }
											</h3>

											{ showExcerpt && (
												<div className="wcefp-event-excerpt">
													<p>{ event.excerpt }</p>
												</div>
											) }

											<div className="wcefp-event-meta">
												{ showPrice && (
													<div className="wcefp-event-price">
														<strong>
															{ event.currency }{ ' ' }
															{ event.price }
														</strong>
													</div>
												) }

												{ showBookButton && (
													<div className="wcefp-event-actions">
														<button
															className="wcefp-btn wcefp-btn-primary"
															disabled
														>
															{ __(
																'Book Now',
																'wceventsfp'
															) }
														</button>
													</div>
												) }
											</div>
										</div>
									</div>
								) ) }
							</div>
						) }
					</div>
				</div>
			);
		},

		save() {
			// Server-side rendering
			return null;
		},
	} );
	
	/**
	 * Experiences Catalog Block
	 */
	registerBlockType( 'wcefp/experiences-catalog', {
		title: __( 'Catalogo Esperienze', 'wceventsfp' ),
		description: __(
			'Display a filterable catalog of experiences in marketplace style',
			'wceventsfp'
		),
		icon: 'grid-view',
		category: 'wcefp',
		keywords: [
			__( 'experiences', 'wceventsfp' ),
			__( 'catalog', 'wceventsfp' ),
			__( 'marketplace', 'wceventsfp' ),
			__( 'esperienze', 'wceventsfp' ),
		],
		supports: {
			align: [ 'wide', 'full' ],
			className: true,
		},
		attributes: {
			limit: {
				type: 'number',
				default: 12,
			},
			category: {
				type: 'string',
				default: '',
			},
			showFilters: {
				type: 'boolean',
				default: true,
			},
			showMap: {
				type: 'boolean',
				default: false,
			},
			layout: {
				type: 'string',
				default: 'grid',
			},
			columns: {
				type: 'number',
				default: 3,
			},
			orderBy: {
				type: 'string',
				default: 'date',
			},
			orderDir: {
				type: 'string',
				default: 'DESC',
			},
			showPrice: {
				type: 'boolean',
				default: true,
			},
			showRating: {
				type: 'boolean',
				default: true,
			},
			showDuration: {
				type: 'boolean',
				default: true,
			},
			showLocation: {
				type: 'boolean',
				default: true,
			},
		},

		edit( { attributes, setAttributes, className } ) {
			const {
				limit,
				category,
				showFilters,
				showMap,
				layout,
				columns,
				orderBy,
				orderDir,
				showPrice,
				showRating,
				showDuration,
				showLocation,
			} = attributes;

			const [ experiences, setExperiences ] = useState( [] );
			const [ isLoading, setIsLoading ] = useState( true );
			const [ categories, setCategories ] = useState( [] );

			// Fetch experiences and categories
			useEffect( () => {
				setIsLoading( true );

				// Fetch experiences
				apiFetch( {
					path: '/wcefp/v1/experiences',
					method: 'GET',
				} )
					.then( ( data ) => {
						setExperiences( data );
						setIsLoading( false );
					} )
					.catch( () => {
						setIsLoading( false );
					} );

				// Fetch categories
				apiFetch( {
					path: '/wp/v2/product_cat?per_page=50',
					method: 'GET',
				} )
					.then( ( data ) => {
						setCategories( data );
					} )
					.catch( () => {
						setCategories( [] );
					} );
			}, [] );

			return (
				<div className={ className }>
					<InspectorControls>
						<PanelBody
							title={ __( 'Catalog Settings', 'wceventsfp' ) }
							initialOpen={ true }
						>
							<RangeControl
								label={ __( 'Number of experiences', 'wceventsfp' ) }
								value={ limit }
								onChange={ ( value ) => setAttributes( { limit: value } ) }
								min={ 1 }
								max={ 50 }
							/>

							<SelectControl
								label={ __( 'Category', 'wceventsfp' ) }
								value={ category }
								options={ [
									{ label: __( 'All Categories', 'wceventsfp' ), value: '' },
									...categories.map( ( cat ) => ( {
										label: cat.name,
										value: cat.slug,
									} ) ),
								] }
								onChange={ ( value ) => setAttributes( { category: value } ) }
							/>

							<SelectControl
								label={ __( 'Layout', 'wceventsfp' ) }
								value={ layout }
								options={ [
									{ label: __( 'Grid', 'wceventsfp' ), value: 'grid' },
									{ label: __( 'List', 'wceventsfp' ), value: 'list' },
									{ label: __( 'Masonry', 'wceventsfp' ), value: 'masonry' },
								] }
								onChange={ ( value ) => setAttributes( { layout: value } ) }
							/>

							{ layout === 'grid' && (
								<RangeControl
									label={ __( 'Columns', 'wceventsfp' ) }
									value={ columns }
									onChange={ ( value ) => setAttributes( { columns: value } ) }
									min={ 1 }
									max={ 4 }
								/>
							) }

							<SelectControl
								label={ __( 'Order by', 'wceventsfp' ) }
								value={ orderBy }
								options={ [
									{ label: __( 'Date', 'wceventsfp' ), value: 'date' },
									{ label: __( 'Title', 'wceventsfp' ), value: 'title' },
									{ label: __( 'Price', 'wceventsfp' ), value: 'price' },
									{ label: __( 'Popularity', 'wceventsfp' ), value: 'popularity' },
								] }
								onChange={ ( value ) => setAttributes( { orderBy: value } ) }
							/>

							<SelectControl
								label={ __( 'Order direction', 'wceventsfp' ) }
								value={ orderDir }
								options={ [
									{ label: __( 'Descending', 'wceventsfp' ), value: 'DESC' },
									{ label: __( 'Ascending', 'wceventsfp' ), value: 'ASC' },
								] }
								onChange={ ( value ) => setAttributes( { orderDir: value } ) }
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Display Options', 'wceventsfp' ) }
							initialOpen={ false }
						>
							<ToggleControl
								label={ __( 'Show filters', 'wceventsfp' ) }
								checked={ showFilters }
								onChange={ ( value ) => setAttributes( { showFilters: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show map', 'wceventsfp' ) }
								checked={ showMap }
								onChange={ ( value ) => setAttributes( { showMap: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show price', 'wceventsfp' ) }
								checked={ showPrice }
								onChange={ ( value ) => setAttributes( { showPrice: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show rating', 'wceventsfp' ) }
								checked={ showRating }
								onChange={ ( value ) => setAttributes( { showRating: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show duration', 'wceventsfp' ) }
								checked={ showDuration }
								onChange={ ( value ) => setAttributes( { showDuration: value } ) }
							/>

							<ToggleControl
								label={ __( 'Show location', 'wceventsfp' ) }
								checked={ showLocation }
								onChange={ ( value ) => setAttributes( { showLocation: value } ) }
							/>
						</PanelBody>
					</InspectorControls>

					<div className="wcefp-block-preview">
						<h3 className="wcefp-block-title">
							{ __( '🏛️ Catalogo Esperienze', 'wceventsfp' ) }
						</h3>

						<div className="wcefp-block-settings-summary">
							<p>
								<strong>{ __( 'Layout:', 'wceventsfp' ) }</strong> { layout } 
								{ layout === 'grid' && ` (${columns} ${__( 'columns', 'wceventsfp' )})` }
							</p>
							<p>
								<strong>{ __( 'Showing:', 'wceventsfp' ) }</strong> { limit } { __( 'experiences', 'wceventsfp' ) }
								{ category && ` ${__( 'from category', 'wceventsfp' )} "${category}"` }
							</p>
							<p>
								<strong>{ __( 'Features:', 'wceventsfp' ) }</strong>
								{ showFilters && ' ' + __( 'Filters', 'wceventsfp' ) }
								{ showMap && ' | ' + __( 'Map', 'wceventsfp' ) }
								{ showPrice && ' | ' + __( 'Price', 'wceventsfp' ) }
								{ showRating && ' | ' + __( 'Rating', 'wceventsfp' ) }
							</p>
						</div>

						{ isLoading ? (
							<div className="wcefp-block-loading">
								<Spinner />
								<p>{ __( 'Loading experiences...', 'wceventsfp' ) }</p>
							</div>
						) : (
							<div className="wcefp-experiences-preview">
								<p className="wcefp-preview-note">
									{ __( '✨ Preview: The actual catalog will display here on the frontend with interactive filters and marketplace-style cards.', 'wceventsfp' ) }
								</p>
								{ experiences.length > 0 ? (
									<div className={ `wcefp-preview-grid wcefp-layout-${layout}` }>
										{ experiences.slice( 0, Math.min( limit, 6 ) ).map( ( experience ) => (
											<div key={ experience.id } className="wcefp-preview-card">
												<div className="wcefp-preview-image">
													{ experience.featured_image ? (
														<img src={ experience.featured_image } alt={ experience.title } />
													) : (
														<div className="wcefp-preview-placeholder">🎯</div>
													) }
												</div>
												<div className="wcefp-preview-content">
													<h4>{ experience.title }</h4>
													{ showPrice && experience.price && (
														<div className="wcefp-preview-price">
															{ experience.currency }{ experience.price }
														</div>
													) }
													{ showRating && (
														<div className="wcefp-preview-rating">⭐ 4.8 (124)</div>
													) }
													{ showDuration && (
														<div className="wcefp-preview-meta">⏱ 2 hours</div>
													) }
													{ showLocation && (
														<div className="wcefp-preview-meta">📍 Rome</div>
													) }
												</div>
											</div>
										) ) }
									</div>
								) : (
									<Placeholder
										icon="grid-view"
										label={ __( 'No experiences found', 'wceventsfp' ) }
										instructions={ __( 'Create some experience products to see them here.', 'wceventsfp' ) }
									/>
								) }
							</div>
						) }
					</div>
				</div>
			);
		},

		save() {
			// Server-side rendering
			return null;
		},
	} );
} )();
