<?php declare(strict_types=1);

/**
 * Bootstrap file for Guest module tests.
 *
 * Uses Common module Bootstrap helper for test setup.
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'Guest',
        // Optional: a second factor prevents the login via the api.
        '?TwoFactorAuth',
    ],
    'GuestTest',
    __DIR__ . '/GuestTest'
);
