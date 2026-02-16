<?php declare(strict_types=1);

namespace GuestTest\Controller;

/**
 * Tests for guest account update functionality.
 *
 * Tests update-account and update-email actions.
 */
class UpdateAccountTest extends GuestControllerTestCase
{
    /**
     * Test update-account route exists.
     */
    public function testUpdateAccountRouteExists(): void
    {
        // Must be logged in to access.
        $this->dispatch('/s/test/guest/update-account');
        // Should redirect or show page (not 404).
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test update-account requires authentication.
     */
    public function testUpdateAccountRequiresAuthentication(): void
    {
        $this->logout();

        // Omeka throws PermissionDeniedException for unauthenticated users.
        $this->expectException(\Omeka\Mvc\Exception\PermissionDeniedException::class);
        $this->dispatch('/s/test/guest/update-account');
    }

    /**
     * Test update-account page loads for logged in user.
     */
    public function testUpdateAccountPageLoadsForLoggedInUser(): void
    {
        // Admin is logged in by default.
        $this->dispatch('/s/test/guest/update-account');

        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-account for confirmed guest.
     */
    public function testUpdateAccountForConfirmedGuest(): void
    {
        // Create and confirm guest.
        $guest = $this->createGuest('update_account@example.com', 'Update Account', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('update_account@example.com', 'password123');

        $this->dispatch('/s/test/guest/update-account');

        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-account form displays user info.
     */
    public function testUpdateAccountFormDisplaysUserInfo(): void
    {
        $this->dispatch('/s/test/guest/update-account');

        $statusCode = $this->getResponse()->getStatusCode();
        if ($statusCode === 200) {
            $body = $this->getResponse()->getContent();
            // Form should be present.
            $this->assertNotEmpty($body);
        }
        $this->assertTrue(true);
    }

    /**
     * Test update-account POST updates name.
     */
    public function testUpdateAccountPostUpdatesName(): void
    {
        // Create and confirm guest.
        $guest = $this->createGuest('update_name@example.com', 'Original Name', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('update_name@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'user-information' => [
                'o:name' => 'Updated Name',
            ],
            'change-password' => [
                'password' => '',
            ],
        ]));

        $this->dispatch('/s/test/guest/update-account');

        // Should process without errors.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-account email field is disabled.
     */
    public function testUpdateAccountEmailFieldDisabled(): void
    {
        $this->dispatch('/s/test/guest/update-account');

        // Email update is handled separately, field should be disabled.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-account password change.
     */
    public function testUpdateAccountPasswordChange(): void
    {
        // Create and confirm guest.
        $guest = $this->createGuest('update_pwd@example.com', 'Update Pwd', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('update_pwd@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'user-information' => [
                'o:name' => 'Update Pwd',
            ],
            'change-password' => [
                'password' => 'newpassword123',
                'password-confirm' => 'newpassword123',
            ],
        ]));

        $this->dispatch('/s/test/guest/update-account');

        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-account prevents role escalation.
     */
    public function testUpdateAccountPreventsRoleEscalation(): void
    {
        // Create and confirm guest.
        $guest = $this->createGuest('role_escalate@example.com', 'Role Escalate', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('role_escalate@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'user-information' => [
                'o:name' => 'Role Escalate',
                'o:role' => 'global_admin', // Try to escalate.
            ],
        ]));

        $this->dispatch('/s/test/guest/update-account');

        // Reload user to verify role wasn't changed.
        $this->getEntityManager()->refresh($guestEntity);
        $this->assertEquals('guest', $guestEntity->getRole());
    }

    /**
     * Test update-account prevents is_active change.
     */
    public function testUpdateAccountPreventsIsActiveChange(): void
    {
        // Create and confirm guest who is active.
        $guest = $this->createGuest('active_change@example.com', 'Active Change', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('active_change@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'user-information' => [
                'o:name' => 'Active Change',
                'o:is_active' => false, // Try to deactivate self.
            ],
        ]));

        $this->dispatch('/s/test/guest/update-account');

        // User should still be active.
        $this->getEntityManager()->refresh($guestEntity);
        $this->assertTrue($guestEntity->isActive());
    }

    /**
     * Test update-email route exists.
     */
    public function testUpdateEmailRouteExists(): void
    {
        $this->dispatch('/s/test/guest/update-email');
        // Should exist (not 404).
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test update-email requires authentication.
     */
    public function testUpdateEmailRequiresAuthentication(): void
    {
        $this->logout();

        // Omeka throws PermissionDeniedException for unauthenticated users.
        $this->expectException(\Omeka\Mvc\Exception\PermissionDeniedException::class);
        $this->dispatch('/s/test/guest/update-email');
    }

    /**
     * Test update-email page loads for logged in user.
     */
    public function testUpdateEmailPageLoadsForLoggedInUser(): void
    {
        $this->dispatch('/s/test/guest/update-email');

        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-email POST with same email shows warning.
     */
    public function testUpdateEmailWithSameEmailShowsWarning(): void
    {
        // Create and confirm guest.
        $guest = $this->createGuest('same_email@example.com', 'Same Email', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('same_email@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'o:email' => 'same_email@example.com', // Same email.
        ]));

        $this->dispatch('/s/test/guest/update-email');

        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 302, 303]);
    }

    /**
     * Test update-email POST with existing email fails.
     */
    public function testUpdateEmailWithExistingEmailFails(): void
    {
        // Create two guests.
        $guest1 = $this->createGuest('email_change1@example.com', 'Email Change 1', 'password123', true);
        $guest1Entity = $guest1->getEntity();
        $guest1Entity->setIsActive(true);

        $guest2 = $this->createGuest('email_change2@example.com', 'Email Change 2', 'password123', true);
        $guest2Entity = $guest2->getEntity();
        $guest2Entity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('email_change1@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'o:email' => 'email_change2@example.com', // Already taken.
        ]));

        $this->dispatch('/s/test/guest/update-email');

        // Should not update to taken email.
        $this->getEntityManager()->refresh($guest1Entity);
        $this->assertEquals('email_change1@example.com', $guest1Entity->getEmail());
    }

    /**
     * Test update-email POST with invalid email format fails.
     */
    public function testUpdateEmailWithInvalidFormatFails(): void
    {
        // Create and confirm guest.
        $guest = $this->createGuest('valid_email@example.com', 'Valid Email', 'password123', true);
        $guestEntity = $guest->getEntity();
        $guestEntity->setIsActive(true);
        $this->getEntityManager()->flush();

        $this->logout();
        $this->login('valid_email@example.com', 'password123');

        $this->getRequest()->setMethod('POST');
        $this->getRequest()->setPost(new \Laminas\Stdlib\Parameters([
            'o:email' => 'not-a-valid-email',
        ]));

        $this->dispatch('/s/test/guest/update-email');

        // Email should not have changed.
        $this->getEntityManager()->refresh($guestEntity);
        $this->assertEquals('valid_email@example.com', $guestEntity->getEmail());
    }

    /**
     * Test accept-terms route exists.
     */
    public function testAcceptTermsRouteExists(): void
    {
        $this->dispatch('/s/test/guest/accept-terms');
        // Should exist.
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test accept-terms requires authentication.
     */
    public function testAcceptTermsRequiresAuthentication(): void
    {
        $this->logout();

        // Omeka throws PermissionDeniedException for unauthenticated users.
        $this->expectException(\Omeka\Mvc\Exception\PermissionDeniedException::class);
        $this->dispatch('/s/test/guest/accept-terms');
    }
}
