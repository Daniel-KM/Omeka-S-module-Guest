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

if (version_compare($oldVersion, '3.4.19', '<')) {
    // Update existing tables.
    $sqls = <<<'SQL'
DROP INDEX guest_token_idx ON `guest_token`;
DROP INDEX IDX_4AC9362FA76ED395 ON `guest_token`;
DROP INDEX IDX_4AC9362F5F37A13B ON `guest_token`;
ALTER TABLE `guest_token`
    CHANGE `id` `id` INT AUTO_INCREMENT NOT NULL,
    CHANGE `user_id` `user_id` INT NOT NULL AFTER `id`,
    CHANGE `email` `email` VARCHAR(255) NOT NULL AFTER `user_id`,
    CHANGE `token` `token` VARCHAR(255) NOT NULL AFTER `email`,
    CHANGE `confirmed` `confirmed` TINYINT(1) NOT NULL AFTER `token`,
    CHANGE `created` `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL AFTER `confirmed`,
    INDEX IDX_4AC9362FA76ED395 (`user_id`),
    INDEX IDX_4AC9362F5F37A13B (`token`);
ALTER TABLE `guest_token` ADD CONSTRAINT FK_4AC9362FA76ED395 FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
        }
    }
}
