<?php declare(strict_types=1);

namespace Guest\Service\Controller;

use Guest\Controller\ApiController;
use Psr\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\Session;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Authentication\Adapter\PasswordAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;

class ApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // The user is automatically authenticated via api, but when option
        // "guest_login_session" is set, the user is authenticalted via the
        // local session too.
        $entityManager = $services->get('Omeka\EntityManager');
        $userRepository = $entityManager->getRepository('Omeka\Entity\User');

        $adapters = $services->get('Omeka\ApiAdapterManager');

        $storage = new DoctrineWrapper(new Session, $userRepository);
        $adapter = new PasswordAdapter($userRepository);
        $authServiceSession = new AuthenticationService($storage, $adapter);

        // The api returns a key without second factor, so the users that have
        // one, or that must set one, cannot log in via the api.
        $requireSecondFactor = function (\Omeka\Entity\User $user) use ($services): bool {
            $plugins = $services->get('ControllerPluginManager');
            if ($plugins->has('twoFactorLogin') && $plugins->get('twoFactorLogin')->requireSecondFactor($user)) {
                return true;
            }
            $registryName = 'TwoFactorTotp\Service\SecondFactorRegistry';
            if ($services->has($registryName)) {
                $registry = $services->get($registryName);
                return $registry->hasAnyEnrolled($user) || $registry->mustEnroll($user);
            }
            return false;
        };

        return new ApiController(
            $services->get('Omeka\Acl'),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\AuthenticationService'),
            $authServiceSession,
            $services->get('Config'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Paginator'),
            $adapters->get('sites'),
            $services->get('MvcTranslator'),
            $adapters->get('users'),
            $requireSecondFactor
        );
    }
}
