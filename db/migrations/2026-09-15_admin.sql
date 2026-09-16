-- VoxlyOne — migration: painel administrativo (15 set 2026)
-- Rodar UMA vez no phpMyAdmin, ANTES de subir o código.
-- Pode rodar antes ou depois da 2026-09-15_tts.sql (são independentes).

-- Bloqueio e registro de acesso dos usuários do app
ALTER TABLE users
  ADD COLUMN blocked_at     DATETIME NULL AFTER consent_version,
  ADD COLUMN blocked_reason VARCHAR(200) NULL AFTER blocked_at,
  ADD COLUMN last_login_at  DATETIME NULL AFTER blocked_reason,
  ADD COLUMN login_count    INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login_at,
  ADD KEY idx_blocked (blocked_at),
  ADD KEY idx_last_login (last_login_at);

-- Administradores do painel. Senha inicial "adm123" (hash bcrypt abaixo):
-- o painel exibe um aviso até ela ser trocada.
CREATE TABLE admins (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username            VARCHAR(60)  NOT NULL UNIQUE,
  password_hash       VARCHAR(255) NOT NULL,
  password_changed_at DATETIME NULL,      -- NULL = ainda com a senha inicial
  last_login_at       DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admins (username, password_hash)
VALUES ('Adm', '$2y$12$iNDlwNH2j6xoZ4lux6dnOeS4fzo.y7mD5v5LKebbttoHbIfLEEwpC');

-- Tentativas de login no painel: trava força bruta e alimenta a tela de Acessos
CREATE TABLE admin_logins (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id       INT UNSIGNED NULL,
  username_tried VARCHAR(60)  NOT NULL,
  ip             VARCHAR(45)  NOT NULL,
  user_agent     VARCHAR(255) NULL,
  success        TINYINT(1)   NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip, created_at),
  KEY idx_user_time (username_tried, created_at),
  KEY idx_time (created_at),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uso da IA por chamada: tokens e custo calculado no momento da chamada.
-- user_id vira NULL se a conta for excluída — o custo histórico fica, anônimo.
CREATE TABLE ai_usage (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NULL,
  action              ENUM('analyze','translate','tts') NOT NULL,
  model               VARCHAR(60) NOT NULL,
  text_input_tokens   INT UNSIGNED NOT NULL DEFAULT 0,
  audio_input_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
  text_output_tokens  INT UNSIGNED NOT NULL DEFAULT 0,
  audio_output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
  cost_usd            DECIMAL(12,6) NOT NULL DEFAULT 0,
  estimated           TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = tokens estimados (TTS não devolve uso)
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at),
  KEY idx_action_time (action, created_at),
  KEY idx_time (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações editáveis pelo painel (preços por modelo, início do monitoramento)
CREATE TABLE app_settings (
  name       VARCHAR(80)  NOT NULL PRIMARY KEY,
  value      TEXT         NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (name, value) VALUES ('usage_tracking_since', NOW());
