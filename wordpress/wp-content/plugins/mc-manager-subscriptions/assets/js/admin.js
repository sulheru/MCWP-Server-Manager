(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tabRoot = document.querySelector(
            '[data-gateway-tabs]'
        );

        if (tabRoot) {
            var tabs = Array.prototype.slice.call(
                tabRoot.querySelectorAll(
                    '[data-gateway-tab]'
                )
            );

            var panels = Array.prototype.slice.call(
                document.querySelectorAll(
                    '[data-gateway-panel]'
                )
            );

            var activateGateway = function (
                gatewayId,
                updateUrl
            ) {
                tabs.forEach(function (tab) {
                    var active =
                        tab.getAttribute(
                            'data-gateway-tab'
                        ) === gatewayId;

                    tab.classList.toggle(
                        'is-active',
                        active
                    );

                    tab.setAttribute(
                        'aria-selected',
                        active ? 'true' : 'false'
                    );

                    tab.setAttribute(
                        'tabindex',
                        active ? '0' : '-1'
                    );
                });

                panels.forEach(function (panel) {
                    var active =
                        panel.getAttribute(
                            'data-gateway-panel'
                        ) === gatewayId;

                    panel.hidden = !active;
                    panel.classList.toggle(
                        'is-active',
                        active
                    );
                });

                if (updateUrl && window.history) {
                    var url = new URL(
                        window.location.href
                    );

                    url.searchParams.set(
                        'gateway',
                        gatewayId
                    );

                    window.history.replaceState(
                        {},
                        '',
                        url.toString()
                    );
                }
            };

            tabs.forEach(function (tab, index) {
                tab.addEventListener(
                    'click',
                    function () {
                        activateGateway(
                            tab.getAttribute(
                                'data-gateway-tab'
                            ),
                            true
                        );
                    }
                );

                tab.addEventListener(
                    'keydown',
                    function (event) {
                        var targetIndex = null;

                        if (
                            event.key === 'ArrowRight'
                            || event.key === 'ArrowDown'
                        ) {
                            targetIndex =
                                (index + 1)
                                % tabs.length;
                        }

                        if (
                            event.key === 'ArrowLeft'
                            || event.key === 'ArrowUp'
                        ) {
                            targetIndex =
                                (index - 1 + tabs.length)
                                % tabs.length;
                        }

                        if (event.key === 'Home') {
                            targetIndex = 0;
                        }

                        if (event.key === 'End') {
                            targetIndex =
                                tabs.length - 1;
                        }

                        if (targetIndex === null) {
                            return;
                        }

                        event.preventDefault();

                        tabs[targetIndex].focus();

                        activateGateway(
                            tabs[targetIndex].getAttribute(
                                'data-gateway-tab'
                            ),
                            true
                        );
                    }
                );
            });
        }

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

            form.addEventListener(
                'submit',
                function () {
                    var button = form.querySelector(
                        'button[type="submit"]'
                    );

                    if (!button) {
                        return;
                    }

                    button.disabled = true;
                    button.textContent = 'Guardando…';
                }
            );
        });
    });
}());
