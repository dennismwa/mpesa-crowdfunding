<?php
/**
 * Database operations for M-Pesa Crowd Funding
 */

class MpesaCF_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Campaigns table
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        $campaigns_sql = "CREATE TABLE $campaigns_table (
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
            PRIMARY KEY (id),
            KEY category (category),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Donations table
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        $donations_sql = "CREATE TABLE $donations_table (
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
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY transaction_id (transaction_id),
            KEY mpesa_receipt_number (mpesa_receipt_number),
            KEY status (status),
            KEY created_at (created_at),
            FOREIGN KEY (campaign_id) REFERENCES $campaigns_table(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Transaction logs table
        $logs_table = $wpdb->prefix . 'mpesa_cf_transaction_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            donation_id int(11),
            transaction_type enum('stk_push','callback','manual') DEFAULT 'stk_push',
            request_data text,
            response_data text,
            status varchar(50),
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY donation_id (donation_id),
            KEY transaction_type (transaction_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Categories table (for custom categories)
        $categories_table = $wpdb->prefix . 'mpesa_cf_categories';
        $categories_sql = "CREATE TABLE $categories_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#007cba',
            icon varchar(50),
            sort_order int(11) DEFAULT 0,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY status (status),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($campaigns_sql);
        dbDelta($donations_sql);
        dbDelta($logs_sql);
        dbDelta($categories_sql);
        
        // Insert default categories
        self::insert_default_categories();
    }
    
    private static function insert_default_categories() {
        global $wpdb;
        
        $categories_table = $wpdb->prefix . 'mpesa_cf_categories';
        
        // Check if categories already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
        if ($count > 0) {
            return; // Categories already exist
        }
        
        $default_categories = array(
            array('name' => 'Church Project', 'description' => 'Building and infrastructure projects', 'color' => '#8B4513', 'icon' => 'church', 'sort_order' => 1),
            array('name' => 'Offertories', 'description' => 'Weekly and special offerings', 'color' => '#FFD700', 'icon' => 'gift', 'sort_order' => 2),
            array('name' => 'Tithe', 'description' => 'Monthly tithe contributions', 'color' => '#32CD32', 'icon' => 'hand-holding-heart', 'sort_order' => 3),
            array('name' => 'Wedding', 'description' => 'Wedding ceremony funding', 'color' => '#FF69B4', 'icon' => 'heart', 'sort_order' => 4),
            array('name' => 'Funeral', 'description' => 'Funeral arrangement support', 'color' => '#696969', 'icon' => 'dove', 'sort_order' => 5),
            array('name' => 'Education', 'description' => 'Educational support and scholarships', 'color' => '#4169E1', 'icon' => 'graduation-cap', 'sort_order' => 6),
            array('name' => 'Medical', 'description' => 'Medical emergency funding', 'color' => '#DC143C', 'icon' => 'heartbeat', 'sort_order' => 7),
            array('name' => 'Community', 'description' => 'Community development projects', 'color' => '#228B22', 'icon' => 'users', 'sort_order' => 8)
        );
        
        foreach ($default_categories as $category) {
            $wpdb->insert($categories_table, $category);
        }
    }
    
    public static function get_campaigns($filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        $where_conditions = array();
        $where_values = array();
        
        // Default filter - only active campaigns
        if (!isset($filters['include_all_statuses'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = 'active';
        }
        
        if (!empty($filters['category'])) {
            $where_conditions[] = "category = %s";
            $where_values[] = $filters['category'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $order_by = isset($filters['order_by']) ? $filters['order_by'] : 'created_at DESC';
        $limit = isset($filters['limit']) ? $filters['limit'] : '';
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY $order_by";
        
        if (!empty($limit)) {
            $query .= " LIMIT " . intval($limit);
        }
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query);
    }
    
    public static function get_campaign($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
    
    public static function create_campaign($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        $defaults = array(
            'title' => '',
            'description' => '',
            'category' => '',
            'target_amount' => 0,
            'current_amount' => 0,
            'image_url' => '',
            'status' => 'active',
            'created_by' => get_current_user_id()
        );
        
        $campaign_data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table_name, $campaign_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public static function update_campaign($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        return $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    public static function delete_campaign($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        return $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    public static function create_donation($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_donations';
        
        $defaults = array(
            'campaign_id' => 0,
            'donor_name' => '',
            'donor_email' => '',
            'donor_phone' => '',
            'amount' => 0,
            'payment_method' => 'mpesa',
            'transaction_id' => '',
            'mpesa_receipt_number' => '',
            'status' => 'pending',
            'anonymous' => 0,
            'message' => '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        $donation_data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert($table_name, $donation_data);
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public static function update_donation($id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_donations';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        // If donation is completed, update campaign total
        if (isset($data['status']) && $data['status'] === 'completed') {
            self::update_campaign_total($data['campaign_id'] ?? self::get_donation_campaign_id($id));
        }
        
        return $result;
    }
    
    public static function get_donation($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_donations';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
    
    public static function get_donation_campaign_id($donation_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_donations';
        return $wpdb->get_var($wpdb->prepare("SELECT campaign_id FROM $table_name WHERE id = %d", $donation_id));
    }
    
    public static function update_campaign_total($campaign_id) {
        global $wpdb;
        
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        $campaigns_table = $wpdb->prefix . 'mpesa_cf_campaigns';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $donations_table WHERE campaign_id = %d AND status = 'completed'",
            $campaign_id
        ));
        
        $total = $total ?: 0;
        
        $wpdb->update(
            $campaigns_table,
            array('current_amount' => $total),
            array('id' => $campaign_id),
            array('%f'),
            array('%d')
        );
        
        return $total;
    }
    
    public static function log_transaction($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_transaction_logs';
        
        $defaults = array(
            'donation_id' => null,
            'transaction_type' => 'stk_push',
            'request_data' => '',
            'response_data' => '',
            'status' => '',
            'error_message' => ''
        );
        
        $log_data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($table_name, $log_data);
    }
    
    public static function get_categories() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_categories';
        return $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    }
    
    public static function get_campaign_donations($campaign_id, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mpesa_cf_donations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE campaign_id = %d AND status = 'completed' 
             ORDER BY created_at DESC 
             LIMIT %d",
            $campaign_id,
            $limit
        ));
    }
    
    public static function get_donation_stats($campaign_id = null) {
        global $wpdb;
        
        $donations_table = $wpdb->prefix . 'mpesa_cf_donations';
        $where_clause = "WHERE status = 'completed'";
        $where_values = array();
        
        if ($campaign_id) {
            $where_clause .= " AND campaign_id = %d";
            $where_values[] = $campaign_id;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_donations,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount
                  FROM $donations_table $where_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_row($query);
    }
}
?>