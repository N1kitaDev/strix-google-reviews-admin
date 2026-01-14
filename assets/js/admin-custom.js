/**
 * Strix Google Reviews Admin Panel - Custom JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize modal system
        window.Frame = {
            getSystemModal: function() {
                return $('#modal-sys');
            }
        };

        // Bootstrap notify wrapper
        $.notify = function(message, options) {
            options = options || {};
            options.message = message;

            if (typeof $.fn.notify === 'function') {
                $('body').notify(options);
            } else {
                alert(message);
            }
        };

        // Loading button functionality
        $('.btn-loading').on('click', function() {
            $(this).addClass('btn-loading-animation disabled').prop('disabled', true);
        });

        // Reset loading state
        window.Frame.resetLoadingLink = function(selector) {
            $(selector).removeClass('btn-loading-animation disabled').prop('disabled', false);
        };

        // Handle form validation
        $('#form-source').on('submit', function(e) {
            e.preventDefault();
            return false;
        });

        // Add loading state management
        $(document).on('ajaxStart', function() {
            $('.source-input-container').addClass('is-loading');
        });

        $(document).on('ajaxStop', function() {
            $('.source-input-container').removeClass('is-loading');
        });
    });

})(jQuery);