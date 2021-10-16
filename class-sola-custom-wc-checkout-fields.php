<?php
// Exit if accessed directly.
defined('WPINC') or exit;

class Sola_Custom_WC_Checkout_Fields{

	private $field_names     = array( 'billing_fields','shipping_fields','special_fields');
	private $shipping_fields = false;
	private $billing_fields  = false;
	private $special_fields  = false;

	public function __construct( $billing_fields = false, $shipping_fields = false, $special_fields = false ){

		if( is_array($billing_fields) && sizeof($billing_fields) ){
			$this->billing_fields = $billing_fields;
		}

		if( is_array($shipping_fields) && sizeof($shipping_fields) ){
			$this->shipping_fields = $shipping_fields;
		}

		if( is_array($special_fields) && sizeof($special_fields) ){
			$this->special_fields = $special_fields;
		}

		if( $this->billing_fields || $this->shipping_fields || $this->special_fields ){
			$this->init();
		}
		
	}


	function init(){

		$this->show_fields_for_checkout();

		$this->validate_fields();

		/**
		 * Check if the fields need to be saved to usermeta
		 */
		add_action( 'woocommerce_checkout_update_customer',[$this,'may_update_customer'],10, 2 );

		$this->save_special_fields();

		$this->display_fields_for_order_details();

		$this->display_fields_for_admin();

		$this->display_fields_for_email();
	
	}


	function show_fields_for_checkout(){
		/**
		 * Define checkout fields
		 * Fields defined in checkout_fields array is automatically processed and saved to the order post meta
		 * 
		 */
		add_filter( 'woocommerce_checkout_fields' , [$this,'override_checkout_fields'] );


		/**
		 * Define special custom fields which are displayed after order notes
		 * 
		 */
		add_action( 'woocommerce_after_order_notes', [$this,'custom_special_fields'] );
	}

	function override_checkout_fields( $checkout_fields ) {

		if( $this->billing_fields ){
			$checkout_fields['billing'] += $this->billing_fields;
		}

		if( $this->shipping_fields ){
			$checkout_fields['shipping'] += $this->shipping_fields;
		}
		
	    return $checkout_fields;
	}


	function custom_special_fields( $checkout ) {

		if( ! $this->special_fields ){
			return $checkout;
		}

		echo '<div class="cp-checkout-special-fields">';
		foreach( $this->special_fields as $field_key => $field ){
			woocommerce_form_field( $field_key, $field, $checkout->get_value($field_key) );
		}
		echo '</div>';
	}


	/**
	 * Validate fields, both for normal fields and special fields
	 * 
	 */
	function validate_fields(){
		
		add_action( 'woocommerce_checkout_process', [$this,'validate_all_checkout_fields'] );

	}

	function validate_all_checkout_fields(){

		if( $this->special_fields ){

			foreach( $this->special_fields as $field_key => $field ){

				if( $field['required'] && isset($_POST[$field_key]) && ! $_POST[$field_key] ){
					wc_add_notice( sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $field['label'] ) . '</strong>' ), 'error' );
				}
			}
		}
	}


	/**
	 * Wheather to save data to custom user meta or not
	 * If saved to user meta, the fields will be populated with saved data on checkout page
	 * 
	 */
	function may_update_customer( $customer, $data ){
		$this->maybe_not_save_to_usermeta( $this->billing_fields, $customer );
		$this->maybe_not_save_to_usermeta( $this->shipping_fields, $customer );
	}

	function maybe_not_save_to_usermeta( $fields, $customer ){

		if( $fields ){
			foreach( $fields as $field_key => $field ){
				if( isset($field['save_usermeta']) && ! $field['save_usermeta'] ){
					$customer->delete_meta_data($field_key);
				}
			}
		}	
	}


	/**
	 * Save the fields after order notes to order post meta
	 * 
	 */
	function save_special_fields(){
		
		add_action( 'woocommerce_checkout_update_order_meta', [$this,'save_speical_fields_to_order_meta'] );
	}

	function save_speical_fields_to_order_meta( $order_id ) {

		if( $this->special_fields ){

			foreach( $this->special_fields as $field_key => $field ){

				if ( ! empty( $_POST[$field_key] ) ) {
				    update_post_meta( $order_id, '_' . $field_key, sanitize_text_field( $_POST[$field_key] ) );
				}
			}
		}
	}


	/**
	 * Display fields in order details
	 * 
	 */
	function display_fields_for_order_details(){

		/**
		 * Position 1 - Display inside order total after shipping method
		 */
		add_filter( 'woocommerce_get_order_item_totals', [$this, 'display_after_shipping_method'], 10, 2 );

		/**
		 * Position 2 - Display after order total
		 */
		add_action( 'woocommerce_order_details_after_order_table', [$this,'display_after_order_details'] );
		
	}

	function display_after_shipping_method( $total_rows, $order ){

		$new_row = array();

		foreach( $this->field_names as $field_name ){

			if( $formatted = $this->get_field_values( $this->$field_name, $order ) ){
				
				foreach( $formatted as $field ){

					if( isset($field['display_position']) && ($field['display_position'] != '1')){
						continue;
					}

					$new_row[$field['field_key']] = array(
						'label' => $field['label'],
						'value' => $field['value']
					);
				}
			}
		}

		if( sizeof($new_row) ){
			$total_rows = array_merge( array_splice( $total_rows,0,2), $new_row, $total_rows );
		}
		
		
		return $total_rows;
	}

	function display_after_order_details( $order ){
		ob_start();
		$html = '';

		foreach( $this->field_names as $field_name ){
			if( $formatted = $this->get_field_values( $this->$field_name, $order ) ){

				foreach( $formatted as $field ){

					if( isset($field['display_position']) && $field['display_position'] != '2' ){
						continue;
					}

					if( isset($field['show_in_order_details']) && ! $field['show_in_order_details'] ){
						continue;
					}
					echo '<tr><th>', $field['label'], '</th><td>', $field['value'],'</td></tr>';
				}
			}
		}

		$html= ob_get_clean();

		if( $html ){
			echo '<table class="shop_table order_details order_extra"><tbody>', $html,'</tbody></table>';
		}
		
	}


	/**
	 * Display fields in user email
	 * 
	 */
	function display_fields_for_email(){

		add_filter( 'woocommerce_email_order_meta_fields', [$this,'display_after_email_item_details'],10, 3 );
	}

	function display_after_email_item_details( $fields, $sent_to_admin, $order ){

		$billing_fields  = $this->get_email_order_meta_keys( $this->billing_fields, $order );
		$shipping_fields = $this->get_email_order_meta_keys( $this->shipping_fields, $order );
		$special_fields  = $this->get_email_order_meta_keys( $this->special_fields, $order );
		
		return $billing_fields + $shipping_fields + $special_fields;
	}
	
	function get_email_order_meta_keys( $fields, $order ){

		$formatted = array();

		if( $fields ){

			$field_values = $this->get_field_values( $fields, $order );

			foreach( $field_values as $field ){

				if( isset($field['display_position']) && ($field['display_position'] != '2') ){
					continue;
				}

				if( isset($field['show_in_email']) && ! $field['show_in_email'] ){
					continue;
				}

				$value = $field['value'];
				$field_key = $field['field_key'];

				$formatted[$field_key] = array(
					'label' => $field['label'],
					'value' => $value
				);	
			}
		}
		return $formatted;
	}


	/**
	 * Display meta info in admin order after billing address
	 * 
	 */
	function display_fields_for_admin(){
		add_action( 'woocommerce_admin_order_data_after_billing_address', [$this,'display_in_admin_order_meta_billing'],20 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [$this,'display_in_admin_order_meta_shipping'],20 );
	}

	function display_in_admin_order_meta_billing( $order ){

		$this->admin_show_fields( $this->special_fields, $order );

		$this->admin_show_fields( $this->billing_fields, $order );

	}

	function display_in_admin_order_meta_shipping( $order ){

		$this->admin_show_fields( $this->shipping_fields, $order );
	}

	function admin_show_fields( $fields, $order ){

		if( $formatted = $this->get_field_values( $fields, $order ) ){
			foreach( $formatted as $field ){
				if( isset($field['show_in_admin']) && ! $field['show_in_admin'] ){
					continue;
				}
				echo '<p><strong style="display:block">', $field['label'], ':</strong> ', $field['value'],'</p>';
			}
		}
	}


	function get_field_values( $fields, $order  ){
		$formatted = array();
		if( $fields ){
			foreach( $fields as $field_key => $field ){

				$saved_field_value = $this->get_order_meta( $field_key, $order );

				if( in_array($field['type'], array('select','radio','checkbox') ) ){
					$saved_field_value = $field['options'][$saved_field_value] ?? false;
				} 

				$formatted[] = array(
					'label'                 => esc_html($field['label']),
					'value'                 => $saved_field_value,
					'show_in_email'         => $field['show_in_email'] ?? true,
					'show_in_admin'         => $field['show_in_admin'] ?? true,
					'show_in_order_details' => $field['show_in_order_details'] ?? true,
					'field_key'             => $field_key,
					'display_position'	    => $field['display_position'] ?? '1'
				);
			}
			return $formatted;
		}
		return false;
	}


	function get_order_meta( $field_key, $order ){
		return get_post_meta( $order->get_id(), '_' . $field_key,true );
	}

}