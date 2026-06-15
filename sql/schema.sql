-- Enyak backend (Rembuk TV) — Fase 1 schema. MySQL 5.7+ / 8.
SET NAMES utf8mb4;

-- Subscriber devices. Entitlement (free/trial/premium) is COMPUTED from the dates
-- below at request time; `status` only tracks active vs banned.
CREATE TABLE IF NOT EXISTS devices (
  id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id               VARCHAR(64) NOT NULL UNIQUE,        -- ANDROID_ID
  status                  ENUM('active','banned') NOT NULL DEFAULT 'active',
  trial_expires_at        DATETIME NULL,
  subscription_expires_at DATETIME NULL,
  note_admin              VARCHAR(255) NULL,
  last_seen               DATETIME NULL,
  created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Channel catalog. stream_url is the REAL upstream URL (secret, never sent to the app).
CREATE TABLE IF NOT EXISTS channels (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(255) NOT NULL,
  logo_url            VARCHAR(1024) NULL,
  group_title         VARCHAR(191) NULL,
  stream_url          TEXT NOT NULL,
  stream_type         ENUM('hls','dash','other') NOT NULL DEFAULT 'other',
  is_free             TINYINT(1) NOT NULL DEFAULT 0,
  is_enabled          TINYINT(1) NOT NULL DEFAULT 1,
  sort_index          INT NOT NULL DEFAULT 0,
  drm_scheme          VARCHAR(32) NULL,    -- widevine | playready | clearkey
  drm_license_url     TEXT NULL,           -- license server URL (if any)
  drm_clearkey        TEXT NULL,           -- static ClearKey "kid:key,kid:key"
  drm_license_headers TEXT NULL,           -- JSON object {"Header":"Value"}
  headers             TEXT NULL,           -- JSON object {"User-Agent":"...","Referer":"..."}
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_enabled (is_enabled),
  INDEX idx_group (group_title),
  INDEX idx_sort (sort_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin accounts (used from Fase 3 dashboard).
CREATE TABLE IF NOT EXISTS admins (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(191) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(32) NOT NULL DEFAULT 'admin',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail for manual activations.
CREATE TABLE IF NOT EXISTS activation_logs (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id  BIGINT UNSIGNED NOT NULL,
  admin_id   BIGINT UNSIGNED NULL,
  action     VARCHAR(64) NOT NULL,
  old_expiry DATETIME NULL,
  new_expiry DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_device (device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- App-facing remote config + tunables (editable without redeploy).
CREATE TABLE IF NOT EXISTS settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
