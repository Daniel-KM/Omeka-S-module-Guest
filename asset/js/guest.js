'use strict';

(function ($) {
    $(document).ready(function() {

        /**
         * @see ContactUs, Guest, Selection, TwoFactorAuth.
         */

        const beforeSpin = function (element) {
            var span = $(element).find('span');
            if (!span.length) {
                span = $(element).next('span.appended');
                if (!span.length) {
                    $('<span class="appended"></span>').insertAfter($(element));
                    span = $(element).next('span');
                }
            }
            element.hide();
            span.addClass('fas fa-sync fa-spin');
        };

        const afterSpin = function (element) {
            var span = $(element).find('span');
            if (!span.length) {
                span = $(element).next('span.appended');
                if (span.length) {
                    span.remove();
                }
            } else {
                span.removeClass('fas fa-sync fa-spin');
            }
            element.show();
        };

        /**
         * Get the main message of jSend output, in particular for status fail.
         */
        const jSendMessage = function(data) {
            if (typeof data !== 'object') {
                return null;
            }
            if (data.message) {
                return data.message;
            }
            if (!data.data) {
                return null;
            }
            for (let value of Object.values(data.data)) {
                if (typeof value === 'string' && value.length) {
                    return value;
                }
            }
            return null;
        }

        const dialogMessage = function (message, nl2br = false) {
            // Use a dialog to display a message, that should be escaped.
            var dialog = document.querySelector('dialog.popup-message');
            if (!dialog) {
                dialog = `
<dialog class="popup popup-dialog dialog-message popup-message" data-is-dynamic="1">
    <div class="dialog-background">
        <div class="dialog-panel">
            <div class="dialog-header">
                <button type="button" class="dialog-header-close-button" title="Close" autofocus="autofocus">
                    <span class="dialog-close">🗙</span>
                </button>
            </div>
            <div class="dialog-contents">
                {{ message }}
            </div>
        </div>
    </div>
</dialog>`;
                $('body').append(dialog);
                dialog = document.querySelector('dialog.dialog-message');
            }
            if (nl2br) {
                message = message.replace(/(?:\r\n|\r|\n)/g, '<br/>');
            }
            dialog.innerHTML = dialog.innerHTML.replace('{{ message }}', message);
            dialog.showModal();
            $(dialog).trigger('o:dialog-opened');
        };

        /**
         * Override submit of login form to manage optional login-token form.
         * @see\TwoFactorAuth to manage the same login form off-site.
         */
        $(document).on('submit', '#loginform', function(ev) {
            ev.preventDefault();
            const form = $(this);
            const urlLogin = form.attr('action') ? form.attr('action') : window.location.href;
            const submitButton = form.find('[type=submit]');
            $
                .ajax({
                    type: 'POST',
                    url: urlLogin,
                    data: form.serialize(),
                    beforeSend: beforeSpin(submitButton),
                })
                .done(function(data) {
                    // Success may be a single step login or a second step login.
                    if (data.data && data.data.login === true) {
                        window.location.reload();
                        return;
                    }
                    // Success for first step, but require a second step.
                    // Use the existing dialog if any, else use the one sent.
                    let dialog = document.querySelector('dialog.dialog-2fa-token');
                    if (!dialog) {
                        dialog = data.data.dialog;
                        $('body').append(dialog);
                        dialog = document.querySelector('dialog.dialog-2fa-token');
                        if (!dialog) {
                            let msg = jSendMessage(data);
                            dialogMessage(msg ? msg : 'Check input', true);
                            return;
                        }
                    }
                    dialog.showModal();
                    $(dialog).trigger('o:dialog-opened');
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    const data = xhr.responseJSON;
                    if (data && data.status === 'fail') {
                        // Fail is always an email/password error here.
                        let msg = jSendMessage(data);
                        dialogMessage(msg ? msg : 'Check input', true);
                        form[0].reset();
                    } else {
                        // Error is a server error (in particular cannot send mail).
                        let msg = data && data.status === 'error' && data.message && data.message.length ? data.message : 'An error occurred.';
                        dialogMessage(msg, true);
                    }
                })
                .always(function () {
                    afterSpin(submitButton)
                });
        });

        /**
         * Register a new user.
         */
        $(document).on('submit', '#user-form', function(ev) {
            ev.preventDefault();
            const form = $(this);
            const urlLogin = form.attr('action') ? form.attr('action') : window.location.href;
            const submitButton = form.find('[type=submit]');
            $
                .ajax({
                    type: 'POST',
                    url: urlLogin,
                    data: form.serialize(),
                    beforeSend: beforeSpin(submitButton),
                })
                .done(function(data) {
                    // Success may be a single step login or a second step login.
                    if (data.data && data.data.login === true) {
                        window.location.reload();
                        return;
                    }
                    // Success for first step, but require a second step.
                    // Use the existing dialog if any, else use the one sent.
                    let dialog = document.querySelector('dialog.dialog-2fa-token');
                    if (!dialog) {
                        dialog = data.data.dialog;
                        $('body').append(dialog);
                        dialog = document.querySelector('dialog.dialog-2fa-token');
                        if (!dialog) {
                            let msg = jSendMessage(data);
                            dialogMessage(msg ? msg : 'Check input', true);
                            return;
                        }
                    }
                    dialog.showModal();
                    $(dialog).trigger('o:dialog-opened');
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    const data = xhr.responseJSON;
                    if (data && data.status === 'fail') {
                        // Fail is always an email/password error here.
                        let msg = jSendMessage(data);
                        dialogMessage(msg ? msg : 'Check input', true);
                        form[0].reset();
                    } else {
                        // Error is a server error (in particular cannot send mail).
                        let msg = data && data.status === 'error' && data.message && data.message.length ? data.message : 'An error occurred.';
                        dialogMessage(msg, true);
                    }
                })
                .always(function () {
                    afterSpin(submitButton)
                });
        });

        $(document).on('click', '.button-login', function(e) {
            const button = $(this);
            const urlButton = button.attr('data-url') ? button.attr('data-url') : button.attr('href');
            $
                .ajax({
                    type: 'GET',
                    url: urlButton,
                    beforeSend: beforeSpin(button),
                })
                .done(function(data) {
                    // Success for loading dialog.
                    // Use the existing dialog if any, else use the one sent.
                    let dialog = document.querySelector('dialog.dialog-login');
                    if (!dialog) {
                        dialog = data.data.dialog;
                        $('body').append(dialog);
                        dialog = document.querySelector('dialog.dialog-login');
                        if (!dialog) {
                            let msg = jSendMessage(data);
                            dialogMessage(msg ? msg : 'Check input', true);
                            return;
                        }
                    }
                    dialog.showModal();
                    $(dialog).trigger('o:dialog-opened');
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    const data = xhr.responseJSON;
                    let msg = jSendMessage(data);
                    dialogMessage(msg ? msg : 'An error occurred.', true);
                })
                .always(function () {
                    afterSpin(button)
                });
        });

        $(document).on('click', '.button-register', function(e) {
            const button = $(this);
            const urlButton = button.attr('data-url') ? button.attr('data-url') : button.attr('href');
            $
                .ajax({
                    type: 'GET',
                    url: urlButton,
                    beforeSend: beforeSpin(button),
                })
                .done(function(data) {
                    // Success for loading dialog.
                    // Use the existing dialog if any, else use the one sent.
                    let dialog = document.querySelector('dialog.dialog-register');
                    if (!dialog) {
                        dialog = data.data.dialog;
                        $('body').append(dialog);
                        dialog = document.querySelector('dialog.dialog-register');
                        if (!dialog) {
                            let msg = jSendMessage(data);
                            dialogMessage(msg ? msg : 'Check input', true);
                            return;
                        }
                    }
                    dialog.showModal();
                    $(dialog).trigger('o:dialog-opened');
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    const data = xhr.responseJSON;
                    let msg = jSendMessage(data);
                    dialogMessage(msg ? msg : 'An error occurred.', true);
                })
                .always(function () {
                    afterSpin(button)
                });
        });

        $(document).on('click', '.dialog-header-close-button', function(e) {
            const dialog = this.closest('dialog.popup');
            if (dialog) {
                dialog.close();
                if (dialog.hasAttribute('data-is-dynamic') && dialog.getAttribute('data-is-dynamic')) {
                    dialog.remove();
                }
            } else {
                $(this).closest('.popup').addClass('hidden').hide();
            }
        });

    });
})(jQuery);