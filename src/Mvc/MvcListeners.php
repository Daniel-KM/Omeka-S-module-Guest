<?php declare(strict_types=1);

namespace Guest\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;

class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'redirectToAcceptTerms']
        );
    }

    public function redirectToAcceptTerms(MvcEvent $event)
    {
        $services = $event->getApplication()->getServiceManager();
        $auth = $services->get('Omeka\AuthenticationService');

        if (!$auth->hasIdentity()) {
            return;
        }

        $user = $auth->getIdentity();
        // Manage sub guest roles, for example "guest_ext" or "guest_private".
        // TODO Manage rights with multi-roles or permissions.
        if (substr($user->getRole(), 0, 5) !== \Guest\Permissions\Acl::ROLE_GUEST) {
            return;
        }

        /** @var \Omeka\Settings\Settings $settings */
        $settings = $services->get('Omeka\Settings');
        if ($settings->get('guest_terms_skip')) {
            return;
        }

        /** @var \Omeka\Settings\UserSettings $userSettings */
        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($user->getId());
        if ($userSettings->get('guest_agreed_terms')) {
            return;
        }

        // The default site may not be set yet, but it is required to use site
        // settings, so get it first.

        /** @var \Omeka\Api\Manager $api */
        $api = $services->get('Omeka\ApiManager');
        $siteId = null;
        $routeMatch = $event->getRouteMatch();
        if ($routeMatch->getParam('__SITE__')) {
            $siteSlug = $routeMatch->getParam('site-slug');
            try {
                $siteId = $api->read('sites', ['slug' => $siteSlug], [], ['responseContent' => 'resource'])->getContent()->getId();
            } catch (\Exception $e) {
                // Nothing.
            }
        }
        if (empty($siteId)) {
            // Get first site when no site is set, for example on main login page.
            $defaultSiteId = (int) $settings->get('default_site');
            if ($defaultSiteId) {
                try {
                    $siteSlug = $api->read('sites', ['id' => $defaultSiteId], [], ['responseContent' => 'resource'])->getContent()->getSlug();
                    $siteId = $defaultSiteId;
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    // Nothing.
                }
            }
            if (empty($siteId)) {
                // No search one, this is the api manager.
                // Check of rights (is public) is automatically managed.
                $site = $api->search('sites', ['sort_by' => 'id', 'limit' => 1], ['responseContent' => 'resource'])->getContent() ?: null;
                if ($site) {
                    $site = reset($site);
                    $siteId = $site->getId();
                    $siteSlug = $site->getSlug();
                }
            }
        }

        // Use the default page if no site.
        $page = $settings->get('guest_terms_page');
        $regex = $settings->get('guest_terms_request_regex');
        if ($site) {
            /** @var \Omeka\Settings\SiteSettings $siteSettings */
            $siteSettings = $services->get('Omeka\Settings\Site');
            $page = $siteSettings->get('guest_terms_page', $page, $siteId) ?: $page;
            $regex = $siteSettings->get('guest_terms_request_regex', $regex, $siteId) ?: $regex;
            if ($page) {
                $regex .= ($regex ? '|' : '') . 'page/' . $page;
            }
        }

        $request = $event->getRequest();
        $requestUri = $request->getRequestUri();
        $requestUriBase = strtok($requestUri, '?');

        $regex = '~/(|' . $regex . '|maintenance|login|logout|migrate|guest/accept-terms)$~';
        if (preg_match($regex, $requestUriBase)) {
            return;
        }

        $baseUrl = $request->getBaseUrl() ?? '';
        $acceptUri = $baseUrl . '/s/' . $siteSlug . '/guest/accept-terms';

        /** @var \Laminas\Http\Response $response */
        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $acceptUri);
        $response->setStatusCode(302);
        $response->sendHeaders();
        return $response;
    }
}
