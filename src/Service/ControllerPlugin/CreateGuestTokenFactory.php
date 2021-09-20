<?php declare(strict_types=1);
namespace Guest\Service\ControllerPlugin;

use Guest\Mvc\Controller\Plugin\CreateGuestToken;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CreateGuestTokenFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CreateGuestToken(
            $services->get('Omeka\EntityManager')
        );
    }
}
