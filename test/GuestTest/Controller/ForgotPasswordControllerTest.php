<?php declare(strict_types=1);

namespace GuestTest\Controller;

class ForgotPasswordControllerTest extends GuestControllerTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->logout();
    }

    public function tearDown(): void
    {
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
     * @test
     */
    public function forgotPasswordShouldDisplayEmailSent(): void
    {
        $csrf = new \Laminas\Form\Element\Csrf('forgotpasswordform_csrf');
        $this->postDispatch('/s/test/guest/forgot-password', [
            'email' => "test@test.fr",
            'forgotpasswordform_csrf' => $csrf->getValue(),
        ]);

        $this->assertXPathQueryContentContains('//li[@class="success"]', 'Check your email for instructions on how to reset your password');
    }

    /**
     * @test
     */
    public function forgotPasswordShouldSendEmail(): void
    {
        $csrf = new \Laminas\Form\Element\Csrf('forgotpasswordform_csrf');
        $this->postDispatch('/s/test/guest/forgot-password', [
            'email' => "test@test.fr",
            'forgotpasswordform_csrf' => $csrf->getValue(),
        ]);

        $mailer = $this->getServiceLocator()->get('Omeka\Mailer');
        $message = $mailer->getMessage();
        $this->assertNotNull($message);

        $body = $message->getBody();
        $this->assertContains('To reset your password, click this link', $body);
    }
}
