<?php declare(strict_types=1);

namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\GuestNavigationTranslator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GuestNavigationTranslatorFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        return new GuestNavigationTranslator(
            $services->get('MvcTranslator'),
            $services->get('Omeka\Site\NavigationLinkManager'),
            $services->get('ViewHelperManager')->get('Url')
        );
    }
}
