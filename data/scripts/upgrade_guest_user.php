<?php
namespace Guest;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
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
$exists = $connection->query('SHOW TABLES LIKE "guest_user_token";');
if ($exists) {
    $table = 'guest_user_token';
} else {
    $exists = $connection->query('SHOW TABLES LIKE "guest_user_tokens";');
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
