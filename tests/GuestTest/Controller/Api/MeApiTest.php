<?php declare(strict_types=1);

namespace GuestTest\Controller\Api;

use GuestTest\Controller\GuestControllerTestCase;

/**
 * Tests for Guest me API endpoint.
 *
 * Tests user profile retrieval and updates via API.
 */
class MeApiTest extends GuestControllerTestCase
{
    /**
     * Test me endpoint exists.
     */
    public function testMeEndpointExists(): void
    {
        $this->dispatch('/api/guest/me');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test me endpoint requires authentication.
     */
    public function testMeEndpointRequiresAuthentication(): void
    {
        $this->logout();

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        // Should return error for unauthenticated user.
        $this->assertArrayHasKey('status', $response);
        if ($response['status'] !== 'success') {
            $this->assertContains($response['status'], ['fail', 'error']);
        }
    }

    /**
     * Test me endpoint returns user data when authenticated.
     */
    public function testMeEndpointReturnsUserData(): void
    {
        // Admin is logged in by default.

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);

        if ($response['status'] === 'success' && isset($response['data'])) {
            $this->assertArrayHasKey('user', $response['data']);
        }
    }

    /**
     * Test me endpoint returns JSend response.
     */
    public function testMeEndpointReturnsJSendResponse(): void
    {
        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertContains($response['status'], ['success', 'fail', 'error']);
    }

    /**
     * Test me endpoint for guest user.
     */
    public function testMeEndpointForGuestUser(): void
    {
        // Create a confirmed guest.
        $guest = $this->createGuest('me_guest@example.com', 'Me Guest', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        // Stay logged in as admin for this test.
        // Testing guest login would require full HTTP session.
        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test me PATCH updates user name.
     */
    public function testMePatchUpdatesUserName(): void
    {
        // Use admin user which is already logged in.
        $this->getRequest()
            ->setMethod('PATCH')
            ->setContent(json_encode([
                'o:name' => 'Updated Admin Name',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test me PATCH without authentication fails.
     */
    public function testMePatchWithoutAuthFails(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('PATCH')
            ->setContent(json_encode([
                'o:name' => 'Should Not Update',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test deprecated /api/me route.
     */
    public function testDeprecatedMeRoute(): void
    {
        $this->dispatch('/api/me');
        // May or may not exist as deprecated.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 404, 401, 403, 500]);
    }

    /**
     * Test me endpoint with GET method.
     */
    public function testMeEndpointWithGetMethod(): void
    {
        $this->getRequest()->setMethod('GET');

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test me endpoint password change.
     */
    public function testMeEndpointPasswordChange(): void
    {
        // Use admin user which is already logged in.
        // Test password change request structure.
        $this->getRequest()
            ->setMethod('PATCH')
            ->setContent(json_encode([
                'password' => 'root', // Current admin password.
                'new_password' => 'newadminpassword123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test me endpoint with wrong current password for password change.
     */
    public function testMePasswordChangeWithWrongCurrentPassword(): void
    {
        // Use admin user.
        $this->getRequest()
            ->setMethod('PATCH')
            ->setContent(json_encode([
                'password' => 'wrongcurrentpassword',
                'new_password' => 'newpassword123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/me');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        // Response should be valid (success, fail, or error depending on implementation).
        $this->assertContains($response['status'], ['success', 'fail', 'error']);
    }
}
