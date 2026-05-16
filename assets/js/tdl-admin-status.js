jQuery(document).ready(function ($) {

    var pollingInterval = null;
    var failCount = 0;

    // Initialize charts
    $('.tdl-stat-circle').each(function () {
        var percent = $(this).data('percent');
        $(this).css('--percent', percent + '%');
    });

    // Check status on load if we think it's running
    if ($('#tdl-system-status').text().trim() === 'Running') {
        startPolling();
    }

    // Run Geocoding (normal or force)
    function triggerGeocoding(force) {
        var $btn = $('#tdl-run-geocoding');
        var $forceBtn = $('#tdl-force-geocoding');
        var $spinner = $btn.nextAll('.spinner').first();
        var $cancelBtn = $('#tdl-stop-geocoding');

        $btn.prop('disabled', true).text(tdlGeoStatus.strings.running);
        $forceBtn.prop('disabled', true);
        $spinner.addClass('is-active');

        $.ajax({
            url: tdlGeoStatus.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tdl_trigger_geocoding',
                nonce: tdlGeoStatus.nonce,
                force: force ? 1 : 0
            },
            success: function (response) {
                if (response.success) {
                    $cancelBtn.show();
                    startPolling();
                } else {
                    alert(response.data.message || tdlGeoStatus.strings.error);
                    resetUI();
                }
            },
            error: function () {
                alert(tdlGeoStatus.strings.error);
                resetUI();
            }
        });
    }

    $('#tdl-run-geocoding').on('click', function (e) {
        e.preventDefault();
        triggerGeocoding(false);
    });

    // Re-geocode All (force mode)
    $('#tdl-force-geocoding').on('click', function (e) {
        e.preventDefault();
        if (!confirm('This will retry ALL locations including previously errored ones. Continue?')) {
            return;
        }
        triggerGeocoding(true);
    });

    // Stop Geocoding
    $('#tdl-stop-geocoding').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).text(tdlGeoStatus.strings.stopping);

        $.ajax({
            url: tdlGeoStatus.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tdl_stop_geocoding',
                nonce: tdlGeoStatus.nonce
            },
            success: function (response) {
                // We don't stop polling immediately, we wait for the status to change to 'cancelled'
            }
        });
    });

    // Clear Errors
    $('#tdl-clear-errors').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to clear the error log?')) {
            return;
        }

        $.ajax({
            url: tdlGeoStatus.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tdl_clear_geo_errors',
                nonce: tdlGeoStatus.nonce
            },
            success: function (response) {
                if (response.success) {
                    location.reload();
                }
            }
        });
    });

    function startPolling() {
        if (pollingInterval) return;

        // Poll every 2 seconds
        pollingInterval = setInterval(checkStatus, 2000);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    function checkStatus() {
        $.ajax({
            url: tdlGeoStatus.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tdl_get_geocoding_status',
                nonce: tdlGeoStatus.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateUI(response.data);
                } else {
                    failCount++;
                }
            },
            error: function () {
                failCount++;
            }
        });

        // If too many failures, stop polling
        if (failCount > 5) {
            stopPolling();
            alert('Lost connection to server. Please refresh the page.');
        }
    }

    function updateUI(data) {
        // Update numbers
        $('#tdl-total-count').text(data.total);
        $('#tdl-pending-count').text(data.pending);
        $('#tdl-errored-count').text(data.errored);

        // Update circle and bar
        $('.tdl-stat-circle').css('--percent', data.percent + '%').find('.tdl-percent').text(data.percent + '%');
        $('.tdl-progress-fill').css('width', data.percent + '%');

        // Update status text
        var statusLabel = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        if (data.status === 'stalled') {
            statusLabel = 'Stalled (Timeout)';
            $('#tdl-system-status').css('color', '#d63638');
        } else {
            $('#tdl-system-status').css('color', '');
        }
        $('#tdl-system-status').text(statusLabel);
        $('#tdl-last-activity').text(data.last_activity_formatted);

        // Update Errors Table
        if (data.errors && data.errors.length > 0) {
            var $tbody = $('.tdl-error-table tbody');
            if ($tbody.length === 0) {
                // Remove empty state and create table if it doesn't exist (or just reload page for simplicity?
                // Creating table dynamically is better UX but requires more DOM manip.
                // For now, let's just reload if we see errors but have no table)
                if ($('.tdl-empty-state').length > 0) {
                    location.reload();
                    return;
                }
            } else {
                $tbody.empty();
                data.errors.forEach(function (err) {
                    var row = '<tr>' +
                        '<td>' + err.time_formatted + '</td>' +
                        '<td>' + err.location_id + '</td>' +
                        '<td>' + err.address + '</td>' +
                        '<td class="tdl-error-msg">' + err.error + '</td>' +
                        '</tr>';
                    $tbody.append(row);
                });
            }
        }

        // Handle states
        if (data.status === 'running') {
            $('#tdl-run-geocoding').prop('disabled', true).text(tdlGeoStatus.strings.running);
            $('#tdl-force-geocoding').prop('disabled', true);
            $('#tdl-stop-geocoding').show().prop('disabled', false).text('Cancel');
            $('.spinner').addClass('is-active');
        } else {
            // Stopped, Completed, Cancelled, Stalled
            stopPolling();
            resetUI(data.pending, data.errored);

            if (data.status === 'completed') {
                $('#tdl-system-status').text(tdlGeoStatus.strings.complete);
            } else if (data.status === 'stalled') {
                // Allow restarting
                $('#tdl-run-geocoding').text('Resume Geocoding');
            }
        }
    }

    function resetUI(pendingCount, erroredCount) {
        var $runBtn = $('#tdl-run-geocoding');
        var $forceBtn = $('#tdl-force-geocoding');
        var $stopBtn = $('#tdl-stop-geocoding');
        var $spinner = $('.spinner');

        $spinner.removeClass('is-active');
        $stopBtn.hide().text('Cancel').prop('disabled', false);

        var hasPending = pendingCount !== 0 && pendingCount !== '0';
        var hasErrored = erroredCount !== 0 && erroredCount !== '0' && typeof erroredCount !== 'undefined';

        if (!hasPending && !hasErrored) {
            $runBtn.prop('disabled', true).text(tdlGeoStatus.strings.complete);
        } else {
            $runBtn.prop('disabled', false).text('Run Geocoding Now');
        }

        // Show/hide force button based on errored count
        if (hasErrored) {
            if ($forceBtn.length === 0) {
                // Create force button if it doesn't exist yet
                $runBtn.after(' <button id="tdl-force-geocoding" class="button button-secondary button-large">Re-geocode All (incl. errors)</button>');
            }
            $forceBtn.prop('disabled', false);
        } else {
            $forceBtn.hide();
        }
    }

});
