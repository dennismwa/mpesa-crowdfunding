<?php
/**
 * Database operations for M-Pesa Crowd Funding
 */

if (!defined('ABSPATH')) {
    exit;
}

class MpesaCF_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        $campaigns_sql = "CREATE TABLE IF NOT EXISTS $campaigns_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            category varchar(100) NOT NULL,
            target_amount decimal(15,2) DEFAULT 0.00,
            current_amount decimal(15,2) DEFAULT 0.00,
            image_url varchar(500),
            status enum('active','paused','completed','draft') DEFAULT 'active',
            created_by int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Donations table
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        $donations_sql = "CREATE TABLE IF NOT EXISTS $donations_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            donor_name varchar(255),
            donor_email varchar(255),
            donor_phone varchar(20),
            amount decimal(15,2) NOT NULL,
            payment_method enum('mpesa','card','bank') DEFAULT 'mpesa',
            transaction_id varchar(100),
            mpesa_receipt_number varchar(100),
            status enum('pending','completed','failed','cancelled') DEFAULT 'pending',
            anonymous tinyint(1) DEFAULT 0,
            message text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Transaction logs table
        $logs_table = $wpdb->prefix . 'mpesa_cf_transaction_logs';
        $logs_sql = "CREATE TABLE IF NOT EXISTS $logs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            donation_id int(11),
            transaction_type enum('stk_push','callback','manual') DEFAULT 'stk_push',
            request_data text,
            response_data text,
            status varchar(50),
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Categories table
        $categories_table = $wpdb->prefix . 'mpesa_cf_categories';
        $categories_sql = "CREATE TABLE IF NOT EXISTS $categories_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#007cba',
            icon varchar(50),
            sort_order int(11) DEFAULT 0,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        // Execute queries
        $wpdb->query($campaigns_sql);
        $wpdb->query($donations_sql);
        $wpdb->query($logs_sql);
        $wpdb->query($categories_sql);
        
        // Insert default categories
        self::insert_default_categories();
        
        // Insert sample campaign
        self::insert_sample_campaign();
    }
    
    private static function insert_default_categories() {
        global $wpdb;
        
        $categories_table = $wpdb->prefix . 'mpesa_cf_categories';
        
        // Check if categories already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
        if ($count > 0) {
            return;
        }
        
        $default_categories = array(
            array('name' => 'Church Project', 'description' => 'Building and infrastructure projects', 'color' => '#8B4513', 'sort_order' => 1),
            array('name' => 'Offertories', 'description' => 'Weekly and special offerings', 'color' => '#FFD700', 'sort_order' => 2),
            array('name' => 'Tithe', 'description' => 'Monthly tithe contributions', 'color' => '#32CD32', 'sort_order' => 3),
            array('name' => 'Wedding', 'description' => 'Wedding ceremony funding', 'color' => '#FF69B4', 'sort_order' => 4),
            array('name' => 'Funeral', 'description' => 'Funeral arrangement support', 'color' => '#696969', 'sort_order' => 5),
            array('name' => 'Education', 'description' => 'Educational support and scholarships', 'color' => '#4169E1', 'sort_order' => 6),
            array('name' => 'Medical', 'description' => 'Medical emergency funding', 'color' => '#DC143C', 'sort_order' => 7),
            array('name' => 'Community', 'description' => 'Community development projects', 'color' => '#228B22', 'sort_order' => 8)
        );
        
        foreach ($default_categories as $category) {
            $wpdb->insert($categories_table, $category);
        }
    }
    
    private static function insert_sample_campaign() {
        global $wpdb;
        
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        // Check if sample campaign already exists
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $campaigns_table");
        if ($count > 0) {
            return;
        }
        
        // Insert sample campaigns
        $sample_campaigns = array(
            array(
                'title' => 'Church Building Fund',
                'description' => 'Help us build a new sanctuary for our growing congregation. We need funds for construction materials, labor, and equipment to create a beautiful place of worship.',
                'category' => 'Church Project',
                'target_amount' => 500000.00,
                'current_amount' => 85000.00,
                'status' => 'active',
                'created_by' => 1
            ),
            array(
                'title' => 'Medical Emergency Fund',
                'description' => 'Supporting our church member who needs urgent medical assistance. Every contribution helps provide the care they need.',
                'category' => 'Medical',
                'target_amount' => 150000.00,
                'current_amount' => 45000.00,
                'status' => 'active',
                'created_by' => 1
            ),
            array(
                'title' => 'Sunday School Equipment',
                'description' => 'Purchase new chairs, tables, and teaching materials for our Sunday school children to enhance their learning experience.',
                'category' => 'Education',
                'target_amount' => 75000.00,
                'current_amount' => 25000.00,
                'status' => 'active',
                'created_by' => 1
            )
        );
        
        foreach ($sample_campaigns as $campaign) {
            $wpdb->insert($campaigns_table, $campaign);
        }
    }
    
    public static function get_campaigns($filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        $where_conditions = array("status = 'active'");
        
        if (!empty($filters['category'])) {
            $where_conditions[] = $wpdb->prepare("category = %s", $filters['category']);
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC";
        
        return $wpdb->get_results($query);
    }
    
    public static function get_campaign($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
}
?>