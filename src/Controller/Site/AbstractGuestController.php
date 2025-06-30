<?php declare(strict_types=1);

namespace Guest\Controller\Site;

use Doctrine\ORM\EntityManager;
use Guest\Controller\TraitGuestController;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Permissions\Acl;

/**
 * Manage guests pages.
 */
abstract class AbstractGuestController extends AbstractActionController
{
    use TraitGuestController;

    /**
     * @var \Omeka\Permissions\Acl $acl
     */
    protected $acl;

    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var array
     */
    protected $config;

    public function __construct(
        Acl $acl,
        AuthenticationService $authenticationService,
        EntityManager $entityManager,
        array $config
    ) {
        $this->acl = $acl;
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    /**
     * @return \Laminas\Authentication\AuthenticationService
     */
    protected function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return array
     */
    protected function getConfig()
    {
        return $this->config;
    }
}
