<?php


class WC_Gateway_Orangepay_Card
{
    public $card_holder;
    public $card_number;
    public $card_expiry_month;
    public $card_expiry_year;
    public $cvv;
}

class WC_Gateway_Orangepay_Payment_Response
{
    public $status;
    public $redirect_url;
    public $error;

    const status_failed = 0;
    const status_success = 1;
    const status_redirect = 2;
    const status_process = 3;

    public function __construct()
    {
        $this->status = self::status_process;
    }
}