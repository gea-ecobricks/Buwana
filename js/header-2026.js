/* ── header-2026.js ────────────────────────────────────────────────────────
   Shared sidebar menu JS for header-2026.
   Include in both GoBrik and Buwana — no page-specific references here.

   On viewports > 1200px the menu slides in as a 360px sidebar and pushes
   the page content to the right.  On smaller viewports the original
   full-screen overlay behaviour is preserved.
-------------------------------------------------------------------------- */

function openSideMenu() {
    var menu        = document.getElementById('main-menu-overlay');
    var pageContent = document.getElementById('page-content');
    var closeBtn    = menu.querySelector('.sidebar-close-btn');

    if (window.innerWidth > 1200) {
        // ── Desktop: sidebar panel mode ──
        menu.classList.add('sidebar-panel');
        menu.style.display = '';          // let CSS flex handle it
        menu.style.width   = '';          // CSS sets 360px via .sidebar-panel
        if (closeBtn) closeBtn.style.display = 'flex';

        // Hide hamburger & raise header above sidebar via body class
        document.body.classList.add('sidebar-is-open');

        // Trigger transition on next frame
        requestAnimationFrame(function() {
            menu.classList.add('sidebar-open');
            if (pageContent) pageContent.classList.add('sidebar-pushed');
        });

        // No body scroll lock — page remains scrollable alongside sidebar
    } else {
        // ── Mobile/tablet: original full-screen overlay ──
        menu.classList.remove('sidebar-panel', 'sidebar-open');
        if (closeBtn) closeBtn.style.display = 'none';
        menu.style.width   = '100%';
        menu.style.display = 'block';
        document.body.style.overflowY  = 'hidden';
        document.body.style.maxHeight  = '101vh';

        // Close when clicking outside the menu content
        menu.addEventListener('click', function overlayClickClose(e) {
            if (e.target === menu) {
                closeSettings();
                menu.removeEventListener('click', overlayClickClose);
            }
        }, true);
    }
}

function closeSettings() {
    var menu        = document.getElementById('main-menu-overlay');
    var pageContent = document.getElementById('page-content');
    var closeBtn    = menu.querySelector('.sidebar-close-btn');

    if (menu.classList.contains('sidebar-panel')) {
        // ── Desktop: reverse sidebar slide ──
        menu.classList.remove('sidebar-open');
        if (pageContent) pageContent.classList.remove('sidebar-pushed');

        // Hide element after transition completes
        menu.addEventListener('transitionend', function onEnd() {
            if (!menu.classList.contains('sidebar-open')) {
                menu.style.display = 'none';
                menu.classList.remove('sidebar-panel');
                if (closeBtn) closeBtn.style.display = 'none';
                // Restore hamburger & header position
                document.body.classList.remove('sidebar-is-open');
            }
            menu.removeEventListener('transitionend', onEnd);
        });
    } else {
        // ── Mobile/tablet: original close ──
        menu.style.width         = '0%';
        document.body.style.overflowY = 'unset';
        document.body.style.maxHeight = 'unset';
    }
}

// Also expose as closeMainMenu for backward compatibility with pages that
// call closeMainMenu() in their onclick attributes.
function closeMainMenu() { closeSettings(); }

window.openSideMenu  = openSideMenu;
window.closeSettings = closeSettings;
window.closeMainMenu = closeMainMenu;

// ── Expand-panel overrides for header-2026b ────────────────────────────────
// Loaded after core-2025.js. Overrides showLangSelector / showLoginSelector /
// hideLangSelector / hideLoginSelector / loginOrMenu so that clicking the lang
// or app icon expands #settings-buttons downward instead of showing the old
// separate slider elements.  Also closes the expand panel if the user clicks
// outside or toggles the settings bar closed.
// --------------------------------------------------------------------------

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var expandPanel    = document.getElementById('settings-expand-panel');
        var settingsButtons = document.getElementById('settings-buttons');

        // Only activate on pages that use the 2026b expand-panel structure.
        if (!expandPanel || !settingsButtons) return;

        // Duration (ms) of the bapFadeUp CSS animation — must match header-2026.css.
        var FADE_MS = 160;

        // ── Open a named grid section inside the expand panel ──────────────
        function openSection(sectionId) {
            var target = document.getElementById(sectionId);
            if (!target) return;

            // Same button clicked again → toggle closed
            if (target.classList.contains('grid-visible')) {
                closePanel();
                return;
            }

            // Instantly reset any other open section (no animation needed)
            expandPanel.querySelectorAll('.expand-grid-section').forEach(function (s) {
                s.classList.remove('grid-visible', 'grid-hiding');
            });

            // Expand the wrapper and show the section
            expandPanel.classList.add('panel-open');
            settingsButtons.classList.add('panel-expanded');
            target.classList.add('grid-visible');

            // Defer click-outside listener so this click doesn't immediately close
            setTimeout(function () {
                document.addEventListener('click', clickOutsideHandler);
            }, 0);
        }

        // ── Collapse with animation: fade content out, then shrink wrapper ──
        function closePanel() {
            var visible = expandPanel.querySelector('.grid-visible');
            if (!visible) return;

            // 1. Fade the grid content out
            visible.classList.remove('grid-visible');
            visible.classList.add('grid-hiding');

            // 2. After the fade, collapse the max-height wrapper
            setTimeout(function () {
                visible.classList.remove('grid-hiding');
                expandPanel.classList.remove('panel-open');
                settingsButtons.classList.remove('panel-expanded');
            }, FADE_MS);

            document.removeEventListener('click', clickOutsideHandler);
        }

        function clickOutsideHandler(e) {
            if (!settingsButtons.contains(e.target)) {
                closePanel();
            }
        }

        // ── Override core-2025.js public API ──────────────────────────────
        window.showLangSelector  = function () { openSection('language-menu-slider'); };
        window.hideLangSelector  = function () { closePanel(); };
        window.showLoginSelector = function () { openSection('login-menu-slider'); };
        window.hideLoginSelector = function () { closePanel(); };

        // Directly bind language button — bypasses inline onclick resolution ambiguity
        // caused by the hoisted global function hideLangSelector() in core-2025.js
        var langCodeBtn = document.getElementById('language-code');
        if (langCodeBtn) {
            langCodeBtn.onclick = function () { openSection('language-menu-slider'); };
        }

        window.loginOrMenu = function (loginUrl, loggedIn) {
            if (loggedIn) {
                openSection('login-menu-slider');
            } else {
                window.location.href = loginUrl;
            }
        };

        // Close expand panel when user slides the settings bar closed
        var origToggle = window.toggleSettingsMenu;
        window.toggleSettingsMenu = function () {
            closePanel();
            if (typeof origToggle === 'function') origToggle();
        };
    });
}());
