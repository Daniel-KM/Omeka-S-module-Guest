<?php
namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\UserSites;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserSitesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new UserSites(
            $services->get('Omeka\EntityManager')
        );
    }
}
