/**
 * Taylor Distributor Locator - CSV Import JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initCsvImport();
    });

    function initCsvImport() {
        const $form = $('#tdl-csv-import-form');
        const $progress = $('#tdl-import-progress');
        const $progressFill = $('.tdl-progress-fill');
        const $progressText = $('.tdl-progress-text');
        const $result = $('#tdl-import-result');
        const $btn = $('#tdl-import-btn');

        if (!$form.length) return;

        $form.on('submit', function (e) {
            e.preventDefault();

            const file = $('#csv_file')[0].files[0];
            if (!file) {
                showResult('Please select a file', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'tdl_import_csv');
            formData.append('nonce', tdlCsvImport.nonce);
            formData.append('csv_file', file);

            $btn.prop('disabled', true);
            $progress.show();
            $result.hide();
            $progressFill.css('width', '0%');
            $progressText.text(tdlCsvImport.i18n.uploading);

            $.ajax({
                url: tdlCsvImport.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function (evt) {
                        if (evt.lengthComputable) {
                            const percent = Math.round((evt.loaded / evt.total) * 50);
                            $progressFill.css('width', percent + '%');
                            $progressText.text(tdlCsvImport.i18n.uploading + ' ' + percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function (response) {
                    $progressFill.css('width', '100%');
                    if (response.success) {
                        showResult(response.data.message, 'success');
                    } else {
                        showResult(response.data?.message || tdlCsvImport.i18n.error, 'error');
                    }
                },
                error: function () {
                    showResult(tdlCsvImport.i18n.error, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false);
                    setTimeout(function () {
                        $progress.fadeOut();
                    }, 1000);
                }
            });
        });

        function showResult(message, type) {
            $result.text(message)
                .removeClass('success error')
                .addClass(type)
                .show();
        }
    }

})(jQuery);
