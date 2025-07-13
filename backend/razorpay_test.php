<?php
namespace App;

use Razorpay\Api\Api;

class RazorpayService {
    private $api;
    private $keyId;
    private $keySecret;

    public function __construct() {
        $this->keyId = Config::get('RAZORPAY_KEY_ID');
        $this->keySecret = Config::get('RAZORPAY_KEY_SECRET');

        if ($this->keyId && $this->keySecret) {
            $this->api = new Api($this->keyId, $this->keySecret);
        } else {
            $this->api = null;
        }
    }

    public function createOrder($amountInPaise, $receiptId) {
       if (!$this->api) {
            return ['error' => 'Razorpay service is not configured.'];
        }

        $orderData = [
            'receipt'         => $receiptId,
            'amount'          => (int)$amountInPaise, // Correctly uses the passed parameter
            'currency'        => 'INR',
            'payment_capture' => 1
        ];

        try {
            $razorpayOrder = $this->api->order->create($orderData);
            return $razorpayOrder->toArray();
        } catch (\Exception $e) {
            error_log("Razorpay Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    } // <-- The extra '}' that caused a syntax error is removed.

    public function verifySignature(array $attributes) {
        if (!$this->api) {
            return false;
        }
        
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_signature'  => $attributes['razorpay_signature'],
                'razorpay_payment_id' => $attributes['razorpay_payment_id'],
                'razorpay_order_id'   => $attributes['razorpay_order_id']
            ]);
            return true;
        } catch(\Exception $e) {
            error_log("Razorpay Signature Verification Failed: " . $e->getMessage());
            return false;
        }
    }

    public function getKeyId() {
        return $this->keyId;
    }
}