<?php declare(strict_types=1);
namespace Guest\Service\Controller\Site;

use Guest\Controller\Site\GuestController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GuestControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GuestController(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('Config')
        );
    }
}
