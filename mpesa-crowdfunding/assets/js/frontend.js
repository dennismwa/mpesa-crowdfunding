// M-Pesa Crowdfunding Frontend JavaScript

jQuery(document).ready(function($) {
    // Initialize frontend
    initializeFrontend();
    
    function initializeFrontend() {
        // Set up event listeners
        setupEventListeners();
        
        // Initialize payment methods visibility
        updatePaymentMethodsVisibility();
        
        // Set up preset amounts
        setupPresetAmounts();
    }
    
    function setupEventListeners() {
        // Donation form submission
        $(document).on('submit', '#donation-form', handleDonationSubmit);
        
        // Amount selection
        $(document).on('click', '.amount-btn', handleAmountSelection);
        $(document).on('input', '#donation-amount', handleCustomAmount);
        
        // Payment method selection
        $(document).on('change', 'input[name="payment_method"]', handlePaymentMethodChange);
        
        // Modal close events
        $(document).on('click', '.modal-close, .modal-overlay', closeDonationModal);
        
        // Escape key to close modal
        $(document).keyup(function(e) {
            if (e.keyCode === 27) {
                closeDonationModal();
            }
        });
    }
    
    function setupPresetAmounts() {
        const amounts = mpesa_cf_frontend.default_amounts;
        const container = $('#preset-amounts');
        
        if (container.length && amounts.length > 0) {
            container.empty();
            amounts.forEach(function(amount) {
                const btn = $('<button>')
                    .addClass('amount-btn')
                    .attr('type', 'button')
                    .attr('data-amount', amount)
                    .text(mpesa_cf_frontend.currency + ' ' + formatNumber(amount));
                container.append(btn);
            });
        }
    }
    
    function updatePaymentMethodsVisibility() {
        if (mpesa_cf_frontend.enable_cards == '1') {
            $('#card-method').show();
        }
        
        if (mpesa_cf_frontend.enable_bank == '1') {
            $('#bank-method').show();
        }
    }
    
    function handleAmountSelection(e) {
        e.preventDefault();
        
        $('.amount-btn').removeClass('selected');
        $(this).addClass('selected');
        
        const amount = $(this).data('amount');
        $('#donation-amount').val(amount);
    }
    
    function handleCustomAmount() {
        $('.amount-btn').removeClass('selected');
    }
    
    function handlePaymentMethodChange() {
        const selectedMethod = $('input[name="payment_method"]:checked').val();
        
        // Update UI based on selected payment method
        $('.payment-instructions').hide();
        $('#' + selectedMethod + '-instructions').show();
    }
    
    function handleDonationSubmit(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const btnText = submitBtn.find('#donate-btn-text');
        const btnLoading = submitBtn.find('#donate-btn-loading');
        
        // Validate form
        if (!validateDonationForm(form)){
return;
}
// Show loading state
    submitBtn.prop('disabled', true);
    btnText.hide();
    btnLoading.show();
    
    // Prepare form data
    const formData = {
        action: 'mpesa_cf_process_donation',
        nonce: mpesa_cf_frontend.nonce,
        campaign_id: $('#campaign-id').val(),
        amount: $('#donation-amount').val(),
        payment_method: $('input[name="payment_method"]:checked').val(),
        donor_name: $('#donor-name').val(),
        donor_phone: $('#donor-phone').val(),
        donor_email: $('#donor-email').val(),
        message: $('#donor-message').val(),
        anonymous: $('#anonymous-donation').is(':checked') ? 1 : 0
    };
    
    // Submit donation
    $.ajax({
        url: mpesa_cf_frontend.ajax_url,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                handleDonationSuccess(response.data, formData);
            } else {
                handleDonationError(response.data.message);
            }
        },
        error: function() {
            handleDonationError('An error occurred. Please try again.');
        },
        complete: function() {
            // Reset loading state
            submitBtn.prop('disabled', false);
            btnText.show();
            btnLoading.hide();
        }
    });
}

function validateDonationForm(form) {
    let isValid = true;
    const errors = [];
    
    // Check required fields
    const requiredFields = ['#donation-amount', '#donor-name', '#donor-phone'];
    requiredFields.forEach(function(field) {
        const input = form.find(field);
        if (!input.val().trim()) {
            isValid = false;
            input.addClass('error');
            errors.push('Please fill in all required fields.');
        } else {
            input.removeClass('error');
        }
    });
    
    // Validate amount
    const amount = parseFloat($('#donation-amount').val());
    if (isNaN(amount) || amount < 1) {
        isValid = false;
        $('#donation-amount').addClass('error');
        errors.push('Please enter a valid donation amount (minimum KES 1).');
    }
    
    // Validate phone number
    const phone = $('#donor-phone').val().replace(/[\s\-\+]/g, '');
    if (!/^(254|0)[7][0-9]{8}$/.test(phone)) {
        isValid = false;
        $('#donor-phone').addClass('error');
        errors.push('Please enter a valid Kenyan phone number.');
    }
    
    // Validate email if provided
    const email = $('#donor-email').val().trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        isValid = false;
        $('#donor-email').addClass('error');
        errors.push('Please enter a valid email address.');
    }
    
    if (!isValid) {
        alert(errors.join('\n'));
    }
    
    return isValid;
}

function handleDonationSuccess(data, formData) {
    // Update confirmation details
    $('#confirm-amount').text(mpesa_cf_frontend.currency + ' ' + formatNumber(formData.amount));
    $('#confirm-phone').text(formData.donor_phone);
    
    if (formData.payment_method === 'mpesa') {
        // Show M-Pesa instructions
        showDonationStep(2);
        
        // Start polling for payment status
        if (data.checkout_request_id) {
            pollPaymentStatus(data.checkout_request_id, data.donation_id);
        }
    } else if (formData.payment_method === 'card') {
        // Handle card payment
        if (data.redirect_url) {
            window.open(data.redirect_url, '_blank');
        }
        showDonationStep(2);
    } else if (formData.payment_method === 'bank') {
        // Show bank transfer details
        $('#bank-reference').text(data.reference);
        showDonationStep(2);
    }
}

function handleDonationError(message) {
    alert('Error: ' + message);
}

function pollPaymentStatus(checkoutRequestId, donationId) {
    let pollCount = 0;
    const maxPolls = 30; // Poll for 5 minutes (30 * 10 seconds)
    
    const pollInterval = setInterval(function() {
        pollCount++;
        
        if (pollCount > maxPolls) {
            clearInterval(pollInterval);
            showPaymentTimeout();
            return;
        }
        
        $.ajax({
            url: mpesa_cf_frontend.ajax_url,
            type: 'POST',
            data: {
                action: 'mpesa_cf_query_status',
                nonce: mpesa_cf_frontend.nonce,
                checkout_request_id: checkoutRequestId
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data.status;
                    
                    if (status === 'completed') {
                        clearInterval(pollInterval);
                        showPaymentSuccess(response.data, donationId);
                    } else if (status === 'failed' || status === 'cancelled') {
                        clearInterval(pollInterval);
                        showPaymentFailed(status);
                    }
                    // Continue polling if status is still pending
                }
            }
        });
    }, 10000); // Poll every 10 seconds
}

function showPaymentSuccess(data, donationId) {
    $('#payment-status').html(
        '<div class="status-success" style="color: #48bb78;">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>' +
        '<polyline points="22,4 12,14.01 9,11.01"></polyline>' +
        '</svg>' +
        '<span>Payment successful!</span>' +
        '</div>'
    );
    
    // Show success step after 2 seconds
    setTimeout(function() {
        $('#success-amount').text(data.amount || $('#confirm-amount').text());
        $('#success-campaign').text($('#modal-campaign-title').text());
        $('#success-receipt').text(data.mpesa_receipt || 'Processing...');
        showDonationStep(3);
    }, 2000);
}

function showPaymentFailed(status) {
    const message = status === 'cancelled' ? 'Payment was cancelled' : 'Payment failed';
    $('#payment-status').html(
        '<div class="status-failed" style="color: #f56565;">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<circle cx="12" cy="12" r="10"></circle>' +
        '<line x1="15" y1="9" x2="9" y2="15"></line>' +
        '<line x1="9" y1="9" x2="15" y2="15"></line>' +
        '</svg>' +
        '<span>' + message + '</span>' +
        '</div>'
    );
}

function showPaymentTimeout() {
    $('#payment-status').html(
        '<div class="status-timeout" style="color: #ed8936;">' +
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
        '<circle cx="12" cy="12" r="10"></circle>' +
        '<polyline points="12,6 12,12 16,14"></polyline>' +
        '</svg>' +
        '<span>Payment timeout. Please check your phone for any pending M-Pesa requests.</span>' +
        '</div>'
    );
}

function showDonationStep(step) {
    $('.donation-step').removeClass('active');
    $('#donation-step-' + step).addClass('active');
}

function formatNumber(num) {
    return parseFloat(num).toLocaleString();
}

// Global functions
window.openDonationModal = function(campaignId) {
    // Get campaign data
    $.ajax({
        url: mpesa_cf_frontend.ajax_url,
        type: 'POST',
        data: {
            action: 'mpesa_cf_get_campaign',
            nonce: mpesa_cf_frontend.nonce,
            campaign_id: campaignId
        },
        success: function(response) {
            if (response.success) {
                populateDonationModal(response.data.campaign);
                $('#mpesa-cf-donation-modal').addClass('active');
                $('body').addClass('modal-open');
            } else {
                alert('Error loading campaign details.');
            }
        },
        error: function() {
            alert('Error loading campaign details.');
        }
    });
};

window.closeDonationModal = function() {
    $('#mpesa-cf-donation-modal').removeClass('active');
    $('body').removeClass('modal-open');
    
    // Reset modal to first step
    setTimeout(function() {
        showDonationStep(1);
        resetDonationForm();
    }, 300);
};

function populateDonationModal(campaign) {
    // Populate campaign preview
    $('#modal-campaign-title').text(campaign.title);
    $('#modal-campaign-category').text(campaign.category);
    $('#modal-current-amount').text(mpesa_cf_frontend.currency + ' ' + formatNumber(campaign.current_amount));
    $('#modal-target-amount').text(mpesa_cf_frontend.currency + ' ' + formatNumber(campaign.target_amount));
    $('#modal-progress-fill').css('width', campaign.progress_percentage + '%');
    $('#campaign-id').val(campaign.id);
    
    // Handle campaign image
    if (campaign.image_url) {
        $('#modal-campaign-image').html('<img src="' + campaign.image_url + '" alt="' + campaign.title + '">');
    } else {
        $('#modal-campaign-image').html('<div class="no-image">No Image</div>');
    }
    
    // Update modal title
    $('#donation-modal-title').text('Donate to ' + campaign.title);
}

function resetDonationForm() {
    $('#donation-form')[0].reset();
    $('.amount-btn').removeClass('selected');
    $('.form-group input, .form-group textarea').removeClass('error');
    $('#payment-status').html('<div class="status-pending"><div class="spinner"></div><span>Waiting for payment confirmation...</span></div>');
}

// Add CSS for modal-open body
$('<style>').prop('type', 'text/css').html(`
    body.modal-open {
        overflow: hidden;
    }
    .form-group input.error,
    .form-group textarea.error {
        border-color: #f56565 !important;
    }
    .status-success {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .status-failed {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .status-timeout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-align: center;
    }
    .no-image {
        width: 80px;
        height: 80px;
        background: #f7fafc;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #a0aec0;
        font-size: 0.75rem;
    }
`).appendTo('head');
});