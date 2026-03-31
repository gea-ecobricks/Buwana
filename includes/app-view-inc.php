<?php
// app-view-inc.php
// Custom CSS and JS for the Delete App alert modal in app-view.php.
// Generic alert modal structure, CSS, and JS live in header-2026b.php,
// styles/header-2026.css, and js/header-2026.js respectively.
?>
<style>
/* ── Delete App modal — custom styles layered on generic .alert-modal-* ── */

.delete-app-warning {
    background: #fff3cd;
    border-left: 4px solid #c0392b;
    border-radius: 6px;
    padding: 12px 14px;
    font-size: 0.92rem;
    color: #5a2d0c;
}

.delete-app-confirm-label {
    font-size: 0.92rem;
    color: var(--subdued-text);
    margin: 0 0 6px;
}

.delete-app-confirm-input {
    width: 100%;
    box-sizing: border-box;
    padding: 9px 12px;
    border: 1.5px solid var(--subdued-text, #ccc);
    border-radius: 7px;
    font-size: 0.97rem;
    font-family: 'Mulish', sans-serif;
    background: var(--form-field-background);
    color: var(--text-color);
    transition: border-color 0.18s;
}

.delete-app-confirm-input:focus {
    outline: none;
    border-color: #c0392b;
}

.delete-app-trigger-btn {
    display: block;
    margin: 30px auto 20px;
    padding: 11px 28px;
    background: #c0392b;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'Mulish', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s;
}

.delete-app-trigger-btn:hover {
    background: #a93226;
}

.delete-app-section {
    text-align: center;
    margin-top: 10px;
    padding-bottom: 10px;
}
</style>

<script>
(function () {
    var APP_NAME   = <?php echo json_encode($app['app_display_name']); ?>;
    var APP_ID     = <?php echo intval($app_id); ?>;
    var CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    window.openDeleteAppModal = function () {
        openAlertModal({
            title: 'Delete "' + APP_NAME + '"?',
            body:
                '<div class="delete-app-warning">' +
                    '<strong>This action is permanent and cannot be undone.</strong> ' +
                    'All app data will be erased and any users currently registered with this app ' +
                    'will be stranded — they will lose access to features that depend on this app.' +
                '</div>' +
                '<p class="delete-app-confirm-label">To confirm, type the app name exactly:</p>' +
                '<input type="text" id="delete-confirm-input" class="delete-app-confirm-input" ' +
                       'placeholder="' + APP_NAME.replace(/"/g, '&quot;') + '" autocomplete="off">',
            actions:
                '<button type="button" class="alert-modal-btn alert-modal-btn-cancel" ' +
                        'onclick="closeAlertModal()">Cancel</button>' +
                '<button type="button" id="delete-confirm-btn" ' +
                        'class="alert-modal-btn alert-modal-btn-danger" disabled ' +
                        'onclick="confirmDeleteApp()">Delete App</button>',
            onOpen: function () {
                setTimeout(function () {
                    var input = document.getElementById('delete-confirm-input');
                    var btn   = document.getElementById('delete-confirm-btn');
                    if (input && btn) {
                        input.addEventListener('input', function () {
                            btn.disabled = (this.value.trim() !== APP_NAME);
                        });
                        input.focus();
                    }
                }, 60);
            }
        });
    };

    window.confirmDeleteApp = function () {
        var input = document.getElementById('delete-confirm-input');
        if (!input || input.value.trim() !== APP_NAME) return;

        var btn = document.getElementById('delete-confirm-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Deleting\u2026'; }

        fetch('../api/delete_app.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ app_id: APP_ID, csrf_token: CSRF_TOKEN })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                closeAlertModal();
                window.location.href = 'dashboard.php?deleted=1';
            } else {
                if (btn) { btn.disabled = false; btn.textContent = 'Delete App'; }
                openAlertModal({
                    title: 'Delete Failed',
                    body: '<p>' + (d.error || 'An unexpected error occurred. Please try again.') + '</p>',
                    actions: '<button type="button" class="alert-modal-btn alert-modal-btn-cancel" ' +
                                     'onclick="closeAlertModal()">Close</button>'
                });
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Delete App'; }
            openAlertModal({
                title: 'Network Error',
                body: '<p>Could not connect to the server. Please check your connection and try again.</p>',
                actions: '<button type="button" class="alert-modal-btn alert-modal-btn-cancel" ' +
                                 'onclick="closeAlertModal()">Close</button>'
            });
        });
    };
}());
</script>
