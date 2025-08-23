/**
 * Gutenberg Block Editor
 * 
 * Custom blocks for WCEventsFP booking forms and event listings.
 * Part of Phase 3: Data & Integration
 */
(function() {
    'use strict';
    
    const { registerBlockType } = wp.blocks;
    const { InspectorControls } = wp.blockEditor;
    const { 
        PanelBody, 
        SelectControl, 
        ToggleControl, 
        RangeControl,
        Spinner,
        Placeholder 
    } = wp.components;
    const { useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const { apiFetch } = wp;
    
    /**
     * Booking Form Block
     */
    registerBlockType('wcefp/booking-form', {
        title: __('Event Booking Form', 'wceventsfp'),
        description: __('Display a booking form for a specific event', 'wceventsfp'),
        icon: 'calendar-alt',
        category: 'wcefp',
        keywords: [__('booking', 'wceventsfp'), __('event', 'wceventsfp'), __('form', 'wceventsfp')],
        supports: {
            align: ['left', 'right', 'center', 'wide', 'full'],
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
        
        edit: function({ attributes, setAttributes }) {
            const { productId, showTitle, showDescription, showPrice, showImages } = attributes;
            const [events, setEvents] = useState([]);
            const [selectedEvent, setSelectedEvent] = useState(null);
            const [loading, setLoading] = useState(false);
            
            // Load events list
            useEffect(() => {
                setLoading(true);
                apiFetch({
                    path: '/wcefp/v1/events',
                }).then((data) => {
                    setEvents(data);
                    setLoading(false);
                }).catch(() => {
                    setLoading(false);
                });
            }, []);
            
            // Load selected event details
            useEffect(() => {
                if (productId > 0) {
                    apiFetch({
                        path: `/wcefp/v1/events/${productId}`,
                    }).then((data) => {
                        setSelectedEvent(data);
                    }).catch(() => {
                        setSelectedEvent(null);
                    });
                } else {
                    setSelectedEvent(null);
                }
            }, [productId]);
            
            const eventOptions = [
                { value: 0, label: __('Select an event', 'wceventsfp') }
            ].concat(
                events.map(event => ({
                    value: event.id,
                    label: event.title
                }))
            );
            
            return (
                <div className="wcefp-booking-form-block-editor">
                    <InspectorControls>
                        <PanelBody title={__('Event Settings', 'wceventsfp')}>
                            <SelectControl
                                label={__('Select Event', 'wceventsfp')}
                                value={productId}
                                options={eventOptions}
                                onChange={(value) => setAttributes({ productId: parseInt(value) })}
                            />
                        </PanelBody>
                        
                        <PanelBody title={__('Display Options', 'wceventsfp')}>
                            <ToggleControl
                                label={__('Show Title', 'wceventsfp')}
                                checked={showTitle}
                                onChange={(value) => setAttributes({ showTitle: value })}
                            />
                            <ToggleControl
                                label={__('Show Description', 'wceventsfp')}
                                checked={showDescription}
                                onChange={(value) => setAttributes({ showDescription: value })}
                            />
                            <ToggleControl
                                label={__('Show Price', 'wceventsfp')}
                                checked={showPrice}
                                onChange={(value) => setAttributes({ showPrice: value })}
                            />
                            <ToggleControl
                                label={__('Show Images', 'wceventsfp')}
                                checked={showImages}
                                onChange={(value) => setAttributes({ showImages: value })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    
                    <div className="wcefp-block-preview">
                        {loading && (
                            <Placeholder icon="calendar-alt" label={__('WCEventsFP Booking Form', 'wceventsfp')}>
                                <Spinner />
                                <p>{__('Loading events...', 'wceventsfp')}</p>
                            </Placeholder>
                        )}
                        
                        {!loading && productId === 0 && (
                            <Placeholder 
                                icon="calendar-alt" 
                                label={__('WCEventsFP Booking Form', 'wceventsfp')}
                                instructions={__('Select an event to display the booking form.', 'wceventsfp')}
                            >
                                <SelectControl
                                    value={productId}
                                    options={eventOptions}
                                    onChange={(value) => setAttributes({ productId: parseInt(value) })}
                                />
                            </Placeholder>
                        )}
                        
                        {!loading && productId > 0 && selectedEvent && (
                            <div className="wcefp-booking-form-preview">
                                {showTitle && (
                                    <h3 className="wcefp-block-title">{selectedEvent.title}</h3>
                                )}
                                
                                {showImages && selectedEvent.featured_image && (
                                    <div className="wcefp-block-images">
                                        <img src={selectedEvent.featured_image} alt={selectedEvent.title} />
                                    </div>
                                )}
                                
                                {showDescription && (
                                    <div className="wcefp-block-description">
                                        <p>{selectedEvent.excerpt}</p>
                                    </div>
                                )}
                                
                                {showPrice && (
                                    <div className="wcefp-block-price">
                                        <strong>{selectedEvent.currency} {selectedEvent.price}</strong>
                                    </div>
                                )}
                                
                                <div className="wcefp-block-form-placeholder">
                                    <p><em>{__('Booking form will appear here on the frontend.', 'wceventsfp')}</em></p>
                                </div>
                            </div>
                        )}
                        
                        {!loading && productId > 0 && !selectedEvent && (
                            <Placeholder icon="warning" label={__('Event Not Found', 'wceventsfp')}>
                                <p>{__('The selected event could not be found. Please select a different event.', 'wceventsfp')}</p>
                            </Placeholder>
                        )}
                    </div>
                </div>
            );
        },
        
        save: function() {
            // Server-side rendering
            return null;
        },
    });
    
    /**
     * Event List Block
     */
    registerBlockType('wcefp/event-list', {
        title: __('Event List', 'wceventsfp'),
        description: __('Display a list of upcoming events', 'wceventsfp'),
        icon: 'list-view',
        category: 'wcefp',
        keywords: [__('events', 'wceventsfp'), __('list', 'wceventsfp'), __('calendar', 'wceventsfp')],
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
        
        edit: function({ attributes, setAttributes }) {
            const { numberOfEvents, showFeaturedImage, showExcerpt, showPrice, showBookButton } = attributes;
            const [events, setEvents] = useState([]);
            const [loading, setLoading] = useState(false);
            
            // Load events list
            useEffect(() => {
                setLoading(true);
                apiFetch({
                    path: '/wcefp/v1/events',
                }).then((data) => {
                    setEvents(data.slice(0, numberOfEvents));
                    setLoading(false);
                }).catch(() => {
                    setLoading(false);
                });
            }, [numberOfEvents]);
            
            return (
                <div className="wcefp-event-list-block-editor">
                    <InspectorControls>
                        <PanelBody title={__('List Settings', 'wceventsfp')}>
                            <RangeControl
                                label={__('Number of Events', 'wceventsfp')}
                                value={numberOfEvents}
                                onChange={(value) => setAttributes({ numberOfEvents: value })}
                                min={1}
                                max={20}
                            />
                        </PanelBody>
                        
                        <PanelBody title={__('Display Options', 'wceventsfp')}>
                            <ToggleControl
                                label={__('Show Featured Image', 'wceventsfp')}
                                checked={showFeaturedImage}
                                onChange={(value) => setAttributes({ showFeaturedImage: value })}
                            />
                            <ToggleControl
                                label={__('Show Excerpt', 'wceventsfp')}
                                checked={showExcerpt}
                                onChange={(value) => setAttributes({ showExcerpt: value })}
                            />
                            <ToggleControl
                                label={__('Show Price', 'wceventsfp')}
                                checked={showPrice}
                                onChange={(value) => setAttributes({ showPrice: value })}
                            />
                            <ToggleControl
                                label={__('Show Book Button', 'wceventsfp')}
                                checked={showBookButton}
                                onChange={(value) => setAttributes({ showBookButton: value })}
                            />
                        </PanelBody>
                    </InspectorControls>
                    
                    <div className="wcefp-block-preview">
                        {loading && (
                            <Placeholder icon="list-view" label={__('WCEventsFP Event List', 'wceventsfp')}>
                                <Spinner />
                                <p>{__('Loading events...', 'wceventsfp')}</p>
                            </Placeholder>
                        )}
                        
                        {!loading && events.length === 0 && (
                            <Placeholder icon="list-view" label={__('WCEventsFP Event List', 'wceventsfp')}>
                                <p>{__('No events found.', 'wceventsfp')}</p>
                            </Placeholder>
                        )}
                        
                        {!loading && events.length > 0 && (
                            <div className="wcefp-event-list-preview">
                                {events.map((event, index) => (
                                    <div key={index} className="wcefp-event-item-preview">
                                        {showFeaturedImage && event.featured_image && (
                                            <div className="wcefp-event-image">
                                                <img src={event.featured_image} alt={event.title} />
                                            </div>
                                        )}
                                        
                                        <div className="wcefp-event-content">
                                            <h3 className="wcefp-event-title">{event.title}</h3>
                                            
                                            {showExcerpt && (
                                                <div className="wcefp-event-excerpt">
                                                    <p>{event.excerpt}</p>
                                                </div>
                                            )}
                                            
                                            <div className="wcefp-event-meta">
                                                {showPrice && (
                                                    <div className="wcefp-event-price">
                                                        <strong>{event.currency} {event.price}</strong>
                                                    </div>
                                                )}
                                                
                                                {showBookButton && (
                                                    <div className="wcefp-event-actions">
                                                        <button className="wcefp-btn wcefp-btn-primary" disabled>
                                                            {__('Book Now', 'wceventsfp')}
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            );
        },
        
        save: function() {
            // Server-side rendering
            return null;
        },
    });
    
})();