<!--FOOTER STARTS-->
<div id="footer-full" style="margin-top:0px;background-color: var(--footer-background);">

    <div class="vision-landscape" style="background-color: var(--footer-background);">
        <img src="../webps/vision-day-2025.webp" style="width:100%; margin-top:-2px;margin-bottom:10px;" loading="lazy" data-lang-id="400-visionscape-description" alt="We envision a great green transition from ways that pollute to ways that enrich.  And it starts with our plastic.">
    </div>

    <div class="footer-vision" data-lang-id="2000-buwana-vision">

        We envision a Transition in our Households, Communities and Enterprises to an ever Greener Harmony with Earth's Cycles.

    </div>



    <div class="footer-bottom">
        <div class="footer-conclusion">

<div class="footer-conclusion" data-lang-id="2000-we-track-and-disclose">We track and disclose our net-green ecological impact.  See our <a href="https://ecobricks.org/en/regenreports.php" target="_blank">Regen Reporting</a>.</a>
            </div>

            <div id="wcb" class="carbonbadge wcb-d"></div>

            <div class="footer-conclusion" data-lang-id="2000-no-big-tech">
                  We use no Big-Tech platforms, databases, or web services. The Buwana system is an open source CC-BY_SA project by the <a href="https://ecobricks.org/about.php">Global Ecobrick Alliance Earthen Enterprise</a>.  See the code for this page on Github:
            </div>

            <div class="footer-conclusion">
            ↳ <a href="https://github.com/gea-ecobricks/buwana/blob/main/<?php echo ($lang); ;?>/<?php echo ($name); ;?>" target="_blank">github.com/gea-ecobricks/buwana/blob/main/<?php echo ($lang); ;?>/<?php echo ($name); ;?></a>
            </div>
            <div class="footer-conclusion" data-lang-id="2000-copyright">
                        The Buwana, GEA, Earthen, AES and Gobrik logos and emblems are copyright 2010-2025 by the Global Ecobrick Alliance.
                    </div>

            <div style="margin-top:15px">
                <a rel="license" href="http://creativecommons.org/licenses/by-sa/4.0/"><img alt="Creative Commons BY SA 4.0 License" src="../icons/cc-by-sa.svg" style="width:200px;height:45px;border-width:0" loading="lazy"></a>
            </div>





        </div>

    </div>

</div>

	<!--FOOTER ENDS-->

<script>
  const lang = '<?php echo $lang; ?>';
  const page = '<?php echo $page; ?>';
  const version = '<?php echo $version; ?>';

  loadTranslationScripts(lang, page, () => {
    switchLanguage(lang); // Or your language rendering logic
  });
</script>




<script src="../scripts/website-carbon-badges.js" defer></script>



<script>
(function() {
    try {
        // Canonical key is color_mode; fall back to legacy keys once. See
        // docs/color-mode-policy.md
        function validMode(m){ return m === 'light' || m === 'dark'; }
        var savedTheme = localStorage.getItem('color_mode')
            || localStorage.getItem('dark-mode-toggle')
            || localStorage.getItem('user_dark_mode');
        const toggle = document.getElementById('dark-mode-toggle-5');
        const bannerElement = document.getElementById('top-page-image');

        let initialMode = 'light';
        if (validMode(savedTheme)) {
            initialMode = savedTheme;
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            initialMode = 'dark';
        }
        // Normalize storage onto the canonical key immediately.
        try { localStorage.setItem('color_mode', initialMode); } catch (e) {}

        // Persist a Buwana-side toggle to the server source of truth. No-op
        // (graceful) when no CSRF token / not logged in.
        function persistColorMode(mode) {
            try {
                var csrf = (typeof CSRF_TOKEN !== 'undefined') ? CSRF_TOKEN : null;
                if (!csrf) return;
                fetch('/api/set_color_mode.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mode: mode, csrf_token: csrf })
                }).catch(function(){});
            } catch (e) {}
        }

        if (toggle) {
            toggle.mode = initialMode;
        }
        document.documentElement.setAttribute('data-theme', initialMode);

        document.addEventListener('DOMContentLoaded', function() {
            const logoElement = document.querySelector('.the-app-logo');
            const wordmarkElement = document.getElementById('top-app-logo');

            function updateLogos() {
                const mode = document.documentElement.getAttribute('data-theme') || 'light';

                if (logoElement) {
                    const lightLogo = logoElement.getAttribute('data-light-logo');
                    const darkLogo = logoElement.getAttribute('data-dark-logo');
                    logoElement.style.transition = 'background-image 0.5s ease'; // ✨ Smooth transition
                    logoElement.style.backgroundImage = mode === 'dark' ? `url('${darkLogo}')` : `url('${lightLogo}')`;
                }

                if (wordmarkElement) {
                    const lightWordmark = wordmarkElement.getAttribute('data-light-wordmark');
                    const darkWordmark = wordmarkElement.getAttribute('data-dark-wordmark');
                    wordmarkElement.style.transition = 'background-image 0.5s ease'; // ✨ Smooth transition
                    wordmarkElement.style.backgroundImage = mode === 'dark' ? `url('${darkWordmark}')` : `url('${lightWordmark}')`;
                }
            }



        function updateBanner() {
            const mode = document.documentElement.getAttribute('data-theme') || 'light';

            if (bannerElement) {
                const lightImg = bannerElement.getAttribute('data-light-img');
                const darkImg = bannerElement.getAttribute('data-dark-img');
                bannerElement.style.transition = 'background-image 0.5s ease'; // Smooth fade
                bannerElement.style.backgroundImage = mode === 'dark' ? `url('${darkImg}')` : `url('${lightImg}')`;
            }
        }


            updateLogos(); // 🚀 Initial on load
            updateBanner();

            if (toggle) {
                toggle.addEventListener('colorschemechange', function(event) {
                    const mode = event.detail.colorScheme;
                    localStorage.setItem('color_mode', mode);
                    localStorage.setItem('dark-mode-toggle', mode); // keep component's own remembered value in sync
                    console.log('🌗 Saved user theme preference:', mode);
                    document.documentElement.setAttribute('data-theme', mode);

                    persistColorMode(mode); // 🌍 update server source of truth
                    updateLogos(); // 🔥 Update logos immediately on toggle!
                    updateBanner();
                });
            }
        });

    } catch (err) {
        console.warn('⚠️ Could not access localStorage for dark-mode-toggle.');
    }
})();
</script>




