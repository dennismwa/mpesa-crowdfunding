<?php
/**
 * Plugin Name: M-Pesa Crowd Funding
 * Plugin URI: https://example.com
 * Description: A comprehensive crowd funding plugin with M-Pesa integration for churches, donations, and community fundraising.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: mpesa-crowdfunding
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check PHP version
if (version_compare(PHP_VERSION, '7.0', '<')) {
    add_action('admin_notices', 'mpesa_cf_php_version_notice');
    return;
}

function mpesa_cf_php_version_notice() {
    echo '<div class="notice notice-error"><p>M-Pesa Crowdfunding requires PHP 7.0 or higher. You are running PHP ' . PHP_VERSION . '</p></div>';
}

// Define plugin constants
define('MPESA_CF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPESA_CF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MPESA_CF_VERSION', '1.0.0');

// Main plugin class
class MpesaCrowdFunding {
    
    public function __construct() {
        // Hook into WordPress
        add_action('plugins_loaded', array($this, 'init'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Check if dependencies exist
        if (!$this->check_dependencies()) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
        
        // Add shortcode
        add_shortcode('mpesa_crowdfunding', array($this, 'shortcode'));
        
        // Load text domain
        load_plugin_textdomain('mpesa-crowdfunding', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function check_dependencies() {
        // Check if required files exist
        $required_files = array(
            'includes/class-database.php',
            'includes/class-admin.php',
            'includes/class-frontend.php',
            'includes/class-api.php'
        );
        
        foreach ($required_files as $file) {
            if (!file_exists(MPESA_CF_PLUGIN_PATH . $file)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function dependency_notice() {
        echo '<div class="notice notice-error"><p>M-Pesa Crowdfunding: Required plugin files are missing. Please ensure all plugin files are uploaded correctly.</p></div>';
    }
    
    private function load_dependencies() {
        try {
            require_once MPESA_CF_PLUGIN_PATH . 'includes/class-database.php';
            require_once MPESA_CF_PLUGIN_PATH . 'includes/class-admin.php';
            require_once MPESA_CF_PLUGIN_PATH . 'includes/class-frontend.php';
            require_once MPESA_CF_PLUGIN_PATH . 'includes/class-api.php';
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>M-Pesa Crowdfunding Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    private function init_components() {
        try {
            // Initialize admin
            if (is_admin()) {
                new MpesaCF_Admin();
            }
            
            // Initialize frontend
            new MpesaCF_Frontend();
            
            // Initialize API
            new MpesaCF_API();
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>M-Pesa Crowdfunding Initialization Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    public function activate() {
        try {
            // Create database tables
            if (class_exists('MpesaCF_Database')) {
                MpesaCF_Database::create_tables();
            }
            
            // Create upload directory for campaign images
            $upload_dir = wp_upload_dir();
            $mpesa_cf_dir = $upload_dir['basedir'] . '/mpesa-crowdfunding';
            if (!file_exists($mpesa_cf_dir)) {
                wp_mkdir_p($mpesa_cf_dir);
            }
            
            // Set default options
            $this->set_default_options();
            
            // Clear any cached data
            wp_cache_flush();
            
        } catch (Exception $e) {
            wp_die('Plugin activation failed: ' . $e->getMessage());
        }
    }
    
    private function set_default_options() {
        // Default categories
        $default_categories = array(
            'Church Project' => 'Building and infrastructure projects',
            'Offertories' => 'Weekly and special offerings',
            'Tithe' => 'Monthly tithe contributions',
            'Wedding' => 'Wedding ceremony funding',
            'Funeral' => 'Funeral arrangement support',
            'Education' => 'Educational support and scholarships',
            'Medical' => 'Medical emergency funding',
            'Community' => 'Community development projects'
        );
        
        // Set options if they don't exist
        add_option('mpesa_cf_categories', $default_categories);
        add_option('mpesa_cf_currency', 'KES');
        add_option('mpesa_cf_business_shortcode', '');
        add_option('mpesa_cf_consumer_key', '');
        add_option('mpesa_cf_consumer_secret', '');
        add_option('mpesa_cf_passkey', '');
        add_option('mpesa_cf_environment', 'sandbox');
        add_option('mpesa_cf_default_amounts', '100,500,1000,5000');
        add_option('mpesa_cf_admin_email', get_option('admin_email'));
        add_option('mpesa_cf_send_receipts', 1);
        add_option('mpesa_cf_enable_cards', 0);
        add_option('mpesa_cf_enable_bank', 0);
    }
    
    public function deactivate() {
        // Clean up temporary data
        wp_cache_flush();
        
        // Clear scheduled events if any
        wp_clear_scheduled_hook('mpesa_cf_cleanup_logs');
    }
    
    public function shortcode($atts, $content = null) {
        // Sanitize attributes
        $atts = shortcode_atts(array(
            'category' => '',
            'campaign_id' => '',
            'style' => 'grid'
        ), $atts, 'mpesa_crowdfunding');
        
        // Validate attributes
        $atts['category'] = sanitize_text_field($atts['category']);
        $atts['campaign_id'] = intval($atts['campaign_id']);
        $atts['style'] = in_array($atts['style'], array('grid', 'list')) ? $atts['style'] : 'grid';
        
        ob_start();
        
        try {
            if (!empty($atts['campaign_id'])) {
                $this->display_single_campaign($atts['campaign_id']);
            } else {
                $this->display_campaigns($atts['category'], $atts['style']);
            }
        } catch (Exception $e) {
            echo '<p>Error loading campaigns: ' . esc_html($e->getMessage()) . '</p>';
        }
        
        return ob_get_clean();
    }
    
    private function display_campaigns($category = '', $style = 'grid') {
        if (!class_exists('MpesaCF_Database')) {
            echo '<p>Plugin error: Database class not found.</p>';
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
            echo '<p>Plugin error: Database tables not found. Please deactivate and reactivate the plugin.</p>';
            return;
        }
        
        $where_clause = "WHERE status = 'active'";
        $where_values = array();
        
        if (!empty($category)) {
            $where_clause .= " AND category = %s";
            $where_values[] = $category;
        }
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC";
        
        if (!empty($where_values)) {
            $campaigns = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $campaigns = $wpdb->get_results($query);
        }
        
        if (empty($campaigns)) {
            echo '<p>No campaigns found.</p>';
            return;
        }
        
        echo '<div class="mpesa-cf-campaigns ' . esc_attr($style) . '">';
        
        foreach ($campaigns as $campaign) {
            $this->display_campaign_card($campaign);
        }
        
        echo '</div>';
    }
    
    private function display_campaign_card($campaign) {
        $progress_percentage = ($campaign->target_amount > 0) ? 
            min(100, ($campaign->current_amount / $campaign->target_amount) * 100) : 0;
        
        echo '<div class="mpesa-cf-campaign-card" data-campaign-id="' . esc_attr($campaign->id) . '">';
        
        if (!empty($campaign->image_url)) {
            echo '<div class="campaign-image">';
            echo '<img src="' . esc_url($campaign->image_url) . '" alt="' . esc_attr($campaign->title) . '">';
            echo '</div>';
        }
        
        echo '<div class="campaign-content">';
        echo '<h3>' . esc_html($campaign->title) . '</h3>';
        echo '<p class="campaign-category">' . esc_html($campaign->category) . '</p>';
        echo '<p class="campaign-description">' . esc_html(wp_trim_words($campaign->description, 20)) . '</p>';
        
        echo '<div class="campaign-progress">';
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" style="width: ' . esc_attr($progress_percentage) . '%"></div>';
        echo '</div>';
        echo '<div class="progress-text">';
        echo '<span class="current">KES ' . number_format($campaign->current_amount) . '</span>';
        echo '<span class="target">of KES ' . number_format($campaign->target_amount) . '</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="campaign-actions">';
        echo '<button class="donate-btn" onclick="openDonationModal(' . esc_attr($campaign->id) . ')">Donate Now</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    private function display_single_campaign($campaign_id) {
        if (!class_exists('MpesaCF_Database')) {
            echo '<p>Plugin error: Database class not found.</p>';
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND status = 'active'", $campaign_id));
        
        if (!$campaign) {
            echo '<p>Campaign not found or not active.</p>';
            return;
        }
        
        $progress_percentage = ($campaign->target_amount > 0) ? 
            min(100, ($campaign->current_amount / $campaign->target_amount) * 100) : 0;
        
        echo '<div class="mpesa-cf-single-campaign">';
        
        if (!empty($campaign->image_url)) {
            echo '<div class="campaign-hero-image">';
            echo '<img src="' . esc_url($campaign->image_url) . '" alt="' . esc_attr($campaign->title) . '">';
            echo '</div>';
        }
        
        echo '<div class="campaign-details">';
        echo '<h2>' . esc_html($campaign->title) . '</h2>';
        echo '<p class="campaign-category"><strong>Category:</strong> ' . esc_html($campaign->category) . '</p>';
        echo '<div class="campaign-description">' . wp_kses_post(wpautop($campaign->description)) . '</div>';
        
        echo '<div class="campaign-stats">';
        echo '<div class="progress-section">';
        echo '<div class="progress-bar large">';
        echo '<div class="progress-fill" style="width: ' . esc_attr($progress_percentage) . '%"></div>';
        echo '</div>';
        echo '<div class="stats-grid">';
        echo '<div class="stat"><strong>KES ' . number_format($campaign->current_amount) . '</strong><br>Raised</div>';
        echo '<div class="stat"><strong>KES ' . number_format($campaign->target_amount) . '</strong><br>Goal</div>';
        echo '<div class="stat"><strong>' . number_format($progress_percentage, 1) . '%</strong><br>Complete</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="donation-section">';
        echo '<button class="donate-btn large" onclick="openDonationModal(' . esc_attr($campaign->id) . ')">Make a Donation</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
}

// Initialize plugin
new MpesaCrowdFunding();

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'mpesa_cf_enqueue_scripts');
function mpesa_cf_enqueue_scripts() {
    if (!defined('MPESA_CF_PLUGIN_URL')) {
        return;
    }
    
    wp_enqueue_style('mpesa-cf-style', MPESA_CF_PLUGIN_URL . 'assets/css/frontend.css', array(), MPESA_CF_VERSION);
    wp_enqueue_script('mpesa-cf-script', MPESA_CF_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), MPESA_CF_VERSION, true);
    
    wp_localize_script('mpesa-cf-script', 'mpesa_cf_frontend', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mpesa_cf_frontend_nonce'),
        'currency' => get_option('mpesa_cf_currency', 'KES'),
        'default_amounts' => explode(',', get_option('mpesa_cf_default_amounts', '100,500,1000,5000')),
        'enable_cards' => get_option('mpesa_cf_enable_cards', 0),
        'enable_bank' => get_option('mpesa_cf_enable_bank', 0)
    ));
}
?>