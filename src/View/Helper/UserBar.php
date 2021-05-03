<?php declare(strict_types=1);

namespace Guest\View\Helper;

/**
 * View helper for rendering the user bar.
 *
 * Same of the Omeka user bar, except for guest users, who cannot go admin, and
 * a default different link for the account.
 */
class UserBar extends \Omeka\View\Helper\UserBar
{
    /**
     * The default partial view script when the user has no right to go admin.
     */
    const PARTIAL_NAME_GUEST = 'common/user-bar-guest';

    public function __invoke($partialName = null)
    {
        $view = $this->getView();

        $site = $view->vars()->site;
        if (empty($site)) {
            return '';
        }

        $showUserBar = $view->siteSetting('show_user_bar', 0);
        if ($showUserBar == -1) {
            return '';
        }

        $user = $view->identity();
        if ($showUserBar != 1 && !$user) {
            return '';
        }

        if ($user) {
            $hasAdminRights = $view->userIsAllowed('Omeka\Controller\Admin\Index', 'index');
            if ($hasAdminRights) {
                $links = $this->links($view, $site, $user);
                $partialName = $partialName ?: self::PARTIAL_NAME;
            } else {
                $links = [];
                $partialName = $partialName ?: self::PARTIAL_NAME_GUEST;
            }
        } else {
            $links = [];
            $partialName = $partialName ?: self::PARTIAL_NAME;
        }

        return $view->partial(
            $partialName,
            [
                'site' => $site,
                'user' => $user,
                'links' => $links,
            ]
        );
    }
}
