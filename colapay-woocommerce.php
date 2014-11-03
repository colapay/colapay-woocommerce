<?php
/**
 * Plugin Name: colapay-woocommerce
 * Plugin URI: https://github.com/bobofzhang/colapay-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with ColaPay.
 * Version: 1.0.0
 * Author: ColaPay Inc.
 * Author URI: https://www.colapay.com
 * License: MIT
 * Text Domain: colapay-woocommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function colapay_woocommerce_init() {

        if (!class_exists('WC_Payment_Gateway')) return;

        /**
         * ColaPay Payment Gateway
         *
         * Provides a ColaPay Payment Gateway.
         *
         * @class       WC_Gateway_ColaPay
         * @extends     WC_Payment_Gateway
         * @version     1.0.0
         * @author      ColaPay Inc.
         */
        class WC_Gateway_ColaPay extends WC_Payment_Gateway {

            var $notify_url;

            public function __construct() {
                $this->id                 = 'colapay';
                $this->has_fields         = false;
                $this->method_title       = __('ColaPay', 'woocommerce');
                $this->method_description = __('Pay with bitcoin using technology from ColaPay.', 'woocommerce');
                $this->icon               = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/colapay.png';
                $this->order_button_text  = __('Proceed to ColaPay', 'colapay-woocommerce');
                $this->notify_url         = $this->construct_notify_url();

                $this->init_form_fields();
                $this->init_settings();

                $this->title       = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->debug       = $this->get_option('debug');

                if ('yes' == $this->debug) {
                    $this->log = new WC_Logger();
                }

                // Actions
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                            $this,
                            'process_admin_options'
                            ));
                add_action('woocommerce_receipt_colapay', array(
                            $this,
                            'receipt_page'
                            ));

                // Payment listener/API hook
                add_action('woocommerce_api_wc_gateway_colapay', array(
                            $this,
                            'check_colapay_callback'
                            ));
            }

            public function admin_options() {
                $colapay_account_email = get_option("colapay_account_email");
                $colapay_error_message = get_option("colapay_error_message");
                if ($colapay_account_email != false) {
                    echo '<p><strong><font color="green">' . __('Connected to ColaPay account', 'colapay-woocommerce') . " '$colapay_account_email'"
                        . __('.&nbsp;&nbsp;&nbsp;Enjoy paying with Bitcoin!', 'colapay-woocommerce') . '</font></strong></p>';
                } elseif ($colapay_error_message != false) {
                    echo '<p><font color="red">' . __('Could not validate API Credentials:', 'colapay-woocommerce') . " $colapay_error_message" . '</font></p>';
                }
                parent::admin_options();
            }

            function process_admin_options() {
                if (!parent::process_admin_options()) return false;

                require_once(plugin_dir_path(__FILE__) . 'colapay-php' . DIRECTORY_SEPARATOR . 'Colapay.php');

                $api_key    = $this->get_option('api_key');
                $api_secret = $this->get_option('api_secret');

                // validate API credentials
                try {
                    $colapay   = Colapay::key_secret_mode($api_key, $api_secret);
                    $user_info = $colapay->get_user_info();
                    update_option("colapay_account_email", $user_info->user->email);
                    update_option("colapay_error_message", false);
                    return true;
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                    update_option("colapay_account_email", false);
                    update_option("colapay_error_message", $error_message);
                    return false;
                }
            }

            function init_form_fields() {
                $this->form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable/Disable', 'colapay-woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('Show ColaPay as an option to customers during checkout', 'colapay-woocommerce'),
                            'default' => 'yes'
                        ),
                        'title' => array(
                            'title' => __('Title', 'colapay-woocommerce'),
                            'type' => 'text',
                            'description' => __('This controls the title which the user sees during checkout.', 'colapay-woocommerce'),
                            'default' => __('ColaPay', 'colapay-woocommerce'),
                            'desc_tip' => true
                        ),
                        'description' => array(
                            'title' => __('Description', 'colapay-woocommerce'),
                            'type' => 'textarea',
                            'description' => __('This controls the description which the user sees during checkout.', 'colapay-woocommerce'),
                            'default' => __('Pay with ', 'colapay-woocommerce' )
                            . '<a href="https://bitcoin.org" target="_blank">'
                            . __( 'Bitcoin', 'colapay-woocommerce' )
                            . '</a>'
                            . __( ' using technology from ', 'colapay-woocommerce' )
                            . '<a href="https://www.colapay.com" target="_blank">'
                            . __( 'ColaPay', 'colapay-woocommerce' )
                            . '</a>.',
                            'desc_tip' => true
                        ),
                        'debug' => array(
                                'title' => __('Debug Log', 'colapay-woocommerce'),
                                'type' => 'checkbox',
                                'label' => __('Enable logging', 'colapay-woocommerce'),
                                'default' => 'no',
                                'description' => sprintf(__('Log ColaPay events, such as IPN, inside <code>%s</code>.', 'colapay-woocommerce'), wc_get_log_file_path('colapay'))
                        ),
                        'api_details' => array(
                                'title' => __('API Credentials', 'colapay-woocommerce'),
                                'type' => 'title',
                                'description' => sprintf(__('Enter your API Key & API Secret & Merchant ID to use ColaPay payment gateway. Learn how to access your API Credentials from %shere%s.', 'colapay-woocommerce'), '<a href="https://www.colapay.com">', '</a>')
                        ),
                        'merchant_id' => array(
                                'title' => __('Merchant ID', 'colapay-woocommerce'),
                                'type' => 'text',
                                'description' => __('Get your merchant id from ColaPay', 'colapay-woocommerce'),
                                'desc_tip' => true,
                                'default' => ''
                        ),
                        'api_key' => array(
                                'title' => __('API Key', 'colapay-woocommerce'),
                                'type' => 'text',
                                'description' => __('Get your API Key from ColaPay', 'colapay-woocommerce'),
                                'desc_tip' => true,
                                'default' => ''
                        ),
                        'api_secret' => array(
                                'title' => __('API Secret', 'colapay-woocommerce'),
                                'type' => 'password',
                                'description' => __('Get your API Secret from ColaPay', 'colapay-woocommerce'),
                                'desc_tip' => true,
                                'default' => ''
                        )
                    );
            }

            function modify_url($url) {
                //return str_replace('localhost', '192.168.0.110', $url);
                return $url;
            }

            function process_payment($order_id) {
                require_once(plugin_dir_path(__FILE__) . 'colapay-php' . DIRECTORY_SEPARATOR . 'Colapay.php');
                global $woocommerce;

                $order = new WC_Order($order_id);

                $success_url = add_query_arg('return_from_colapay', true, $this->get_return_url($order));

                // ColaPay mangles the order param so we have to put it somewhere else and restore it on init
                $cancel_url = add_query_arg('return_from_colapay', true, $order->get_cancel_order_url());
                $cancel_url = add_query_arg('cancelled', true, $cancel_url);
                $cancel_url = add_query_arg('order_key', $order->order_key, $cancel_url);

                $api_key    = $this->get_option('api_key');
                $api_secret = $this->get_option('api_secret');
                $merchant   = $this->get_option('merchant_id');

                if ($api_key == '' || $api_secret == '' || $merchant == '') {
                    $woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method. (plugin not configured)', 'colapay-woocommerce'));
                    return;
                }

                $name     = 'Order #' . $order_id;
                $price    = $order->get_total();
                $currency = get_woocommerce_currency();
                $options  = array(
                        'custom_id'          => $order_id,
                        'callback_url'       => $this->modify_url($this->notify_url),
                        'redirect_url'       => $this->modify_url($success_url),
                );

                if ('yes' == $this->debug) {
                    $tmp = $options;
                    $tmp['name'] = $name;
                    $tmp['price'] = $price;
                    $tmp['currency'] = $currency;
                    $this->log->add('colapay', 'Process order ' . $order_id . ': ' . json_encode($tmp, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }

                try {
                    $colapay    = Colapay::key_secret_mode($api_key, $api_secret);
                    if ('yes' == $this->debug) {
                        $this->log->add('colapay', 'Create Colapay client ok');
                    }
                    $res        = $colapay->create_invoice($name, $price, $currency, $merchant, $options);
                    if ('yes' == $this->debug) {
                        $this->log->add('colapay', 'Create invoice using colapay client ok');
                        $this->log->add('colapay', 'Colapay response is ' . json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    }
                    $invoice_id = $res->invoice->id;
                } catch (Exception $e) {
                    $order->add_order_note(__('Error while processing colapay payment:', 'colapay-woocommerce') . ' ' . var_export($e, TRUE));
                    $woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.' . var_export($e, TRUE), 'colapay-woocommerce'));
                    return;
                }

                return array(
                        'result'   => 'success',
                        'redirect' => Colapay::API_HOST_4_PRODUCTION . "/invoice/$invoice_id/pay"
                );
            }

            function construct_notify_url() {
                // must NOT use $this->get_option
                $callback_secret = get_option("colapay_callback_secret");
                if ($callback_secret == false) {
                    $callback_secret = sha1(openssl_random_pseudo_bytes(20));
                    update_option("colapay_callback_secret", $callback_secret);
                }
                $notify_url = WC()->api_request_url('WC_Gateway_ColaPay');
                $notify_url = add_query_arg('callback_secret', $callback_secret, $notify_url);
                return $notify_url;
            }

            function check_colapay_callback() {
                // must NOT use $this->get_option
                $callback_secret = get_option("colapay_callback_secret");
                if ('yes' == $this->debug) {
                    $this->log->add('colapay', 'Process ColaPay IPN');
                    $this->log->add('colapay', 'Compare callback secret: ' . $callback_secret . '(target) vs '
                            . $_REQUEST['callback_secret'] . '(received)');
                }
                if ($callback_secret != false && $callback_secret == $_REQUEST['callback_secret']) {
                    if ('yes' == $this->debug) {
                        $this->log->add('colapay', 'Compare callback secret successfully');
                    }
                    $received_data = file_get_contents("php://input");
                    if ('yes' == $this->debug) {
                        $this->log->add('colapay', 'Received_data: ' . $received_data);
                    }
                    $post_body = json_decode($received_data);
                    if (NULL !== $post_body) {
                        if ('yes' == $this->debug) {
                            $this->log->add('colapay', 'Decode ColaPay IPN content successfully');
                            $this->log->add('colapay', 'Order_id: ' . $post_body->custom_id);
                        }
                        $colapay_order = $post_body;
                        $order_id      = $post_body->custom_id;
                        $order         = new WC_Order($order_id);
                    } else {
                        if ('yes' == $this->debug) {
                            $this->log->add('colapay', 'Decode ColaPay IPN content fail');
                        }
                        header("HTTP/1.1 400 Bad Request");
                        exit("Unrecognized ColaPay Callback");
                    }
                } else {
                    if ('yes' == $this->debug) {
                        $this->log->add('colapay', 'Compare callback secret fail');
                    }
                    header("HTTP/1.1 401 Not Authorized");
                    exit("Spoofed callback");
                }

                // Legitimate order callback from ColaPay
                header('HTTP/1.1 200 OK');

                // Add ColaPay metadata to the order
                update_post_meta($order->id, __('ColaPay Order ID', 'colapay-woocommerce'), wc_clean($colapay_order->id));
                if (isset($colapay_order->customer_info) && isset($colapay_order->customer_info->email)) {
                    update_post_meta($order->id, __('ColaPay Account of Payer', 'colapay-woocommerce'), wc_clean($colapay_order->customer_info->email));
                }

                $colapay_order_status = strtolower($colapay_order->status);
                if ('yes' == $this->debug) {
                    $this->log->add('colapay', 'ColaPay order status: ' . $colapay_order_status);
                }
                switch ($colapay_order_status) {
                    case 'paid':  // almost instantly
                        $order->update_status('processing', __('ColaPay detects payment without confirmation', 'colapay-woocommerce'));
                        break;

                    case 'confirmed':  // need about 10 minutes
                        $order->update_status('processing', __('ColaPay reports payment confirmed', 'colapay-woocommerce'));
                        break;

                    case 'credited':  // need about 1 hour
                        // Check order not already completed
                        if ($order->status == 'completed') {
                            exit;
                        }

                        $order->add_order_note(__('ColaPay payment completed', 'colapay-woocommerce'));
                        $order->payment_complete();

                        break;

                    case 'canceled':
                        $order->update_status('cancelled', __('ColaPay reports payment cancelled', 'colapay-woocommerce'));
                        break;

                    case 'expired':
                    case 'invalid':
                        $order->update_status('failed', __('ColaPay reports payment ' . $colapay_order_status, 'colapay-woocommerce'));
                        break;
                }

                if ('yes' == $this->debug) {
                    $this->log->add('colapay', 'Process ColaPay IPN successfully');
                }

                exit;
            }
        }

        /**
         * Add this Gateway to WooCommerce
         **/
        function woocommerce_add_colapay_gateway($methods) {
            $methods[] = 'WC_Gateway_ColaPay';
            return $methods;
        }

        function woocommerce_handle_colapay_return() {
            if (!isset($_GET['return_from_colapay'])) return;

            if (isset($_GET['cancelled'])) {
                $order = new WC_Order($_GET['order']['custom']);
                if ($order->status != 'completed') {
                    $order->update_status('cancelled', __('Customer cancelled colapay payment', 'colapay-woocommerce'));
                }
            }

            // ColaPay order param interferes with woocommerce
            unset($_GET['order']);
            unset($_REQUEST['order']);
            if (isset($_GET['order_key'])) {
                $_GET['order'] = $_GET['order_key'];
            }
        }

        add_action('init', 'woocommerce_handle_colapay_return');
        add_filter('woocommerce_payment_gateways', 'woocommerce_add_colapay_gateway');
    }

    add_action('plugins_loaded', 'colapay_woocommerce_init', 0);
}
