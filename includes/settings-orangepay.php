<?php
/**
 * Settings for Orangepay Gateway.
 */

if ( ! defined('ABSPATH')) {
    exit;
}

return array(
    'enabled'     => array(
        'title'   => __('Enable/Disable', 'woocommerce'),
        'type'    => 'checkbox',
        'label'   => __('Enable Orangepay', 'woocommerce'),
        'default' => 'no',
    ),
    'title'       => array(
        'title'       => __('Title', 'woocommerce'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
        'default'     => __('Orangepay', 'woocommerce'),
        'desc_tip'    => true,
    ),
    'description' => array(
        'title'       => __('Description', 'woocommerce'),
        'type'        => 'text',
        'desc_tip'    => true,
        'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
        'default'     => __('Pay via Orangepay; you can pay with your credit card.', 'woocommerce'),
    ),
    'testmode'    => array(
        'title'       => __('Orangepay sandbox', 'woocommerce'),
        'type'        => 'checkbox',
        'label'       => __('Enable Orangepay sandbox', 'woocommerce'),
        'default'     => 'no',
        'description' => __('Orangepay sandbox can be used to test payments. API token must be set up for test payments.')
    ),
    'debug'       => array(
        'title'       => __('Debug log', 'woocommerce'),
        'type'        => 'checkbox',
        'label'       => __('Enable logging', 'woocommerce'),
        'default'     => 'no',
        'description' => sprintf(__('Log Orangepay events, such as requests, responses, inside %s Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'woocommerce'), '<code>' . WC_Log_Handler_File::get_log_file_path('orangepay') . '</code>'),
    ),
    'api_details' => array(
        'title'       => __('API credentials', 'woocommerce'),
        'type'        => 'title',
        'description' => sprintf(__('Enter your Orangepay API credentials. Learn how to access your <a href="%s">Orangepay API</a>.', 'woocommerce'), 'https://orange-pay.com/api'),
    ),
    'api_url'     => array(
        'title'       => __('Orangepay API url', 'woocommerce'),
        'type'        => 'text',
        'description' => '',
        'default'     => 'https://example.com/api',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
    'api_token'   => array(
        'title'       => __('Orangepay API token', 'woocommerce'),
        'type'        => 'text',
        'description' => '',
        'default'     => '',
        'desc_tip'    => true,
        'placeholder' => '',
    ),
);