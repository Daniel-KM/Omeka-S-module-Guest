<?php declare(strict_types=1);

namespace Guest\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Mvc\Controller\Plugin\Url;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\UserIsAllowed;
use Omeka\Settings\Settings;
use Omeka\Settings\UserSettings;

/**
 * Copy:
 * @see \Guest\Mvc\Controller\Plugin\UserRedirectUrl
 * @see \GuestPrivateRole\Mvc\Controller\Plugin\UserRedirectUrl
 */
class UserRedirectUrl extends AbstractPlugin
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var \Laminas\Mvc\Controller\Plugin\Url
     */
    protected $url;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\UserIsAllowed
     */
    protected $userIsAllowed;

    /**
     * @var \Omeka\Settings\UserSettings
     */
    protected $userSettings;

    /**
     * @var \Omeka\Entity\User|null
     */
    protected $user;

    public function __construct(
        ApiManager $api,
        Settings $settings,
        Url $url,
        UserIsAllowed $userIsAllowed,
        UserSettings $userSettings,
        ?User $user
    ) {
        $this->api = $api;
        $this->settings = $settings;
        $this->url = $url;
        $this->userIsAllowed = $userIsAllowed;
        $this->userSettings = $userSettings;
        $this->user = $user;
    }

    /**
     * Get the redirect url after login according to user settings.
     *
     * The url is stored in session.
     *
     * @see https://github.com/omeka/omeka-s/pull/1961
     *
     * Useful for:
     * @see \CAS\Module
     * @see \Guest\Module
     * @see \Ldap\Module
     * @see \SingleSignOn\Module
     * @see \UserNames\Module
     */
    public function __invoke(): string
    {
        if ($this->userIsAllowed->__invoke('Omeka\Controller\Admin\Index', 'browse')) {
            $redirectUrl = $this->url->fromRoute('admin');
        } elseif ($this->user) {
            $this->userSettings->setTargetId($this->user->getId());
            $defaultSite = (int) $this->userSettings->get('guest_site', $this->settings->get('default_site', 1));
            if ($defaultSite) {
                try {
                    /** @var \Omeka\Api\Representation\SiteRepresentation $site */
                    $site = $this->api->read('sites', ['id' => $defaultSite])->getContent();
                    $redirectUrl = $site->siteUrl();
                } catch (\Exception $e) {
                    $redirectUrl = $this->url->fromRoute('top');
                }
            } else {
                $redirectUrl = $this->url->fromRoute('top');
            }
        } else {
            $redirectUrl = $this->url->fromRoute('top');
        }

        $session = \Laminas\Session\Container::getDefaultManager()->getStorage();
        $session->offsetSet('redirect_url', $redirectUrl);
        return $redirectUrl;
    }
}
