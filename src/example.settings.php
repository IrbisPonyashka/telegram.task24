<?php

define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');


define('DB_HOST', 'localhost');
define('DB_LOGIN', 'root');
define('DB_PASSWORD', 'abc123456');
define('DB_NAME', 'sitemanager');

define('DB_TABLE_NAME', 'b24_auth_telegram_tasker');
define('DB_TG_TABLE_NAME', 'b24_telegram_tasker_users');

define('TG_BOT_API_KEY','1234567:asdfghjkl1234567qwerty');

define('C_REST_CLIENT_ID','app.zxcvbnmasdfg.123456');
define('C_REST_CLIENT_SECRET','QWERTYUIOPASDFGHJKLZXCVBNM');

define('C_REST_WEB_HOOK_URL','');//Application key
define('C_REST_IGNORE_SSL',false);//Application key
define('C_REST_BLOCK_LOG',false);//Application key


define('DOMAIN', $_REQUEST['DOMAIN'] ?? '');