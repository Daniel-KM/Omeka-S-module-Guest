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
            $siteSettings = $services->get('Omeka\Settings\Site');
            $page = $siteSettings->get('guest_terms_page', $page);
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
            $acceptUri = $baseUrl . '/s/' . $siteSlug . '/guest/accept-terms';
        } else {
            $acceptUri = $baseUrl;
        }

        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $acceptUri);
        $response->setStatusCode(302);
        $response->sendHeaders();
        return $response;
    }
}
