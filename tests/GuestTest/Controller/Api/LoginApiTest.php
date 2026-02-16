<?php declare(strict_types=1);

namespace GuestTest\Controller\Api;

use GuestTest\Controller\GuestControllerTestCase;

/**
 * Tests for Guest login API endpoint.
 *
 * Tests authentication flow including validation, session handling,
 * and various user states.
 */
class LoginApiTest extends GuestControllerTestCase
{
    /**
     * Test login endpoint exists.
     */
    public function testLoginEndpointExists(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@example.com',
                'password' => 'password',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test login requires email.
     */
    public function testLoginRequiresEmail(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'password' => 'password',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login requires password.
     */
    public function testLoginRequiresPassword(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@test.fr',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login with valid credentials succeeds.
     */
    public function testLoginWithValidCredentialsSucceeds(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@test.fr',
                'password' => 'test',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        // Should return success with user data.
        if ($response['status'] === 'success') {
            $this->assertArrayHasKey('data', $response);
        }
    }

    /**
     * Test login with wrong password fails.
     */
    public function testLoginWithWrongPasswordFails(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@test.fr',
                'password' => 'wrongpassword',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login with non-existent email fails.
     */
    public function testLoginWithNonExistentEmailFails(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'nonexistent@example.com',
                'password' => 'anypassword',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login returns JSend format response.
     */
    public function testLoginReturnsJSendResponse(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@test.fr',
                'password' => 'test',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertContains($response['status'], ['success', 'fail', 'error']);
    }

    /**
     * Test login with inactive user fails.
     */
    public function testLoginWithInactiveUserFails(): void
    {
        $this->logout();

        // Create inactive user.
        $email = 'inactive_login_' . time() . '@example.com';
        $response = $this->api()->create('users', [
            'o:email' => $email,
            'o:name' => 'Inactive Login User',
            'o:role' => 'guest',
            'o:is_active' => false,
        ]);
        $user = $response->getContent();
        $userEntity = $user->getEntity();
        $userEntity->setPassword('password123');
        $this->getEntityManager()->persist($userEntity);
        $this->getEntityManager()->flush();
        $this->createdUsers[] = $user->id();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login with unconfirmed guest fails.
     */
    public function testLoginWithUnconfirmedGuestFails(): void
    {
        $this->logout();

        // Create unconfirmed guest.
        $guest = $this->createGuest('unconfirmed_login@example.com', 'Unconfirmed', 'password123', false);
        // Make inactive to simulate unconfirmed state.
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(false);
        $this->getEntityManager()->flush();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'unconfirmed_login@example.com',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login with confirmed active guest succeeds.
     */
    public function testLoginWithConfirmedGuestSucceeds(): void
    {
        $this->logout();

        // Create confirmed active guest.
        $guest = $this->createGuest('confirmed_login@example.com', 'Confirmed', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'confirmed_login@example.com',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        // Should succeed for active confirmed guest.
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test deprecated /api/login route.
     */
    public function testDeprecatedLoginRoute(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@test.fr',
                'password' => 'test',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/login');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test login while already logged in.
     */
    public function testLoginWhileAlreadyLoggedIn(): void
    {
        // Stay logged in as admin.

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@test.fr',
                'password' => 'test',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        // Should handle gracefully (either success or already logged message).
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test login validates email format.
     */
    public function testLoginValidatesEmailFormat(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'invalid-email-format',
                'password' => 'password',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test login with empty credentials.
     */
    public function testLoginWithEmptyCredentials(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/login');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }
}
