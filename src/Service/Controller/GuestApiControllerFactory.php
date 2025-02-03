<?php declare(strict_types=1);

namespace Guest\Service\Controller;

use Guest\Controller\GuestApiController;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Authentication\Adapter\PasswordAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;

class GuestApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // The user is automatically authenticated via api, but when an option
        // is set, the user should be authenticalted vai the local session too.
        $entityManager = $services->get('Omeka\EntityManager');
        $userRepository = $entityManager->getRepository('Omeka\Entity\User');

        $adapters = $services->get('Omeka\ApiAdapterManager');

        $storage = new DoctrineWrapper(new Session, $userRepository);
        $adapter = new PasswordAdapter($userRepository);
        $authServiceSession = new AuthenticationService($storage, $adapter);

        return new GuestApiController(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\AuthenticationService'),
            $authServiceSession,
            $services->get('Config'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Paginator'),
            $adapters->get('sites'),
            $services->get('MvcTranslator'),
            $adapters->get('users')
        );
    }
}
