-- VoxlyOne — schema completo (seção 4 do documento de arquitetura v2.6)
-- Rodar no phpMyAdmin do hPanel, no banco criado para o projeto.
-- Engine InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci.

CREATE TABLE users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_id    VARCHAR(64)  NOT NULL UNIQUE,
  email        VARCHAR(255) NOT NULL UNIQUE,
  name         VARCHAR(120) NOT NULL,
  avatar_url   VARCHAR(500),
  -- plan (free/premium): adicionar na Fase 2, quando premium existir
  consented_at DATETIME NULL,          -- consentimento de gravação (RF-12)
  consent_version TINYINT UNSIGNED NOT NULL DEFAULT 0, -- texto aceito; ver CONSENT_VERSION
  blocked_at     DATETIME NULL,        -- bloqueado pelo painel administrativo
  blocked_reason VARCHAR(200) NULL,
  last_login_at  DATETIME NULL,
  login_count    INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_blocked (blocked_at),
  KEY idx_last_login (last_login_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  name       VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_cat (user_id, name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE phrases (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  category_id     INT UNSIGNED NULL,
  text_pt         VARCHAR(200) NULL,   -- opcional; text_en é o obrigatório
  text_en         VARCHAR(200) NOT NULL,
  phonetic_guide  VARCHAR(400) NULL,   -- fonética PT-BR é mais longa que o original
  level           ENUM('beginner','intermediate','advanced') NOT NULL,
  status          ENUM('not_started','in_progress','mastered')
                  NOT NULL DEFAULT 'not_started',
  best_score      DECIMAL(3,1) NULL,   -- 0.0 a 10.0
  attempts_count  INT UNSIGNED NOT NULL DEFAULT 0,
  mastered_at     DATETIME NULL,
  last_attempt_at DATETIME NULL,       -- ordenação da fila / skip
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_status (user_id, status),
  KEY idx_queue (user_id, status, last_attempt_at),
  KEY idx_category (category_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attempts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phrase_id     INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  score         DECIMAL(3,1) NOT NULL,
  heard         VARCHAR(500) NULL,     -- o que a IA ouviu (modelo pode ser verboso)
  feedback_json JSON NOT NULL,
  audio_seconds TINYINT UNSIGNED NULL, -- derivado: (bytes - 44) / 32000
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- O áudio da tentativa não fica aqui: só o aprovado (> 8) vai para recordings
  KEY idx_phrase (phrase_id),
  KEY idx_user (user_id),
  FOREIGN KEY (phrase_id) REFERENCES phrases(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  user_id INT UNSIGNED NOT NULL,
  action  ENUM('analyze','translate','tts') NOT NULL,
  day     DATE NOT NULL,
  count   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, action, day),
  KEY idx_action_day (action, day),    -- teto global sem full scan
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gravação aprovada ATUAL de cada frase (nota > 8). Uma por frase: quando uma
-- nova gravação também é aprovada, a linha é mantida e só o arquivo muda — assim
-- toda playlist que referencia esta linha passa a tocar o áudio novo sozinha.
CREATE TABLE recordings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  phrase_id     INT UNSIGNED NOT NULL,
  attempt_id    INT UNSIGNED NULL,       -- tentativa que gerou o áudio atual
  score         DECIMAL(3,1) NOT NULL,   -- sempre > 8.0
  file_name     VARCHAR(40) NOT NULL,    -- nome aleatório; muda a cada substituição
  audio_bytes   INT UNSIGNED NOT NULL,
  audio_seconds TINYINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_phrase (phrase_id),
  KEY idx_user (user_id),
  FOREIGN KEY (phrase_id)  REFERENCES phrases(id)  ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE playlists (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  name            VARCHAR(80) NOT NULL,
  description     VARCHAR(300) NULL,
  shuffle_enabled TINYINT(1) NOT NULL DEFAULT 0,
  repeat_enabled  TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_name (user_id, name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relacionamento, não cópia: o item aponta para a gravação. Excluir a playlist
-- ou o item nunca toca no arquivo; excluir a frase leva a gravação e os itens.
CREATE TABLE playlist_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  playlist_id  INT UNSIGNED NOT NULL,
  recording_id INT UNSIGNED NOT NULL,
  position     INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_playlist_recording (playlist_id, recording_id),
  KEY idx_order (playlist_id, position),
  KEY idx_recording (recording_id),
  FOREIGN KEY (playlist_id)  REFERENCES playlists(id)  ON DELETE CASCADE,
  FOREIGN KEY (recording_id) REFERENCES recordings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
