<?php declare(strict_types=1);

namespace Guest;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

/** @var \Omeka\Module\Manager $moduleManager */
$moduleManager = $services->get('Omeka\ModuleManager');
$module = $moduleManager->getModule('GuestUser');
$hasGuestUser = (bool) $module;
if (!$hasGuestUser) {
    return;
}

// Check if the table guest_user_token exists.
$exists = $connection->executeQuery('SHOW TABLES LIKE "guest_user_token";');
if ($exists) {
    $table = 'guest_user_token';
} else {
    $exists = $connection->executeQuery('SHOW TABLES LIKE "guest_user_tokens";');
    if ($exists) {
        $table = 'guest_user_tokens';
    } else {
        return;
    }
}

// Copy all settings.
$sql = <<<SQL
INSERT INTO setting(id, value)
SELECT REPLACE(s.id, "guestuser_", "guest_"), value
FROM setting s
WHERE id LIKE "guestuser\_%"
ON DUPLICATE KEY UPDATE
    id = REPLACE(s.id, "guestuser_", "guest_"),
    value = s.value
;
SQL;
$connection->executeStatement($sql);

// Copy all guest user tokens.
$sql = <<<SQL
INSERT INTO guest_token
    (id, token, user_id, email, created, confirmed)
SELECT
    id, token, user_id, email, created, confirmed
FROM $table
;
SQL;
$connection->executeStatement($sql);
