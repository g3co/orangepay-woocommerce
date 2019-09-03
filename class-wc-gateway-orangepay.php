<?php
/*
  Plugin Name: Orangepay Payment Gateway
  Plugin URI:
  Description: Allows to use Orangepay Payment Gateway with the WooCommerce plugin.
  Version: 0.1
  Author: hellovoid
  Author URI: https://github.com/hellovoid/orangepay-woocommerce
 */

if (!defined('ABSPATH')) exit;

add_action('plugins_loaded', 'woocommerce_orangepay', 0);


function woocommerce_orangepay()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;
    if (class_exists('WC_Gateway_Orangepay'))
        return;

    class WC_Gateway_Orangepay extends WC_Payment_Gateway
    {
        public static $log_enabled = false;
        public static $log = false;

        protected $payment_data = [];

        public function __construct()
        {
            global $woocommerce;

            $this->id = 'orangepay';
            $this->icon = apply_filters('woocommerce_orangepay_icon', '' . plugin_dir_url(__FILE__) . 'orangepay.png');
            $this->has_fields = true;
            $this->order_button_text = __('Proceed to Orangepay', 'woocommerce');
            $this->method_title = __('Orangepay', 'woocommerce');
            $this->method_description = __('Redirects customers to Orangepay to enter their payment information.', 'woocommerce');

            $this->supports = array(
                'products',
                'refunds',
            );
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->testmode = 'yes' === $this->get_option('testmode', 'no');
            $this->debug = 'yes' === $this->get_option('debug', 'no');
            $this->email = $this->get_option('email');
            $this->receiver_email = $this->get_option('receiver_email', $this->email);
            $this->api_url = $this->get_option('api_url');
            $this->api_token = $this->get_option('api_token');
            self::$log_enabled = $this->debug;

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_orangepay_webhook', array($this, 'webhook'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Logging method.
         *
         * @param string $message Log message.
         * @param string $level Optional. Default 'info'. Possible values:
         *                      emergency|alert|critical|error|warning|notice|info|debug.
         */
        public static function log($message, $level = 'info')
        {
            if (self::$log_enabled) {
                if (empty(self::$log)) {
                    self::$log = wc_get_logger();
                }
                self::$log->log($level, $message, array('source' => 'orangepay'));
            }
        }

        /**
         * Get gateway icon.
         *
         * @return string
         */
        public function get_icon()
        {
            $icon_html = sprintf('<a href="%1$s" class="about_orangepay" onclick="javascript:window.open(\'%1$s\',\'WIOP\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__('What is Orangepay?', 'woocommerce') . '</a>', esc_url($this->get_icon_url($base_country)));

            $icon_html .= '<div style="text-align:center;"><img style="max-width:200px;width:100%;max-height:none;float:none;margin:0 auto;" src="' . esc_attr($this->get_icon_image($base_country)) . '" alt="' . esc_attr__('Orangepay', 'woocommerce') . '" /></div>';
            return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
        }

        /**
         * Get the link for an icon based on country.
         *
         * @param string $country Country two letter code (ignored).
         * @return string
         */
        protected function get_icon_url($country)
        {
            return 'https://orange-pay.com/about';
        }

        /**
         * Get Orangepay image.
         *
         * @param string $country Country code. (ignored)
         * @return image URL
         */
        protected function get_icon_image($country)
        {
            $icon = plugin_dir_url(__FILE__) . 'assets/images/orangepay.png';

            return apply_filters('woocommerce_orangepay_icon', $icon);
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use()
        {
            return in_array(
                get_woocommerce_currency(),
                apply_filters(
                    'woocommerce_orangepay_supported_currencies',
                    array('EUR', 'USD', 'RUB', 'CZK', 'HUF', 'PLN', 'CHF', 'AUD', 'GBP', 'THB')),
                true);
        }

        /**
         * Admin Panel Options.
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options()
        {

            if ($this->is_valid_for_use()) {
                parent::admin_options();
            } else {
                ?>
                <div class="inline error">
                    <p>
                        <strong><?php esc_html_e('Gateway disabled', 'woocommerce'); ?></strong>: <?php esc_html_e('Orangepay does not support your store currency.', 'woocommerce'); ?>
                    </p>
                </div>
                <?php
            }
        }

        public function payment_fields()
        {

            $form_id = "wc-" . esc_attr($this->id) . "-cc-form";

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="' . $form_id . '" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action('woocommerce_credit_card_form_start', $this->id);

            $month_list = '<select id="' . $form_id . '-expdate-month" name="' . $form_id . '-expdate-month">';

            for ($i=1; $i <= 12; $i++) {
                $selected = "";
                if ((int)date("m") == $i) {
                    $selected = "selected";
                }
                $month_list .= sprintf('<option value="%02d" %s>%02d</option>', $i, $selected, $i);
            }

            $month_list .= '</select>';

            $year_list = '<select id="' . $form_id . '-expdate-year" name="' . $form_id . '-expdate-year">';

            for ($i=date("y"); $i <= date("y") + 20; $i++) {
                $year_list .= sprintf('<option value="%02d">%02d</option>', $i, $i);
            }

            $year_list .= '</select>';


            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide"><label>Card Holder <span class="required">*</span></label>
                    <input id="' . $form_id . '-card-holder" type="text" name="' . $form_id . '-card-holder" autocomplete="off">
                </div>
                <div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
                    <input id="' . $form_id . '-ccNo" type="text" name="' . $form_id . '-ccNo" minlength="16" maxlength="16" autocomplete="off">
                </div>
                <div class="form-row form-row-wide">
                    <span>Expiry Date <span class="required">*</span></span>
                </div>
                <div class="form-row form-row-first">' . $month_list . '</div>
                <div class="form-row form-row-last">' . $year_list . '</div>
                <div class="form-row form-row-wide">
                    <label>Card Code (CVC) <span class="required">*</span></label>
                    <input id="' . $form_id . '-cvv" name="' . $form_id . '-cvv" type="password" maxlength="4" autocomplete="off" placeholder="CVC">
                </div>
                <div class="clear"></div>';

            do_action('woocommerce_credit_card_form_end', $this->id);

            echo '<div class="clear"></div></fieldset>';

        }


        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields()
        {
            $this->form_fields = include plugin_dir_path(__FILE__) . 'includes/settings-orangepay.php';
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment($order_id)
        {
            global $woocommerce;

            $order = wc_get_order($order_id);


            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-orangepay-request.php';
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-orangepay-helpers.php';

            $card = new WC_Gateway_Orangepay_Card();
            $card->card_holder = $_POST['wc-orangepay-cc-form-card-holder'];
            $card->card_number = $_POST['wc-orangepay-cc-form-ccNo'];
            $card->card_expiry_month = $_POST['wc-orangepay-cc-form-expdate-month'];
            $card->card_expiry_year = $_POST['wc-orangepay-cc-form-expdate-year'];
            $card->cvv = $_POST['wc-orangepay-cc-form-cvv'];

            $orangepay_request = new WC_Gateway_Orangepay_Request($this);
            $orangepay_request->add_card($card);

            if ($response = $orangepay_request->process_payment($order)) {
                switch ($response->status) {
                    case WC_Gateway_Orangepay_Payment_Response::status_success:
                        $woocommerce->cart->empty_cart();
                        $order->add_order_note(
                            sprintf(__('Transaction has been paid - ID: %1$s', 'woocommerce'), $order->get_transaction_id())
                        );
                        $order->payment_complete();
                        break;
                    case WC_Gateway_Orangepay_Payment_Response::status_redirect:
                        $woocommerce->cart->empty_cart();
                        break;
                    default:
                        $order->add_order_note(__('Payment complete event failed.', 'woocommerce') . ' ' . $response->error);
                        $order->save();
                        break;
                }

                return array(
                    'result' => 'success',
                    'redirect' => $response->redirect_url,
                );
            }

            return new WP_Error('error', __('Orangepay - Could not initialize transaction.', 'woocommerce'));
        }

        public function can_refund_order($order)
        {
            return !$this->testmode;
        }

        /**
         * Process a refund if supported.
         *
         * @param int $order_id Order ID.
         * @param float $amount Refund amount.
         * @param string $reason Refund reason.
         * @return bool|WP_Error
         */
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);

            if (!$this->can_refund_order($order) || !preg_match('/^\d+(\.\d+)?$/', $amount)) {
                return new WP_Error('error', __('Refund failed.', 'woocommerce'));
            }

            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-orangepay-request.php';
            $orangepay_request = new WC_Gateway_Orangepay_Request($this);

            if ($refund_id = $orangepay_request->make_payment_refund($order, $amount)) {
                $order->add_order_note(
                    sprintf(__('Refunded %1$s - Refund ID: %2$s', 'woocommerce'), $amount, $refund_id)
                );
                return true;
            }

            return new WP_Error('error', __('Orangepay - Refund failed.', 'woocommerce'));
        }

        public function webhook()
        {
            $order = null;

            if ($response = json_decode(file_get_contents('php://input'), true)) {
                if (isset($response['data'])) {
                    $data = $response['data'];
                    if (isset($data['charge'])) {
                        $charge = $data['charge'];
                        if (isset($charge['attributes'])) {
                            $attributes = $charge['attributes'];
                            $transaction_id = $charge['id'];
                            $order_id = wc_get_order_id_by_order_key($attributes['reference_id']);
                            $order = wc_get_order($order_id);
                        }
                    }
                }
            }

            if ($order && $order->get_status() === 'pending') {
                include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-orangepay-request.php';
                $orangepay_request = new WC_Gateway_Orangepay_Request($this);

                if ($response = $orangepay_request->get_payment_details($order)) {
                    if (isset($response['attributes'])) {
                        $attributes = $response['attributes'];
                        if ($attributes['status'] === 'successful') {
                            $order->add_order_note(
                                sprintf(__('Transaction has been paid - ID: %1$s', 'woocommerce'), $transaction_id)
                            );
                            $order->payment_complete();
                            $order->reduce_order_stock();
                            return;
                        }
                    }
                }
            }

            echo -1;
        }
    }

    add_filter('woocommerce_payment_gateways', 'add_orangepay_gateway');
    /**
     * Add the gateway to WooCommerce
     **/
    function add_orangepay_gateway($gateways)
    {
        $gateways[] = 'WC_Gateway_Orangepay';
        return $gateways;
    }
}