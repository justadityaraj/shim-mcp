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
        $btn.text('Copied!').addClass('copied');
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
        $('#mcp-key-status').html(
            '<span class="mcp-badge mcp-badge-active">API Key Active</span>' +
            '<button type="button" class="button button-secondary" id="mcp-generate-key">Generate New Key</button>' +
            '<button type="button" class="button button-secondary" id="mcp-revoke-key">Revoke Key</button>'
        );
        $('#mcp-key-value').text(password);
        $('#mcp-key-output').show();
        setCredential(base64);
        $('#mcp-test-connection').prop('disabled', false);
        $('#mcp-connection-dot').removeClass('mcp-dot-inactive mcp-dot-active').addClass('mcp-dot-unknown');
        $('#mcp-connection-text').text('Key configured \u2014 run the endpoint check');
    }

    function showNoKey() {
        $('#mcp-key-status').html(
            '<button type="button" class="button button-primary" id="mcp-generate-key">Generate API Key</button>'
        );
        $('#mcp-key-value').text('');
        $('#mcp-key-output').hide();
        setCredential(null);
        $('#mcp-test-connection').prop('disabled', true);
        $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
        $('#mcp-connection-text').text('No API key configured');
    }

    // ---- Event handlers ----

    $(document).on('click', '#mcp-generate-key', function () {
        var $btn = $(this);
        var label = $btn.text();

        if ($('#mcp-revoke-key').length && !confirm('Generating a new key revokes the current one. MCP clients using it will stop working.')) {
            return;
        }

        $btn.prop('disabled', true).text('Generating...');

        $.post(shimMcp.ajaxUrl, {
            action: 'shim_mcp_generate_key',
            nonce: shimMcp.nonce
        }, function (response) {
            if (response.success) {
                showKeyActive(response.data.password, response.data.base64);
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text(label);
            }
        }).fail(function () {
            alert('Request failed. Please try again.');
            $btn.prop('disabled', false).text(label);
        });
    });

    $(document).on('click', '#mcp-revoke-key', function () {
        if (!confirm('Are you sure you want to revoke the API key? MCP clients will stop working.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Revoking...');

        $.post(shimMcp.ajaxUrl, {
            action: 'shim_mcp_revoke_key',
            nonce: shimMcp.nonce
        }, function (response) {
            if (response.success) {
                showNoKey();
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Revoke Key');
            }
        }).fail(function () {
            alert('Request failed. Please try again.');
            $btn.prop('disabled', false).text('Revoke Key');
        });
    });

    $(document).on('click', '#mcp-test-connection', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Checking...');

        $.post(shimMcp.ajaxUrl, {
            action: 'shim_mcp_test_connection',
            nonce: shimMcp.nonce
        }, function (response) {
            if (response.success) {
                $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-inactive').addClass('mcp-dot-active');
                $('#mcp-connection-text').text(response.data && response.data.message ? response.data.message : 'Endpoint reachable; client credentials were not verified.');
            } else {
                $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
                $('#mcp-connection-text').text(response.data && response.data.message ? response.data.message : 'Connection failed');
            }
            $btn.prop('disabled', false).text('Check Endpoint');
        }).fail(function () {
            $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
            $('#mcp-connection-text').text('Request failed');
            $btn.prop('disabled', false).text('Check Endpoint');
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
