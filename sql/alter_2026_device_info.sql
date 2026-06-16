-- Migration (non-destructive): add device info + last IP to existing `devices` tables.
-- Run ONCE on a database created before this change. Fresh installs already get these
-- columns from schema.sql, so skip there.
ALTER TABLE devices
  ADD COLUMN device_model VARCHAR(191) NULL,
  ADD COLUMN app_version  VARCHAR(32)  NULL,
  ADD COLUMN os_version   VARCHAR(32)  NULL,
  ADD COLUMN last_ip      VARCHAR(64)  NULL;
