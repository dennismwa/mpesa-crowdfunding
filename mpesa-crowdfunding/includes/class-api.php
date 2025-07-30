<?php
/**
 * M-Pesa API integration for Crowd Funding
 */

if (!defined('ABSPATH')) {
    exit;
}

class MpesaCF_API {
    
    private $environment;
    private $consumer_key;
    private $consumer_secret;
    private $business_shortcode;
    private $passkey;
    private $base_url;
    
    public function __construct() {
        $this->environment = get_option('mpesa_cf_environment', 'sandbox');
        $this->consumer_key = get_option('mpesa_cf_consumer_key');
        $this->consumer_secret = get_option('mpesa_cf_consumer_secret');
        $this->business_shortcode = get_option('mpesa_cf_business_shortcode');
        $this->passkey = get_option('mpesa_cf_passkey');
        
        $this->base_url = ($this->environment === 'live') 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
        
        // Add AJAX endpoints for future use
        add_action('wp_ajax_mpesa_cf_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_nopriv_mpesa_cf_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_mpesa_cf_test_connection', array($this, 'test_connection'));
    }
    
    /**
     * Test M-Pesa API connection
     */
    public function test_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (empty($this->consumer_key) || empty($this->consumer_secret)) {
            wp_send_json_error(array(
                'message' => 'Please configure your M-Pesa API credentials first.'
            ));
        }
        
        $token = $this->get_access_token();
        
        if ($token) {
            wp_send_json_success(array(
                'message' => 'Connection successful! M-Pesa API is properly configured.',
'environment' => $this->environment,
'token_preview' => substr($token, 0, 20) . '...'
));
} else {
wp_send_json_error(array(
'message' => 'Failed to connect to M-Pesa API. Please check your credentials.'
));
}
}
/**
 * Generate access token
 */
private function get_access_token() {
    if (empty($this->consumer_key) || empty($this->consumer_secret)) {
        return false;
    }
    
    $url = $this->base_url . '/oauth/v1/generate?grant_type=client_credentials';
    
    $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
    
    $headers = array(
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json'
    );
    
    $response = $this->make_request($url, null, $headers);
    
    if ($response && isset($response['access_token'])) {
        return $response['access_token'];
    }
    
    return false;
}

/**
 * Handle M-Pesa callback (placeholder for future implementation)
 */
public function handle_callback() {
    $input = file_get_contents('php://input');
    
    // Log the callback for debugging
    error_log('M-Pesa Callback received: ' . $input);
    
    // Basic response to Safaricom
    http_response_code(200);
    echo json_encode(array('ResultCode' => 0, 'ResultDesc' => 'Success'));
    exit;
}

/**
 * Make HTTP request
 */
private function make_request($url, $data = null, $headers = array()) {
    if (!function_exists('curl_init')) {
        return false;
    }
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        error_log('CURL Error: ' . $error);
        return false;
    }
    
    if ($http_code !== 200) {
        error_log('HTTP Error: ' . $http_code . ' - ' . $response);
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Get API status for admin display
 */
public function get_api_status() {
    if (empty($this->consumer_key) || empty($this->consumer_secret)) {
        return array(
            'status' => 'not_configured',
            'message' => 'M-Pesa API credentials not configured'
        );
    }
    
    $token = $this->get_access_token();
    
    if ($token) {
        return array(
            'status' => 'connected',
            'message' => 'M-Pesa API connected successfully',
            'environment' => $this->environment
        );
    } else {
        return array(
            'status' => 'error',
            'message' => 'Failed to connect to M-Pesa API'
        );
    }
}
}
?>