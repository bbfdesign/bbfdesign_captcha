/**
 * BBF Captcha & Spam-Schutz – Admin Helper Functions
 * Vanilla JS (ES2022+), no dependencies except Alpine.js (loaded separately).
 */
;(function() {
  'use strict';

  var NOTIFICATION_DURATION = 4000;

  var bbfAdmin = {

    /**
     * POST an action to the plugin admin endpoint.
     */
    post: function(action, data) {
      data = data || {};
      var params = new URLSearchParams(data);
      params.set('action', action);
      params.set('is_ajax', '1');

      var tokenEl = document.querySelector('[name="jtl_token"]');
      if (tokenEl) {
        params.set('jtl_token', tokenEl.value);
      }

      return fetch(window.postURL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
      }).then(function(res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      });
    },

    /**
     * Show a floating toast notification.
     */
    showNotification: function(message, type) {
      type = type || 'info';
      var colours = {
        success: '#059669',
        error: '#dc2626',
        warning: '#d97706',
        info: '#2563eb'
      };
      var el = document.createElement('div');
      el.style.cssText = 'position:fixed;top:1rem;right:1rem;padding:0.75rem 1.25rem;border-radius:0.375rem;color:#fff;background-color:' + (colours[type] || colours.info) + ';box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:99999;font-size:0.875rem;transition:opacity .3s ease;opacity:0;font-family:var(--bbf-font-family);';
      el.textContent = message;
      document.body.appendChild(el);
      requestAnimationFrame(function() { el.style.opacity = '1'; });
      setTimeout(function() {
        el.style.opacity = '0';
        el.addEventListener('transitionend', function() { el.remove(); });
      }, NOTIFICATION_DURATION);
    },

    /**
     * Copy text to clipboard.
     */
    copyToClipboard: function(text) {
      var self = this;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
          self.showNotification('In die Zwischenablage kopiert', 'success');
        });
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        self.showNotification('In die Zwischenablage kopiert', 'success');
      }
    },

    /**
     * Format a date string to German locale.
     */
    formatDate: function(dateStr) {
      var d = new Date(dateStr);
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },

    /**
     * Confirm dialog as a promise.
     */
    confirmAction: function(message) {
      return Promise.resolve(window.confirm(message));
    },

    /**
     * Debounce utility.
     */
    debounce: function(fn, delay) {
      delay = delay || 300;
      var timer;
      return function() {
        var args = arguments;
        var self = this;
        clearTimeout(timer);
        timer = setTimeout(function() { fn.apply(self, args); }, delay);
      };
    },

    /**
     * Number formatting (German locale).
     */
    formatNumber: function(num) {
      return new Intl.NumberFormat('de-DE').format(num);
    }
  };

  // Expose on window
  window.bbfAdmin = bbfAdmin;
})();
