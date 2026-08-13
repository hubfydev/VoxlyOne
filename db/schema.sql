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
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  -- Áudio NÃO é armazenado no MVP (descartado após análise)
  KEY idx_phrase (phrase_id),
  KEY idx_user (user_id),
  FOREIGN KEY (phrase_id) REFERENCES phrases(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  user_id INT UNSIGNED NOT NULL,
  action  ENUM('analyze','translate') NOT NULL,
  day     DATE NOT NULL,
  count   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id, action, day),
  KEY idx_action_day (action, day),    -- teto global sem full scan
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
