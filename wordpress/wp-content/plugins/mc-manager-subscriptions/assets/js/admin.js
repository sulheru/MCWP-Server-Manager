(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll(
            '.optigrid-gateway__form'
        );

        forms.forEach(function (form) {
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
