<?php
/*
 * Plugin Name: Easy Digital Downloads - Software Licensing - Renew All
 * Description: Adds a "Renew all licenses" button to account page
 * Author: Pippin Williamson
 * Version: 1.0
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
				<input type="submit" name="edd_renew_all" value="<?php _e( 'Renew', 'edd-sl-renew-all' ); ?>"/>
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

				$this->add_renewal_to_cart( $license );

			}

		}

	}

	public function add_renewal_to_cart( $license ) {

		$valid          = true;
		$license_id     = $license->ID;
		$payment_id     = get_post_meta( $license_id, '_edd_sl_payment_id', true );
		$payment        = get_post( $payment_id );
		$download_id    = edd_software_licensing()->get_download_id( $license_id );
		$license_key    = edd_software_licensing()->get_license_key( $license_id );

		if( empty( $download_id ) || empty( $payment_id ) ) {
			return false;
		}

		if ( 'publish' !== $payment->post_status && 'complete' !== $payment->post_status ) {
			return false;
		}

		$options = array( 'is_renewal' => true );
		$license_parent = ! empty( $license->post_parent ) ? get_post( $license->post_parent ) : false ;

		if ( $license->post_parent && ! empty( $license_parent ) ) {

			$parent_license_id  = $license_parent->ID;
			$parent_download_id = edd_software_licensing()->get_download_id( $parent_license_id );
			$parent_license_key = edd_software_licensing()->get_license_key( $parent_license_id );

			if ( ! edd_item_in_cart( $parent_download_id ) && ! edd_has_variable_prices( $download_id ) ) {
				edd_add_to_cart( $parent_download_id, $options );
			}

			$license_id  = $parent_license_id;
			$license     = $parent_license_key;
			$download_id = $parent_download_id;

		} elseif ( edd_is_bundled_product( $download_id ) && ! edd_item_in_cart( $download_id ) ) {

			$valid = 1;

			// Check if at least one of the bundled products is in the cart.
			foreach ( edd_get_bundled_products( $download_id ) as $item_id ) {
				if ( edd_item_in_cart( $item_id ) ) {
					$valid = true;
					break;
				}
			}

			if ( $valid && ! edd_has_variable_prices( $download_id ) ) {
				// Add the bundle to the cart.
				edd_add_to_cart( $download_id, $options );
			}

		}

		// if product has variable prices, find previous used price id and add it to cart
		if ( edd_has_variable_prices( $download_id ) ) {

			$price_id = edd_software_licensing()->get_price_id( $license_id );

			if( '' === $price_id ) {

				// If no $price_id is available, try and find it from the payment ID. See https://github.com/pippinsplugins/EDD-Software-Licensing/issues/110
				$payment_items = edd_get_payment_meta_downloads( $payment_id );

				foreach( $payment_items as $payment_item ) {

					if( (int) $payment_item['id'] !== (int) $download_id ) {
						continue;
					}

					if( isset( $payment_item['options']['price_id'] ) ) {

						$options['price_id'] = $payment_item['options']['price_id'];
						break;
					}
				}

			} else {
				$options['price_id'] = $price_id;
			}

			$cart_key = edd_get_item_position_in_cart( $download_id, $options );
			if ( false !== $cart_key ) {
				edd_remove_from_cart( $cart_key );
			}

			edd_add_to_cart( $download_id, $options );
			$valid = true;

		} else {

			$cart_key = edd_get_item_position_in_cart( $download_id );
			if ( false !== $cart_key ) {
				edd_remove_from_cart( $cart_key );
			}

			edd_add_to_cart( $download_id, $options );
			$valid = true;

		}

		if( empty( $download_id ) || ! edd_item_in_cart( $download_id ) ) {
			return;
		}

		if( true === $valid ) {

			$keys = (array) EDD()->session->get( 'edd_renewal_keys' );
			$keys[ $download_id ] = $license_key;

			EDD()->session->set( 'edd_is_renewal', '1' );
			EDD()->session->set( 'edd_renewal_keys', $keys );


		} else {

			return false;

		}

		return true;
	}

}
new EDD_SL_Renew_All;