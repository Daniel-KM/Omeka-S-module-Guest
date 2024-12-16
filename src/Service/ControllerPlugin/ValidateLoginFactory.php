<?php declare(strict_types=1);

namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\ValidateLogin;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Module\Manager as ModuleManager;

class ValidateLoginFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('UserNames');
        $hasModuleUserNames = $module
            && $module->getState() === ModuleManager::STATE_ACTIVE;

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
            $hasModuleUserNames
        );
    }
}
