<?php declare(strict_types=1);

namespace Guest\Mvc\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class UserRedirectUrl extends AbstractPlugin
{
    /**
     * Get the redirect url after login according to user settings.
     *
     * The url is stored in session.
     *
     * @see https://github.com/omeka/omeka-s/pull/1961
     *
     * Useful for:
     * @see \CAS
     * @see \Guest
     * @see \GuestApi
     * @see \Ldap
     * @see \SingleSignOn
     * @see \UserNames
     */
    public function __invoke(): string
    {
        $plugins = $this->getController()->getPluginManager();

        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Mvc\Controller\Plugin\Settings $settings
         * @var \Omeka\Mvc\Controller\Plugin\Settings $userSettings
         * @var \Laminas\Mvc\Controller\Plugin\Url $url
         * @var \Omeka\Entity\User $user
         * @var \Omeka\Api\Representation\SiteRepresentation $site
         */
        $url = $plugins->get('url');
        $userIsAllowed= $plugins->get('userIsAllowed');

        if ($userIsAllowed('Omeka\Controller\Admin\Index', 'browse')) {
            $redirectUrl = $url->fromRoute('admin');
        } else {
            $user = $plugins->get('identity')();
            $settings = $plugins->get('settings')();
            $userSettings = $plugins->get('userSettings')();
            $userSettings->setTargetId($user->getId());
            $defaultSite = (int) $userSettings->get('guest_site', $settings->get('default_site', 1));
            if ($defaultSite) {
                $api = $plugins->get('api');
                try {
                    $site = $api->read('sites', ['id' => $defaultSite])->getContent();
                    $redirectUrl = $site->siteUrl();
                } catch (\Exception $e) {
                    $redirectUrl = $url->fromRoute('top');
                }
            } else {
                $redirectUrl = $url->fromRoute('top');
            }
        }

        $session = \Laminas\Session\Container::getDefaultManager()->getStorage();
        $session->offsetSet('redirect_url', $redirectUrl);
        return $redirectUrl;
    }
}
