<?php declare(strict_types=1);

namespace Guest\Service;

use Guest\Authentication\Adapter\PasswordAdapter;
use Guest\Entity\GuestToken;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\Adapter\Callback;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\Authentication\Storage\Session;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Authentication\Adapter\KeyAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;

/**
 * Authentication service factory.
 */
class AuthenticationServiceFactory implements FactoryInterface
{
    /**
     * Create the authentication service.
     *
     * It is a copy of the Omeka service with a line to set the token repository
     * in order to validate only confirmed guest users.
     *
     * Furthermore, a check for api credentials allows local authentication for
     * api requests.
     *
     * @return AuthenticationService
     * @see \Omeka\Service\AuthenticationServiceFactory
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Mvc\Status $status */
        $status = $services->get('Omeka\Status');

        // Skip auth retrieval entirely if we're installing or migrating.
        if (!$status->isInstalled() ||
            ($status->needsVersionUpdate() && $status->needsMigration())
        ) {
            $storage = new NonPersistent;
            $adapter = new Callback(function () {
                return null;
            });
        } else {
            $entityManager = $services->get('Omeka\EntityManager');
            $userRepository = $entityManager->getRepository(User::class);

            $useApiKeyAuthentication = $status->isApiRequest();
            if ($useApiKeyAuthentication) {
                $request = $services->get('Application')->getMvcEvent()->getRequest();
                $useApiKeyAuthentication = $request->getQuery('key_identity') !== null
                    && $request->getQuery('key_credential') !== null;
            }

            if ($useApiKeyAuthentication) {
                // Authenticate using key for API requests with credentials.
                $keyRepository = $entityManager->getRepository(ApiKey::class);
                $storage = new DoctrineWrapper(new NonPersistent, $userRepository);
                $adapter = new KeyAdapter($keyRepository, $entityManager);
            } else {
                // Authenticate using user/password for all other requests.
                // The session storage is used for api requests too when
                // credentials are not provided.
                $tokenRepository = $entityManager->getRepository(GuestToken::class);
                $storage = new DoctrineWrapper(new Session, $userRepository);
                $adapter = new PasswordAdapter($userRepository, $tokenRepository);
            }
        }

        return new AuthenticationService($storage, $adapter);
    }
}
