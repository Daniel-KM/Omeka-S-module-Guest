<?php declare(strict_types=1);
namespace Guest;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

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
$connection->exec($sql);

// Copy all guest user tokens.
$sql = <<<SQL
INSERT INTO guest_token
    (id, token, user_id, email, created, confirmed)
SELECT
    id, token, user_id, email, created, confirmed
FROM $table
;
SQL;
$connection->exec($sql);
