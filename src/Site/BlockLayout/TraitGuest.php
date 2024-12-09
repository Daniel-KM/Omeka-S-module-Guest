<?php declare(strict_types=1);

namespace Guest\Site\BlockLayout;

use Laminas\Session\Container as SessionContainer;
use Laminas\View\Renderer\PhpRenderer;

trait TraitGuest
{
    /**
     * Redirect to admin or site according to the role of the user and setting.
     *
     * @return \Laminas\Http\Response
     *
     * Adapted:
     * @see \Guest\Controller\Site\AbstractGuestController::redirectToAdminOrSite()
     * @see \Guest\Site\BlockLayout\TraitGuest::redirectToAdminOrSite()
     * @see \SingleSignOn\Controller\SsoController::redirectToAdminOrSite()
     */
    protected function redirectToAdminOrSite(PhpRenderer $view): void
    {
        $params = $view->params();
        // Bypass settings if set in url query.
        $redirectUrl = $params->fromQuery('redirect_url')
            // Deprecated: replace query argument "redirect" by "redirect_url".
            ?: $params->fromQuery('redirect')
            ?: SessionContainer::getDefaultManager()->getStorage()->offsetGet('redirect_url');

        if (!$redirectUrl) {
            $redirect = $view->siteSetting('guest_redirect')
                ?: $view->setting('guest_redirect');
            switch ($redirect) {
                case empty($redirect):
                case 'home':
                    if ($view->userIsAllowed('Omeka\Controller\Admin\Index')) {
                        $redirectUrl = $view->url('admin', [], true);
                        break;
                    }
                    // no break.
                case 'site':
                    $siteSlug = $params->fromRoute('site-slug');
                    $redirectUrl = $siteSlug
                        ? $view->url('site', ['site-slug' => $siteSlug], true)
                        : $view->url('top');
                    break;
                case 'top':
                    $redirectUrl = $view->url('top');
                    break;
                case 'me':
                    $redirectUrl = $view->url('site/guest', ['action' => 'me'], [], true);
                    break;
                default:
                    $redirectUrl = $redirect;
                    break;
            }
        }

        header('Location: ' . $redirectUrl, true, 302);
        die();
    }
}
