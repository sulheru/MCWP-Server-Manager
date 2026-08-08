(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll(
            '.optigrid-gateway__form'
        );

        forms.forEach(function (form) {
            var selector = form.querySelector(
                '[data-paypal-environment-selector]'
            );

            if (selector) {
                var panels = form.querySelectorAll(
                    '[data-paypal-environment-panel]'
                );
                var notice = form.querySelector(
                    '[data-paypal-environment-notice]'
                );
                var label = form.querySelector(
                    '[data-paypal-environment-label]'
                );
                var message = form.querySelector(
                    '[data-paypal-environment-message]'
                );

                var renderPayPalEnvironment = function () {
                    var environment = selector.value;

                    panels.forEach(function (panel) {
                        panel.hidden =
                            panel.getAttribute(
                                'data-paypal-environment-panel'
                            ) !== environment;
                    });

                    if (label) {
                        label.textContent =
                            environment === 'live'
                                ? 'LIVE'
                                : 'SANDBOX';
                    }

                    if (message) {
                        message.textContent =
                            environment === 'live'
                                ? 'Las nuevas operaciones procesan dinero real.'
                                : 'Las nuevas operaciones son de prueba.';
                    }

                    if (notice) {
                        notice.classList.remove(
                            'notice-error',
                            'notice-info'
                        );

                        notice.classList.add(
                            environment === 'live'
                                ? 'notice-error'
                                : 'notice-info'
                        );
                    }
                };

                selector.addEventListener(
                    'change',
                    renderPayPalEnvironment
                );

                renderPayPalEnvironment();
            }

            form.addEventListener('submit', function () {
                var button = form.querySelector(
                    'button[type="submit"]'
                );

                if (!button) {
                    return;
                }

                button.disabled = true;
                button.textContent = 'Guardando…';
            });
        });
    });
}());
