<?php declare(strict_types=1);

namespace Guest\Service\BlockLayout;

use Guest\Site\BlockLayout\Register;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Module\Manager as ModuleManager;

class RegisterFactory implements FactoryInterface
{
    /**
     * Create the Register block layout service.
     *
     * @param ContainerInterface $services
     * @return Register
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('UserNames');
        $hasModuleUserNames = $module
            && $module->getState() === ModuleManager::STATE_ACTIVE;

        $plugins = $services->get('ControllerPluginManager');

        return new Register(
            $services->get('FormElementManager'),
            $plugins->get('messenger'),
            $hasModuleUserNames
        );
    }
}
