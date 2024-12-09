<?php declare(strict_types=1);

namespace Guest\View\Helper;

use Guest\Mvc\Controller\Plugin\GuestNavigationTranslator as NavigationTranslator;
use Laminas\Navigation\Service\ConstructedNavigationFactory;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\AbstractHtmlElement;
use Omeka\Api\Representation\SiteRepresentation;

class GuestNavigation extends AbstractHtmlElement
{
    /**
     * @var \Guest\Mvc\Controller\Plugin\GuestNavigationTranslator
     */
    protected $navigationTranslator;

    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $services;

    public function __construct(
        NavigationTranslator $navigationTranslator,
        ServiceLocatorInterface $services
    ) {
        $this->navigationTranslator = $navigationTranslator;
        $this->services = $services;
    }

    /**
     * Get the configured navigation for guest.
     */
    public function __invoke(?SiteRepresentation $site = null, array $options = []): \Laminas\View\Helper\Navigation
    {
        $view = $this->getView();

        return $this->guestNav(
            $site ?? $view->currentSite(),
            // If navigation is empty, use empty array: null means default nav.
            $view->siteSetting('guest_navigation') ?: [],
            $options
        );
    }

    /**
     * Get the navigation helper for public-side nav for this site.
     *
     * Adapted from SiteRepresentation::publicNav().
     * @see \Omeka\Api\Representation\SiteRepresentation::publicNav()
     * @see \Menu\View\Helper\NavMenu::publicNav()
     * @see \Guest\View\Helper\GuestNavigation::guestNav()
     *
     * @todo Check if the translator should be skipped here, in particular to display title of resources.
     */
    protected function guestNav(SiteRepresentation $site, ?array $menu = null, array $options = []): \Laminas\View\Helper\Navigation
    {
        $helper = $this->view->getHelperPluginManager()->build('Navigation');
        $helper->getPluginManager()->addInitializer(function ($container, $plugin): void {
            $plugin->setTranslatorEnabled(false);
        });
        return $helper($this->getGuestNavContainer($site, $menu, $options));
    }

    /**
     * Get the navigation container for this site's public guest nav.
     *
     * Adapted from SiteRepresentation::getPublicNavContainer().
     * @see \Omeka\Api\Representation\SiteRepresentation::getPublicNavContainer()
     * @see \Menu\View\Helper\NavMenu::getPublicNavContainer()
     * @see \Guest\View\Helper\GuestNavigation::getGuestNavContainer()
     */
    protected function getGuestNavContainer(SiteRepresentation $site, ?array $menu = null, array $options = []): \Laminas\Navigation\Navigation
    {
        $factory = new ConstructedNavigationFactory($this->navigationTranslator->toLaminas($site, $menu, $options));
        return $factory($this->services, '');
    }
}
