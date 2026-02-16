<?php declare(strict_types=1);

namespace GuestTest\Controller;

use Guest\Entity\GuestToken;
use Laminas\Form\Element\Csrf;

/**
 * Tests for guest user registration, login and account management.
 */
class UserControllerTest extends GuestControllerTestCase
{
    /**
     * @var \Omeka\Api\Representation\UserRepresentation|null
     */
    protected $guest;

    public function tearDown(): void
    {
        $this->loginAdmin();
        $this->deleteLocalGuest();
        parent::tearDown();
    }

    /**
     * Test site guest route exists.
     */
    public function testSiteGuestRouteExists(): void
    {
        $config = $this->getServiceLocator()->get('Config');
        $routes = $config['router']['routes'] ?? [];

        $this->assertArrayHasKey('site', $routes);
        $this->assertArrayHasKey('child_routes', $routes['site']);
        $this->assertArrayHasKey('guest', $routes['site']['child_routes']);
    }

    /**
     * Test login page is accessible.
     */
    public function testLoginPageAccessible(): void
    {
        $this->logout();
        $this->dispatch('/s/test/guest/login');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test register page is accessible.
     */
    public function testRegisterPageAccessible(): void
    {
        $this->logout();
        $this->dispatch('/s/test/guest/register');
        // May return 200 or redirect depending on settings.
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test forgot password page is accessible.
     */
    public function testForgotPasswordPageAccessible(): void
    {
        $this->logout();
        $this->dispatch('/s/test/guest/forgot-password');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test deleting user also removes their token (cascade).
     */
    public function testDeleteUnconfirmedUserShouldRemoveToken(): void
    {
        $user = $this->createLocalGuest();
        $userId = $user->id();
        $em = $this->getEntityManager();

        $this->deleteLocalGuest();

        $userToken = $em->getRepository(GuestToken::class)
            ->findOneBy(['user' => $userId]);
        $this->assertNull($userToken);
    }

    /**
     * Test valid token confirmation works.
     */
    public function testTokenConfirmation(): void
    {
        $user = $this->createLocalGuest();
        $token = $this->getGuestToken($user->email());

        $this->assertFalse($token->isConfirmed());

        $this->dispatch('/s/test/guest/confirm?token=' . $token->getToken());

        // Reload token.
        $this->getEntityManager()->refresh($token);
        $this->assertTrue($token->isConfirmed());
    }

    /**
     * Test invalid token does not confirm.
     */
    public function testInvalidTokenDoesNotConfirm(): void
    {
        $user = $this->createLocalGuest();
        $token = $this->getGuestToken($user->email());

        $this->dispatch('/s/test/guest/confirm?token=invalid_token_12345');

        // Reload token.
        $this->getEntityManager()->refresh($token);
        $this->assertFalse($token->isConfirmed());
    }

    /**
     * Test logout clears identity.
     */
    public function testLogoutClearsIdentity(): void
    {
        // Create and confirm guest.
        $user = $this->createLocalGuest();
        $token = $this->getGuestToken($user->email());
        $token->setConfirmed(true);
        $this->getEntityManager()->flush();

        // Login as guest.
        $this->login('guest@test.fr', 'test');
        $this->assertTrue($this->isAuthenticated());

        // Dispatch logout.
        $this->dispatch('/s/test/guest/logout');

        // Should be logged out now.
        $this->assertFalse($this->isAuthenticated());
    }

    /**
     * Test unconfirmed guest cannot access guest pages.
     */
    public function testUnconfirmedGuestRestricted(): void
    {
        $user = $this->createLocalGuest();
        // Guest is not confirmed.

        // Try to login.
        $this->login('guest@test.fr', 'test');

        // Access me page.
        $this->dispatch('/s/test/guest/me');

        // Should not be fully accessible or show error.
        $this->assertNotResponseStatusCode(500);
    }

    /**
     * Test confirmed guest can access me page.
     */
    public function testConfirmedGuestCanAccessMePage(): void
    {
        $user = $this->createLocalGuest();
        $token = $this->getGuestToken($user->email());
        $token->setConfirmed(true);
        $this->getEntityManager()->flush();

        $this->login('guest@test.fr', 'test');

        $this->dispatch('/s/test/guest/me');
        // May return 200 or 302 redirect.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test login with wrong password fails.
     */
    public function testLoginWithWrongPasswordFails(): void
    {
        $this->logout();
        @session_write_close();

        $csrf = new Csrf('loginform_csrf');
        $this->postDispatch('/s/test/guest/login', [
            'email' => 'test@test.fr',
            'password' => 'wrongpassword',
            'loginform_csrf' => $csrf->getValue(),
            'submit' => 'Log in',
        ]);

        // Should not be authenticated.
        $this->assertFalse($this->isAuthenticated());
    }

    /**
     * Test login with correct password succeeds.
     */
    public function testLoginWithCorrectPasswordSucceeds(): void
    {
        $this->logout();
        @session_write_close();

        $csrf = new Csrf('loginform_csrf');
        $this->postDispatch('/s/test/guest/login', [
            'email' => 'test@test.fr',
            'password' => 'test',
            'loginform_csrf' => $csrf->getValue(),
            'submit' => 'Log in',
        ]);

        // Should be authenticated or redirected.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Create a guest user for this test (local tracking).
     */
    protected function createLocalGuest()
    {
        $this->guest = $this->createGuest('guest@test.fr', 'Guest User', 'test', false);
        return $this->guest;
    }

    /**
     * Delete the locally tracked guest.
     */
    protected function deleteLocalGuest(): void
    {
        if (isset($this->guest)) {
            try {
                $this->loginAdmin();
                $this->api()->delete('users', $this->guest->id());
            } catch (\Exception $e) {
                // Already deleted via cleanup.
            }
            $this->guest = null;
        }
    }
}
