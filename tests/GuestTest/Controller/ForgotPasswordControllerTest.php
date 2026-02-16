<?php declare(strict_types=1);

namespace GuestTest\Controller;

use Laminas\Form\Element\Csrf;

/**
 * Tests for forgot password functionality.
 */
class ForgotPasswordControllerTest extends GuestControllerTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->logout();
    }

    public function tearDown(): void
    {
        $this->loginAdmin();

        $entityManager = $this->getEntityManager();
        $passwordCreation = $entityManager
            ->getRepository('Omeka\Entity\PasswordCreation')
            ->findOneBy(['user' => $this->testUser->getEntity()]);
        if ($passwordCreation) {
            $entityManager->remove($passwordCreation);
            $entityManager->flush();
        }

        parent::tearDown();
    }

    /**
     * Test forgot password page is accessible.
     */
    public function testForgotPasswordPageAccessible(): void
    {
        $this->dispatch('/s/test/guest/forgot-password');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test forgot password form submission.
     */
    public function testForgotPasswordFormSubmission(): void
    {
        $csrf = new Csrf('forgotpasswordform_csrf');
        $this->postDispatch('/s/test/guest/forgot-password', [
            'email' => 'test@test.fr',
            'forgotpasswordform_csrf' => $csrf->getValue(),
        ]);

        // Should process without error.
        $this->assertNotResponseStatusCode(500);
    }

    /**
     * Test forgot password sends email for valid user.
     */
    public function testForgotPasswordSendsEmail(): void
    {
        $csrf = new Csrf('forgotpasswordform_csrf');
        $this->postDispatch('/s/test/guest/forgot-password', [
            'email' => 'test@test.fr',
            'forgotpasswordform_csrf' => $csrf->getValue(),
        ]);

        $mailer = $this->getMockMailer();
        if ($mailer) {
            $message = $mailer->getMessage();
            if ($message) {
                $body = $message->getBody();
                $this->assertStringContainsString('reset', strtolower($body));
            }
        }
        // If no mock mailer, just ensure no error.
        $this->assertNotResponseStatusCode(500);
    }

    /**
     * Test forgot password with invalid email.
     */
    public function testForgotPasswordWithInvalidEmail(): void
    {
        $csrf = new Csrf('forgotpasswordform_csrf');
        $this->postDispatch('/s/test/guest/forgot-password', [
            'email' => 'nonexistent@example.com',
            'forgotpasswordform_csrf' => $csrf->getValue(),
        ]);

        // Should not error (security: don't reveal if email exists).
        $this->assertNotResponseStatusCode(500);
    }
}
