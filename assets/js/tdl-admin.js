/**
 * Taylor Distributor Locator - Admin JavaScript
 */
(function ($) {
    'use strict';

    // Location index counter
    let locationIndex = 100;

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        initLocationRepeater();
        initEmailRepeater();
        initServiceZoneTabs();
        initSelectAll();
        initPrimaryLocation();
        initGeocode();
        initSettingsToggles();
    });

    /**
     * Settings page conditional toggles
     */
    function initSettingsToggles() {
        const $providerSelect = $('select[name="tdl_map_provider"]');
        if (!$providerSelect.length) return;

        const toggleFields = function () {
            const provider = $providerSelect.val();
            // Target the row directly if class is on TR, or search for inputs with that class if on input
            const $googleRows = $('.tdl-google-field');

            if (provider === 'google') {
                $googleRows.show();
            } else {
                $googleRows.hide();
            }
        };

        $providerSelect.on('change', toggleFields);
        toggleFields(); // Run on load
    }

    /**
     * Location repeater functionality
     */
    function initLocationRepeater() {
        const $wrapper = $('#tdl-locations-wrapper');
        const $addBtn = $('#tdl-add-location');

        if (!$wrapper.length) return;

        // Get current max index
        $wrapper.find('.tdl-location-card').each(function () {
            const idx = parseInt($(this).data('index'), 10);
            if (idx >= locationIndex) {
                locationIndex = idx + 1;
            }
        });

        // Add location
        $addBtn.on('click', function () {
            const template = wp.template('tdl-location');
            const html = template({ index: locationIndex++ });
            $wrapper.find('.tdl-no-locations').remove();
            $wrapper.append(html);
        });

        // Remove location
        $wrapper.on('click', '.tdl-remove-location', function () {
            if (confirm(tdlAdmin.i18n.confirmRemove)) {
                $(this).closest('.tdl-location-card').remove();
            }
        });

        // Update title when location name changes
        $wrapper.on('keyup', 'input[name*="[location_name]"]', function () {
            const $card = $(this).closest('.tdl-location-card');
            const name = $(this).val() || 'Location';
            $card.find('.tdl-location-title').text(name);
        });
    }

    /**
     * Email repeater functionality
     */
    function initEmailRepeater() {
        const $container = $('#tdl-other-emails');
        const $addBtn = $('#tdl-add-email');

        if (!$container.length) return;

        $addBtn.on('click', function () {
            const $row = $('<div class="tdl-email-row">' +
                '<input type="text" name="_tdl_email_other_label[]" value="" placeholder="Label" />' +
                '<input type="email" name="_tdl_email_other_email[]" value="" placeholder="Email" />' +
                '<button type="button" class="button tdl-remove-email">&times;</button>' +
                '</div>');
            $container.append($row);
        });

        $container.on('click', '.tdl-remove-email', function () {
            $(this).closest('.tdl-email-row').remove();
        });
    }

    /**
     * Service zone tabs
     */
    function initServiceZoneTabs() {
        const $tabs = $('.tdl-zone-tab');
        const $panels = $('.tdl-zone-panel');

        if (!$tabs.length) return;

        $tabs.on('click', function () {
            const tab = $(this).data('tab');

            $tabs.removeClass('active');
            $(this).addClass('active');

            $panels.removeClass('active');
            $panels.filter('[data-panel="' + tab + '"]').addClass('active');
        });
    }

    /**
     * Select all checkboxes functionality
     */
    function initSelectAll() {
        $('.tdl-select-all').on('click', function (e) {
            e.preventDefault();
            const target = $(this).data('target');
            const $checkboxes = $('input[name="tdl_states_' + target + '[]"]');
            const allChecked = $checkboxes.filter(':checked').length === $checkboxes.length;
            $checkboxes.prop('checked', !allChecked);
        });
    }

    /**
     * Primary location radio handling
     */
    function initPrimaryLocation() {
        const $wrapper = $('#tdl-locations-wrapper');

        $wrapper.on('change', 'input[name="tdl_location_primary"]', function () {
            // Update all hidden is_primary fields
            $wrapper.find('.tdl-is-primary').val('0');
            $(this).closest('.tdl-location-card').find('.tdl-is-primary').val('1');
        });
    }

    /**
     * Geocode button functionality
     */
    function initGeocode() {
        const $wrapper = $('#tdl-locations-wrapper');

        $wrapper.on('click', '.tdl-geocode-btn', function () {
            const $btn = $(this);
            const $card = $btn.closest('.tdl-location-card');
            const $status = $card.find('.tdl-geocode-status');
            const $lat = $card.find('.tdl-lat');
            const $lng = $card.find('.tdl-lng');

            const street = $card.find('input[name*="[street_address]"]').val();
            const city = $card.find('input[name*="[city]"]').val();
            const state = $card.find('input[name*="[state_province]"]').val();
            const zip = $card.find('input[name*="[zip_postal]"]').val();
            const country = $card.find('select[name*="[country_code]"]').val();

            // Build address from fields
            const address = [street, city, state, zip, country].filter(Boolean).join(', ');

            if (!address) {
                $status.text('Enter an address first').addClass('error');
                return;
            }

            $btn.prop('disabled', true);
            $status.text(tdlAdmin.i18n.geocoding).removeClass('error success');

            // Use our REST API for geocoding (provider-agnostic)
            $.ajax({
                url: tdlAdmin.restUrl + 'geocode',
                method: 'POST',
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', tdlAdmin.nonce);
                },
                data: {
                    address: address,
                    street: street,
                    city: city,
                    state: state,
                    zip: zip,
                    country: country
                },
                success: function (response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $lat.val(response.latitude);
                        $lng.val(response.longitude);
                        $status.text(tdlAdmin.i18n.geocodeSuccess).addClass('success');
                    } else {
                        $status.text(response.message || tdlAdmin.i18n.geocodeError).addClass('error');
                    }
                },
                error: function (xhr) {
                    $btn.prop('disabled', false);
                    const response = xhr.responseJSON;
                    $status.text((response && response.message) ? response.message : tdlAdmin.i18n.geocodeError).addClass('error');
                }
            });
        });
    }

})(jQuery);
