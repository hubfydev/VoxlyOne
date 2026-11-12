-- Executar antes de publicar o código. Preserva gravações e playlists existentes.
ALTER TABLE users
  ADD COLUMN playlist_score_threshold DECIMAL(3,1) NOT NULL DEFAULT 8.0,
  ADD CONSTRAINT chk_playlist_score_threshold
    CHECK (playlist_score_threshold BETWEEN 4.0 AND 9.0);
