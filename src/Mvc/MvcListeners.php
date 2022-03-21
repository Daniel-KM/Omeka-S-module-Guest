<?php
namespace Guest\Mvc;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\Mvc\MvcEvent;

class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1)
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
        if ($user->getRole() !== \Guest\Permissions\Acl::ROLE_GUEST) {
            return;
        }

        $userSettings = $services->get('Omeka\Settings\User');
        if ($userSettings->get('guest_agreed_terms')) {
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $page = $settings->get('guest_terms_page');

        $routeMatch = $event->getRouteMatch();
        if ($routeMatch->getParam('__SITE__')) {
            /** @var \Omeka\Settings\SiteSettings $siteSettings */
            $siteSettings = $services->get('Omeka\Settings\Site');
            // The target id may be unavailable when the default site isn't set.
            try {
                $page = $siteSettings->get('guest_terms_page') ?: $page;
            } catch (\Omeka\Service\Exception\RuntimeException $e) {
                // Keep page.
            }
        }

        $request = $event->getRequest();
        $requestUri = $request->getRequestUri();
        $requestUriBase = strtok($requestUri, '?');

        $regex = $settings->get('guest_terms_request_regex');
        if ($page) {
            $regex .= ($regex ? '|' : '') . 'page/' . $page;
        }
        $regex = '~/(|' . $regex . '|maintenance|login|logout|migrate|guest/accept-terms)$~';
        if (preg_match($regex, $requestUriBase)) {
            return;
        }

        $baseUrl = $request->getBaseUrl() ?? '';

        if ($routeMatch->getParam('__SITE__')) {
            $siteSlug = $routeMatch->getParam('site-slug');
        } else {
            // Get first site when no site is set, for example on main login page.
            $defaultSiteId = $services->get('Omeka\Settings')->get('default_site');
            $api = $services->get('Omeka\ApiManager');
            if ($defaultSiteId) {
                try {
                    $siteSlug = $api->read('sites', ['id' => $defaultSiteId], ['initialize' => false, 'returnScalar' => 'slug'])->getContent();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    // Nothing.
                }
            }
            if (empty($siteSlug)) {
                $siteSlug = $api->search('sites', ['sort_by' => 'id'], ['initialize' => false, 'returnScalar' => 'slug'])->getContent();
            }
        }
        $acceptUri = $baseUrl . '/s/' . $siteSlug . '/guest/accept-terms';

        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $acceptUri);
        $response->setStatusCode(302);
        $response->sendHeaders();
        return $response;
    }
}
