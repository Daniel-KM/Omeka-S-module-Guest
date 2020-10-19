<?php declare(strict_types=1);
namespace Guest\Service\Controller\Site;

use Guest\Controller\Site\AnonymousController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AnonymousControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new AnonymousController(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('Config')
        );
    }
}
