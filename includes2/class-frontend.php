<?php
/**
 * Frontend functionality for M-Pesa Crowd Funding
 */

class MpesaCF_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_mpesa_cf_process_donation', array($this, 'ajax_process_donation'));
        add_action('wp_ajax_nopriv_mpesa_cf_process_donation', array($this, 'ajax_process_donation'));
        add_action('wp_ajax_mpesa_cf_get_campaign', array($this, 'ajax_get_campaign'));
        add_action('wp_ajax_nopriv_mpesa_cf_get_campaign', array($this, 'ajax_get_campaign'));
        add_action('wp_footer', array($this, 'add_donation_modal'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('mpesa-cf-frontend', MPESA_CF_PLUGIN_URL . 'assets/css/frontend.css', array(), MPESA_CF_VERSION);
        wp_enqueue_script('mpesa-cf-frontend', MPESA_CF_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), MPESA_CF_VERSION, true);
        
        wp_localize_script('mpesa-cf-frontend', 'mpesa_cf_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpesa_cf_frontend_nonce'),
            'currency' => get_option('mpesa_cf_currency', 'KES'),
            'default_amounts' => explode(',', get_option('mpesa_cf_default_amounts', '100,500,1000,5000')),
            'enable_cards' => get_option('mpesa_cf_enable_cards', 0),
            'enable_bank' => get_option('mpesa_cf_enable_bank', 0)
        ));
    }
    
    public function add_donation_modal() {
        if (is_admin()) return;
        
        ?>
        <div id="mpesa-cf-donation-modal" class="mpesa-cf-modal" style="display: none;">
            <div class="modal-overlay" onclick="closeDonationModal()"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="donation-modal-title">Make a Donation</h3>
                    <button class="modal-close" onclick="closeDonationModal()">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div id="donation-step-1" class="donation-step active">
                        <div class="campaign-preview">
                            <div id="modal-campaign-image"></div>
                            <div class="campaign-info">
                                <h4 id="modal-campaign-title"></h4>
                                <p id="modal-campaign-category"></p>
                                <div class="progress-info">
                                    <div class="progress-bar">
                                        <div id="modal-progress-fill" class="progress-fill"></div>
                                    </div>
                                    <div class="progress-text">
                                        <span id="modal-current-amount"></span>
                                        <span> of </span>
                                        <span id="modal-target-amount"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form id="donation-form">
                            <div class="form-section">
                                <label>Donation Amount (KES)</label>
                                <div class="amount-options">
                                    <div class="preset-amounts" id="preset-amounts">
                                        <!-- Will be populated by JavaScript -->
                                    </div>
                                    <div class="custom-amount">
                                        <input type="number" id="donation-amount" name="amount" min="1" step="0.01" placeholder="Enter custom amount" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <label>Payment Method</label>
                                <div class="payment-methods">
                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="mpesa" checked>
                                        <div class="method-option">
                                            <strong>M-Pesa</strong>
                                            <span>Pay with your M-Pesa mobile money</span>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-method" id="card-method" style="display: none;">
                                        <input type="radio" name="payment_method" value="card">
                                        <div class="method-option">
                                            <strong>Credit/Debit Card</strong>
                                            <span>Pay with Visa, Mastercard, or other cards</span>
                                        </div>
                                    </label>
                                    
                                    <label class="payment-method" id="bank-method" style="display: none;">
                                        <input type="radio" name="payment_method" value="bank">
                                        <div class="method-option">
                                            <strong>Bank Transfer</strong>
                                            <span>Direct bank transfer</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-section donor-info">
                                <h4>Donor Information</h4>
                                <div class="form-row">
                                    <div class="form-group">
                                        <input type="text" id="donor-name" name="donor_name" placeholder="Full Name" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="tel" id="donor-phone" name="donor_phone" placeholder="Phone Number (07xxxxxxxx)" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="email" id="donor-email" name="donor_email" placeholder="Email Address (optional)">
                                </div>
                                <div class="form-group">
                                    <textarea id="donor-message" name="message" placeholder="Leave a message (optional)" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="anonymous-donation" name="anonymous">
                                        <span>Make this donation anonymous</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closeDonationModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <span id="donate-btn-text">Donate Now</span>
                                    <span id="donate-btn-loading" style="display: none;">Processing...</span>
                                </button>
                            </div>
                            
                            <input type="hidden" id="campaign-id" name="campaign_id">
                        </form>
                    </div>
                    
                    <div id="donation-step-2" class="donation-step">
                        <div class="step-content">
                            <div class="payment-instructions" id="mpesa-instructions">
                                <div class="instruction-icon">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                </div>
                                <h4>Complete Your M-Pesa Payment</h4>
                                <div class="instruction-steps">
                                    <p><strong>Step 1:</strong> You will receive an M-Pesa STK push notification on your phone</p>
                                    <p><strong>Step 2:</strong> Enter your M-Pesa PIN to complete the payment</p>
                                    <p><strong>Step 3:</strong> Wait for confirmation SMS</p>
                                </div>
                                <div class="payment-details">
                                    <div class="detail-item">
                                        <span class="label">Amount:</span>
                                        <span class="value" id="confirm-amount">KES 0</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Phone:</span>
                                        <span class="value" id="confirm-phone"></span>
                                    </div>
                                </div>
                                <div class="payment-status" id="payment-status">
                                    <div class="status-pending">
                                        <div class="spinner"></div>
                                        <span>Waiting for payment confirmation...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-instructions" id="card-instructions" style="display: none;">
                                <div class="instruction-icon">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                        <line x1="1" y1="10" x2="23" y2="10"></line>
                                    </svg>
                                </div>
                                <h4>Card Payment</h4>
                                <p>You will be redirected to our secure payment gateway to complete your card payment.</p>
                                <div id="card-payment-form">
                                    <!-- Card payment form will be loaded here -->
                                </div>
                            </div>
                            
                            <div class="payment-instructions" id="bank-instructions" style="display: none;">
                                <div class="instruction-icon">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21l18 0"></path>
                                        <path d="M5 21V7l8-4v18"></path>
                                        <path d="M19 21V11l-6-4"></path>
                                        <path d="M9 9v.01"></path>
                                        <path d="M9 12v.01"></path>
                                        <path d="M9 15v.01"></path>
                                        <path d="M9 18v.01"></path>
                                    </svg>
                                </div>
                                <h4>Bank Transfer Details</h4>
                                <div class="bank-details">
                                    <div class="detail-item">
                                        <span class="label">Bank Name:</span>
                                        <span class="value">Cooperative Bank of Kenya</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Account Name:</span>
                                        <span class="value">Your Organization Name</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Account Number:</span>
                                        <span class="value">01129465123456</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Reference:</span>
                                        <span class="value" id="bank-reference"></span>
                                    </div>
                                </div>
                                <p><strong>Important:</strong> Please use the reference number above when making your bank transfer.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="donation-step-3" class="donation-step">
                        <div class="step-content success-content">
                            <div class="success-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22,4 12,14.01 9,11.01"></polyline>
                                </svg>
                            </div>
                            <h4>Thank You for Your Donation!</h4>
                            <p>Your donation has been successfully processed.</p>
                            <div class="donation-summary">
                                <div class="summary-item">
                                    <span class="label">Amount:</span>
                                    <span class="value" id="success-amount"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Campaign:</span>
                                    <span class="value" id="success-campaign"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Receipt:</span>
                                    <span class="value" id="success-receipt"></span>
                                </div>
                            </div>
                            <p><small>A confirmation email will be sent to you shortly.</small></p>
                            <button class="btn btn-primary" onclick="closeDonationModal()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_get_campaign() {
        check_ajax_referer('mpesa_cf_frontend_nonce', 'nonce');
        
        $campaign_id = intval($_POST['campaign_id']);
        $campaign = MpesaCF_Database::get_campaign($campaign_id);
        
        if (!$campaign) {
            wp_send_json_error(array('message' => 'Campaign not found'));
        }
        
        $progress_percentage = ($campaign->target_amount > 0) ? 
            min(100, ($campaign->current_amount / $campaign->target_amount) * 100) : 0;
        
        wp_send_json_success(array(
            'campaign' => array(
                'id' => $campaign->id,
                'title' => $campaign->title,
                'category' => $campaign->category,
                'description' => $campaign->description,
                'target_amount' => $campaign->target_amount,
                'current_amount' => $campaign->current_amount,
                'progress_percentage' => $progress_percentage,
                'image_url' => $campaign->image_url
            )
        ));
    }
    
    public function ajax_process_donation() {
        check_ajax_referer('mpesa_cf_frontend_nonce', 'nonce');
        
        // Sanitize and validate input
        $campaign_id = intval($_POST['campaign_id']);
        $amount = floatval($_POST['amount']);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $donor_name = sanitize_text_field($_POST['donor_name']);
        $donor_phone = sanitize_text_field($_POST['donor_phone']);
        $donor_email = sanitize_email($_POST['donor_email']);
        $message = sanitize_textarea_field($_POST['message']);
        $anonymous = isset($_POST['anonymous']) ? 1 : 0;
        
        // Validate required fields
        if (empty($campaign_id) || empty($amount) || empty($donor_name) || empty($donor_phone)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
        }
        
        // Validate campaign exists and is active
        $campaign = MpesaCF_Database::get_campaign($campaign_id);
        if (!$campaign || $campaign->status !== 'active') {
            wp_send_json_error(array('message' => 'Campaign is not available for donations'));
        }
        
        // Validate amount
        if ($amount < 1) {
            wp_send_json_error(array('message' => 'Minimum donation amount is KES 1'));
        }
        
        // Validate phone number (Kenyan format)
        if (!preg_match('/^(254|0)[7][0-9]{8}$/', str_replace(array('+', ' ', '-'), '', $donor_phone))) {
            wp_send_json_error(array('message' => 'Please enter a valid Kenyan phone number'));
        }
        
        // Normalize phone number
        $donor_phone = $this->normalize_phone_number($donor_phone);
        
        // Create donation record
        $donation_data = array(
            'campaign_id' => $campaign_id,
            'donor_name' => $donor_name,
            'donor_email' => $donor_email,
            'donor_phone' => $donor_phone,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'anonymous' => $anonymous,
            'message' => $message,
            'status' => 'pending'
        );
        
        $donation_id = MpesaCF_Database::create_donation($donation_data);
        
        if (!$donation_id) {
            wp_send_json_error(array('message' => 'Failed to create donation record'));
        }
        
        // Process payment based on method
        switch ($payment_method) {
            case 'mpesa':
                $result = $this->process_mpesa_payment($donation_id, $amount, $donor_phone);
                break;
            case 'card':
                $result = $this->process_card_payment($donation_id, $amount);
                break;
            case 'bank':
                $result = $this->process_bank_transfer($donation_id, $amount);
                break;
            default:
                wp_send_json_error(array('message' => 'Invalid payment method'));
        }
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data']);
        }
    }
    
    private function normalize_phone_number($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert to 254 format
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '254') {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
    
    private function process_mpesa_payment($donation_id, $amount, $phone) {
        // Get M-Pesa settings
        $business_shortcode = get_option('mpesa_cf_business_shortcode');
        $consumer_key = get_option('mpesa_cf_consumer_key');
        $consumer_secret = get_option('mpesa_cf_consumer_secret');
        $passkey = get_option('mpesa_cf_passkey');
        $environment = get_option('mpesa_cf_environment', 'sandbox');
        
        if (empty($business_shortcode) || empty($consumer_key) || empty($consumer_secret) || empty($passkey)) {
            return array(
                'success' => false,
                'data' => array('message' => 'M-Pesa is not properly configured')
            );
        }
        
        // Initialize M-Pesa API
        $mpesa_api = new MpesaCF_API();
        $result = $mpesa_api->initiate_stk_push($donation_id, $amount, $phone);
        
        if ($result['success']) {
            // Update donation with transaction ID
            MpesaCF_Database::update_donation($donation_id, array(
                'transaction_id' => $result['data']['CheckoutRequestID']
            ));
            
            return array(
                'success' => true,
                'data' => array(
                    'message' => 'STK push sent successfully',
                    'donation_id' => $donation_id,
                    'checkout_request_id' => $result['data']['CheckoutRequestID']
                )
            );
        } else {
            return array(
                'success' => false,
                'data' => array('message' => $result['message'])
            );
        }
    }
    
    private function process_card_payment($donation_id, $amount) {
        // This would integrate with a card payment processor
        // For now, return success for demonstration
        return array(
            'success' => true,
            'data' => array(
                'message' => 'Card payment initiated',
                'donation_id' => $donation_id,
                'redirect_url' => '#' // Would be actual payment gateway URL
            )
        );
    }
    
    private function process_bank_transfer($donation_id, $amount) {
        // Generate reference number
        $reference = 'DON' . str_pad($donation_id, 6, '0', STR_PAD_LEFT);
        
        // Update donation with reference
        MpesaCF_Database::update_donation($donation_id, array(
            'transaction_id' => $reference
        ));
        
        return array(
            'success' => true,
            'data' => array(
                'message' => 'Bank transfer details provided',
                'donation_id' => $donation_id,
                'reference' => $reference
            )
        );
    }
}
?>