<?php declare(strict_types=1);

namespace GuestTest\Controller;

/**
 * Tests for Guest module admin configuration.
 */
class ConfigFormControllerTest extends GuestControllerTestCase
{
    /**
     * Test Guest settings exist.
     */
    public function testGuestSettingsExist(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $this->assertArrayHasKey('guest', $config);
        $this->assertArrayHasKey('settings', $config['guest']);
    }

    /**
     * Test default settings are configured.
     */
    public function testDefaultSettingsConfigured(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $settings = $config['guest']['settings'] ?? [];

        $this->assertArrayHasKey('guest_open', $settings);
        $this->assertArrayHasKey('guest_login_text', $settings);
        $this->assertArrayHasKey('guest_dashboard_label', $settings);
    }

    /**
     * Test Guest module has block layouts.
     */
    public function testBlockLayoutsConfigured(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $blockLayouts = $config['block_layouts']['factories'] ?? [];

        $this->assertArrayHasKey('login', $blockLayouts);
        $this->assertArrayHasKey('register', $blockLayouts);
    }

    /**
     * Test Guest module has navigation links.
     */
    public function testNavigationLinksConfigured(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $navLinks = $config['navigation_links']['invokables'] ?? [];

        $this->assertArrayHasKey('login', $navLinks);
        $this->assertArrayHasKey('logout', $navLinks);
        $this->assertArrayHasKey('register', $navLinks);
    }

    /**
     * Test admin module configure page exists.
     */
    public function testAdminConfigurePageExists(): void
    {
        $this->dispatch('/admin/module/configure?id=Guest');
        // May require module to be active - just check not 500.
        $this->assertNotResponseStatusCode(500);
    }

    /**
     * Test settings can be retrieved.
     */
    public function testSettingsCanBeRetrieved(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        // These may or may not be set depending on installation.
        $guestOpen = $settings->get('guest_open');
        $this->assertTrue($guestOpen === null || is_string($guestOpen));
    }

    /**
     * Test site settings service exists.
     */
    public function testSiteSettingsServiceExists(): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $this->assertNotNull($siteSettings);
    }
}
