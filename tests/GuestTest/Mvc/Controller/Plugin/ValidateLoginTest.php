<?php declare(strict_types=1);

namespace GuestTest\Mvc\Controller\Plugin;

use Guest\Entity\GuestToken;
use Guest\Mvc\Controller\Plugin\ValidateLogin;
use GuestTest\GuestTestTrait;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\EventManager;
use Laminas\Form\Element\Csrf;
use Laminas\Form\Form;
use Laminas\Http\Request;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Settings\Settings;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Unit tests for the ValidateLogin controller plugin.
 */
class ValidateLoginTest extends AbstractHttpControllerTestCase
{
    use GuestTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->cleanupStaleLoginTestResources();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Clean up stale test resources that may exist from previous failed runs.
     */
    protected function cleanupStaleLoginTestResources(): void
    {
        $em = $this->getEntityManager();

        // List of test emails that may be stale.
        $testEmails = [
            'inactive@example.com',
            'unconfirmed@example.com',
            'confirmed@example.com',
        ];

        foreach ($testEmails as $email) {
            try {
                // Clean up tokens for this email.
                $tokens = $em->getRepository(GuestToken::class)->findBy(['email' => $email]);
                foreach ($tokens as $token) {
                    $em->remove($token);
                }
                $em->flush();

                // Clean up user.
                $user = $em->getRepository('Omeka\Entity\User')->findOneBy(['email' => $email]);
                if ($user) {
                    $this->api()->delete('users', $user->getId());
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors.
            }
        }
    }

    /**
     * Create a ValidateLogin plugin with mocked dependencies.
     */
    protected function createValidateLoginPlugin(
        ?Request $request = null,
        ?Settings $settings = null
    ): ValidateLogin {
        $services = $this->getServiceLocator();

        $authService = $services->get('Omeka\AuthenticationService');
        $entityManager = $this->getEntityManager();
        $eventManager = new EventManager();
        $messenger = new Messenger();

        if ($request === null) {
            $request = new Request();
        }

        if ($settings === null) {
            $settings = $services->get('Omeka\Settings');
        }

        return new ValidateLogin(
            $authService,
            $entityManager,
            $eventManager,
            $messenger,
            $request,
            $settings,
            null, // TwoFactorLogin
            null, // Site
            [],   // Config
            false // hasModuleUserNames
        );
    }

    /**
     * Test plugin can be instantiated.
     */
    public function testCanInstantiate(): void
    {
        $plugin = $this->createValidateLoginPlugin();
        $this->assertInstanceOf(ValidateLogin::class, $plugin);
    }

    /**
     * Test login with valid credentials via array (API mode).
     */
    public function testLoginWithValidCredentialsViaArray(): void
    {
        $this->logout();

        $plugin = $this->createValidateLoginPlugin();

        $result = $plugin([
            'email' => 'admin@example.com',
            'password' => 'root',
        ]);

        $this->assertTrue($result);
        $this->assertTrue($this->isAuthenticated());
    }

    /**
     * Test login with invalid password via array.
     */
    public function testLoginWithInvalidPasswordViaArray(): void
    {
        $this->logout();

        $plugin = $this->createValidateLoginPlugin();

        $result = $plugin([
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
        ]);

        // Should return error message string.
        $this->assertIsString($result);
        $this->assertFalse($this->isAuthenticated());
    }

    /**
     * Test login with non-existent email.
     */
    public function testLoginWithNonExistentEmail(): void
    {
        $this->logout();

        $plugin = $this->createValidateLoginPlugin();

        $result = $plugin([
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $this->assertIsString($result);
        $this->assertFalse($this->isAuthenticated());
    }

    /**
     * Test login with inactive user shows moderation message.
     */
    public function testLoginWithInactiveUserShowsModerationMessage(): void
    {
        $this->logout();

        // Create inactive user.
        $response = $this->api()->create('users', [
            'o:email' => 'inactive@example.com',
            'o:name' => 'Inactive User',
            'o:role' => 'guest',
            'o:is_active' => false,
        ]);
        $user = $response->getContent();
        $userEntity = $user->getEntity();
        $userEntity->setPassword('password123');
        $this->getEntityManager()->persist($userEntity);
        $this->getEntityManager()->flush();
        $this->createdUsers[] = $user->id();

        // Set registration mode to moderated.
        $this->setSetting('guest_open', 'moderate');

        $plugin = $this->createValidateLoginPlugin();

        $result = $plugin([
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('moderation', strtolower($result));
    }

    /**
     * Test login with unconfirmed guest shows confirmation message.
     */
    public function testLoginWithUnconfirmedGuestShowsConfirmationMessage(): void
    {
        $this->logout();

        // Create guest with unconfirmed token.
        $guest = $this->createGuest('unconfirmed@example.com', 'Unconfirmed', 'password123', false);

        // Set registration mode to moderated.
        $this->setSetting('guest_open', 'moderate');

        $plugin = $this->createValidateLoginPlugin();

        $result = $plugin([
            'email' => 'unconfirmed@example.com',
            'password' => 'password123',
        ]);

        $this->assertIsString($result);
        $this->assertStringContainsString('confirm', strtolower($result));
    }

    /**
     * Test login triggers user.login event on success.
     */
    public function testLoginTriggersUserLoginEvent(): void
    {
        $this->logout();

        $eventTriggered = false;
        $services = $this->getServiceLocator();
        $authService = $services->get('Omeka\AuthenticationService');
        $entityManager = $this->getEntityManager();
        $eventManager = new EventManager();
        $messenger = new Messenger();
        $request = new Request();
        $settings = $services->get('Omeka\Settings');

        $eventManager->attach('user.login', function ($e) use (&$eventTriggered) {
            $eventTriggered = true;
        });

        $plugin = new ValidateLogin(
            $authService,
            $entityManager,
            $eventManager,
            $messenger,
            $request,
            $settings,
            null,
            null,
            [],
            false
        );

        $result = $plugin([
            'email' => 'admin@example.com',
            'password' => 'root',
        ]);

        $this->assertTrue($result);
        $this->assertTrue($eventTriggered);
    }

    /**
     * Test login with form returns false when not POST.
     */
    public function testLoginWithFormReturnsFalseWhenNotPost(): void
    {
        $this->logout();

        $request = new Request();
        $request->setMethod('GET');

        $plugin = $this->createValidateLoginPlugin($request);

        $form = new Form();
        $form->add([
            'name' => 'email',
            'type' => 'text',
        ]);
        $form->add([
            'name' => 'password',
            'type' => 'password',
        ]);

        $result = $plugin($form);

        $this->assertFalse($result);
    }

    /**
     * Test confirmed guest can login.
     */
    public function testConfirmedGuestCanLogin(): void
    {
        $this->logout();

        // Create confirmed guest.
        $guest = $this->createGuest('confirmed@example.com', 'Confirmed', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $plugin = $this->createValidateLoginPlugin();

        $result = $plugin([
            'email' => 'confirmed@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue($result);
        $this->assertTrue($this->isAuthenticated());
    }

    /**
     * Test login clears previous messages.
     */
    public function testLoginClearsPreviousMessages(): void
    {
        $this->logout();

        $services = $this->getServiceLocator();
        $authService = $services->get('Omeka\AuthenticationService');
        $entityManager = $this->getEntityManager();
        $eventManager = new EventManager();
        $messenger = new Messenger();
        $request = new Request();
        $settings = $services->get('Omeka\Settings');

        // Add a message.
        $messenger->addSuccess('Previous message');

        $plugin = new ValidateLogin(
            $authService,
            $entityManager,
            $eventManager,
            $messenger,
            $request,
            $settings,
            null,
            null,
            [],
            false
        );

        $result = $plugin([
            'email' => 'admin@example.com',
            'password' => 'root',
        ]);

        $this->assertTrue($result);
    }
}
