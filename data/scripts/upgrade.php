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
$translate = $plugins->get('translate');
$urlPlugin = $plugins->get('url');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

$localConfig = require dirname(__DIR__, 2) . '/config/module.config.php';

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.54')) {
    $message = new Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.54'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

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

if (version_compare($oldVersion, '3.4.21', '<')) {
    // Integration of module Guest Api.
    // The module should be uninstalled manually.
    // TODO Add a warning about presence of module Guest Api with a message in settings.
    $settings->set('guest_register_site', (bool) $settings->get('guestapi_register_site', false));
    $settings->set('guest_register_email_is_valid', (bool) $settings->get('guestapi_register_email_is_valid', false));
    $settings->set('guest_login_roles', $settings->get('guestapi_login_roles', ['annotator', 'contributor', 'guest']));
    $settings->set('guest_login_session', (bool) $settings->get('guestapi_login_session', false));
    $settings->set('guest_cors', $settings->get('guestapi_cors', []));

    if ($this->isModuleActive('GuestApi')) {
        $this->disableModule('GuestApi');
        $message = new \Common\Stdlib\PsrMessage(
            'This module integrates the features from module GuestApi, that is no longer needed. The config were merged (texts of messages) so you should check them in {link_url}main settings{link_end}. The module was disabled, but you should uninstall the module once params are checked.', // @translate
            [
                'link_url' => sprintf('<a href="%s">', $urlPlugin->fromRoute('admin') . '/setting#guest'),
                'link_end' => '</a>',
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);
    }
}

if (version_compare($oldVersion, '3.4.22', '<')) {
    // Update existing tables.
    $sqls = <<<'SQL'
ALTER TABLE `guest_token`
CHANGE `email` `email` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `user_id`,
CHANGE `token` `token` varchar(190) COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `email`;
SQL;
    foreach (explode(";\n", $sqls) as $sql) {
        try {
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
        }
    }

    $message = $settings->get('guest_message_confirm_registration_email');
    $old = '<p>Hi {user_name},</p>
<p>We are happy to open your account on <a href="{site_url}">{site_title}</a> ({main_title}).</p>
<p>You can now login and discover the site.</p>';
    if ($message === $old) {
        $settings->set('guest_message_confirm_registration_email', $localConfig['settings']['guest_message_confirm_registration_email']);
    }

    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $message = $siteSettings->get('guest_message_confirm_registration_email');
        if ($message === $old) {
            $siteSettings->set('guest_message_confirm_registration_email', $localConfig['site_settings']['guest_message_confirm_registration_email']);
        }
    }
}

if (version_compare($oldVersion, '3.4.24', '<')) {
    // On update, this option is set to true to update themes if they take it in account.
    $settings->set('guest_append_links_to_login_view', true);
    $message = new \Common\Stdlib\PsrMessage(
        'A new option allows to display the list of external accounts on login page.' // @translate
    );
    $messenger->addSuccess($message);
}
