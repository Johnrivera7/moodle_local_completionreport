/**
 * Client-side search for completion report table.
 *
 * @module     local_completionreport/report
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /**
     * Init search filter.
     */
    function init() {
        const input = document.querySelector('[data-lcr-search]');
        const table = document.getElementById('lcr-table');
        if (!input || !table) {
            return;
        }

        const rows = table.querySelectorAll('tbody tr');
        input.addEventListener('input', function() {
            const q = input.value.trim().toLowerCase();
            rows.forEach(function(row) {
                const hay = (row.getAttribute('data-search') || '').toLowerCase();
                row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    return {init: init};
});
