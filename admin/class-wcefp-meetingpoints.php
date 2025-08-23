<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCEFP_MeetingPoints {

    public function __construct() {
        // Ensure submenu registers after the main plugin menu to prevent redirects
        add_action( 'admin_menu', array( $this, 'add_settings_page' ), 20 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_submenu_page(
            'wcefp', // slug principale del plugin
            __( 'Meeting Points', 'wceventsfp' ),
            __( 'Meeting Points', 'wceventsfp' ),
            'manage_woocommerce',
            'wcefp-meetingpoints',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wcefp_meetingpoints_group', 'wcefp_meetingpoints', array( $this, 'sanitize_points' ) );
    }

    public function sanitize_points( $input ) {
        $sanitized = array();

        if ( is_array( $input ) ) {
            foreach ( $input as $point ) {
                $sanitized[] = array(
                    'address' => isset( $point['address'] ) ? sanitize_text_field( $point['address'] ) : '',
                    'lat'     => isset( $point['lat'] ) ? sanitize_text_field( $point['lat'] ) : '',
                    'lng'     => isset( $point['lng'] ) ? sanitize_text_field( $point['lng'] ) : '',
                );
            }
        }

        return $sanitized;
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Meeting Points', 'wceventsfp' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'wcefp_meetingpoints_group' ); ?>
                <?php $points = get_option( 'wcefp_meetingpoints', array() ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Meeting Points', 'wceventsfp' ); ?></th>
                        <td>
                            <table id="wcefp-points" class="widefat" style="max-width:600px;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Indirizzo', 'wceventsfp' ); ?></th>
                                        <th><?php esc_html_e( 'Latitudine', 'wceventsfp' ); ?></th>
                                        <th><?php esc_html_e( 'Longitudine', 'wceventsfp' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( is_array( $points ) ) : ?>
                                        <?php foreach ( $points as $i => $p ) : ?>
                                            <tr>
                                                <td><input type="text" name="wcefp_meetingpoints[<?php echo esc_attr( $i ); ?>][address]" value="<?php echo esc_attr( isset( $p['address'] ) ? $p['address'] : '' ); ?>" class="regular-text" /></td>
                                                <td><input type="text" name="wcefp_meetingpoints[<?php echo esc_attr( $i ); ?>][lat]" value="<?php echo esc_attr( isset( $p['lat'] ) ? $p['lat'] : '' ); ?>" class="small-text" /></td>
                                                <td><input type="text" name="wcefp_meetingpoints[<?php echo esc_attr( $i ); ?>][lng]" value="<?php echo esc_attr( isset( $p['lng'] ) ? $p['lng'] : '' ); ?>" class="small-text" /></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <p><button type="button" class="button" id="wcefp-add-point"><?php esc_html_e( 'Aggiungi', 'wceventsfp' ); ?></button></p>
                        </td>
                    </tr>
                </table>
                <script type="text/html" id="wcefp-point-row-template">
                    <tr>
                        <td><input type="text" name="wcefp_meetingpoints[{{INDEX}}][address]" class="regular-text" /></td>
                        <td><input type="text" name="wcefp_meetingpoints[{{INDEX}}][lat]" class="small-text" /></td>
                        <td><input type="text" name="wcefp_meetingpoints[{{INDEX}}][lng]" class="small-text" /></td>
                    </tr>
                </script>
                <script>
                jQuery(function($){
                    var i = $('#wcefp-points tbody tr').length;
                    $('#wcefp-add-point').on('click', function(){
                        var html = $('#wcefp-point-row-template').html().replace(/{{INDEX}}/g, i++);
                        $('#wcefp-points tbody').append(html);
                    });
                });
                </script>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Auto-initialization removed - this class is now managed by the AdminServiceProvider
// Use WCEFP\Admin\MenuManager for consolidated admin menu management
