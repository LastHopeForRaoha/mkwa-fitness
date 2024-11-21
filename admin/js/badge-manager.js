// admin/js/badge-manager.js

jQuery(document).ready(function($) {
    $('#mkwa-generate-badge').on('click', function() {
        var data = {
            action: 'mkwa_generate_badge',
            type: $('#mkwa-badge-type').val(),
            tier: $('#mkwa-badge-tier').val(),
            text: $('#mkwa-badge-text').val(),
            nonce: mkwaBadgeManager.nonce
        };

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#badge-preview-container').html(response.data.svg);
            }
        });
    });
});