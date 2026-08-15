<?php
declare(strict_types=1);

/**
 * MODELO do config.php. Copie para /home/USUARIO/config/config.php no servidor,
 * FORA do public_html, e preencha os valores reais.
 * Este arquivo de exemplo pode ficar no repositório; o config.php real NUNCA.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'uXXXX_voxlyone');
define('DB_USER', 'uXXXX_voxly');
define('DB_PASS', '********');

define('GOOGLE_CLIENT_ID',     '....apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '********');
define('GOOGLE_REDIRECT_URI',  'https://voxly.hubfy.app/auth/callback.php');

define('OPENAI_API_KEY', 'sk-********');

// Modelo de áudio validado no spike do Dia 1 (11/08/2026).
// Alternativa mais barata, porém menos coerente no campo 'heard': 'gpt-audio-mini'
define('OPENAI_AUDIO_MODEL', 'gpt-audio-1.5');

// Modelo de texto para tradução + geração da fonética aproximada
define('OPENAI_TEXT_MODEL', 'gpt-4o-mini');

// Limites diários por usuário
define('LIMIT_ANALYZE_PER_DAY',   30);
define('LIMIT_TRANSLATE_PER_DAY', 50);

// Botão de emergência: teto global de análises/dia somando TODOS os usuários.
// Com o custo medido (~US$ 0,004/análise), 300 ≈ US$ 1,17/dia no pior caso.
define('GLOBAL_DAILY_ANALYSES', 300);

// Nota mínima para avançar para a próxima frase; 10 é o selo 'Dominada'
define('SCORE_TO_ADVANCE', 8.0);

/**
 * Fuso horário de referência do produto (sediado nos EUA).
 * Define quando o limite diário de análises zera e como as datas são exibidas.
 * O PHP e a sessão do MySQL são alinhados a ele — ver includes/db.php.
 */
define('APP_TIMEZONE', 'America/New_York');

define('APP_URL',  'https://voxly.hubfy.app');
define('LOG_FILE', __DIR__ . '/../logs/app.log');
