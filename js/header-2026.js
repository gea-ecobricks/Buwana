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
