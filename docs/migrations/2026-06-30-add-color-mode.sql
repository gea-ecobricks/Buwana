-- Migration: add canonical color-mode preference to users_tb
-- See docs/color-mode-policy.md
-- Safe to run once. color_mode holds the source-of-truth dark/light UI
-- preference, emitted as the `color_mode` JWT/userinfo claim.


