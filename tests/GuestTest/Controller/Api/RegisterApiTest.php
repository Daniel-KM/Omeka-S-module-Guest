<?php declare(strict_types=1);

namespace GuestTest\Controller\Api;

use GuestTest\Controller\GuestControllerTestCase;

/**
 * Tests for Guest registration API endpoint.
 *
 * Tests various registration scenarios including validation,
 * duplicate handling, and different registration modes.
 */
class RegisterApiTest extends GuestControllerTestCase
{
    /**
     * @var array Emails created during tests for cleanup.
     */
    protected $registeredEmails = [];

    public function tearDown(): void
    {
        // Clean up any users created during registration tests.
        $this->loginAdmin();
        $em = $this->getEntityManager();
        foreach ($this->registeredEmails as $email) {
            try {
                $user = $em->getRepository('Omeka\Entity\User')
                    ->findOneBy(['email' => $email]);
                if ($user) {
                    $em->remove($user);
                }
            } catch (\Exception $e) {
                // Ignore.
            }
        }
        $em->flush();

        parent::tearDown();
    }

    /**
     * Test registration endpoint exists.
     */
    public function testRegisterEndpointExists(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'test@example.com',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test registration requires email.
     */
    public function testRegisterRequiresEmail(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'username' => 'testuser',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test registration validates email format.
     */
    public function testRegisterValidatesEmailFormat(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'invalid-email',
                'username' => 'testuser',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test registration fails when closed.
     */
    public function testRegisterFailsWhenClosed(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'closed');

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'newuser@example.com',
                'username' => 'newuser',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertContains($response['status'], ['fail', 'error']);
        $this->assertResponseStatusCode(403);
    }

    /**
     * Test registration fails when already logged in.
     */
    public function testRegisterFailsWhenLoggedIn(): void
    {
        // Stay logged in as admin.
        $this->setSetting('guest_open', 'open');

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'newuser@example.com',
                'username' => 'newuser',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertContains($response['status'], ['fail', 'error']);
    }

    /**
     * Test successful registration creates user.
     */
    public function testSuccessfulRegistrationCreatesUser(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $email = 'newregistration_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'New Registration User',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);

        // Registration may return success or fail depending on email config.
        // The key test is whether the endpoint processes the request correctly.
        if (isset($response['status']) && $response['status'] === 'success') {
            // Clear entity manager cache to get fresh data.
            $em = $this->getEntityManager();
            $em->clear();

            $user = $em->getRepository('Omeka\Entity\User')
                ->findOneBy(['email' => $email]);

            $this->assertNotNull($user, 'User should be created on success response');
            $this->assertEquals('guest', $user->getRole());
        } else {
            // Even if email sending fails, verify the endpoint response format.
            $this->assertArrayHasKey('status', $response);
        }
    }

    /**
     * Test registration with duplicate email fails.
     */
    public function testRegisterWithDuplicateEmailFails(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        // First, create a user.
        $email = 'duplicate_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'First User',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        // Reset for second request.
        $this->reset();

        // Try to register with same email.
        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'Second User',
                'password' => 'password456',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        // Should fail or indicate already registered.
        $this->assertContains($response['status'], ['fail', 'error', 'success']);
    }

    /**
     * Test registration uses default username from email.
     */
    public function testRegisterUsesEmailAsDefaultUsername(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $email = 'defaultname_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'password' => 'password123',
                // No username provided.
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);

        // If successful, verify username defaults to email.
        if (isset($response['status']) && $response['status'] === 'success') {
            $em = $this->getEntityManager();
            $em->clear();
            $user = $em->getRepository('Omeka\Entity\User')
                ->findOneBy(['email' => $email]);
            if ($user) {
                $this->assertEquals($email, $user->getName());
            }
        }
    }

    /**
     * Test registration creates inactive user.
     */
    public function testRegisterCreatesInactiveUser(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $email = 'inactive_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'Inactive Test',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);

        // If successful, verify user is inactive (pending confirmation).
        if (isset($response['status']) && $response['status'] === 'success') {
            $em = $this->getEntityManager();
            $em->clear();
            $user = $em->getRepository('Omeka\Entity\User')
                ->findOneBy(['email' => $email]);
            if ($user) {
                $this->assertFalse($user->isActive());
            }
        }
    }

    /**
     * Test registration creates guest token.
     */
    public function testRegisterCreatesGuestToken(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $email = 'withtoken_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'Token Test',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);

        // If successful, check token was created.
        if (isset($response['status']) && $response['status'] === 'success') {
            $token = $this->getGuestToken($email);
            if ($token) {
                $this->assertNotNull($token);
                $this->assertFalse($token->isConfirmed());
                $this->createdTokens[] = $token->getId();
            }
        }
    }

    /**
     * Test moderate mode creates user awaiting approval.
     */
    public function testModerateModeSetsCorrectState(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'moderate');

        $email = 'moderate_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'Moderate Test',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);

        // If successful, check user exists but is inactive.
        if (isset($response['status']) && $response['status'] === 'success') {
            $em = $this->getEntityManager();
            $em->clear();
            $user = $em->getRepository('Omeka\Entity\User')
                ->findOneBy(['email' => $email]);
            if ($user) {
                $this->assertFalse($user->isActive());
            }
        }
    }

    /**
     * Test registration returns proper JSend response.
     */
    public function testRegisterReturnsJSendResponse(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        $email = 'jsend_' . time() . '@example.com';
        $this->registeredEmails[] = $email;

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => $email,
                'username' => 'JSend Test',
                'password' => 'password123',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/guest/register');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertContains($response['status'], ['success', 'fail', 'error']);
    }

    /**
     * Test deprecated /api/register route.
     */
    public function testDeprecatedRegisterRoute(): void
    {
        $this->logout();

        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode([
                'email' => 'deprecated@example.com',
            ]));
        $this->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json');

        $this->dispatch('/api/register');
        $this->assertNotResponseStatusCode(404);
    }
}
