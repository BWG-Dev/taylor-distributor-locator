jQuery(document).ready(function ($) {
    $('.tdl-geocode-now').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var postId = button.data('id');
        var spinner = $('.tdl-spinner-' + postId);

        if (button.prop('disabled')) {
            return;
        }

        button.prop('disabled', true);
        spinner.addClass('is-active');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tdl_geocode_distributor',
                post_id: postId,
                nonce: tdlAdminList.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Reload to show updated status
                    window.location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    button.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            },
            error: function () {
                alert('Request failed');
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
});
