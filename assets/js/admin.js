// M-Pesa Crowdfunding Admin JavaScript

jQuery(document).ready(function($) {
    // Initialize admin interface
    initializeAdmin();
    
    function initializeAdmin() {
        // Setup event listeners
        setupEventListeners();
        
        // Initialize charts if on reports page
        if ($('#donations-chart').length) {
            initializeCharts();
        }
        
        // Setup image uploader
        setupImageUploader();
    }
    
    function setupEventListeners() {
        // Campaign form submission
        $(document).on('submit', '#campaign-form', handleCampaignSubmit);
        
        // Settings form submission
        $(document).on('submit', '#settings-form', handleSettingsSubmit);
        
        // Filter changes
        $(document).on('change', '#filter-category, #filter-status', handleFilterChange);
        
        // Modal events
        $(document).on('click', '.modal-close, .modal-overlay', closeModal);
        
        // Test M-Pesa connection
        $(document).on('click', 'button[onclick="testMpesaConnection()"]', testMpesaConnection);
    }
    
    function handleCampaignSubmit(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        // Show loading state
        submitBtn.prop('disabled', true).html('<span class="spinner-admin"></span> Saving...');
        
        // Prepare form data
        const formData = {
            action: 'mpesa_cf_save_campaign',
            nonce: mpesa_cf_admin.nonce,
            id: $('#campaign-id').val(),
            title: $('#campaign-title').val(),
            description: $('#campaign-description').val(),
            category: $('#campaign-category').val(),
            target_amount: $('#campaign-target').val(),
            status: $('#campaign-status').val(),
            image_url: $('#campaign-image-url').val()
        };
        
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotice('Campaign saved successfully!', 'success');
                    closeCampaignModal();
                    location.reload(); // Refresh to show updated data
                } else {
                    showNotice('Error: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while saving the campaign.', 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function handleSettingsSubmit(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        // Show loading state
        submitBtn.prop('disabled', true).html('<span class="spinner-admin"></span> Saving...');
        
        // Serialize form data
        const formData = form.serialize() + '&action=mpesa_cf_save_settings&nonce=' + mpesa_cf_admin.nonce;
        
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotice('Settings saved successfully!', 'success');
                } else {
                    showNotice('Error saving settings.', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while saving settings.', 'error');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function handleFilterChange() {
        const category = $('#filter-category').val();
        const status = $('#filter-status').val();
        
        // Filter table rows
        $('table tbody tr').each(function() {
            const row = $(this);
            const rowCategory = row.find('td:nth-child(3)').text().trim();
            const rowStatus = row.find('.status-badge').text().trim().toLowerCase();
            
            let showRow = true;
            
            if (category && rowCategory !== category) {
                showRow = false;
            }
            
            if (status && rowStatus !== status) {
                showRow = false;
            }
            
            row.toggle(showRow);
        });
    }
    
    function setupImageUploader() {
        window.selectImage = function() {
            const mediaUploader = wp.media({
                title: 'Select Campaign Image',
                button: {
                    text: 'Use This Image'
                },
                multiple: false
            });
            
            mediaUploader.on('select', function() {
                const attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#campaign-image-url').val(attachment.url);
                $('#image-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 150px; border-radius: 8px;">');
            });
            
            mediaUploader.open();
        };
    }
    
    function testMpesaConnection(e) {
        e.preventDefault();
        
        const btn = $(this);
        const originalText = btn.text();
        
        btn.prop('disabled', true).html('<span class="spinner-admin"></span> Testing...');
        
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_test_connection',
                nonce: mpesa_cf_admin.nonce
            },
            success: function(response) {
                const resultsDiv = $('#test-results');
                
                if (response.success) {
                    resultsDiv.removeClass('error').addClass('success')
                        .html('<strong>Success!</strong> ' + response.data.message);
                } else {
                    resultsDiv.removeClass('success').addClass('error')
                        .html('<strong>Error!</strong> ' + response.data.message);
                }
            },
            error: function() {
                $('#test-results').removeClass('success').addClass('error')
                    .html('<strong>Error!</strong> Failed to test connection.');
            },
            complete: function() {
                btn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    function initializeCharts() {
        // Donations trend chart
        const donationsCtx = document.getElementById('donations-chart');
        if (donationsCtx) {
            fetchDonationsTrendData().then(data => {
                new Chart(donationsCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Daily Donations (KES)',
                            data: data.amounts,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Donation Trends (Last 30 Days)'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'KES ' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            });
        }
        
        // Categories performance chart
        const categoriesCtx = document.getElementById('categories-chart');
        if (categoriesCtx) {
            fetchCategoriesData().then(data => {
                new Chart(categoriesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.categories,
                        datasets: [{
                            data: data.amounts,
                            backgroundColor: [
                                '#FF6384',
                                '#36A2EB',
                                '#FFCE56',
                                '#4BC0C0',
                                '#9966FF',
                                '#FF9F40',
                                '#FF6384',
                                '#C9CBCF'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Donations by Category'
                            },
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            });
        }
    }
    
    function fetchDonationsTrendData() {
        return $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_get_donations_trend',
                nonce: mpesa_cf_admin.nonce
            }
        }).then(function(response) {
            if (response.success) {
                return response.data;
            } else {
                // Return dummy data if API fails
                return {
                    labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
                    amounts: [1000, 1500, 800, 2000, 1200]
                };
            }
        });
    }
    
    function fetchCategoriesData() {
        return $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_get_categories_stats',
                nonce: mpesa_cf_admin.nonce
            }
        }).then(function(response) {
            if (response.success) {
                return response.data;
            } else {
                // Return dummy data if API fails
                return {
                    categories: ['Church Project', 'Education', 'Medical', 'Community'],
                    amounts: [50000, 25000, 30000, 15000]
                };
            }
        });
    }
    
    function showNotice(message, type) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="' + noticeClass + '">' + message + '</div>');
        
        $('.mpesa-cf-admin').prepend(notice);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 5000);
    }
    
    function closeModal() {
        $('.mpesa-cf-modal').hide();
    }
    
    // Global functions
    window.openCampaignModal = function() {
        resetCampaignForm();
        $('#modal-title').text('Add New Campaign');
        $('#campaign-modal').show();
    };
    
    window.closeCampaignModal = function() {
        $('#campaign-modal').hide();
        resetCampaignForm();
    };
    
    window.editCampaign = function(campaignId) {
        // Fetch campaign data and populate form
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_get_campaign',
                nonce: mpesa_cf_admin.nonce,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    const campaign = response.data.campaign;
                    
                    $('#campaign-id').val(campaign.id);
                    $('#campaign-title').val(campaign.title);
                    $('#campaign-description').val(campaign.description);
                    $('#campaign-category').val(campaign.category);
                    $('#campaign-target').val(campaign.target_amount);
                    $('#campaign-status').val(campaign.status);
                    $('#campaign-image-url').val(campaign.image_url);
                    
                    if (campaign.image_url) {
                        $('#image-preview').html('<img src="' + campaign.image_url + '" style="max-width: 200px; max-height: 150px; border-radius: 8px;">');
                    }
                    
                    $('#modal-title').text('Edit Campaign');
                    $('#campaign-modal').show();
                } else {
                    showNotice('Error loading campaign data.', 'error');
                }
            },
            error: function() {
                showNotice('Error loading campaign data.', 'error');
            }
        });
    };
    
    window.deleteCampaign = function(campaignId) {
        if (!confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_delete_campaign',
                nonce: mpesa_cf_admin.nonce,
                id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Campaign deleted successfully.', 'success');
                    location.reload();
                } else {
                    showNotice('Error deleting campaign.', 'error');
                }
            },
            error: function() {
                showNotice('Error deleting campaign.', 'error');
            }
        });
    };
    
    window.viewDonations = function(campaignId) {
        window.location.href = 'admin.php?page=mpesa-crowdfunding-donations&campaign_id=' + campaignId;
    };
    
    window.viewDonationDetails = function(donationId) {
        // Open donation details modal or redirect
        window.open('admin.php?page=mpesa-crowdfunding-donations&view=details&id=' + donationId, '_blank');
    };
    
    window.updateDonationStatus = function(donationId, status) {
        if (!confirm('Are you sure you want to update this donation status?')) {
            return;
        }
        
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_update_donation_status',
                nonce: mpesa_cf_admin.nonce,
                donation_id: donationId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Donation status updated successfully.', 'success');
                    location.reload();
                } else {
                    showNotice('Error updating donation status.', 'error');
                }
            },
            error: function() {
                showNotice('Error updating donation status.', 'error');
            }
        });
    };
    
    function resetCampaignForm() {
        $('#campaign-form')[0].reset();
        $('#campaign-id').val('');
        $('#image-preview').empty();
    }
    
    // Export functionality
    window.exportCampaignData = function(format) {
        const url = mpesa_cf_admin.ajax_url + '?action=mpesa_cf_export_data&format=' + format + '&nonce=' + mpesa_cf_admin.nonce;
        window.open(url, '_blank');
    };
    
    // Bulk actions
    window.performBulkAction = function() {
        const action = $('#bulk-action-selector').val();
        const selectedItems = $('.bulk-checkbox:checked').map(function() {
            return this.value;
        }).get();
        
        if (!action) {
            alert('Please select an action.');
            return;
        }
        
        if (selectedItems.length === 0) {
            alert('Please select items to perform the action on.');
            return;
        }
        
        if (!confirm('Are you sure you want to perform this action on ' + selectedItems.length + ' items?')) {
            return;
        }
        
        $.ajax({
            url: mpesa_cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_bulk_action',
                nonce: mpesa_cf_admin.nonce,
                bulk_action: action,
                items: selectedItems
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Bulk action completed successfully.', 'success');
                    location.reload();
                } else {
                    showNotice('Error performing bulk action.', 'error');
                }
            },
            error: function() {
                showNotice('Error performing bulk action.', 'error');
            }
        });
    };
});