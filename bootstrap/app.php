<?php

declare(strict_types=1);

use Fahri\LapakId\Core\Env;

Env::load(dirname(__DIR__) . '/.env');

if (session_status() === PHP_SESSION_NONE) {
    session_name(Env::get('SESSION_NAME', 'lapakid_session'));
    session_start();
}
