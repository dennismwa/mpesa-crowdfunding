<?php
/**
 * Admin functionality for M-Pesa Crowd Funding
 */

class MpesaCF_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_mpesa_cf_save_campaign', array($this, 'ajax_save_campaign'));
        add_action('wp_ajax_mpesa_cf_delete_campaign', array($this, 'ajax_delete_campaign'));
        add_action('wp_ajax_mpesa_cf_upload_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_mpesa_cf_save_settings', array($this, 'ajax_save_settings'));
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
            'Donations',
            'Donations',
            'manage_options',
            'mpesa-crowdfunding-donations',
            array($this, 'donations_page')
        );
        
        add_submenu_page(
            'mpesa-crowdfunding',
            'Categories',
            'Categories',
            'manage_options',
            'mpesa-crowdfunding-categories',
            array($this, 'categories_page')
        );
        
        add_submenu_page(
            'mpesa-crowdfunding',
            'Settings',
            'Settings',
            'manage_options',
            'mpesa-crowdfunding-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'mpesa-crowdfunding',
            'Reports',
            'Reports',
            'manage_options',
            'mpesa-crowdfunding-reports',
            array($this, 'reports_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'mpesa-crowdfunding') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true);
        wp_enqueue_style('mpesa-cf-admin', MPESA_CF_PLUGIN_URL . 'assets/css/admin.css', array(), MPESA_CF_VERSION);
        wp_enqueue_script('mpesa-cf-admin', MPESA_CF_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'chart-js'), MPESA_CF_VERSION, true);
        
        wp_localize_script('mpesa-cf-admin', 'mpesa_cf_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpesa_cf_admin_nonce')
        ));
    }
    
    public function admin_page() {
        $this->campaigns_page();
    }
    
    public function campaigns_page() {
        $campaigns = MpesaCF_Database::get_campaigns(array('include_all_statuses' => true));
        $categories = MpesaCF_Database::get_categories();
        
        ?>
        <div class="wrap mpesa-cf-admin">
            <h1>Crowdfunding Campaigns 
                <button class="button button-primary" onclick="openCampaignModal()">Add New Campaign</button>
            </h1>
            
            <div class="mpesa-cf-stats-overview">
                <?php $this->display_dashboard_stats(); ?>
            </div>
            
            <div class="campaign-filters">
                <select id="filter-category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category->name); ?>"><?php echo esc_html($category->name); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select id="filter-status">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="paused">Paused</option>
                    <option value="completed">Completed</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Target</th>
                        <th>Raised</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign): 
                        $progress = ($campaign->target_amount > 0) ? 
                            min(100, ($campaign->current_amount / $campaign->target_amount) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <?php if ($campaign->image_url): ?>
                                <img src="<?php echo esc_url($campaign->image_url); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                    <span style="color: #666;">No Image</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html($campaign->title); ?></strong></td>
                        <td><?php echo esc_html($campaign->category); ?></td>
                        <td>KES <?php echo number_format($campaign->target_amount); ?></td>
                        <td>KES <?php echo number_format($campaign->current_amount); ?></td>
                        <td>
                            <div class="progress-bar-admin">
                                <div class="progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                            </div>
                            <small><?php echo number_format($progress, 1); ?>%</small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($campaign->status); ?>">
                                <?php echo ucfirst($campaign->status); ?>
                            </span>
                        </td>
                        <td>
                            <button class="button button-small" onclick="editCampaign(<?php echo $campaign->id; ?>)">Edit</button>
                            <button class="button button-small button-link-delete" onclick="deleteCampaign(<?php echo $campaign->id; ?>)">Delete</button>
                            <button class="button button-small" onclick="viewDonations(<?php echo $campaign->id; ?>)">Donations</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Campaign Modal -->
            <div id="campaign-modal" class="mpesa-cf-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="modal-title">Add New Campaign</h2>
                        <button class="modal-close" onclick="closeCampaignModal()">&times;</button>
                    </div>
                    <form id="campaign-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="campaign-title">Title *</label>
                                <input type="text" id="campaign-title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="campaign-category">Category *</label>
                                <select id="campaign-category" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo esc_attr($category->name); ?>"><?php echo esc_html($category->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="campaign-description">Description</label>
                            <textarea id="campaign-description" name="description" rows="5"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="campaign-target">Target Amount (KES) *</label>
                                <input type="number" id="campaign-target" name="target_amount" min="1" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="campaign-status">Status</label>
                                <select id="campaign-status" name="status">
                                    <option value="active">Active</option>
                                    <option value="paused">Paused</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="campaign-image">Campaign Image</label>
                            <div class="image-upload">
                                <button type="button" class="button" onclick="selectImage()">Choose Image</button>
                                <div id="image-preview"></div>
                                <input type="hidden" id="campaign-image-url" name="image_url">
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="button" onclick="closeCampaignModal()">Cancel</button>
                            <button type="submit" class="button button-primary">Save Campaign</button>
                        </div>
                        
                        <input type="hidden" id="campaign-id" name="id">
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function donations_page() {
        global $wpdb;
        
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        $donations = $wpdb->get_results("
            SELECT d.*, c.title as campaign_title 
            FROM $donations_table d 
            LEFT JOIN $campaigns_table c ON d.campaign_id = c.id 
            ORDER BY d.created_at DESC 
            LIMIT 100
        ");
        
        ?>
        <div class="wrap mpesa-cf-admin">
            <h1>Donations</h1>
            
            <div class="donation-stats">
                <?php $this->display_donation_stats(); ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Campaign</th>
                        <th>Donor</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Receipt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation): ?>
                    <tr>
                        <td><?php echo esc_html(date('M j, Y H:i', strtotime($donation->created_at))); ?></td>
                        <td><?php echo esc_html($donation->campaign_title); ?></td>
                        <td>
                            <?php if ($donation->anonymous): ?>
                                <em>Anonymous</em>
                            <?php else: ?>
                                <?php echo esc_html($donation->donor_name); ?><br>
                                <small><?php echo esc_html($donation->donor_phone); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><strong>KES <?php echo number_format($donation->amount); ?></strong></td>
                        <td><?php echo ucfirst($donation->payment_method); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($donation->status); ?>">
                                <?php echo ucfirst($donation->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($donation->mpesa_receipt_number); ?></td>
                        <td>
                            <button class="button button-small" onclick="viewDonationDetails(<?php echo $donation->id; ?>)">View</button>
                            <?php if ($donation->status === 'pending'): ?>
                                <button class="button button-small" onclick="updateDonationStatus(<?php echo $donation->id; ?>, 'completed')">Complete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function categories_page() {
        $categories = MpesaCF_Database::get_categories();
        
        ?>
        <div class="wrap mpesa-cf-admin">
            <h1>Categories 
                <button class="button button-primary" onclick="openCategoryModal()">Add New Category</button>
            </h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th>Icon</th>
                        <th>Sort Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><strong><?php echo esc_html($category->name); ?></strong></td>
                        <td><?php echo esc_html($category->description); ?></td>
                        <td>
                            <div style="width: 20px; height: 20px; background-color: <?php echo esc_attr($category->color); ?>; border-radius: 3px; display: inline-block;"></div>
                            <?php echo esc_html($category->color); ?>
                        </td>
                        <td><?php echo esc_html($category->icon); ?></td>
                        <td><?php echo esc_html($category->sort_order); ?></td>
                        <td>
                            <button class="button button-small" onclick="editCategory(<?php echo $category->id; ?>)">Edit</button>
                            <button class="button button-small button-link-delete" onclick="deleteCategory(<?php echo $category->id; ?>)">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function settings_page() {
        ?>
        <div class="wrap mpesa-cf-admin">
            <h1>M-Pesa Crowdfunding Settings</h1>
            
            <form id="settings-form">
                <div class="settings-section">
                    <h2>M-Pesa API Configuration</h2>
                    <p>Enter your Safaricom Daraja API credentials. You can get these from the <a href="https://developer.safaricom.co.ke/" target="_blank">Safaricom Developer Portal</a>.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Environment</th>
                            <td>
                                <select name="mpesa_cf_environment">
                                    <option value="sandbox" <?php selected(get_option('mpesa_cf_environment'), 'sandbox'); ?>>Sandbox (Testing)</option>
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
                </div>
                
                <div class="settings-section">
                    <h2>General Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Currency</th>
                            <td>
                                <select name="mpesa_cf_currency">
                                    <option value="KES" <?php selected(get_option('mpesa_cf_currency'), 'KES'); ?>>KES (Kenyan Shilling)</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Default Donation Amounts</th>
                            <td>
                                <input type="text" name="mpesa_cf_default_amounts" value="<?php echo esc_attr(get_option('mpesa_cf_default_amounts', '100,500,1000,5000')); ?>" class="regular-text" />
                                <p class="description">Comma-separated list of suggested donation amounts.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Enable Card Payments</th>
                            <td>
                                <input type="checkbox" name="mpesa_cf_enable_cards" value="1" <?php checked(get_option('mpesa_cf_enable_cards'), 1); ?> />
                                <label>Allow credit/debit card payments (requires payment gateway setup)</label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Enable Bank Transfers</th>
                            <td>
                                <input type="checkbox" name="mpesa_cf_enable_bank" value="1" <?php checked(get_option('mpesa_cf_enable_bank'), 1); ?> />
                                <label>Allow bank transfer payments</label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="settings-section">
                    <h2>Notification Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Admin Notification Email</th>
                            <td>
                                <input type="email" name="mpesa_cf_admin_email" value="<?php echo esc_attr(get_option('mpesa_cf_admin_email', get_option('admin_email'))); ?>" class="regular-text" />
                                <p class="description">Email address to receive donation notifications.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Send Donor Receipts</th>
                            <td>
                                <input type="checkbox" name="mpesa_cf_send_receipts" value="1" <?php checked(get_option('mpesa_cf_send_receipts'), 1); ?> />
                                <label>Send email receipts to donors</label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button-primary">Save Settings</button>
                    <button type="button" class="button" onclick="testMpesaConnection()">Test M-Pesa Connection</button>
                </p>
            </form>
            
            <div id="test-results" style="margin-top: 20px;"></div>
        </div>
        <?php
    }
    
    public function reports_page() {
        ?>
        <div class="wrap mpesa-cf-admin">
            <h1>Reports & Analytics</h1>
            
            <div class="reports-dashboard">
                <div class="report-section">
                    <h2>Overview Statistics</h2>
                    <div class="stats-grid">
                        <?php $this->display_comprehensive_stats(); ?>
                    </div>
                </div>
                
                <div class="report-section">
                    <h2>Donation Trends</h2>
                    <canvas id="donations-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="report-section">
                    <h2>Category Performance</h2>
                    <canvas id="categories-chart" width="400" height="200"></canvas>
                </div>
                
                <div class="report-section">
                    <h2>Top Campaigns</h2>
                    <?php $this->display_top_campaigns(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function display_dashboard_stats() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        
        $total_campaigns = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table WHERE status = 'active'");
        $total_raised = $wpdb->get_var("SELECT SUM(amount) FROM $donations_table WHERE status = 'completed'") ?: 0;
        $total_donations = $wpdb->get_var("SELECT COUNT(*) FROM $donations_table WHERE status = 'completed'");
        $this_month_raised = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $donations_table WHERE status = 'completed' AND created_at >= %s",
            date('Y-m-01')
        )) ?: 0;
        
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_campaigns); ?></div>
                <div class="stat-label">Active Campaigns</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">KES <?php echo number_format($total_raised); ?></div>
                <div class="stat-label">Total Raised</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_donations); ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">KES <?php echo number_format($this_month_raised); ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>
        <?php
    }
    
    private function display_donation_stats() {
        $stats = MpesaCF_Database::get_donation_stats();
        
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats->total_donations ?: 0); ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">KES <?php echo number_format($stats->total_amount ?: 0); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">KES <?php echo number_format($stats->average_amount ?: 0); ?></div>
                <div class="stat-label">Average Donation</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">KES <?php echo number_format($stats->max_amount ?: 0); ?></div>
                <div class="stat-label">Largest Donation</div>
            </div>
        </div>
        <?php
    }
    
    private function display_comprehensive_stats() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        
        // Get various statistics
        $stats = array(
            'total_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table"),
            'active_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table WHERE status = 'active'"),
            'completed_campaigns' => $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table WHERE status = 'completed'"),
            'total_raised' => $wpdb->get_var("SELECT SUM(amount) FROM $donations_table WHERE status = 'completed'") ?: 0,
            'total_donations' => $wpdb->get_var("SELECT COUNT(*) FROM $donations_table WHERE status = 'completed'"),
            'pending_donations' => $wpdb->get_var("SELECT COUNT(*) FROM $donations_table WHERE status = 'pending'"),
            'this_week' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM $donations_table WHERE status = 'completed' AND created_at >= %s",
                date('Y-m-d', strtotime('-7 days'))
            )) ?: 0,
            'this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(amount) FROM $donations_table WHERE status = 'completed' AND created_at >= %s",
                date('Y-m-01')
            )) ?: 0
        );
        
        ?>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total_campaigns']); ?></div>
            <div class="stat-label">Total Campaigns</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['active_campaigns']); ?></div>
            <div class="stat-label">Active Campaigns</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">KES <?php echo number_format($stats['total_raised']); ?></div>
            <div class="stat-label">Total Raised</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total_donations']); ?></div>
            <div class="stat-label">Completed Donations</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['pending_donations']); ?></div>
            <div class="stat-label">Pending Donations</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">KES <?php echo number_format($stats['this_week']); ?></div>
            <div class="stat-label">This Week</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">KES <?php echo number_format($stats['this_month']); ?></div>
            <div class="stat-label">This Month</div>
        </div>
        <?php
    }
    
    private function display_top_campaigns() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        $top_campaigns = $wpdb->get_results("
            SELECT title, current_amount, target_amount, 
                   (current_amount / target_amount * 100) as progress_percent
            FROM $campaigns_table 
            WHERE target_amount > 0 
            ORDER BY current_amount DESC 
            LIMIT 10
        ");
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Raised</th>
                    <th>Target</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_campaigns as $campaign): ?>
                <tr>
                    <td><?php echo esc_html($campaign->title); ?></td>
                    <td>KES <?php echo number_format($campaign->current_amount); ?></td>
                    <td>KES <?php echo number_format($campaign->target_amount); ?></td>
                    <td><?php echo number_format($campaign->progress_percent, 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    // AJAX handlers
    public function ajax_save_campaign() {
        check_ajax_referer('mpesa_cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $campaign_data = array(
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'category' => sanitize_text_field($_POST['category']),
            'target_amount' => floatval($_POST['target_amount']),
            'status' => sanitize_text_field($_POST['status']),
            'image_url' => esc_url_raw($_POST['image_url'])
        );
        
        if (!empty($_POST['id'])) {
            // Update existing campaign
            $result = MpesaCF_Database::update_campaign(intval($_POST['id']), $campaign_data);
            $campaign_id = intval($_POST['id']);
        } else {
            // Create new campaign
            $campaign_id = MpesaCF_Database::create_campaign($campaign_data);
            $result = $campaign_id !== false;
        }
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Campaign saved successfully',
                'campaign_id' => $campaign_id
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to save campaign'));
        }
    }
    
    public function ajax_delete_campaign() {
        check_ajax_referer('mpesa_cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $campaign_id = intval($_POST['id']);
        $result = MpesaCF_Database::delete_campaign($campaign_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Campaign deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete campaign'));
        }
    }
    
    public function ajax_upload_image() {
        check_ajax_referer('mpesa_cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploadedfile = $_FILES['file'];
        $upload_overrides = array('test_form' => false);
        
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            wp_send_json_success(array(
                'url' => $movefile['url'],
                'message' => 'Image uploaded successfully'
            ));
        } else {
            wp_send_json_error(array('message' => $movefile['error']));
        }
    }
    
    public function ajax_save_settings() {
        check_ajax_referer('mpesa_cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = array(
            'mpesa_cf_environment',
            'mpesa_cf_business_shortcode',
            'mpesa_cf_consumer_key',
            'mpesa_cf_consumer_secret',
            'mpesa_cf_passkey',
            'mpesa_cf_currency',
            'mpesa_cf_default_amounts',
            'mpesa_cf_enable_cards',
            'mpesa_cf_enable_bank',
            'mpesa_cf_admin_email',
            'mpesa_cf_send_receipts'
        );
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
        
        wp_send_json_success(array('message' => 'Settings saved successfully'));
   }
}
?>