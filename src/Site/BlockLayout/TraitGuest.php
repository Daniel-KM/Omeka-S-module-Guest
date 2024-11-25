<?php declare(strict_types=1);

namespace Guest\Site\BlockLayout;

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
     */
    protected function redirectToAdminOrSite(PhpRenderer $view): void
    {
        // Bypass settings if set in url query.
        $redirectUrl = $view->params()->fromQuery('redirect');

        if (!$redirectUrl) {
            $redirect = $view->siteSetting('guest_redirect') ?: $view->setting('guest_redirect');
            switch ($redirect) {
                case empty($redirect):
                case 'home':
                    if ($view->userIsAllowed('Omeka\Controller\Admin\Index')) {
                        $redirectUrl = $view->url('admin', [], true);
                        break;
                    }
                    // no break.
                case 'site':
                    $redirectUrl = $view->url('site', [], true);
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
