<?php declare(strict_types=1);

namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\UserRedirectUrl;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserRedirectUrlFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');

        // Sometime, getController() doesn't work, so prepare all plugins early.

        return new UserRedirectUrl(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Settings'),
            $plugins->get('url'),
            $plugins->get('userIsAllowed'),
            $services->get('Omeka\Settings\User'),
            $services->get('Omeka\AuthenticationService')->getIdentity()
        );
    }
}
