/**
 * BBF Captcha & Spam-Schutz – Frontend Logic
 * Modular, lazy-loaded, nur bei Formularen aktiv.
 * Kein jQuery benötigt. Vanilla JS.
 */
;(function() {
    'use strict';

    var BBFCaptcha = {
        initialized: false,
        consentCallbacks: [],

        /**
         * Initialisierung – nur aufrufen wenn Formular auf der Seite
         */
        init: function() {
            if (this.initialized) return;
            this.initialized = true;

            this.initConsentListeners();
            this.initLazyLoading();
        },

        /**
         * Consent Manager Integration
         * Externe Captcha-Scripts nur laden wenn Consent vorhanden
         */
        initConsentListeners: function() {
            if (typeof ConsentManager === 'undefined') return;

            // Turnstile
            if (ConsentManager.hasConsent('bbfdesign_captcha_turnstile')) {
                this.loadExternalCaptcha('turnstile');
            }

            // reCAPTCHA
            if (ConsentManager.hasConsent('bbfdesign_captcha_recaptcha')) {
                this.loadExternalCaptcha('recaptcha');
            }

            // hCaptcha
            if (ConsentManager.hasConsent('bbfdesign_captcha_hcaptcha')) {
                this.loadExternalCaptcha('hcaptcha');
            }

            // Auf nachträglichen Consent lauschen
            if (typeof ConsentManager.on === 'function') {
                ConsentManager.on('consent.ready', function() {
                    if (ConsentManager.hasConsent('bbfdesign_captcha_turnstile')) {
                        BBFCaptcha.loadExternalCaptcha('turnstile');
                    }
                    if (ConsentManager.hasConsent('bbfdesign_captcha_recaptcha')) {
                        BBFCaptcha.loadExternalCaptcha('recaptcha');
                    }
                    if (ConsentManager.hasConsent('bbfdesign_captcha_hcaptcha')) {
                        BBFCaptcha.loadExternalCaptcha('hcaptcha');
                    }
                });
            }
        },

        /**
         * Lazy Loading: Externe Captcha-Scripts erst bei Formular-Interaktion laden
         */
        initLazyLoading: function() {
            var forms = document.querySelectorAll('form');
            if (forms.length === 0) return;

            var loaded = false;
            var loadHandler = function() {
                if (loaded) return;
                loaded = true;

                // Externe Captchas nachladen wenn Consent vorhanden
                BBFCaptcha.consentCallbacks.forEach(function(cb) { cb(); });

                // Event-Listener entfernen
                forms.forEach(function(form) {
                    form.removeEventListener('focusin', loadHandler);
                });
            };

            forms.forEach(function(form) {
                form.addEventListener('focusin', loadHandler, { once: true });
            });

            // Auch bei Scroll-in-View laden
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            loadHandler();
                            observer.disconnect();
                        }
                    });
                }, { rootMargin: '200px' });

                forms.forEach(function(form) {
                    observer.observe(form);
                });
            }
        },

        /**
         * Externes Captcha-Script laden (nur wenn Consent!)
         */
        loadExternalCaptcha: function(type) {
            var config = window.bbfCaptchaConsent || {};
            var captchaConfig = config[type];
            if (!captchaConfig || !captchaConfig.script) return;

            // Script bereits geladen?
            if (document.querySelector('script[data-bbf-captcha="' + type + '"]')) return;

            var script = document.createElement('script');
            script.src = captchaConfig.script;
            script.async = true;
            script.defer = true;
            script.setAttribute('data-bbf-captcha', type);

            if (type === 'friendly_captcha') {
                script.type = 'module';
            }

            document.head.appendChild(script);
        },

        /**
         * Fehlermeldung anzeigen (ARIA-live Region)
         */
        showError: function(form, message) {
            var existing = form.querySelector('.bbf-captcha-error');
            if (existing) {
                existing.textContent = message;
                return;
            }

            var el = document.createElement('div');
            el.className = 'bbf-captcha-error';
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', 'polite');
            el.textContent = message;

            // Vor dem Submit-Button einfügen
            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.parentNode.insertBefore(el, submitBtn);
            } else {
                form.appendChild(el);
            }

            // Fokus auf Fehlermeldung setzen (Barrierefreiheit)
            el.focus();
        },

        /**
         * Fehlermeldung entfernen
         */
        clearError: function(form) {
            var existing = form.querySelector('.bbf-captcha-error');
            if (existing) {
                existing.remove();
            }
        },

        /**
         * Erfolg anzeigen
         */
        showSuccess: function(form) {
            var el = document.createElement('div');
            el.className = 'bbf-captcha-success';
            el.setAttribute('aria-live', 'polite');
            var config = window.bbfCaptchaConsent || {};
            el.textContent = (config.labels && config.labels.captcha_success)
                ? config.labels.captcha_success
                : 'Security check successful';

            var widget = form.querySelector('.bbf-captcha-widget');
            if (widget) {
                widget.appendChild(el);
            }
        }
    };

    // Nur laden wenn DOM bereit ist und ein Formular auf der Seite ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('form')) {
                BBFCaptcha.init();
            }
        });
    } else {
        if (document.querySelector('form')) {
            BBFCaptcha.init();
        }
    }

    // Global verfügbar machen
    window.BBFCaptcha = BBFCaptcha;
})();
