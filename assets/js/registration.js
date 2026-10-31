/* global aplsFaext */
(() => {
    'use strict';
    const config = window.aplsFaext;
    if (!config) { return; }
    const debug = (...items) => { if (config.debug) { console.info('[APLS FluentAuth]', ...items); } };
    function setup() {
        const shortcode = document.getElementById('flsRegistrationForm');
        const native = !shortcode ? document.getElementById('registerform') : null;
        const form = shortcode || native;
        if (!form || form.dataset.aplsInitialized) { return; }
        const mode = shortcode ? 'shortcode' : 'native';
        form.dataset.aplsInitialized = '1';
        debug('registration form detected', { mode });
        const fields = shortcode ? form.querySelector('.fls_registration_fields') : form;
        const submit = shortcode ? form.querySelector('#fls_submit') : form.querySelector('input[type="submit"], button[type="submit"]');
        if (!fields || !submit) { debug('registration markup missing', { mode }); return; }
        // All registration markup is now rendered in PHP. JavaScript is only
        // responsible for activating ACF widgets and optional live validation.
        if (config.acf && window.acf?.doAction) {
            const host = form.querySelector('.apls-faext-acf-host');
            if (host) {
                window.acf.doAction('append', host);
                debug('server-rendered ACF fields initialized', {mode, fields: host.querySelectorAll('.acf-field').length});
            }
        }
        if (form.querySelector('.apls-faext-acf-message')) { submit.disabled = true; debug('ACF schema invalid; submission blocked'); }
        const isVerification = mode === 'native' && (document.body.classList.contains('fls_register_form_token') || !!form.querySelector('[name="_email_verification_hash"]'));
        if (isVerification) {
            form.querySelectorAll('.apls-faext-acf-host, .apls-faext-native-consent').forEach(node => { node.hidden = true; });
            debug('native verification stage; registration extras hidden');
        }
        if (!config.live || isVerification) { return; }
        const email = form.querySelector('input[type="email"], #user_email');
        if (!email) { return; }
        const status = document.createElement('div'); status.className = 'apls-faext-email-status';
        status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite');
        email.insertAdjacentElement('afterend', status);
        let timer, sequence = 0;
        email.addEventListener('input', () => {
            clearTimeout(timer); sequence++; status.textContent = '';
            const value = email.value.trim(); if (!email.validity.valid || !value) { return; }
            const current = sequence;
            timer = setTimeout(async () => {
                status.textContent = config.checking;
                const body = new FormData(); body.append('action', 'apls_faext_check_email');
                body.append('nonce', config.nonce); body.append('email', value);
                try {
                    const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body });
                    const result = await response.json();
                    if (current !== sequence) { return; }
                    status.textContent = result.success && result.data?.allowed ? '' : (result.data?.message || config.bad);
                } catch (error) { if (current === sequence) { status.textContent = ''; } }
            }, 600);
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', setup); }
    else { setup(); }
})();
