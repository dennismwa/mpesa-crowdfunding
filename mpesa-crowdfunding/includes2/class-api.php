<?php
/**
 * M-Pesa API integration for Crowd Funding
 */

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
        
        // Add callback endpoints
        add_action('wp_ajax_mpesa_cf_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_nopriv_mpesa_cf_callback', array($this, 'handle_callback'));
        add_action('wp_ajax_mpesa_cf_timeout', array($this, 'handle_timeout'));
        add_action('wp_ajax_nopriv_mpesa_cf_timeout', array($this, 'handle_timeout'));
        add_action('wp_ajax_mpesa_cf_query_status', array($this, 'query_transaction_status'));
        add_action('wp_ajax_nopriv_mpesa_cf_query_status', array($this, 'query_transaction_status'));
    }
    
    /**
     * Generate access token
     */
    private function get_access_token() {
        $url = $this->base_url . '/oauth/v1/generate?grant_type=client_credentials';
        
        $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
        
        $headers = array(
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        );
        
        $response = $this->make_request($url, null, $headers);
        
        if ($response && isset($response['access_token'])) {
            // Cache token for 55 minutes (tokens expire in 1 hour)
            set_transient('mpesa_cf_access_token', $response['access_token'], 3300);
            return $response['access_token'];
        }
        
        return false;
    }
    
    /**
     * Get cached or fresh access token
     */
    private function get_token() {
        $token = get_transient('mpesa_cf_access_token');
        
        if (!$token) {
            $token = $this->get_access_token();
        }
        
        return $token;
    }
    
    /**
     * Initiate STK Push
     */
    public function initiate_stk_push($donation_id, $amount, $phone) {
        $token = $this->get_token();
        
        if (!$token) {
            return array(
                'success' => false,
                'message' => 'Failed to get access token'
            );
        }
        
        $url = $this->base_url . '/mpesa/stkpush/v1/processrequest';
        
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
        
        $callback_url = admin_url('admin-ajax.php?action=mpesa_cf_callback');
        $timeout_url = admin_url('admin-ajax.php?action=mpesa_cf_timeout');
        
        // Get campaign info for description
        $donation = MpesaCF_Database::get_donation($donation_id);
        $campaign = MpesaCF_Database::get_campaign($donation->campaign_id);
        
        $account_reference = 'Donation-' . $donation_id;
        $transaction_desc = 'Donation for ' . $campaign->title;
        
        $data = array(
            'BusinessShortCode' => $this->business_shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->business_shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callback_url,
            'AccountReference' => $account_reference,
            'TransactionDesc' => $transaction_desc
        );
        
        $headers = array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        );
        
        // Log the request
        MpesaCF_Database::log_transaction(array(
            'donation_id' => $donation_id,
            'transaction_type' => 'stk_push',
            'request_data' => json_encode($data),
            'status' => 'initiated'
        ));
        
        $response = $this->make_request($url, json_encode($data), $headers);
        
        if ($response) {
            // Log the response
            MpesaCF_Database::log_transaction(array(
                'donation_id' => $donation_id,
                'transaction_type' => 'stk_push',
                'response_data' => json_encode($response),
                'status' => isset($response['ResponseCode']) ? $response['ResponseCode'] : 'unknown'
            ));
            
            if (isset($response['ResponseCode']) && $response['ResponseCode'] === '0') {
                return array(
                    'success' => true,
                    'data' => $response,
                    'message' => 'STK push sent successfully'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => isset($response['errorMessage']) ? $response['errorMessage'] : 'STK push failed'
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => 'Failed to initiate payment'
        );
    }
    
    /**
     * Handle M-Pesa callback
     */
    public function handle_callback() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Log the callback
        error_log('M-Pesa Callback: ' . $input);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(array('ResultCode' => 1, 'ResultDesc' => 'Invalid data'));
            exit;
        }
        
        $result_code = isset($data['Body']['stkCallback']['ResultCode']) ? $data['Body']['stkCallback']['ResultCode'] : null;
        $checkout_request_id = isset($data['Body']['stkCallback']['CheckoutRequestID']) ? $data['Body']['stkCallback']['CheckoutRequestID'] : null;
        
        if (!$checkout_request_id) {
            http_response_code(400);
            echo json_encode(array('ResultCode' => 1, 'ResultDesc' => 'Missing CheckoutRequestID'));
            exit;
        }
        
        // Find donation by transaction ID
        global $wpdb;
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        $donation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $donations_table WHERE transaction_id = %s",
            $checkout_request_id
        ));
        
        if (!$donation) {
            error_log('Donation not found for CheckoutRequestID: ' . $checkout_request_id);
            http_response_code(404);
            echo json_encode(array('ResultCode' => 1, 'ResultDesc' => 'Transaction not found'));
            exit;
        }
        
        // Log the callback
        MpesaCF_Database::log_transaction(array('donation_id' => $donation->id,
           'transaction_type' => 'callback',
           'request_data' => $input,
           'status' => $result_code
       ));
       
       if ($result_code === 0) {
           // Payment successful
           $callback_metadata = isset($data['Body']['stkCallback']['CallbackMetadata']['Item']) ? 
               $data['Body']['stkCallback']['CallbackMetadata']['Item'] : array();
           
           $mpesa_receipt_number = '';
           $transaction_date = '';
           $phone_number = '';
           
           foreach ($callback_metadata as $item) {
               switch ($item['Name']) {
                   case 'MpesaReceiptNumber':
                       $mpesa_receipt_number = $item['Value'];
                       break;
                   case 'TransactionDate':
                       $transaction_date = $item['Value'];
                       break;
                   case 'PhoneNumber':
                       $phone_number = $item['Value'];
                       break;
               }
           }
           
           // Update donation status to completed
           MpesaCF_Database::update_donation($donation->id, array(
               'status' => 'completed',
               'mpesa_receipt_number' => $mpesa_receipt_number
           ));
           
           // Send notification emails
           $this->send_donation_notifications($donation->id);
           
       } else {
           // Payment failed
           $result_desc = isset($data['Body']['stkCallback']['ResultDesc']) ? $data['Body']['stkCallback']['ResultDesc'] : 'Payment failed';
           
           MpesaCF_Database::update_donation($donation->id, array(
               'status' => 'failed'
           ));
           
           MpesaCF_Database::log_transaction(array(
               'donation_id' => $donation->id,
               'transaction_type' => 'callback',
               'error_message' => $result_desc,
               'status' => 'failed'
           ));
       }
       
       // Respond to Safaricom
       http_response_code(200);
       echo json_encode(array('ResultCode' => 0, 'ResultDesc' => 'Success'));
       exit;
   }
   
   /**
    * Handle timeout callback
    */
   public function handle_timeout() {
       $input = file_get_contents('php://input');
       $data = json_decode($input, true);
       
       error_log('M-Pesa Timeout: ' . $input);
       
       if ($data && isset($data['CheckoutRequestID'])) {
           // Find and update donation
           global $wpdb;
           $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
           $donation = $wpdb->get_row($wpdb->prepare(
               "SELECT * FROM $donations_table WHERE transaction_id = %s",
               $data['CheckoutRequestID']
           ));
           
           if ($donation && $donation->status === 'pending') {
               MpesaCF_Database::update_donation($donation->id, array(
                   'status' => 'cancelled'
               ));
           }
       }
       
       http_response_code(200);
       echo json_encode(array('ResultCode' => 0, 'ResultDesc' => 'Success'));
       exit;
   }
   
   /**
    * Query transaction status
    */
   public function query_transaction_status() {
       check_ajax_referer('mpesa_cf_frontend_nonce', 'nonce');
       
       $checkout_request_id = sanitize_text_field($_POST['checkout_request_id']);
       
       if (empty($checkout_request_id)) {
           wp_send_json_error(array('message' => 'Missing checkout request ID'));
       }
       
       $token = $this->get_token();
       
       if (!$token) {
           wp_send_json_error(array('message' => 'Failed to get access token'));
       }
       
       $url = $this->base_url . '/mpesa/stkpushquery/v1/query';
       
       $timestamp = date('YmdHis');
       $password = base64_encode($this->business_shortcode . $this->passkey . $timestamp);
       
       $data = array(
           'BusinessShortCode' => $this->business_shortcode,
           'Password' => $password,
           'Timestamp' => $timestamp,
           'CheckoutRequestID' => $checkout_request_id
       );
       
       $headers = array(
           'Authorization: Bearer ' . $token,
           'Content-Type: application/json'
       );
       
       $response = $this->make_request($url, json_encode($data), $headers);
       
       if ($response) {
           // Check donation status in database
           global $wpdb;
           $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
           $donation = $wpdb->get_row($wpdb->prepare(
               "SELECT * FROM $donations_table WHERE transaction_id = %s",
               $checkout_request_id
           ));
           
           if ($donation) {
               wp_send_json_success(array(
                   'status' => $donation->status,
                   'mpesa_receipt' => $donation->mpesa_receipt_number,
                   'api_response' => $response
               ));
           } else {
               wp_send_json_error(array('message' => 'Donation not found'));
           }
       }
       
       wp_send_json_error(array('message' => 'Failed to query transaction status'));
   }
   
   /**
    * Test M-Pesa connection
    */
   public function test_connection() {
       $token = $this->get_access_token();
       
       if ($token) {
           return array(
               'success' => true,
               'message' => 'Connection successful',
               'token' => substr($token, 0, 20) . '...' // Show partial token for confirmation
           );
       } else {
           return array(
               'success' => false,
               'message' => 'Failed to connect to M-Pesa API'
           );
       }
   }
   
   /**
    * Make HTTP request
    */
   private function make_request($url, $data = null, $headers = array()) {
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
    * Send donation notifications
    */
   private function send_donation_notifications($donation_id) {
       $donation = MpesaCF_Database::get_donation($donation_id);
       $campaign = MpesaCF_Database::get_campaign($donation->campaign_id);
       
       if (!$donation || !$campaign) {
           return;
       }
       
       // Send admin notification
       $admin_email = get_option('mpesa_cf_admin_email', get_option('admin_email'));
       if ($admin_email) {
           $subject = 'New Donation Received - ' . $campaign->title;
           $message = "A new donation has been received:\n\n";
           $message .= "Campaign: " . $campaign->title . "\n";
           $message .= "Amount: KES " . number_format($donation->amount) . "\n";
           $message .= "Donor: " . ($donation->anonymous ? 'Anonymous' : $donation->donor_name) . "\n";
           $message .= "Phone: " . $donation->donor_phone . "\n";
           $message .= "Receipt: " . $donation->mpesa_receipt_number . "\n";
           if ($donation->message) {
               $message .= "Message: " . $donation->message . "\n";
           }
           $message .= "\nDonation Date: " . $donation->created_at;
           
           wp_mail($admin_email, $subject, $message);
       }
       
       // Send donor receipt
       if (get_option('mpesa_cf_send_receipts') && !empty($donation->donor_email)) {
           $subject = 'Thank you for your donation - Receipt';
           $message = "Dear " . $donation->donor_name . ",\n\n";
           $message .= "Thank you for your generous donation!\n\n";
           $message .= "Donation Details:\n";
           $message .= "Campaign: " . $campaign->title . "\n";
           $message .= "Amount: KES " . number_format($donation->amount) . "\n";
           $message .= "Receipt Number: " . $donation->mpesa_receipt_number . "\n";
           $message .= "Date: " . $donation->created_at . "\n\n";
           $message .= "Your support makes a difference. Thank you!\n\n";
           $message .= "Best regards,\n";
           $message .= get_bloginfo('name');
           
           wp_mail($donation->donor_email, $subject, $message);
       }
   }
}
?>