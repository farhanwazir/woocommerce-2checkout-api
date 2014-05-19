<?php
/*
  Plugin Name: 2Checkout Payment Gateway
  Plugin URI:
  Description: Allows you to use 2Checkout payment gateway with the WooCommerce plugin.
  Version: 0.0.1
  Author: Craig Christenson
  Author URI: https://www.2checkout.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
  ------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_twocheckout', 0);

function woocommerce_twocheckout(){
	if (!class_exists('WC_Payment_Gateway'))
		return; // if the WC payment gateway class is not available, do nothing
	if(class_exists('WC_Twocheckout'))
		return;

    class WC_Gateway_Twocheckout extends WC_Payment_Gateway{
        public function __construct(){

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'twocheckout';
            $this->icon = apply_filters('woocommerce_twocheckout_icon', ''.$plugin_dir.'twocheckout.png');
            $this->has_fields = true;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->seller_id = $this->get_option('seller_id');
            $this->publishable_key = $this->get_option('publishable_key');
            $this->private_key = $this->get_option('private_key');
            $this->description = $this->get_option('description');
            $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
            $this->sandbox = $this->get_option('sandbox');

            // Logs
            if ($this->debug == 'yes'){
                $this->log = $woocommerce->logger();
            }

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_ipn_response'));

            if (!$this->is_valid_for_use()){
                $this->enabled = false;
            }
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_twocheckout_supported_currencies', array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB' ) ) ) ) return false;

            return true;
        }
	
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 1.0.0
         */
        public function admin_options() {

            ?>
            <h3><?php _e( '2Checkout', 'woocommerce' ); ?></h3>
            <p><?php _e( '2Checkout - Credit Card/Paypal', 'woocommerce' ); ?></p>

            <?php if ( $this->is_valid_for_use() ) : ?>

                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table><!--/.form-table-->

            <?php else : ?>
                <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( '2Checkout does not support your store currency.', 'woocommerce' ); ?></p></div>
            <?php
            endif;
        }
	
	

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {
		
	    $shipping_methods = array();

    	    if ( is_admin() )
	    		foreach ( WC()->shipping->load_shipping_methods() as $method ) {
		    		$shipping_methods[ $method->id ] = $method->get_title();
	    		}
	    	
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable 2Checkout', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Credit Card/PayPal', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __( 'Description', 'woocommerce' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Pay with Credit Card/PayPal', 'woocommerce' )
                ),
                'enable_for_methods' => array(
			'title'             => __( 'Enable for shipping methods', 'woocommerce' ),
			'type'              => 'multiselect',
			'class'             => 'chosen_select',
			'css'               => 'width: 450px;',
			'default'           => '',
			'description'       => __( 'If 2CO is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce' ),
			'options'           => $shipping_methods,
			'desc_tip'          => true,
			'custom_attributes' => array(
						'data-placeholder' => __( 'Select shipping methods', 'woocommerce' )
					)
		),
                'seller_id' => array(
                    'title' => __( 'Seller ID', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter your 2Checkout account number; this is needed in order to take payment.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'publishable_key' => array(
                    'title' => __( 'Publishable Key', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter your 2Checkout Publishable Key; this is needed in order to take payment.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'private_key' => array(
                    'title' => __( 'Private Key', 'woocommerce' ),
                    'type' 			=> 'text',
                    'description' => __( 'Please enter your 2Checkout Private Key; this is needed in order to take payment.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder'	=> ''
                ),
                'sandbox' => array(
                    'title' => __( 'Sandbox/Production', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Use 2Checkout Sandbox', 'woocommerce' ),
                    'default' => 'no'
                )
            );

        }
	
	/**
	 * Check If The Gateway Is Available For Use
	 *
	 * @return bool
	 */
	public function is_available() {
		$order = null;

		if ( ! $this->enable_for_virtual ) {
			if ( WC()->cart && ! WC()->cart->needs_shipping() ) {
				return false;
			}

			if ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
				$order_id = absint( get_query_var( 'order-pay' ) );
				$order    = new WC_Order( $order_id );

				// Test if order needs shipping.
				$needs_shipping = false;

				if ( 0 < sizeof( $order->get_items() ) ) {
					foreach ( $order->get_items() as $item ) {
						$_product = $order->get_product_from_item( $item );

						if ( $_product->needs_shipping() ) {
							$needs_shipping = true;
							break;
						}
					}
				}

				$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

				if ( $needs_shipping ) {
					return false;
				}
			}
		}

		if ( ! empty( $this->enable_for_methods ) ) {

			// Only apply if all packages are being shipped via local pickup
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( isset( $chosen_shipping_methods_session ) ) {
				$chosen_shipping_methods = array_unique( $chosen_shipping_methods_session );
			} else {
				$chosen_shipping_methods = array();
			}

			$check_method = false;

			if ( is_object( $order ) ) {
				if ( $order->shipping_method ) {
					$check_method = $order->shipping_method;
				}

			} elseif ( empty( $chosen_shipping_methods ) || sizeof( $chosen_shipping_methods ) > 1 ) {
				$check_method = false;
			} elseif ( sizeof( $chosen_shipping_methods ) == 1 ) {
				$check_method = $chosen_shipping_methods[0];
			}

			if ( ! $check_method ) {
				return false;
			}

			$found = false;

			foreach ( $this->enable_for_methods as $method_id ) {
				if ( strpos( $check_method, $method_id ) === 0 ) {
					$found = true;
					break;
				}
			}

			if ( ! $found ) {
				return false;
			}
		}

		return parent::is_available();
	}
	
        /**
         * Generate the credit card payment form
         *
         * @access public
         * @param none
         * @return string
         */
        function payment_fields() {
            $plugin_dir = plugin_dir_url(__FILE__);
            // Description of payment method from settings
            if ($this->description) { ?>
                <p><?php
                echo $this->description; ?>
                </p><?php
            } ?>

            <ul class="woocommerce-error" style="display:none" id="twocheckout_error_creditcard">
            <li>Credit Card details are incorrect, please try again.</li>
            </ul>

            <fieldset>

            <input id="sellerId" type="hidden" maxlength="16" width="20" value="<?php echo $this->seller_id ?>">
            <input id="publishableKey" type="hidden" width="20" value="<?php echo $this->publishable_key ?>">
            <input id="token" name="token" type="hidden" value="">

            <!-- Credit card number -->
            <p class="form-row form-row-first">
                <label for="ccNo"><?php echo __( 'Credit Card number', 'woocommerce' ) ?> <span class="required">*</span></label>
                <input type="text" class="input-text" id="ccNo" autocomplete="off" value="" />

            </p>

            <div class="clear"></div>

            <!-- Credit card expiration -->
            <p class="form-row form-row-first">
                <label for="cc-expire-month"><?php echo __( 'Expiration date', 'woocommerce') ?> <span class="required">*</span></label>
                <select id="expMonth" class="woocommerce-select woocommerce-cc-month">
                    <option value=""><?php _e( 'Month', 'woocommerce' ) ?></option><?php
                    $months = array();
                    for ( $i = 1; $i <= 12; $i ++ ) {
                        $timestamp = mktime( 0, 0, 0, $i, 1 );
                        $months[ date( 'n', $timestamp ) ] = date( 'F', $timestamp );
                    }
                    foreach ( $months as $num => $name ) {
                        printf( '<option value="%02d">%s</option>', $num, $name );
                    } ?>
                </select>
                <select id="expYear" class="woocommerce-select woocommerce-cc-year">
                    <option value=""><?php _e( 'Year', 'woocommerce' ) ?></option>
                    <?php
                    $years = array();
                    for ( $i = date( 'y' ); $i <= date( 'y' ) + 15; $i ++ ) {
                        printf( '<option value="20%u">20%u</option>', $i, $i );
                    }
                    ?>
                </select>
            </p>
            <div class="clear"></div>

            <!-- Credit card security code -->
            <p class="form-row">
            <label for="cvv"><?php _e( 'Card security code', 'woocommerce' ) ?> <span class="required">*</span></label>
            <input type="text" class="input-text" id="cvv" autocomplete="off" maxlength="4" style="width:55px" />
            <span class="help"><?php _e( '3 or 4 digits usually found on the signature strip.', 'woocommerce' ) ?></span>
            </p>

            <div class="clear"></div>

            </fieldset>

            <script type="text/javascript">
                var myForm = document.getElementsByName('checkout')[0];
                myForm.id = "tcoCCForm";
                jQuery('#tcoCCForm').on("click", function(){
                    jQuery('#place_order').unbind('click');
                    jQuery('#place_order').click(function(e) {
                        e.preventDefault();
                        retrieveToken();
                    });
                });

                function successCallback(data) {
                    clearPaymentFields();
                    jQuery('#token').val(data.response.token.token);
                    jQuery('#place_order').unbind('click');
                    jQuery('#place_order').click(function(e) {
                        return true;
                    });
                    jQuery('#place_order').click();
                }

                function errorCallback(data) {
                    if (data.errorCode === 200) {
                        TCO.requestToken(successCallback, errorCallback, 'tcoCCForm');
                    } else if(data.errorCode == 401) {
                        clearPaymentFields();
                        jQuery('#place_order').click(function(e) {
                            e.preventDefault();
                            retrieveToken();
                        });
                        jQuery("#twocheckout_error_creditcard").show();
                        
                    } else{
                        clearPaymentFields();
                        jQuery('#place_order').click(function(e) {
                            e.preventDefault();
                            retrieveToken();
                        });
                        alert(data.errorMsg);
                    }
                }

                var retrieveToken = function () {
                    jQuery("#twocheckout_error_creditcard").hide();                    
                    if (jQuery('div.payment_method_twocheckout:first').css('display') === 'block') {
                        jQuery('#ccNo').val(jQuery('#ccNo').val().replace(/[^0-9\.]+/g,''));
                        TCO.requestToken(successCallback, errorCallback, 'tcoCCForm');
                    } else {
                        jQuery('#place_order').unbind('click');
                        jQuery('#place_order').click(function(e) {
                            return true;
                        });
                        jQuery('#place_order').click();
                    }
                }

                function clearPaymentFields() {
                    jQuery('#ccNo').val('');
                    jQuery('#cvv').val('');
                    jQuery('#expMonth').val('');
                    jQuery('#expYear').val('');
                }

            </script>

            <?php if ($this->sandbox == 'yes'): ?>
                <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/script/publickey/<?php echo $this->seller_id ?>"></script>
                <script type="text/javascript" src="https://sandbox.2checkout.com/checkout/api/2co.js"></script>
            <?php else: ?>
                <script type="text/javascript" src="https://www.2checkout.com/checkout/api/script/publickey/<?php echo $this->seller_id ?>"></script>
                <script type="text/javascript" src="https://www.2checkout.com/checkout/api/2co.js"></script>
            <?php endif ?>
            <?php
        }

        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment( $order_id ) {
            global $woocommerce;

            $order = new WC_Order($order_id);

            if ( 'yes' == $this->debug )
                $this->log->add( 'twocheckout', 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );

            // 2Checkout Args
            $twocheckout_args = array(
                                    'token'         => $_POST['token'],
                                    'sellerId'      => $this->seller_id,
                                    'currency' => get_woocommerce_currency(),
                                    'total'         => $order->get_total(),

                                    // Order key
                                    'merchantOrderId'    => $order->get_order_number(),

                                    // Billing Address info
                                    "billingAddr" => array(
                                        'name'          => $order->billing_first_name . ' ' . $order->billing_last_name,
                                        'addrLine1'     => $order->billing_address_1,
                                        'addrLine2'     => $order->billing_address_2,
                                        'city'          => $order->billing_city,
                                        'state'         => $order->billing_state,
                                        'zipCode'       => $order->billing_postcode,
                                        'country'       => $order->billing_country,
                                        'email'         => $order->billing_email,
                                        'phoneNumber'   => $order->billing_phone
                                    )
                                );

            try {
                if ($this->sandbox == 'yes') {
                    TwocheckoutApi::setCredentials($this->seller_id, $this->private_key, 'sandbox');
                } else {
                    TwocheckoutApi::setCredentials($this->seller_id, $this->private_key);
                }
                $charge = Twocheckout_Charge::auth($twocheckout_args);
                if ($charge['response']['responseCode'] == 'APPROVED') {
                    $order->payment_complete();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
            } catch (Twocheckout_Error $e) {
                $woocommerce->add_error(__('Payment error:', 'woothemes') . $e->getMessage());
                return;
            }
        }

    }

    include plugin_dir_path(__FILE__).'Twocheckout/TwocheckoutApi.php';

    /**
     * Add the gateway to WooCommerce
     **/
    function add_twocheckout_gateway($methods){
        $methods[] = 'WC_Gateway_Twocheckout';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_twocheckout_gateway');

}
