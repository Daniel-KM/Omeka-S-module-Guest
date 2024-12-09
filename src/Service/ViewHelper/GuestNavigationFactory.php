<?php declare(strict_types=1);

namespace Guest\Service\ViewHelper;

use Guest\View\Helper\GuestNavigation;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GuestNavigationFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GuestNavigation(
            $services->get('ControllerPluginManager')->get('guestNavigationTranslator'),
            $services
        );
    }
}
