<h2 class="bbf-page-title">{$langVars->getTranslation('nav_dashboard', $adminLang)|default:'Dashboard'|escape:'html'}</h2>

{* ── KPI Cards (ALTCHA-Stil: farbiger Akzentbalken je Kennzahl) ── *}
<div class="bbf-stats-grid">
    <div class="bbf-stat-card bbf-stat-accent-blue">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('kpi_total_checks', $adminLang)|default:'Detected total'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-total">{$dashboardData.total_entries|default:0}</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-red">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('blocked_total', $adminLang)|default:'Blocked total'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-blocked-total">{$dashboardData.blocked_total|default:0}</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-amber">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('kpi_logged', $adminLang)|default:'Logged'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-logged-total">{$dashboardData.logged_total|default:0}</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-pink">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('blocked_today', $adminLang)|default:'Blocked today'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-blocked-today">{$dashboardData.blocked_today|default:0}</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-green">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('detection_rate', $adminLang)|default:'Detection rate'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-detection-rate">{$dashboardData.detection_rate|default:0}%</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-purple">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('kpi_avg_score', $adminLang)|default:'Avg. spam score'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-avg-score">{$dashboardData.avg_score|default:0}</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-indigo">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
        <div class="bbf-stat-label">{$langVars->getTranslation('kpi_unique_ips', $adminLang)|default:'Blocked IPs'|escape:'html'}</div>
        <div class="bbf-stat-value" id="bbf-kpi-unique-ips">{$dashboardData.unique_ips|default:0}</div>
    </div>
    <div class="bbf-stat-card bbf-stat-accent-teal">
        <span class="bbf-stat-bar" aria-hidden="true"></span>
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

{* ── Aktivität nach Tageszeit + Bedrohungen (ALTCHA-Stil) ── *}
<div class="bbf-dash-split">
    <div class="bbf-card">
        <div class="bbf-card-header">
            <div>
                <h3 class="bbf-card-title">{$langVars->getTranslation('hourly_title', $adminLang)|default:'Activity by hour'|escape:'html'}</h3>
                <div style="font-size: 12px; color: var(--bbf-muted); margin-top: 2px;">{$langVars->getTranslation('hourly_subtitle', $adminLang)|default:'When bots are most active (0&ndash;23h)'|escape:'html'}</div>
            </div>
        </div>
        <div class="bbf-chart-container" style="height: 260px;">
            <canvas id="bbf-chart-hourly" aria-label="{$langVars->getTranslation('hourly_title', $adminLang)|default:'Activity by hour'|escape:'html'}" role="img"></canvas>
        </div>
    </div>

    <div class="bbf-card">
        <div class="bbf-card-header">
            <div>
                <h3 class="bbf-card-title">{$langVars->getTranslation('threats_title', $adminLang)|default:'Threats'|escape:'html'}</h3>
                <div style="font-size: 12px; color: var(--bbf-muted); margin-top: 2px;">{$langVars->getTranslation('threats_subtitle', $adminLang)|default:'Most active IPs in range'|escape:'html'}</div>
            </div>
        </div>
        <div class="bbf-threats-list" id="bbf-threats-list">
            {if isset($dashboardData.top_ips) && $dashboardData.top_ips|@count > 0}
                {foreach $dashboardData.top_ips as $ipRow}
                <div class="bbf-threat-row">
                    <span class="bbf-threat-ip">{$ipRow->ip_address|escape:'html'}</span>
                    <span class="bbf-threat-count bbf-badge bbf-badge-danger">{$ipRow->cnt|escape:'html'}&times;</span>
                    <span class="bbf-threat-time" data-ts="{$ipRow->last_seen|escape:'html'}">{$ipRow->last_seen|escape:'html'}</span>
                </div>
                {/foreach}
            {else}
                <div class="bbf-threat-empty">{$langVars->getTranslation('no_threats', $adminLang)|default:'No blocked IPs in range.'|escape:'html'}</div>
            {/if}
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

{* ── Chart.js + Dashboard JS ── *}
<script>
var bbfDashboardData = {$dashboardDataJson nofilter};
</script>
<script>
{literal}
(function() {
    'use strict';

    var PRIMARY = '#db2e87';
    var PRIMARY_LIGHT = 'rgba(219, 46, 135, 0.1)';
    var IS_ENG = (window.adminLang === 'eng');
    var DATE_LOCALE = IS_ENG ? 'en-GB' : 'de-DE';
    var L = window.bbfLang || {};
    var METHOD_COLORS = {
        'honeypot': '#2563eb', 'timing': '#16a34a', 'altcha': '#f59e0b',
        'ai': '#8b5cf6', 'ai_filter': '#8b5cf6', 'ip': '#dc2626', 'rate': '#ec4899',
        'recaptcha': '#06b6d4', 'turnstile': '#f97316', 'hcaptcha': '#84cc16', 'bot': '#6366f1'
    };
    var METHOD_LABELS = {
        'honeypot':  L.method_honeypot  || 'Honeypot',
        'timing':    L.method_timing    || 'Timing',
        'altcha':    L.method_altcha    || 'ALTCHA',
        'ai':        L.method_ai        || 'AI filter',
        'ai_filter': L.method_ai        || 'AI filter',
        'ip':        'IP', 'rate': 'Rate limit', 'recaptcha': 'reCAPTCHA',
        'turnstile': 'Turnstile', 'hcaptcha': 'hCaptcha', 'bot': 'Bot detection'
    };
    var FORM_LABELS = {
        'contact':        L.form_contact        || 'Contact',
        'registration':   L.form_registration   || 'Registration',
        'newsletter':     L.form_newsletter     || 'Newsletter',
        'review':         L.form_review         || 'Reviews',
        'checkout':       L.form_checkout        || 'Checkout',
        'login':          L.form_login          || 'Login',
        'password_reset': L.form_password_reset || 'Password',
        'wishlist':       L.form_wishlist       || 'Wishlist'
    };

    var bbfDashboard = {
        charts: { history: null, methods: null, forms: null, hourly: null },
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
                    bbfDashboard.render(resp.data);
                }
            });
        },

        // Erstaufbau und Re-Render (Range-Wechsel) teilen sich denselben Pfad.
        render: function(data) {
            if (typeof Chart === 'undefined') return;
            this.buildHistory(data);
            this.buildMethods(data);
            this.buildForms(data);
            this.buildHourly(data);
            this.renderThreats(data.top_ips || []);
        },

        // Rückwärtskompatibler Alias der früheren API.
        updateCharts: function(data) { this.render(data); },

        destroy: function(key) {
            if (this.charts[key]) { this.charts[key].destroy(); this.charts[key] = null; }
        },

        buildHistory: function(data) {
            var labels = datesForRange(this.currentRange);
            var values = labels.map(function() { return 0; });
            if (data.spam_history && data.spam_history.length > 0) {
                var map = {};
                data.spam_history.forEach(function(row) {
                    var key = new Date(row.date).toLocaleDateString(DATE_LOCALE, { day: '2-digit', month: '2-digit' });
                    map[key] = (map[key] || 0) + parseInt(row.cnt, 10);
                });
                values = labels.map(function(l) { return map[l] || 0; });
            }
            var ctx = document.getElementById('bbf-chart-history');
            if (!ctx) return;
            this.destroy('history');
            this.charts.history = new Chart(ctx, {
                type: 'line',
                data: { labels: labels, datasets: [{
                    label: IS_ENG ? 'Blocked' : 'Geblockt', data: values,
                    borderColor: PRIMARY, backgroundColor: PRIMARY_LIGHT, fill: true,
                    tension: 0.4, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4
                }] },
                options: lineOpts()
            });
        },

        buildMethods: function(data) {
            var labels = [], values = [], colors = [];
            if (data.method_distribution && data.method_distribution.length > 0) {
                data.method_distribution.forEach(function(row) {
                    labels.push(METHOD_LABELS[row.detection_method] || row.detection_method);
                    values.push(parseInt(row.cnt, 10));
                    colors.push(METHOD_COLORS[row.detection_method] || '#94a3b8');
                });
            } else {
                labels = [IS_ENG ? 'No data' : 'Keine Daten']; values = [1]; colors = ['#e5e7eb'];
            }
            var ctx = document.getElementById('bbf-chart-methods');
            if (!ctx) return;
            this.destroy('methods');
            this.charts.methods = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0 }] },
                options: donutOpts()
            });
        },

        buildForms: function(data) {
            var labels = [], values = [];
            if (data.top_forms && data.top_forms.length > 0) {
                data.top_forms.forEach(function(row) {
                    labels.push(FORM_LABELS[row.form_type] || row.form_type);
                    values.push(parseInt(row.cnt, 10));
                });
            } else {
                labels = [IS_ENG ? 'No data' : 'Keine Daten']; values = [0];
            }
            var ctx = document.getElementById('bbf-chart-forms');
            if (!ctx) return;
            this.destroy('forms');
            this.charts.forms = new Chart(ctx, {
                type: 'bar',
                data: { labels: labels, datasets: [{
                    data: values, backgroundColor: PRIMARY_LIGHT, borderColor: PRIMARY,
                    borderWidth: 1, borderRadius: 4
                }] },
                options: hbarOpts()
            });
        },

        buildHourly: function(data) {
            var src = data.hourly_distribution || [];
            var values = [], labels = [];
            for (var h = 0; h < 24; h++) {
                values.push(parseInt(src[h] != null ? src[h] : 0, 10) || 0);
                labels.push((h < 10 ? '0' : '') + h);
            }
            var ctx = document.getElementById('bbf-chart-hourly');
            if (!ctx) return;
            this.destroy('hourly');
            this.charts.hourly = new Chart(ctx, {
                type: 'bar',
                data: { labels: labels, datasets: [{
                    data: values, backgroundColor: PRIMARY_LIGHT, borderColor: PRIMARY,
                    borderWidth: 1, borderRadius: 3
                }] },
                options: vbarOpts()
            });
        },

        renderThreats: function(topIps) {
            var box = document.getElementById('bbf-threats-list');
            if (!box) return;
            if (!topIps || !topIps.length) {
                box.innerHTML = '<div class="bbf-threat-empty">'
                    + (L.no_threats || (IS_ENG ? 'No blocked IPs in range.' : 'Keine geblockten IPs im Zeitraum.'))
                    + '</div>';
                return;
            }
            var html = '';
            topIps.forEach(function(row) {
                var ip = String(row.ip_address || '');
                var cnt = parseInt(row.cnt, 10) || 0;
                var ts = row.last_seen || '';
                html += '<div class="bbf-threat-row">'
                     +  '<span class="bbf-threat-ip">' + escapeHtml(ip) + '</span>'
                     +  '<span class="bbf-threat-count bbf-badge bbf-badge-danger">' + cnt + '×</span>'
                     +  '<span class="bbf-threat-time" data-ts="' + escapeHtml(ts) + '">' + escapeHtml(timeAgo(ts)) + '</span>'
                     +  '</div>';
            });
            box.innerHTML = html;
        },

        applyTimeAgo: function() {
            document.querySelectorAll('#bbf-threats-list .bbf-threat-time[data-ts]').forEach(function(el) {
                var ts = el.getAttribute('data-ts');
                if (ts) el.textContent = timeAgo(ts);
            });
        }
    };

    window.bbfDashboard = bbfDashboard;

    // ── Helfer ───────────────────────────────────────────────
    function datesForRange(days) {
        var out = [];
        for (var i = days - 1; i >= 0; i--) {
            var d = new Date();
            d.setDate(d.getDate() - i);
            out.push(d.toLocaleDateString(DATE_LOCALE, { day: '2-digit', month: '2-digit' }));
        }
        return out;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function timeAgo(ts) {
        if (!ts) return '';
        var d = new Date(String(ts).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(ts);
        var sec = Math.floor((Date.now() - d.getTime()) / 1000);
        if (sec < 0) sec = 0;
        if (sec < 60) return IS_ENG ? 'just now' : 'gerade eben';
        function unit(n, eng, ger1, gerN) {
            return IS_ENG ? (n + ' ' + eng + (n === 1 ? '' : 's') + ' ago')
                          : ('vor ' + n + ' ' + (n === 1 ? ger1 : gerN));
        }
        var min = Math.floor(sec / 60); if (min < 60) return unit(min, 'minute', 'Minute', 'Minuten');
        var hr = Math.floor(min / 60);  if (hr < 24)  return unit(hr, 'hour', 'Stunde', 'Stunden');
        var day = Math.floor(hr / 24);  if (day < 30) return unit(day, 'day', 'Tag', 'Tagen');
        var mon = Math.floor(day / 30); return unit(mon, 'month', 'Monat', 'Monaten');
    }

    function lineOpts() {
        return { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 8 } },
                      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } } } };
    }
    function donutOpts() {
        return { responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12, usePointStyle: true } } } };
    }
    function hbarOpts() {
        return { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } },
                      y: { grid: { display: false }, ticks: { font: { size: 11 } } } } };
    }
    function vbarOpts() {
        return { responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false },
                tooltip: { callbacks: { title: function(items) { return items[0].label + ':00'; } } } },
            scales: { x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 12 } },
                      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 }, precision: 0 } } } };
    }

    // ── Boot: Chart.js lokal laden (kein CDN, kein Consent-Problem) ──
    if (typeof Chart === 'undefined') {
        var script = document.createElement('script');
        script.src = adminUrl + 'js/vendor/chart.umd.min.js';
        script.onload = boot;
        document.head.appendChild(script);
    } else {
        boot();
    }

    function boot() {
        bbfDashboard.render(window.bbfDashboardData || {});
        // Server-gerenderte Bedrohungszeiten ins "vor X" umschreiben (falls Chart fehlt).
        bbfDashboard.applyTimeAgo();
    }
})();
{/literal}
</script>
