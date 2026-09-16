-- VoxlyOne — migration: voz neural na pronúncia de referência (15 set 2026)
-- Rodar UMA vez no phpMyAdmin, ANTES de subir o código.
-- Sem ela o app continua funcionando, mas o botão "Ouvir pronúncia" usa sempre
-- a voz do aparelho (a cota 'tts' não consegue ser registrada).

-- Nova ação no limite diário: gerar o áudio da pronúncia (OpenAI TTS)
ALTER TABLE rate_limits
  MODIFY action ENUM('analyze','translate','tts') NOT NULL;
