<h2 class="bbf-page-title">{$langVars->getTranslation('nav_dashboard', $adminLang)|default:'Dashboard'|escape:'html'}</h2>

{* ── KPI Cards ── *}
<div class="bbf-stats-grid">
    <div class="bbf-stat-card">
        <div class="bbf-stat-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="bbf-stat-label">{$langVars->getTranslation('blocked_today', $adminLang)|default:'Blocked today'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-blocked-today">{$dashboardData.blocked_today|default:0}</div>
    </div>
    <div class="bbf-stat-card">
        <div class="bbf-stat-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="bbf-stat-label">{$langVars->getTranslation('blocked_total', $adminLang)|default:'Blocked total'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-blocked-total">{$dashboardData.blocked_total|default:0}</div>
    </div>
    <div class="bbf-stat-card">
        <div class="bbf-stat-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <div class="bbf-stat-label">{$langVars->getTranslation('detection_rate', $adminLang)|default:'Detection rate'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-detection-rate">{$dashboardData.detection_rate|default:0}%</div>
    </div>
    <div class="bbf-stat-card">
        <div class="bbf-stat-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div class="bbf-stat-label">{$langVars->getTranslation('active_methods', $adminLang)|default:'Active methods'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-active-methods">{$dashboardData.active_methods|default:0}</div>
    </div>
</div>

{* ── Trend-Anzeige ── *}
{if isset($dashboardData.trend)}
<div class="bbf-card" style="margin-bottom: var(--bbf-spacing-lg); padding: 16px 24px;">
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
        <span style="font-size: 13px; color: var(--bbf-muted);">7-Tage-Trend:</span>
        <span style="font-weight: 600;">
            {$dashboardData.trend.current_week|default:0} Blocks diese Woche
        </span>
        {if isset($dashboardData.trend.change_percent)}
            {if $dashboardData.trend.direction === 'up'}
                <span class="bbf-badge bbf-badge-danger">
                    &#9650; {$dashboardData.trend.change_percent}%
                </span>
            {elseif $dashboardData.trend.direction === 'down'}
                <span class="bbf-badge bbf-badge-success">
                    &#9660; {$dashboardData.trend.change_percent}%
                </span>
            {else}
                <span class="bbf-badge bbf-badge-info">&#8212; stabil</span>
            {/if}
        {/if}
        <span style="font-size: 12px; color: var(--bbf-muted);">
            vs. {$dashboardData.trend.previous_week|default:0} letzte Woche
        </span>
    </div>
</div>
{/if}

{* ── Charts ── *}
<div class="bbf-charts-row">
    {* Spam-Verlauf *}
    <div class="bbf-card">
        <div class="bbf-card-header">
            <h3 class="bbf-card-title">{$langVars->getTranslation('spam_history', $adminLang)|default:'Spam history'|escape:'html'}</h3>
            <div class="bbf-range-selector" role="group" aria-label="Range">
                <button type="button" class="bbf-range-btn" onclick="bbfDashboard.setRange(7, event)">7</button>
                <button type="button" class="bbf-range-btn active" onclick="bbfDashboard.setRange(30, event)" aria-pressed="true">30</button>
                <button type="button" class="bbf-range-btn" onclick="bbfDashboard.setRange(90, event)">90</button>
            </div>
        </div>
        <div class="bbf-chart-container" style="height: 280px;">
            <canvas id="bbf-chart-history" aria-label="{$langVars->getTranslation('spam_history', $adminLang)|default:'Spam history'|escape:'html'}" role="img"></canvas>
        </div>
    </div>

    {* Verteilung nach Methode *}
    <div class="bbf-card">
        <div class="bbf-card-header">
            <h3 class="bbf-card-title">{$langVars->getTranslation('distribution_by_method', $adminLang)|default:'Distribution by method'|escape:'html'}</h3>
        </div>
        <div class="bbf-chart-container" style="height: 280px;">
            <canvas id="bbf-chart-methods" aria-label="{$langVars->getTranslation('distribution_by_method', $adminLang)|default:'Distribution by method'|escape:'html'}" role="img"></canvas>
        </div>
    </div>

    {* Top-Formulare *}
    <div class="bbf-card">
        <div class="bbf-card-header">
            <h3 class="bbf-card-title">{$langVars->getTranslation('top_forms', $adminLang)|default:'Top forms'|escape:'html'}</h3>
        </div>
        <div class="bbf-chart-container" style="height: 280px;">
            <canvas id="bbf-chart-forms" aria-label="{$langVars->getTranslation('top_forms', $adminLang)|default:'Top forms'|escape:'html'}" role="img"></canvas>
        </div>
    </div>
</div>

{* ── Letzte Spam-Versuche ── *}
<div class="bbf-card">
    <div class="bbf-card-header">
        <h3 class="bbf-card-title">{$langVars->getTranslation('recent_spam_attempts', $adminLang)|default:'Recent spam attempts'|escape:'html'}</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="bbf-table" id="bbf-recent-spam-table">
            <thead>
                <tr>
                    <th scope="col">{$langVars->getTranslation('timestamp', $adminLang)|default:'Timestamp'|escape:'html'}</th>
                    <th scope="col">{$langVars->getTranslation('ip_address', $adminLang)|default:'IP'|escape:'html'}</th>
                    <th scope="col">{$langVars->getTranslation('form', $adminLang)|default:'Form'|escape:'html'}</th>
                    <th scope="col">{$langVars->getTranslation('method', $adminLang)|default:'Method'|escape:'html'}</th>
                    <th scope="col">{$langVars->getTranslation('score', $adminLang)|default:'Score'|escape:'html'}</th>
                    <th scope="col">{$langVars->getTranslation('action', $adminLang)|default:'Action'|escape:'html'}</th>
                </tr>
            </thead>
            <tbody>
                {literal}
                <template x-if="false"><!-- Placeholder for server-rendered rows --></template>
                {/literal}
                {if isset($dashboardData.recent_spam) && $dashboardData.recent_spam|@count > 0}
                    {foreach $dashboardData.recent_spam as $entry}
                    <tr>
                        <td>{$entry->created_at|escape:'html'}</td>
                        <td><code>{$entry->ip_address|escape:'html'}</code></td>
                        <td>{$entry->form_type|escape:'html'}</td>
                        <td><span class="bbf-badge bbf-badge-info">{$entry->detection_method|escape:'html'}</span></td>
                        <td>{$entry->spam_score|escape:'html'}</td>
                        <td>
                            {if $entry->action_taken === 'blocked'}
                                <span class="bbf-badge bbf-badge-danger">{$langVars->getTranslation('blocked', $adminLang)|default:'Blocked'|escape:'html'}</span>
                            {elseif $entry->action_taken === 'logged'}
                                <span class="bbf-badge bbf-badge-warning">{$langVars->getTranslation('logged', $adminLang)|default:'Logged'|escape:'html'}</span>
                            {else}
                                <span class="bbf-badge bbf-badge-success">{$langVars->getTranslation('allowed', $adminLang)|default:'Allowed'|escape:'html'}</span>
                            {/if}
                        </td>
                    </tr>
                    {/foreach}
                {else}
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 32px; color: var(--bbf-text-light);">
                            Noch keine Spam-Versuche erkannt. Das System ist aktiv und sch&uuml;tzt Ihren Shop.
                        </td>
                    </tr>
                {/if}
            </tbody>
        </table>
    </div>
</div>

{* ── Top geblockte IPs ── *}
{if isset($dashboardData.top_ips) && $dashboardData.top_ips|@count > 0}
<div class="bbf-card">
    <div class="bbf-card-header">
        <h3 class="bbf-card-title">Top geblockte IPs (30 Tage)</h3>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: var(--bbf-spacing-sm);">
        {foreach $dashboardData.top_ips as $ipRow}
        <div style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: var(--bbf-row-stripe); border-radius: var(--bbf-radius-md); border: 1px solid var(--bbf-border-light);">
            <code style="font-size: 13px;">{$ipRow->ip_address|escape:'html'}</code>
            <span class="bbf-badge bbf-badge-danger">{$ipRow->cnt|escape:'html'}</span>
        </div>
        {/foreach}
    </div>
</div>
{/if}

{* ── Chart.js + Dashboard JS ── *}
<script>
var bbfDashboardData = {$dashboardDataJson nofilter};
</script>
<script>
{literal}
(function() {
    'use strict';

    var bbfDashboard = {
        historyChart: null,
        methodsChart: null,
        formsChart: null,
        currentRange: 30,

        setRange: function(days, evt) {
            this.currentRange = days;
            document.querySelectorAll('.bbf-range-btn').forEach(function(btn) {
                btn.classList.remove('active');
                btn.setAttribute('aria-pressed', 'false');
            });
            var target = (evt && evt.target) ? evt.target : null;
            if (target) {
                target.classList.add('active');
                target.setAttribute('aria-pressed', 'true');
            }
            this.loadDashboardData(days);
        },

        loadDashboardData: function(days) {
            bbfAdmin.post('getDashboardData', { days: days }).then(function(resp) {
                if (resp.success && resp.data) {
                    bbfDashboard.updateCharts(resp.data);
                }
            });
        },

        updateCharts: function(data) {
            // Wird in Phase 6 mit echten Chart-Updates implementiert
        }
    };

    window.bbfDashboard = bbfDashboard;

    // Chart.js wird lokal geladen (kein CDN, kein Consent-Problem im Admin)
    if (typeof Chart === 'undefined') {
        var script = document.createElement('script');
        script.src = adminUrl + 'js/vendor/chart.umd.min.js';
        script.onload = function() { initDashboardCharts(); };
        document.head.appendChild(script);
    } else {
        initDashboardCharts();
    }

    function initDashboardCharts() {
        if (typeof Chart === 'undefined') return;

        var data = window.bbfDashboardData || {};
        var primaryColor = '#2563eb';
        var primaryLight = 'rgba(37, 99, 235, 0.1)';
        var methodColors = {
            'honeypot': '#2563eb',
            'timing': '#16a34a',
            'altcha': '#f59e0b',
            'ai': '#8b5cf6',
            'ai_filter': '#8b5cf6',
            'ip': '#dc2626',
            'rate': '#ec4899',
            'recaptcha': '#06b6d4',
            'turnstile': '#f97316',
            'hcaptcha': '#84cc16',
            'bot': '#6366f1'
        };
        var L = window.bbfLang || {};
        var methodLabels = {
            'honeypot':  L.method_honeypot  || 'Honeypot',
            'timing':    L.method_timing    || 'Timing',
            'altcha':    L.method_altcha    || 'ALTCHA',
            'ai':        L.method_ai        || 'AI filter',
            'ai_filter': L.method_ai        || 'AI filter',
            'ip':        'IP',
            'rate':      'Rate limit',
            'recaptcha': 'reCAPTCHA',
            'turnstile': 'Turnstile',
            'hcaptcha':  'hCaptcha',
            'bot':       'Bot detection'
        };
        var formLabels = {
            'contact':        L.form_contact        || 'Contact',
            'registration':   L.form_registration   || 'Registration',
            'newsletter':     L.form_newsletter     || 'Newsletter',
            'review':         L.form_review         || 'Reviews',
            'checkout':       L.form_checkout       || 'Checkout',
            'login':          L.form_login          || 'Login',
            'password_reset': L.form_password_reset || 'Password',
            'wishlist':       L.form_wishlist       || 'Wishlist'
        };

        // Build history data from server data
        var historyLabels = getDatesForRange(30);
        var historyData = generateEmptyData(30);
        var dateLocale = (window.adminLang === 'eng') ? 'en-GB' : 'de-DE';
        if (data.spam_history && data.spam_history.length > 0) {
            var dateMap = {};
            data.spam_history.forEach(function(row) {
                var dateKey = new Date(row.date).toLocaleDateString(dateLocale, { day: '2-digit', month: '2-digit' });
                dateMap[dateKey] = (dateMap[dateKey] || 0) + parseInt(row.cnt);
            });
            historyData = historyLabels.map(function(label) {
                return dateMap[label] || 0;
            });
        }

        // Spam History Chart
        var historyCtx = document.getElementById('bbf-chart-history');
        if (historyCtx) {
            bbfDashboard.historyChart = new Chart(historyCtx, {
                type: 'line',
                data: {
                    labels: historyLabels,
                    datasets: [{
                        label: 'Geblockt',
                        data: historyData,
                        borderColor: primaryColor,
                        backgroundColor: primaryLight,
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 }, maxTicksLimit: 8 }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        }

        // Methods Donut Chart – from server data
        var mLabels = [];
        var mData = [];
        var mColors = [];
        if (data.method_distribution && data.method_distribution.length > 0) {
            data.method_distribution.forEach(function(row) {
                mLabels.push(methodLabels[row.detection_method] || row.detection_method);
                mData.push(parseInt(row.cnt));
                mColors.push(methodColors[row.detection_method] || '#94a3b8');
            });
        } else {
            mLabels = ['Keine Daten'];
            mData = [1];
            mColors = ['#e5e7eb'];
        }

        var methodsCtx = document.getElementById('bbf-chart-methods');
        if (methodsCtx) {
            bbfDashboard.methodsChart = new Chart(methodsCtx, {
                type: 'doughnut',
                data: {
                    labels: mLabels,
                    datasets: [{
                        data: mData,
                        backgroundColor: mColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 11 }, padding: 12, usePointStyle: true }
                        }
                    }
                }
            });
        }

        // Top Forms Bar Chart – from server data
        var fLabels = [];
        var fData = [];
        if (data.top_forms && data.top_forms.length > 0) {
            data.top_forms.forEach(function(row) {
                fLabels.push(formLabels[row.form_type] || row.form_type);
                fData.push(parseInt(row.cnt));
            });
        } else {
            fLabels = ['Keine Daten'];
            fData = [0];
        }

        var formsCtx = document.getElementById('bbf-chart-forms');
        if (formsCtx) {
            bbfDashboard.formsChart = new Chart(formsCtx, {
                type: 'bar',
                data: {
                    labels: fLabels,
                    datasets: [{
                        data: fData,
                        backgroundColor: primaryLight,
                        borderColor: primaryColor,
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { font: { size: 11 } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        }
    }

    function getDatesForRange(days) {
        var dates = [];
        var loc = (window.adminLang === 'eng') ? 'en-GB' : 'de-DE';
        for (var i = days - 1; i >= 0; i--) {
            var d = new Date();
            d.setDate(d.getDate() - i);
            dates.push(d.toLocaleDateString(loc, { day: '2-digit', month: '2-digit' }));
        }
        return dates;
    }

    function generateEmptyData(count) {
        var data = [];
        for (var i = 0; i < count; i++) {
            data.push(0);
        }
        return data;
    }
})();
{/literal}
</script>
