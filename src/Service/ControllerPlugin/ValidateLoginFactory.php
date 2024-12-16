<?php declare(strict_types=1);

namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\ValidateLogin;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ValidateLoginFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');

        return new ValidateLogin(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('EventManager'),
            $plugins->get('messenger'),
            $services->get('Request'),
            $services->get('Omeka\Settings'),
            $plugins->has('twoFactorLogin') ? $plugins->get('twoFactorLogin') : null,
            $plugins->get('currentSite')(),
            $services->get('Config'),
            class_exists('UserNamesModule')
        );
    }
}
