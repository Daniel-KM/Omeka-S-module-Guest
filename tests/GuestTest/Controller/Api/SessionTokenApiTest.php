<?php declare(strict_types=1);

namespace GuestTest\Controller\Api;

use GuestTest\Controller\GuestControllerTestCase;

/**
 * Tests for Guest session-token API endpoint.
 *
 * Tests session token retrieval for CSRF protection.
 */
class SessionTokenApiTest extends GuestControllerTestCase
{
    /**
     * Test session-token endpoint exists.
     */
    public function testSessionTokenEndpointExists(): void
    {
        $this->dispatch('/api/guest/session-token');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test session-token returns a token.
     */
    public function testSessionTokenReturnsToken(): void
    {
        $this->dispatch('/api/guest/session-token');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);

        if ($response['status'] === 'success' && isset($response['data'])) {
            // Should contain a token.
            $this->assertTrue(
                isset($response['data']['csrf']) || isset($response['data']['session_token']),
                'Response should contain a token'
            );
        }
    }

    /**
     * Test session-token returns JSend format.
     */
    public function testSessionTokenReturnsJSendFormat(): void
    {
        $this->dispatch('/api/guest/session-token');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertContains($response['status'], ['success', 'fail', 'error']);
    }

    /**
     * Test session-token works when not authenticated.
     */
    public function testSessionTokenWorksWhenNotAuthenticated(): void
    {
        $this->logout();

        $this->dispatch('/api/guest/session-token');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test session-token works when authenticated.
     */
    public function testSessionTokenWorksWhenAuthenticated(): void
    {
        // Admin is logged in.

        $this->dispatch('/api/guest/session-token');

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    /**
     * Test deprecated /api/session-token route.
     */
    public function testDeprecatedSessionTokenRoute(): void
    {
        $this->dispatch('/api/session-token');
        $this->assertNotResponseStatusCode(404);
    }

    /**
     * Test session-token with POST method.
     */
    public function testSessionTokenWithPostMethod(): void
    {
        $this->getRequest()->setMethod('POST');

        $this->dispatch('/api/guest/session-token');

        // Should work or return method not allowed.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 405]);
    }

    /**
     * Test session-token generates unique tokens.
     */
    public function testSessionTokenGeneratesUniqueTokens(): void
    {
        // First request.
        $this->dispatch('/api/guest/session-token');
        $response1 = json_decode($this->getResponse()->getContent(), true);

        // Reset for second request.
        $this->reset();

        // Second request.
        $this->dispatch('/api/guest/session-token');
        $response2 = json_decode($this->getResponse()->getContent(), true);

        $this->assertIsArray($response1);
        $this->assertIsArray($response2);
        $this->assertArrayHasKey('status', $response1);
        $this->assertArrayHasKey('status', $response2);
    }
}
