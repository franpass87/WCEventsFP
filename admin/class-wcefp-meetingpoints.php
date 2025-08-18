<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCEFP_MeetingPoints {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_settings_page() {
        add_submenu_page(
            'wcefp', // slug principale del plugin
            'Meeting Points',
            'Meeting Points',
            'manage_options',
            'wcefp-meetingpoints',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wcefp_meetingpoints_group', 'wcefp_meetingpoints' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Meeting Points</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'wcefp_meetingpoints_group' ); ?>
                <?php $points = get_option( 'wcefp_meetingpoints', array() ); ?>
                <table class="form-table">
                    <tr>
                        <th>Indirizzi Meeting Point</th>
                        <td>
                            <textarea name="wcefp_meetingpoints" rows="6" cols="60"><?php echo esc_textarea( implode("\n", (array)$points ) ); ?></textarea>
                            <p class="description">Inserisci un indirizzo per riga</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new WCEFP_MeetingPoints();

