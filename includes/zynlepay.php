<?php
// includes/zynlepay.php
// ZynlePay Integration for Mobile Money Payments

class ZynlePay {
    private $api_url;
    private $merchant_id;
    private $api_id;
    private $api_key;
    private $environment;
    
    public function __construct() {
        $this->merchant_id = getenv('ZYNLEPAY_MERCHANT_ID') ?: 'YOUR_MERCHANT_ID';
        $this->api_id = getenv('ZYNLEPAY_API_ID') ?: 'YOUR_API_ID';
        $this->api_key = getenv('ZYNLEPAY_API_KEY') ?: 'YOUR_API_KEY';
        $this->environment = getenv('ZYNLEPAY_ENVIRONMENT') ?: 'sandbox';

        if (!in_array($this->environment, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('ZYNLEPAY_ENVIRONMENT must be sandbox or production.');
        }
        
        if ($this->environment === 'sandbox') {
            $this->api_url = 'https://sandbox.zynlepay.com/api/v1/';
        } else {
            $this->api_url = 'https://api.zynlepay.com/api/v1/';
        }
    }
    
    /**
     * Initiate a Mobile Money payment collection
     * @param string $phone - Customer phone number (e.g., +2609XXXXXXXX)
     * @param float $amount - Amount to collect
     * @param string $reference - Unique transaction reference
     * @param string $description - Transaction description
     * @return array - Response from API
     */
    public function initiatePayment($phone, $amount, $reference, $description = 'MentorMatch Session Payment') {
        $data = [
            'merchantId' => $this->merchant_id,
            'apiId' => $this->api_id,
            'apiKey' => $this->api_key,
            'phoneNumber' => $phone,
            'amount' => $amount,
            'reference' => $reference,
            'description' => $description,
            'channel' => 'momo'
        ];
        
        $ch = curl_init($this->api_url . 'momo/payment');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Payment initiated successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Payment initiation failed: ' . $response,
                'http_code' => $http_code
            ];
        }
    }
    
    /**
     * Verify payment status
     * @param string $reference - Transaction reference
     * @return array - Payment status
     */
    public function verifyPayment($reference) {
        $data = [
            'merchantId' => $this->merchant_id,
            'apiId' => $this->api_id,
            'apiKey' => $this->api_key,
            'reference' => $reference
        ];
        
        $ch = curl_init($this->api_url . 'momo/verify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'status' => $result['status'] ?? 'unknown',
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Verification failed'
            ];
        }
    }
    
    /**
     * Send payout to mentor's mobile money
     * @param string $phone - Mentor phone number
     * @param float $amount - Amount to send
     * @param string $reference - Unique reference
     * @return array - Response
     */
    public function sendPayout($phone, $amount, $reference) {
        $data = [
            'merchantId' => $this->merchant_id,
            'apiId' => $this->api_id,
            'apiKey' => $this->api_key,
            'phoneNumber' => $phone,
            'amount' => $amount,
            'reference' => $reference,
            'description' => 'MentorMatch Payout'
        ];
        
        $ch = curl_init($this->api_url . 'momo/payout');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'data' => $result,
                'message' => 'Payout sent successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Payout failed: ' . $response
            ];
        }
    }
    
    /**
     * Check wallet balance
     * @return array - Balance information
     */
    public function getBalance() {
        $data = [
            'merchantId' => $this->merchant_id,
            'apiId' => $this->api_id,
            'apiKey' => $this->api_key
        ];
        
        $ch = curl_init($this->api_url . 'momo/balance');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}

// Helper function to format phone numbers
function formatPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If phone starts with 0, replace with +260
    if (substr($phone, 0, 1) === '0') {
        $phone = '+260' . substr($phone, 1);
    }
    
    // If phone starts with 260, add +
    if (substr($phone, 0, 3) === '260') {
        $phone = '+' . $phone;
    }
    
    return $phone;
}
?>