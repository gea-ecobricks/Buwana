-- Migration: add canonical color-mode preference to users_tb
-- See docs/color-mode-policy.md
-- Safe to run once. color_mode holds the source-of-truth dark/light UI
-- preference, emitted as the `color_mode` JWT/userinfo claim.

ALTER TABLE `users_tb`
  ADD COLUMN `color_mode` VARCHAR(5) NOT NULL DEFAULT 'light'
  COMMENT 'Canonical dark/light UI preference. Values: light | dark'
  AFTER `time_zone`;
