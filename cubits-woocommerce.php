<?php
/**
 * Plugin Name: cubits-woocommerce
 * Plugin URI: https://github.com/cubits/cubits-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with Cubits.
 * Version: 1.0
 * Author: Dooga Ltd.
 * Author URI: https://cubits.com
 * License: MIT
 * Text Domain: cubits-woocommerce
 **/

/*  Copyright 2015 Dooga Ltd.

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

	function cubits_woocommerce_init() {

		if (!class_exists('WC_Payment_Gateway'))
			return;

		/**
		 * Cubits Payment Gateway
		 *
		 * Provides a Cubits Payment Gateway.
		 *
		 * @class       WC_Gateway_Cubits
		 * @extends     WC_Payment_Gateway
		 * @version     2.0.1
		 * @author      Dooga Ltd.
		 */
		class WC_Gateway_Cubits extends WC_Payment_Gateway {
			var $notify_url;

			public function __construct() {
				$this->id   = 'cubits';
				$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/bitcoin.png';

				$this->has_fields        = false;
				$this->order_button_text = __('Proceed to Cubits', 'cubits-woocommerce');
				$this->notify_url        = $this->construct_notify_url();

				$this->init_form_fields();
				$this->init_settings();

				$this->title       = $this->get_option('title');
				$this->description = $this->get_option('description');

				// Actions
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				));
				add_action('woocommerce_receipt_cubits', array(
					$this,
					'receipt_page'
				));

				// Payment listener/API hook
				add_action('woocommerce_api_wc_gateway_cubits', array(
					$this,
					'check_cubits_callback'
				));
			}

			public function admin_options() {
				echo '<h3>' . __('Cubits Payment Gateway', 'cubits-woocommerce') . '</h3>';
				$cubits_account_email = get_option("cubits_account_email");
				$cubits_error_message = get_option("cubits_error_message");
				if ($cubits_account_email != false) {
					echo '<p>' . __('Successfully connected Cubits account', 'cubits-woocommerce') . " '$cubits_account_email'" . '</p>';
				} elseif ($cubits_error_message != false) {
					echo '<p>' . __('Could not validate API Key:', 'cubits-woocommerce') . " $cubits_error_message" . '</p>';
				}
				echo '<table class="form-table">';
				$this->generate_settings_html();
				echo '</table>';
			}

			function process_admin_options() {
				if (!parent::process_admin_options())
					return false;

				require_once(plugin_dir_path(__FILE__) . 'cubits-php/lib' . DIRECTORY_SEPARATOR . 'Cubits.php');

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				// Validate merchant API key
				try {
					Cubits::configure("https://pay.cubits.com/api/v1/",true);
					$cubits = Cubits::withApiKey($api_key, $api_secret);
				}
				catch (Exception $e) {
					$error_message = $e->getMessage();
					update_option("cubits_account_email", false);
					update_option("cubits_error_message", $error_message);
					return;
				}
			}

			function construct_notify_url() {
				$callback_secret = get_option("cubits_callback_secret");
				if ($callback_secret == false) {
					$callback_secret = sha1(openssl_random_pseudo_bytes(20));
					update_option("cubits_callback_secret", $callback_secret);
				}
				$notify_url = WC()->api_request_url('WC_Gateway_Cubits');
				$notify_url = add_query_arg('callback_secret', $callback_secret, $notify_url);
				return $notify_url;
			}

			function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable Cubits plugin', 'cubits-woocommerce'),
						'type' => 'checkbox',
						'label' => __('Show bitcoin as an option to customers during checkout?', 'cubits-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'woocommerce'),
						'type' => 'text',
						'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
						'default' => __('Bitcoin', 'cubits-woocommerce')
					),
					'description' => array(
						'title'       => __( 'Description', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
						'default'     => __('Pay with bitcoin, a virtual currency.', 'cubits-woocommerce')
											. " <a href='http://bitcoin.org/' target='_blank'>"
											. __('What is bitcoin?', 'cubits-woocommerce')
											. "</a>"
					),
					'apiKey' => array(
						'title' => __('API Key', 'cubits-woocommerce'),
						'type' => 'text',
						'description' => __('')
					),
					'apiSecret' => array(
						'title' => __('API Secret', 'cubits-woocommerce'),
						'type' => 'password',
						'description' => __('')
					)
				);
			}

			function process_payment($order_id) {

				require_once(plugin_dir_path(__FILE__) . 'cubits-php/lib/Cubits.php');
				global $woocommerce;

				$order = new WC_Order($order_id);

				$success_url = add_query_arg('return_from_cubits', true, $this->get_return_url($order));

				$items = $order->get_items();

				foreach($items as $item) {
					$row = $item['qty']." x ".$item['name'].' = '.$item['line_total'].' '.get_woocommerce_currency().' <br />';
					$description .= $row;
				}
				$description .= 'Shipping & Handling Fee = '.$order->get_total_shipping().' '.$currency;
				// Cubits mangles the order param so we have to put it somewhere else and restore it on init
				$cancel_url = add_query_arg('return_from_cubits', true, $order->get_cancel_order_url());
				$cancel_url = add_query_arg('cancelled', true, $cancel_url);
				$cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);
				$options = array(
					'callback_url'       => $this->notify_url,
					'reference'          => $order->id,
					'success_url'        => $success_url,
					'cancel_url'         => $cancel_url,
					'description'				 => $description
				);

				$api_key    = $this->get_option('apiKey');
				$api_secret = $this->get_option('apiSecret');

				if ($api_key == '' || $api_secret == '') {
					$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'cubits-woocommerce'));
					return;
				}

				try {
					Cubits::configure("https://pay.cubits.com/api/v1/",true);
					$cubits = Cubits::withApiKey($api_key, $api_secret);

					$url   = $cubits->createInvoice("Order $order_id",$order->get_total(),get_woocommerce_currency(), $options)->invoice_url;
				}
				catch (Exception $e) {
					$order->add_order_note(__('Error while processing cubits payment:', 'cubits-woocommerce') . ' ' . var_export($e, TRUE));
					$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'cubits-woocommerce'));
					return;
				}

				return array(
					'result'   => 'success',
					'redirect' => $url
				);
			}

			function check_cubits_callback() {
				$callback_secret = get_option("cubits_callback_secret");
				if ($callback_secret != false && $callback_secret == $_REQUEST['callback_secret']) {
					$post_body = json_decode(file_get_contents("php://input"));
					if (isset($post_body->reference)) {
						$cubits_order = $post_body;
						$order          = new WC_Order($post_body->reference);
					} else if (isset($post_body->payout)) {
						header('HTTP/1.1 200 OK');
						exit("Cubits Payout Callback Ignored");
					} else {
						header("HTTP/1.1 400 Bad Request");
						exit("Unrecognized Cubits Callback");
					}
				} else {
					header("HTTP/1.1 401 Not Authorized");
					exit("Spoofed callback shithead");
				}

				// Legitimate order callback from Cubits
				header('HTTP/1.1 200 OK');

				// Add Cubits metadata to the order
				update_post_meta($order->id, __('Cubits Order ID', 'cubits-woocommerce'), wc_clean($cubits_order->id));
				if (isset($cubits_order->notify_email)) {
					update_post_meta($order->id, __('Cubits Account of Payer', 'cubits-woocommerce'), wc_clean($cubits_order->notify_email));
				}

				switch (strtolower($cubits_order->status)) {

					case 'completed':

						// Check order not already completed
						if ($order->status == 'completed') {
							exit;
						}

						$order->add_order_note(__('Cubits payment completed', 'cubits-woocommerce'));
						$order->payment_complete();

						break;
					case 'canceled':

						$order->update_status('failed', __('Cubits reports payment cancelled.', 'cubits-woocommerce'));
						break;
				}
				exit;
			}
		}

		/**
		 * Add this Gateway to WooCommerce
		 **/
		function woocommerce_add_cubits_gateway($methods) {
			$methods[] = 'WC_Gateway_Cubits';
			return $methods;
		}

		function woocommerce_handle_cubits_return() {
			if (!isset($_GET['return_from_cubits']))
				return;

			if (isset($_GET['cancelled'])) {
				$order = new WC_Order($_GET['order']['custom']);
				if ($order->status != 'completed') {
					$order->update_status('failed', __('Customer cancelled cubits payment', 'cubits-woocommerce'));
				}
			}

			// Cubits order param interferes with woocommerce
			unset($_GET['order']);
			unset($_REQUEST['order']);
			if (isset($_GET['order_key'])) {
				$_GET['order'] = $_GET['order_key'];
			}
		}

		add_action('init', 'woocommerce_handle_cubits_return');
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_cubits_gateway');
	}

	add_action('plugins_loaded', 'cubits_woocommerce_init', 0);
}
