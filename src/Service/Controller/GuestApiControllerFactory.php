<?php declare(strict_types=1);

namespace Guest\Service\Controller;

use Guest\Controller\GuestApiController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class GuestApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // GuestApi uses local session and returns credential tokens for api.
        // In fact, for api, it may use local api instead of credentials.

        $adapters = $services->get('Omeka\ApiAdapterManager');

        return new GuestApiController(
            $services->get('Omeka\Acl'),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\AuthenticationService'),
            $services->get('Config'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Paginator'),
            $adapters->get('sites'),
            $services->get('MvcTranslator'),
            $adapters->get('users')
        );
    }
}
