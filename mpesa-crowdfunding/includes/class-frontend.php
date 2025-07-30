<?php
/**
 * Frontend functionality for M-Pesa Crowd Funding
 */

if (!defined('ABSPATH')) {
    exit;
}

class MpesaCF_Frontend {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_modal_html'));
    }
    
    public function enqueue_scripts() {
        // Add basic CSS
        wp_add_inline_style('wp-block-library', $this->get_frontend_css());
        
        // Add basic JavaScript
        wp_add_inline_script('jquery', $this->get_frontend_js());
    }
    
    private function get_frontend_css() {
        return '
        .mpesa-cf-campaigns { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .mpesa-cf-campaign-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .mpesa-cf-campaign-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .campaign-image { height: 200px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; }
        .campaign-image img { width: 100%; height: 100%; object-fit: cover; }
        .campaign-content h3 { margin: 0 0 10px 0; font-size: 1.3em; color: #333; }
        .campaign-category { color: #666; font-size: 0.9em; margin-bottom: 10px; background: #f0f0f0; padding: 4px 8px; border-radius: 12px; display: inline-block; }
        .campaign-description { margin-bottom: 15px; line-height: 1.5; color: #555; }
        .campaign-progress { margin-bottom: 15px; }
        .progress-bar { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #4CAF50, #45a049); border-radius: 4px; }
        .progress-text { display: flex; justify-content: space-between; font-size: 0.9em; color: #666; }
        .progress-text .current { font-weight: bold; color: #333; }
        .donate-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 25px; font-weight: bold; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px; }
        .donate-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
        .mpesa-cf-single-campaign { max-width: 800px; margin: 20px auto; }
        .campaign-hero-image { height: 300px; border-radius: 12px; overflow: hidden; margin-bottom: 20px; }
        .campaign-details h2 { font-size: 2em; margin-bottom: 15px; color: #333; }
        .campaign-stats { background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center; }
        .stat strong { display: block; font-size: 1.5em; color: #333; margin-bottom: 5px; }
        .donation-section { text-align: center; margin-top: 30px; }
        .donate-btn.large { padding: 15px 40px; font-size: 1.1em; }
        
        /* Modal Styles */
        .mpesa-cf-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; display: none; align-items: center; justify-content: center; }
        .mpesa-cf-modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-primary { background: #007cba; color: white; }
        .btn-secondary { background: #ccc; color: #333; }
        ';
    }
    
    private function get_frontend_js() {
        return '
        function openDonationModal(campaignId) {
            // Simple alert for now - full modal functionality would be added later
            alert("Donation modal for campaign " + campaignId + " would open here. M-Pesa integration coming soon!");
        }
        
        function closeDonationModal() {
            jQuery("#mpesa-cf-donation-modal").removeClass("active");
        }
        
        jQuery(document).ready(function($) {
            // Modal close events
            $(document).on("click", ".modal-close, .modal-overlay", closeDonationModal);
            
            // Escape key to close modal
            $(document).keyup(function(e) {
                if (e.keyCode === 27) {
                    closeDonationModal();
                }
            });
        });
        ';
    }
    
    public function add_modal_html() {
        // Basic modal structure for future enhancement
        ?>
        <div id="mpesa-cf-donation-modal" class="mpesa-cf-modal">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Make a Donation</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>M-Pesa donation functionality coming soon!</p>
                    <p>This is where donors will:</p>
                    <ul>
                        <li>Enter donation amount</li>
                        <li>Provide their details</li>
                        <li>Complete M-Pesa payment</li>
                        <li>Receive confirmation</li>
                    </ul>
                    <div style="text-align: center; margin-top: 20px;">
                        <button class="btn btn-secondary" onclick="closeDonationModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
?>