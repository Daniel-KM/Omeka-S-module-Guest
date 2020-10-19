<?php declare(strict_types=1);
namespace Guest\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Entity\Site;
use Omeka\Entity\User;

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
     * @return Site[]|Site|null
     */
    public function __invoke(User $user, $firstSite = false)
    {
        if ($firstSite) {
            $sitePermission = $this->entityManager->getRepository(\Omeka\Entity\SitePermission::class)
                ->findOneBy(['user' => $user->getId()], ['id' => 'ASC']);
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
