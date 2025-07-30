<?php
/**
 * Admin functionality for M-Pesa Crowd Funding
 */

if (!defined('ABSPATH')) {
    exit;
}

class MpesaCF_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'M-Pesa Crowdfunding',
            'Crowdfunding',
            'manage_options',
            'mpesa-crowdfunding',
            array($this, 'admin_page'),
            'dashicons-heart',
            30
        );
        
        add_submenu_page(
            'mpesa-crowdfunding',
            'Campaigns',
            'Campaigns',
            'manage_options',
            'mpesa-crowdfunding',
            array($this, 'campaigns_page')
        );
        
        add_submenu_page(
            'mpesa-crowdfunding',
            'Settings',
            'Settings',
            'manage_options',
            'mpesa-crowdfunding-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        $this->campaigns_page();
    }
    
    public function campaigns_page() {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        $campaigns = $wpdb->get_results("SELECT * FROM $campaigns_table ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Crowdfunding Campaigns</h1>
            
            <div class="notice notice-success">
                <p><strong>Great!</strong> Your M-Pesa Crowdfunding plugin is now working!</p>
            </div>
            
            <h2>Current Campaigns</h2>
            
            <?php if (empty($campaigns)): ?>
                <p>No campaigns found.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Target</th>
                            <th>Raised</th>
                            <th>Progress</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): 
                            $progress = ($campaign->target_amount > 0) ? 
                                min(100, ($campaign->current_amount / $campaign->target_amount) * 100) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($campaign->title); ?></strong></td>
                            <td><?php echo esc_html($campaign->category); ?></td>
                            <td>KES <?php echo number_format($campaign->target_amount); ?></td>
                            <td>KES <?php echo number_format($campaign->current_amount); ?></td>
                            <td><?php echo number_format($progress, 1); ?>%</td>
                            <td>
                                <span class="status-<?php echo esc_attr($campaign->status); ?>">
                                    <?php echo ucfirst($campaign->status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h2>Next Steps</h2>
            <ul>
                <li>✅ <strong>Plugin is working!</strong> You can see campaigns above.</li>
                <li>📝 <strong>Configure M-Pesa:</strong> Go to <a href="<?php echo admin_url('admin.php?page=mpesa-crowdfunding-settings'); ?>">Settings</a> to add your Daraja API credentials.</li>
                <li>🎯 <strong>Display campaigns:</strong> Add <code>[mpesa_crowdfunding]</code> to any page or post.</li>
                <li>💝 <strong>Test donations:</strong> Visit a page with the shortcode and test the donation flow.</li>
            </ul>
            
            <h2>Sample Shortcodes</h2>
            <p>Use these shortcodes on your pages:</p>
            <ul>
                <li><code>[mpesa_crowdfunding]</code> - Display all campaigns</li>
                <li><code>[mpesa_crowdfunding category="Church Project"]</code> - Display specific category</li>
                <li><code>[mpesa_crowdfunding campaign_id="1"]</code> - Display single campaign</li>
            </ul>
        </div>
        
        <style>
            .status-active { color: #00a32a; font-weight: bold; }
            .status-paused { color: #dba617; font-weight: bold; }
            .status-completed { color: #0073aa; font-weight: bold; }
            .status-draft { color: #646970; font-weight: bold; }
        </style>
        <?php
    }
    
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            update_option('mpesa_cf_business_shortcode', sanitize_text_field($_POST['mpesa_cf_business_shortcode']));
            update_option('mpesa_cf_consumer_key', sanitize_text_field($_POST['mpesa_cf_consumer_key']));
            update_option('mpesa_cf_consumer_secret', sanitize_text_field($_POST['mpesa_cf_consumer_secret']));
            update_option('mpesa_cf_passkey', sanitize_text_field($_POST['mpesa_cf_passkey']));
            update_option('mpesa_cf_environment', sanitize_text_field($_POST['mpesa_cf_environment']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>M-Pesa Crowdfunding Settings</h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Environment</th>
                        <td>
                            <select name="mpesa_cf_environment">
                                <option value="sandbox" <?php selected(get_option('mpesa_cf_environment', 'sandbox'), 'sandbox'); ?>>Sandbox (Testing)</option>
                                <option value="live" <?php selected(get_option('mpesa_cf_environment'), 'live'); ?>>Live (Production)</option>
                            </select>
                            <p class="description">Select sandbox for testing, live for production.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Business Short Code</th>
                        <td>
                            <input type="text" name="mpesa_cf_business_shortcode" value="<?php echo esc_attr(get_option('mpesa_cf_business_shortcode')); ?>" class="regular-text" />
                            <p class="description">Your business short code (Paybill or Till Number).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Consumer Key</th>
                        <td>
                            <input type="text" name="mpesa_cf_consumer_key" value="<?php echo esc_attr(get_option('mpesa_cf_consumer_key')); ?>" class="regular-text" />
                            <p class="description">Consumer Key from Daraja API.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Consumer Secret</th>
                        <td>
                            <input type="password" name="mpesa_cf_consumer_secret" value="<?php echo esc_attr(get_option('mpesa_cf_consumer_secret')); ?>" class="regular-text" />
                            <p class="description">Consumer Secret from Daraja API.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Passkey</th>
                        <td>
                            <input type="text" name="mpesa_cf_passkey" value="<?php echo esc_attr(get_option('mpesa_cf_passkey')); ?>" class="regular-text" />
                            <p class="description">Lipa Na M-Pesa Online Passkey.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>How to Get M-Pesa API Credentials</h2>
            <ol>
                <li>Visit <a href="https://developer.safaricom.co.ke/" target="_blank">Safaricom Developer Portal</a></li>
                <li>Create an account and login</li>
                <li>Create a new app for "Lipa Na M-Pesa Online"</li>
                <li>Get your Consumer Key and Consumer Secret</li>
                <li>Get your Passkey from the API documentation</li>
                <li>For testing, use Sandbox environment</li>
                <li>For live transactions, switch to Live environment</li>
            </ol>
            
            <h2>Callback URLs</h2>
            <p>Add these URLs to your Daraja API app configuration:</p>
            <ul>
                <li><strong>Callback URL:</strong> <code><?php echo admin_url('admin-ajax.php?action=mpesa_cf_callback'); ?></code></li>
                <li><strong>Timeout URL:</strong> <code><?php echo admin_url('admin-ajax.php?action=mpesa_cf_timeout'); ?></code></li>
            </ul>
        </div>
        <?php
    }
}
?>