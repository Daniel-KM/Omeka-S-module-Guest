<?php declare(strict_types=1);
namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\UserSites;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserSitesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new UserSites(
            $services->get('Omeka\EntityManager')
        );
    }
}
