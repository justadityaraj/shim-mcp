/* global jQuery, shimMcp */
(function ($) {
    'use strict';

    /**
     * Copy text content of an element to clipboard.
     */
    function copyToClipboard(elementId, button) {
        var el = document.getElementById(elementId);
        if (!el) return;

        var text = el.textContent || el.innerText;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopied(button);
            });
        } else {
            // Fallback for older browsers.
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopied(button);
        }
    }

    function showCopied(button) {
        var $btn = $(button);
        var original = $btn.text();
        $btn.text(shimMcp.i18n.copied).addClass('copied');
        setTimeout(function () {
            $btn.text(original).removeClass('copied');
        }, 2000);
    }

    function setCredential(base64) {
        $('.mcp-credential').each(function () {
            $(this).text(base64 || $(this).data('placeholder'));
        });
        $('.mcp-config-copy').prop('disabled', !base64);
        $('#mcp-config-note').toggle(!base64);
    }

    function showKeyActive(password, base64) {
        $('#mcp-key-status').empty().append(
            $('<span class="mcp-badge mcp-badge-active">').text(shimMcp.i18n.apiKeyActive),
            $('<button type="button" class="button button-secondary" id="mcp-generate-key">').text(shimMcp.i18n.generateNewKey),
            $('<button type="button" class="button button-secondary" id="mcp-revoke-key">').text(shimMcp.i18n.revokeKey)
        );
        $('#mcp-key-value').text(password);
        $('#mcp-key-output').show();
        setCredential(base64);
        $('#mcp-test-connection').prop('disabled', false);
        $('#mcp-connection-dot').removeClass('mcp-dot-inactive mcp-dot-active').addClass('mcp-dot-unknown');
        $('#mcp-connection-text').text(shimMcp.i18n.keyConfigured);
    }

    function showNoKey() {
        $('#mcp-key-status').empty().append(
            $('<button type="button" class="button button-primary" id="mcp-generate-key">').text(shimMcp.i18n.generateKey)
        );
        $('#mcp-key-value').text('');
        $('#mcp-key-output').hide();
        setCredential(null);
        $('#mcp-test-connection').prop('disabled', true);
        $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
        $('#mcp-connection-text').text(shimMcp.i18n.noKey);
    }

    // ---- Event handlers ----

    $(document).on('click', '#mcp-generate-key', function () {
        var $btn = $(this);
        var label = $btn.text();

        if ($('#mcp-revoke-key').length && !confirm(shimMcp.i18n.confirmRegenerate)) {
            return;
        }

        $btn.prop('disabled', true).text(shimMcp.i18n.generating);

        $.post(shimMcp.ajaxUrl, {
            action: 'shim_mcp_generate_key',
            nonce: shimMcp.nonce
        }, function (response) {
            if (response.success) {
                showKeyActive(response.data.password, response.data.base64);
            } else {
                alert(shimMcp.i18n.error.replace('%s', response.data && response.data.message ? response.data.message : shimMcp.i18n.unknownError));
                $btn.prop('disabled', false).text(label);
            }
        }).fail(function () {
            alert(shimMcp.i18n.requestFailed);
            $btn.prop('disabled', false).text(label);
        });
    });

    $(document).on('click', '#mcp-revoke-key', function () {
        if (!confirm(shimMcp.i18n.confirmRevoke)) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(shimMcp.i18n.revoking);

        $.post(shimMcp.ajaxUrl, {
            action: 'shim_mcp_revoke_key',
            nonce: shimMcp.nonce
        }, function (response) {
            if (response.success) {
                showNoKey();
            } else {
                alert(shimMcp.i18n.error.replace('%s', response.data && response.data.message ? response.data.message : shimMcp.i18n.unknownError));
                $btn.prop('disabled', false).text(shimMcp.i18n.revokeKey);
            }
        }).fail(function () {
            alert(shimMcp.i18n.requestFailed);
            $btn.prop('disabled', false).text(shimMcp.i18n.revokeKey);
        });
    });

    $(document).on('click', '#mcp-test-connection', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text(shimMcp.i18n.checking);

        $.post(shimMcp.ajaxUrl, {
            action: 'shim_mcp_test_connection',
            nonce: shimMcp.nonce
        }, function (response) {
            if (response.success) {
                $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-inactive').addClass('mcp-dot-active');
                $('#mcp-connection-text').text(response.data && response.data.message ? response.data.message : shimMcp.i18n.endpointUnchecked);
            } else {
                $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
                $('#mcp-connection-text').text(response.data && response.data.message ? response.data.message : shimMcp.i18n.connectionFailed);
            }
            $btn.prop('disabled', false).text(shimMcp.i18n.checkEndpoint);
        }).fail(function () {
            $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
            $('#mcp-connection-text').text(shimMcp.i18n.checkFailed);
            $btn.prop('disabled', false).text(shimMcp.i18n.checkEndpoint);
        });
    });

    // Copy buttons.
    $(document).on('click', '.mcp-copy-btn', function () {
        var target = $(this).data('target');
        if (target) {
            copyToClipboard(target, this);
        }
    });

})(jQuery);
