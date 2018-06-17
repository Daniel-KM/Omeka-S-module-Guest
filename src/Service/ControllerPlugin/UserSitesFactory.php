<?php
namespace GuestUser\Service\ControllerPlugin;

use GuestUser\Mvc\Controller\Plugin\UserSites;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserSitesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        return new UserSites($entityManager);
    }
}
