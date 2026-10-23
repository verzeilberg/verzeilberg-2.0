<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Start every run with no rate limits used up (failed logins, registrations, ...) by an earlier run
passthru(sprintf('php %s/bin/console cache:pool:clear cache.rate_limiter --env=test --quiet', escapeshellarg(dirname(__DIR__))));

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
