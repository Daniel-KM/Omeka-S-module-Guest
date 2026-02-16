<?php declare(strict_types=1);

namespace GuestTest;

use Guest\Entity\GuestToken;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\User;

/**
 * Shared test helpers for Guest module tests.
 */
trait GuestTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array List of created user IDs for cleanup.
     */
    protected $createdUsers = [];

    /**
     * @var array List of created site IDs for cleanup.
     */
    protected $createdSites = [];

    /**
     * @var array List of created token IDs for cleanup.
     */
    protected $createdTokens = [];

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Login as a specific user.
     *
     * @param string $email
     * @param string $password
     * @return bool
     */
    protected function login(string $email, string $password): bool
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        $result = $auth->authenticate();
        return $result->isValid();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Get the currently authenticated user.
     *
     * @return User|null
     */
    protected function getCurrentUser(): ?User
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        return $auth->getIdentity();
    }

    /**
     * Check if a user is authenticated.
     *
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        return $auth->hasIdentity();
    }

    /**
     * Create a test site.
     *
     * @param string $slug
     * @param string $title
     * @param bool $isPublic
     * @return SiteRepresentation
     */
    protected function createSite(string $slug, string $title, bool $isPublic = true): SiteRepresentation
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => $title,
            'o:is_public' => $isPublic ? '1' : '0',
        ]);
        $site = $response->getContent();
        $this->createdSites[] = $site->id();
        return $site;
    }

    /**
     * Create a test user.
     *
     * @param string $email
     * @param string $name
     * @param string $role
     * @param string $password
     * @param bool $isActive
     * @return UserRepresentation
     */
    protected function createUser(
        string $email,
        string $name,
        string $role = 'global_admin',
        string $password = 'test',
        bool $isActive = true
    ): UserRepresentation {
        $response = $this->api()->create('users', [
            'o:email' => $email,
            'o:name' => $name,
            'o:role' => $role,
            'o:is_active' => $isActive ? '1' : '0',
        ]);
        $user = $response->getContent();
        $userEntity = $user->getEntity();
        $userEntity->setPassword($password);
        $this->getEntityManager()->persist($userEntity);
        $this->getEntityManager()->flush();
        $this->createdUsers[] = $user->id();
        return $user;
    }

    /**
     * Create a guest user with a GuestToken.
     *
     * @param string $email
     * @param string $name
     * @param string $password
     * @param bool $isConfirmed
     * @return UserRepresentation
     */
    protected function createGuest(
        string $email = 'guest@test.fr',
        string $name = 'Guest User',
        string $password = 'test',
        bool $isConfirmed = false
    ): UserRepresentation {
        $em = $this->getEntityManager();

        $response = $this->api()->create('users', [
            'o:email' => $email,
            'o:name' => $name,
            'o:role' => 'guest',
            'o:is_active' => true,
        ]);
        $user = $response->getContent();
        $userEntity = $user->getEntity();
        $userEntity->setPassword($password);

        $guestToken = new GuestToken();
        $guestToken->setEmail($email);
        $guestToken->setUser($userEntity);
        $guestToken->setToken(sha1('tOkenS@1t' . microtime() . random_bytes(8)));
        $guestToken->setConfirmed($isConfirmed);
        $guestToken->setCreated(new \DateTime());

        $em->persist($userEntity);
        $em->flush();
        $em->persist($guestToken);
        $em->flush();

        $this->createdUsers[] = $user->id();
        $this->createdTokens[] = $guestToken->getId();

        return $user;
    }

    /**
     * Create a GuestToken for an existing user.
     *
     * @param User $user
     * @param bool $isConfirmed
     * @return GuestToken
     */
    protected function createGuestToken(User $user, bool $isConfirmed = false): GuestToken
    {
        $em = $this->getEntityManager();

        $guestToken = new GuestToken();
        $guestToken->setEmail($user->getEmail());
        $guestToken->setUser($user);
        $guestToken->setToken(sha1('tOkenS@1t' . microtime() . random_bytes(8)));
        $guestToken->setConfirmed($isConfirmed);

        $em->persist($guestToken);
        $em->flush();

        $this->createdTokens[] = $guestToken->getId();

        return $guestToken;
    }

    /**
     * Get a GuestToken by email.
     *
     * @param string $email
     * @return GuestToken|null
     */
    protected function getGuestToken(string $email): ?GuestToken
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository(GuestToken::class);
        return $repository->findOneBy(['email' => $email]);
    }

    /**
     * Get all GuestTokens for an email.
     *
     * @param string $email
     * @return GuestToken[]
     */
    protected function getGuestTokens(string $email): array
    {
        $em = $this->getEntityManager();
        $repository = $em->getRepository(GuestToken::class);
        return $repository->findBy(['email' => $email]);
    }

    /**
     * Setup mock mailer to capture sent emails.
     *
     * @return MockMailer
     */
    protected function setupMockMailer(): Service\MockMailer
    {
        $serviceLocator = $this->getServiceLocator();
        $mockMailer = new Service\MockMailer(
            $serviceLocator->get('Omeka\Mailer')->getTransport(),
            $serviceLocator->get('ViewHelperManager'),
            $serviceLocator->get('Omeka\EntityManager'),
            []
        );

        $serviceLocator->setAllowOverride(true);
        $serviceLocator->setService('Omeka\Mailer', $mockMailer);
        $serviceLocator->setAllowOverride(false);

        return $mockMailer;
    }

    /**
     * Get the mock mailer instance.
     *
     * @return Service\MockMailer|null
     */
    protected function getMockMailer(): ?Service\MockMailer
    {
        $mailer = $this->getServiceLocator()->get('Omeka\Mailer');
        return $mailer instanceof Service\MockMailer ? $mailer : null;
    }

    /**
     * Get the last sent email message.
     *
     * @return \Laminas\Mail\Message|null
     */
    protected function getLastSentMessage()
    {
        $mockMailer = $this->getMockMailer();
        return $mockMailer ? $mockMailer->getMessage() : null;
    }

    /**
     * Dispatch a POST request.
     *
     * @param string $url
     * @param array $postData
     * @param bool $isXmlHttpRequest
     */
    protected function postDispatch(string $url, array $postData = [], bool $isXmlHttpRequest = false): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setPost(new \Laminas\Stdlib\Parameters($postData));

        if ($isXmlHttpRequest) {
            $this->getRequest()->getHeaders()->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        }

        $this->dispatch($url);
    }

    /**
     * Get a setting value.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getSetting(string $name, $default = null)
    {
        return $this->getServiceLocator()->get('Omeka\Settings')->get($name, $default);
    }

    /**
     * Set a setting value.
     *
     * @param string $name
     * @param mixed $value
     */
    protected function setSetting(string $name, $value): void
    {
        $this->getServiceLocator()->get('Omeka\Settings')->set($name, $value);
    }

    /**
     * Get a site setting value.
     *
     * @param string $name
     * @param int $siteId
     * @param mixed $default
     * @return mixed
     */
    protected function getSiteSetting(string $name, int $siteId, $default = null)
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($siteId);
        return $siteSettings->get($name, $default);
    }

    /**
     * Set a site setting value.
     *
     * @param string $name
     * @param mixed $value
     * @param int $siteId
     */
    protected function setSiteSetting(string $name, $value, int $siteId): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($siteId);
        $siteSettings->set($name, $value);
    }

    /**
     * Clean up all created test resources.
     */
    protected function cleanupResources(): void
    {
        $this->loginAdmin();
        $em = $this->getEntityManager();

        // Delete tokens first (FK constraint).
        foreach ($this->createdTokens as $tokenId) {
            try {
                $token = $em->find(GuestToken::class, $tokenId);
                if ($token) {
                    $em->remove($token);
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
        }
        $em->flush();
        $this->createdTokens = [];

        // Delete users.
        foreach ($this->createdUsers as $userId) {
            try {
                $this->api()->delete('users', $userId);
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
        }
        $this->createdUsers = [];

        // Delete sites.
        foreach ($this->createdSites as $siteId) {
            try {
                $this->api()->delete('sites', $siteId);
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
        }
        $this->createdSites = [];
    }
}
