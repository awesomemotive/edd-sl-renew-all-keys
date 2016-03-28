<?php
/*
 * Plugin Name: Easy Digital Downloads - Software Licensing - Renew All
 * Description: Adds a "Renew all licenses" button to account page
 * Author: Pippin Williamson
 * Version: 1.0.1
 */

class EDD_SL_Renew_All {
		
	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'edd_sl_license_keys_before', array( $this, 'renew_all_button' ) );
		add_action( 'edd_renew_all_keys', array( $this, 'process_renew_all' ) );
	}

	public function renew_all_button() {
?>
		<form id="edd-sl-renew-all" class="edd-form" method="post">
			<p>
				<select name="edd_sl_renew_type">
					<option value="expired"><?php _e( 'All expired keys', 'edd-sl-renew-all' ); ?></option>
					<option value="expiring_1_month"><?php _e( 'All keys expiring within 30 days', 'edd-sl-renew-all' ); ?></option>
					<option value="all"><?php _e( 'All license keys', 'edd-sl-renew-all' ); ?></option>
				</select>
				<input type="submit" class="button" name="edd_renew_all" value="<?php _e( 'Renew', 'edd-sl-renew-all' ); ?>"/>
				<input type="hidden" name="edd_action" value="renew_all_keys"/>
				<?php wp_nonce_field( 'edd_sl_renew_all_nonce', 'edd_sl_renew_all' ); ?>
			</p>
		</form>
<?php		
	}

	public function process_renew_all() {

		if( empty( $_POST['edd_renew_all'] ) ) {
			return;
		}

		if( ! is_user_logged_in() ) {
			return;
		}

		if( ! wp_verify_nonce( $_POST['edd_sl_renew_all'], 'edd_sl_renew_all_nonce' ) ) {
			wp_die( __( 'Error', 'edd-sl-renew-all' ), __( 'Nonce verification failed', 'edd-sl-renew-all' ), array( 'response' => 403 ) );
		}

		$renew_type   = edd_sanitize_text_field( $_POST['edd_sl_renew_type'] );
		$license_keys = edd_software_licensing()->get_license_keys_of_user( get_current_user_id() );

		switch( $renew_type ) {

			case 'expired' :

				$stop_date = current_time( 'timestamp' );
				break;

			case 'expiring_1_month' :

				$stop_date = strtotime( '+1 month', current_time( 'timestamp') );
				break;

			case 'all' :
			default :

				$stop_date = false;
				break;

		}

		if( $license_keys ) {

			foreach( $license_keys as $license ) {

				if( ! edd_software_licensing()->get_license_key( $license->ID ) ) {
					continue;
				}

				$expiration = edd_software_licensing()->get_license_expiration( $license->ID );

				if( 'lifetime' === $expiration ) {
					continue;
				}

				if( $stop_date && $expiration > $stop_date ) {
					continue;
				}

				edd_sl_add_renewal_to_cart( $license->ID );

			}

			wp_redirect( edd_get_checkout_uri() ); exit;

		}

	}

}
global $edd_sl_renew_all;
$$edd_sl_renew_all = new EDD_SL_Renew_All;
