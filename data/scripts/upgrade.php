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
// $config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
// $entityManager = $services->get('Omeka\EntityManager');
// $plugins = $services->get('ControllerPluginManager');
// $api = $plugins->get('api');
// $space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.4.1', '<')) {
    $settings->set('guest_open', $settings->get('guest_open') ? 'open' : 'closed');
}

if (version_compare($oldVersion, '3.4.3', '<')) {
    $settings->delete('guest_check_requested_with');
}

if (version_compare($oldVersion, '3.4.6', '<')) {
    $guestRedirect = $settings->get('guest_terms_redirect');
    $settings->set('guest_redirect', $guestRedirect === 'home' ? '/' : $guestRedirect);
    $settings->delete('guest_terms_redirect');
}
