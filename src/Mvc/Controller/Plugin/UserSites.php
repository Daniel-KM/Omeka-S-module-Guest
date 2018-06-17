<?php
namespace GuestUser\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Omeka\Entity\Site;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class UserSites extends AbstractPlugin
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Get one or all the sites of a user.
     *
     * @todo Optimize the query to get the sites of a user via site permissions.
     *
     * @param User $user
     * @param bool $firstSite
     * @return Site[]|Site
     */
    public function __invoke(User $user, $firstSite = false)
    {
        if ($firstSite) {
            $sitePermission = $this->entityManager->getRepository(\Omeka\Entity\SitePermission::class)
                ->findOneBy(['user' => $user->getId()]);
            return $sitePermission
                ? $sitePermission->getSite()
                : null;
        }

        $sitePermissions = $this->entityManager->getRepository(\Omeka\Entity\SitePermission::class)
            ->findBy(['user' => $user->getId()]);
        $sites = [];
        foreach ($sitePermissions as $sitePermission) {
            $sites[] = $sitePermission->getSite();
        }
        return $sites;
    }
}
