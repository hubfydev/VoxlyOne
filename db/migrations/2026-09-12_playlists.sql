-- VoxlyOne — migration: gravações aprovadas + playlists (12 set 2026)
-- Rodar UMA vez no phpMyAdmin, no banco que já está no ar.
-- O schema.sql já contém estas estruturas para instalações novas.

-- Versão do texto de consentimento aceito. A v1 dizia que o áudio era sempre
-- descartado; a v2 informa que gravações com nota > 8 ficam guardadas para as
-- playlists. Quem aceitou só a v1 vê o aviso de novo antes de gravar.
ALTER TABLE users
  ADD COLUMN consent_version TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER consented_at;

UPDATE users SET consent_version = 1 WHERE consented_at IS NOT NULL;

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
