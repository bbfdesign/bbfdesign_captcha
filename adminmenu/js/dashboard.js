/**
 * BBF Captcha & Spam-Schutz – Dashboard Charts & KPI Updates
 * Requires Chart.js (loaded inline from vendor/)
 */
;(function() {
    'use strict';

    // Dashboard state is managed inline in dashboard.tpl
    // This file provides additional utility functions

    /**
     * Refresh dashboard data via AJAX
     */
    function refreshDashboard() {
        if (typeof bbfAdmin === 'undefined' || typeof bbfDashboard === 'undefined') return;

        bbfAdmin.post('getDashboardData', { days: bbfDashboard.currentRange || 30 }).then(function(resp) {
            if (!resp.success || !resp.data) return;

            var data = resp.data;

            // Update KPI cards
            var el;
            el = document.getElementById('bbf-kpi-blocked-today');
            if (el) el.textContent = bbfAdmin.formatNumber(data.blocked_today);

            el = document.getElementById('bbf-kpi-blocked-total');
            if (el) el.textContent = bbfAdmin.formatNumber(data.blocked_total);

            el = document.getElementById('bbf-kpi-detection-rate');
            if (el) el.textContent = data.detection_rate + '%';

            el = document.getElementById('bbf-kpi-active-methods');
            if (el) el.textContent = data.active_methods;
        });
    }

    // Auto-refresh every 60 seconds when dashboard is visible
    var refreshInterval = null;

    function startAutoRefresh() {
        if (refreshInterval) return;
        refreshInterval = setInterval(refreshDashboard, 60000);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    // Expose
    window.bbfDashboardUtils = {
        refresh: refreshDashboard,
        startAutoRefresh: startAutoRefresh,
        stopAutoRefresh: stopAutoRefresh
    };
})();
