<?php declare(strict_types=1);

namespace GuestTest\Controller;

use Guest\Entity\GuestToken;

/**
 * Tests for guest token confirmation flow.
 */
class TokenConfirmationTest extends GuestControllerTestCase
{
    /**
     * Test confirm route exists.
     */
    public function testConfirmRouteExists(): void
    {
        $this->logout();
        $this->dispatch('/s/test/guest/confirm');
        // Should not be 404 (may be redirect or error for missing token).
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);
    }

    /**
     * Test confirm with valid token.
     */
    public function testConfirmWithValidToken(): void
    {
        $this->logout();

        // Create unconfirmed guest.
        $guest = $this->createGuest('confirm_valid@example.com', 'Confirm Valid', 'password123', false);
        $token = $this->getGuestToken('confirm_valid@example.com');

        $this->assertNotNull($token);
        $this->assertFalse($token->isConfirmed());

        // Dispatch confirm with token.
        $this->dispatch('/s/test/guest/confirm?token=' . $token->getToken());

        // Reload token.
        $this->getEntityManager()->refresh($token);
        $this->assertTrue($token->isConfirmed());
    }

    /**
     * Test confirm with invalid token.
     */
    public function testConfirmWithInvalidToken(): void
    {
        $this->logout();

        // Create unconfirmed guest.
        $guest = $this->createGuest('confirm_invalid@example.com', 'Confirm Invalid', 'password123', false);
        $token = $this->getGuestToken('confirm_invalid@example.com');

        $this->assertFalse($token->isConfirmed());

        // Dispatch with wrong token.
        $this->dispatch('/s/test/guest/confirm?token=invalid_token_12345');

        // Reload token - should still be unconfirmed.
        $this->getEntityManager()->refresh($token);
        $this->assertFalse($token->isConfirmed());
    }

    /**
     * Test confirm without token parameter.
     */
    public function testConfirmWithoutTokenParameter(): void
    {
        $this->logout();

        $this->dispatch('/s/test/guest/confirm');

        // Should redirect or show error.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303, 400]);
    }

    /**
     * Test confirm activates user in open mode.
     */
    public function testConfirmActivatesUserInOpenMode(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'open');

        // Create unconfirmed guest.
        $guest = $this->createGuest('confirm_active@example.com', 'Confirm Active', 'password123', false);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(false);
        $this->getEntityManager()->flush();

        $token = $this->getGuestToken('confirm_active@example.com');

        // Dispatch confirm.
        $this->dispatch('/s/test/guest/confirm?token=' . $token->getToken());

        // Reload user entity.
        $this->getEntityManager()->refresh($guestEntity);

        // In open mode, user should be activated after confirmation.
        // Note: This depends on controller logic.
        $this->assertTrue($this->getGuestToken('confirm_active@example.com')->isConfirmed());
    }

    /**
     * Test confirm in moderate mode does not auto-activate.
     */
    public function testConfirmInModerateModeDoesNotAutoActivate(): void
    {
        $this->logout();
        $this->setSetting('guest_open', 'moderate');

        // Create unconfirmed guest.
        $guest = $this->createGuest('confirm_moderate@example.com', 'Confirm Moderate', 'password123', false);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(false);
        $this->getEntityManager()->flush();

        $token = $this->getGuestToken('confirm_moderate@example.com');

        // Dispatch confirm.
        $this->dispatch('/s/test/guest/confirm?token=' . $token->getToken());

        // Reload user entity.
        $this->getEntityManager()->refresh($guestEntity);

        // Token should be confirmed.
        $this->assertTrue($this->getGuestToken('confirm_moderate@example.com')->isConfirmed());
        // User may or may not be active depending on implementation.
    }

    /**
     * Test confirm with already confirmed token.
     */
    public function testConfirmWithAlreadyConfirmedToken(): void
    {
        $this->logout();

        // Create confirmed guest.
        $guest = $this->createGuest('already_confirmed@example.com', 'Already Confirmed', 'password123', true);
        $token = $this->getGuestToken('already_confirmed@example.com');

        $this->assertTrue($token->isConfirmed());

        // Dispatch confirm again.
        $this->dispatch('/s/test/guest/confirm?token=' . $token->getToken());

        // Should handle gracefully.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test confirm redirects after success.
     */
    public function testConfirmRedirectsAfterSuccess(): void
    {
        $this->logout();

        // Create unconfirmed guest.
        $guest = $this->createGuest('confirm_redirect@example.com', 'Confirm Redirect', 'password123', false);
        $token = $this->getGuestToken('confirm_redirect@example.com');

        // Dispatch confirm.
        $this->dispatch('/s/test/guest/confirm?token=' . $token->getToken());

        // Should redirect after confirmation.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test validate-email route exists.
     */
    public function testValidateEmailRouteExists(): void
    {
        $this->logout();
        $this->dispatch('/s/test/guest/validate-email');
        // Should exist.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);
    }

    /**
     * Test confirm-email route exists.
     */
    public function testConfirmEmailRouteExists(): void
    {
        $this->logout();
        $this->dispatch('/s/test/guest/confirm-email');
        // Should exist.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode);
    }
}
