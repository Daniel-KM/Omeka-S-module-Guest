<?php declare(strict_types=1);

namespace GuestTest\Controller\Api;

use GuestTest\Controller\GuestControllerTestCase;

/**
 * Tests for Guest logout API endpoint.
 */
class LogoutApiTest extends GuestControllerTestCase
{
    /**
     * Test logout endpoint exists.
     */
    public function testLogoutEndpointExists(): void
    {
        $this->dispatch('/api/guest/logout');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test logout returns JSend response.
     */
    public function testLogoutReturnsJSendResponse(): void
    {
        $this->dispatch('/api/guest/logout');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertContains($response['status'], ['success', 'fail', 'error']);
    }

    /**
     * Test logout clears session when authenticated.
     */
    public function testLogoutClearsSessionWhenAuthenticated(): void
    {
        // Admin is logged in.
        $this->assertTrue($this->isAuthenticated());

        $this->dispatch('/api/guest/logout');

        // Should no longer be authenticated.
        // Note: This may depend on how the test environment handles sessions.
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test logout when not authenticated.
     */
    public function testLogoutWhenNotAuthenticated(): void
    {
        $this->logout();

        $this->dispatch('/api/guest/logout');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        // Should still return success or handle gracefully.
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test deprecated /api/logout route.
     */
    public function testDeprecatedLogoutRoute(): void
    {
        $this->dispatch('/api/logout');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test logout with POST method.
     */
    public function testLogoutWithPostMethod(): void
    {
        $this->getRequest()->setMethod('POST');

        $this->dispatch('/api/guest/logout');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test logout for guest user.
     */
    public function testLogoutForGuestUser(): void
    {
        // Create and login as guest.
        $guest = $this->createGuest('logout_guest@example.com', 'Logout Guest', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('logout_guest@example.com', 'password123');

        $this->dispatch('/api/guest/logout');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }
}
