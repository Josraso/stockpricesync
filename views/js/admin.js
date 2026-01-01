/**
 * Stock & Price Synchronizer - Admin JavaScript
 *
 * This file contains common JavaScript functionality for the admin panel
 */

$(document).ready(function() {
    // Additional JavaScript functionality can be added here as needed

    // Example: Add tooltip functionality if needed
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }

    // Example: Confirm dangerous actions
    $('.confirm-action').on('click', function(e) {
        if (!confirm($(this).data('confirm-message') || 'Are you sure?')) {
            e.preventDefault();
            return false;
        }
    });
});
