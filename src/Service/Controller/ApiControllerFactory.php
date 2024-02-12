<?php declare(strict_types=1);

namespace Guest\Service\Controller;

use Guest\Controller\ApiController;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Authentication\Adapter\PasswordAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;

class ApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        // The user is automatically authenticated via api, but when an option
        // is set, the user should be authenticalted vai the local session too.
        $entityManager = $services->get('Omeka\EntityManager');
        $userRepository = $entityManager->getRepository('Omeka\Entity\User');
        $storage = new DoctrineWrapper(new Session, $userRepository);
        $adapter = new PasswordAdapter($userRepository);
        $authServiceSession = new AuthenticationService($storage, $adapter);
        return new ApiController(
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\AuthenticationService'),
            $authServiceSession,
            $services->get('Omeka\EntityManager'),
            $services->get('MvcTranslator'),
            $services->get('Omeka\ApiAdapterManager')->get('users'),
            $services->get('Config')
        );
    }
}
