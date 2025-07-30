-- M-Pesa Crowdfunding Database Schema
-- This file contains the complete database structure for the plugin
-- Run this if you need to manually create the tables

-- Campaigns Table
CREATE TABLE `wp_mpesa_cf_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(100) NOT NULL,
  `target_amount` decimal(15,2) DEFAULT 0.00,
  `current_amount` decimal(15,2) DEFAULT 0.00,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('active','paused','completed','draft') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Donations Table
CREATE TABLE `wp_mpesa_cf_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `donor_name` varchar(255) DEFAULT NULL,
  `donor_email` varchar(255) DEFAULT NULL,
  `donor_phone` varchar(20) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('mpesa','card','bank') DEFAULT 'mpesa',
  `transaction_id` varchar(100) DEFAULT NULL,
  `mpesa_receipt_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `anonymous` tinyint(1) DEFAULT 0,
  `message` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `mpesa_receipt_number` (`mpesa_receipt_number`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `payment_method` (`payment_method`),
  KEY `donor_phone` (`donor_phone`),
  CONSTRAINT `fk_donations_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `wp_mpesa_cf_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction Logs Table
CREATE TABLE `wp_mpesa_cf_transaction_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donation_id` int(11) DEFAULT NULL,
  `transaction_type` enum('stk_push','callback','manual','query') DEFAULT 'stk_push',
  `request_data` text,
  `response_data` text,
  `status` varchar(50) DEFAULT NULL,
  `error_message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donation_id` (`donation_id`),
  KEY `transaction_type` (`transaction_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_logs_donation` FOREIGN KEY (`donation_id`) REFERENCES `wp_mpesa_cf_donations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories Table
CREATE TABLE `wp_mpesa_cf_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `color` varchar(7) DEFAULT '#007cba',
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `status` (`status`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Categories
INSERT INTO `wp_mpesa_cf_categories` (`name`, `description`, `color`, `icon`, `sort_order`, `status`) VALUES
('Church Project', 'Building and infrastructure projects', '#8B4513', 'church', 1, 'active'),
('Offertories', 'Weekly and special offerings', '#FFD700', 'gift', 2, 'active'),
('Tithe', 'Monthly tithe contributions', '#32CD32', 'hand-holding-heart', 3, 'active'),
('Wedding', 'Wedding ceremony funding', '#FF69B4', 'heart', 4, 'active'),
('Funeral', 'Funeral arrangement support', '#696969', 'dove', 5, 'active'),
('Education', 'Educational support and scholarships', '#4169E1', 'graduation-cap', 6, 'active'),
('Medical', 'Medical emergency funding', '#DC143C', 'heartbeat', 7, 'active'),
('Community', 'Community development projects', '#228B22', 'users', 8, 'active');
-- Sample Data (Optional - Remove if not needed)
-- INSERT INTO wp_mpesa_cf_campaigns (title, description, category, target_amount, current_amount, status, created_by) VALUES
-- ('New Church Building Fund', 'Help us build a new sanctuary for our growing congregation. We need funds for construction materials, labor, and equipment.', 'Church Project', 500000.00, 85000.00, 'active', 1),
-- ('Sunday School Equipment', 'Purchase new chairs, tables, and teaching materials for our Sunday school children.', 'Education', 50000.00, 25000.00, 'active', 1),
-- ('Medical Emergency - John Doe', 'Our church member John needs urgent medical assistance for his surgery. Every contribution counts.', 'Medical', 200000.00, 45000.00, 'active', 1),
-- ('Community Water Project', 'Drilling a borehole to provide clean water access for our local community.', 'Community', 300000.00, 120000.00, 'active', 1);
-- Indexes for better performance
CREATE INDEX idx_campaigns_category_status ON wp_mpesa_cf_campaigns(category, status);
CREATE INDEX idx_campaigns_current_amount ON wp_mpesa_cf_campaigns(current_amount DESC);
CREATE INDEX idx_donations_campaign_status ON wp_mpesa_cf_donations(campaign_id, status);
CREATE INDEX idx_donations_created_status ON wp_mpesa_cf_donations(created_at DESC, status);
CREATE INDEX idx_donations_amount ON wp_mpesa_cf_donations(amount DESC);
CREATE INDEX idx_logs_donation_type ON wp_mpesa_cf_transaction_logs(donation_id, transaction_type);
-- Views for reporting (Optional)
CREATE OR REPLACE VIEW vw_campaign_stats AS
SELECT
c.id,
c.title,
c.category,
c.target_amount,
c.current_amount,
COALESCE(c.current_amount / NULLIF(c.target_amount, 0) * 100, 0) as progress_percentage,
COUNT(d.id) as total_donations,
AVG(d.amount) as average_donation,
MAX(d.amount) as largest_donation,
MIN(d.amount) as smallest_donation,
c.status,
c.created_at
FROM wp_mpesa_cf_campaigns c
LEFT JOIN wp_mpesa_cf_donations d ON c.id = d.campaign_id AND d.status = 'completed'
GROUP BY c.id;
CREATE OR REPLACE VIEW vw_daily_donations AS
SELECT
DATE(created_at) as donation_date,
COUNT(*) as total_donations,
SUM(amount) as total_amount,
AVG(amount) as average_amount,
payment_method
FROM wp_mpesa_cf_donations
WHERE status = 'completed'
GROUP BY DATE(created_at), payment_method
ORDER BY donation_date DESC;
-- Stored Procedures for common operations
DELIMITER //
-- Update campaign total when donation status changes
CREATE TRIGGER tr_update_campaign_total
AFTER UPDATE ON wp_mpesa_cf_donations
FOR EACH ROW
BEGIN
IF OLD.status != NEW.status AND NEW.status = 'completed' THEN
UPDATE wp_mpesa_cf_campaigns
SET current_amount = (
SELECT COALESCE(SUM(amount), 0)
FROM wp_mpesa_cf_donations
WHERE campaign_id = NEW.campaign_id AND status = 'completed'
)
WHERE id = NEW.campaign_id;
END IF;
END//
-- Clean up old transaction logs (keep last 3 months)
CREATE EVENT IF NOT EXISTS ev_cleanup_old_logs
ON SCHEDULE EVERY 1 WEEK
DO
BEGIN
DELETE FROM wp_mpesa_cf_transaction_logs
WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
END//
DELIMITER ;
-- Additional indexes for better query performance
ALTER TABLE wp_mpesa_cf_donations ADD INDEX idx_donor_email (donor_email);
ALTER TABLE wp_mpesa_cf_donations ADD INDEX idx_anonymous (anonymous);
ALTER TABLE wp_mpesa_cf_campaigns ADD INDEX idx_target_amount (target_amount);
ALTER TABLE wp_mpesa_cf_transaction_logs ADD INDEX idx_error_status (status, error_message(100));