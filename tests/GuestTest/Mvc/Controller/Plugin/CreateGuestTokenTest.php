<?php declare(strict_types=1);

namespace GuestTest\Mvc\Controller\Plugin;

use Guest\Entity\GuestToken;
use Guest\Mvc\Controller\Plugin\CreateGuestToken;
use GuestTest\GuestTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Unit tests for the CreateGuestToken controller plugin.
 */
class CreateGuestTokenTest extends AbstractHttpControllerTestCase
{
    use GuestTestTrait;

    /**
     * @var CreateGuestToken
     */
    protected $plugin;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->cleanupStalePluginTestResources();
        $this->plugin = new CreateGuestToken($this->getEntityManager());
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Clean up stale test resources that may exist from previous failed runs.
     */
    protected function cleanupStalePluginTestResources(): void
    {
        $em = $this->getEntityManager();

        // List of test emails that may be stale.
        $testEmails = [
            'createtoken@example.com',
            'customidentifier@example.com',
            'longtoken@example.com',
            'shorttoken@example.com',
            'unique1@example.com',
            'unique2@example.com',
            'persisted@example.com',
            'timestamp@example.com',
            'handlesnew@example.com',
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
     * Test plugin can be instantiated.
     */
    public function testCanInstantiate(): void
    {
        $this->assertInstanceOf(CreateGuestToken::class, $this->plugin);
    }

    /**
     * Test plugin creates token with default settings.
     */
    public function testCreatesTokenWithDefaults(): void
    {
        $user = $this->createUser('createtoken@example.com', 'Create Token User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity);

        $this->assertInstanceOf(GuestToken::class, $token);
        $this->assertNotNull($token->getId());
        $this->assertEquals($userEntity->getId(), $token->getUser()->getId());
        $this->assertEquals('createtoken@example.com', $token->getEmail());
        $this->assertFalse($token->isConfirmed());
        $this->assertNotEmpty($token->getToken());
        $this->assertNotNull($token->getCreated());

        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin uses user email as default identifier.
     */
    public function testUsesUserEmailAsDefaultIdentifier(): void
    {
        $user = $this->createUser('defaultemail@example.com', 'Default Email User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity);

        $this->assertEquals('defaultemail@example.com', $token->getEmail());
        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin uses custom identifier when provided.
     */
    public function testUsesCustomIdentifier(): void
    {
        $user = $this->createUser('original@example.com', 'Custom Identifier User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity, 'newemail@example.com');

        $this->assertEquals('newemail@example.com', $token->getEmail());
        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin creates long alphanumeric token by default.
     */
    public function testCreatesLongTokenByDefault(): void
    {
        $user = $this->createUser('longtoken@example.com', 'Long Token User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity);

        // Long tokens should be 10 characters alphanumeric.
        $this->assertEquals(10, strlen($token->getToken()));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $token->getToken());
        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin creates short numeric token when requested.
     */
    public function testCreatesShortToken(): void
    {
        $user = $this->createUser('shorttoken@example.com', 'Short Token User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity, null, true);

        // Short tokens should be 6 digit numbers.
        $this->assertEquals(6, strlen($token->getToken()));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $token->getToken());
        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin short token is within expected range.
     */
    public function testShortTokenInRange(): void
    {
        $user = $this->createUser('shortrange@example.com', 'Short Range User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity, null, true);

        $tokenValue = (int) $token->getToken();
        $this->assertGreaterThanOrEqual(102030, $tokenValue);
        $this->assertLessThanOrEqual(989796, $tokenValue);
        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin creates unique tokens.
     */
    public function testCreatesUniqueTokens(): void
    {
        $user = $this->createUser('uniquetoken@example.com', 'Unique Token User');
        $userEntity = $user->getEntity();

        $token1 = ($this->plugin)($userEntity);
        $token2 = ($this->plugin)($userEntity);
        $token3 = ($this->plugin)($userEntity);

        $this->assertNotEquals($token1->getToken(), $token2->getToken());
        $this->assertNotEquals($token2->getToken(), $token3->getToken());
        $this->assertNotEquals($token1->getToken(), $token3->getToken());

        $this->createdTokens[] = $token1->getId();
        $this->createdTokens[] = $token2->getId();
        $this->createdTokens[] = $token3->getId();
    }

    /**
     * Test plugin token is persisted to database.
     */
    public function testTokenIsPersistedToDatabase(): void
    {
        $user = $this->createUser('persist@example.com', 'Persist User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity);
        $tokenId = $token->getId();
        $tokenValue = $token->getToken();
        $this->createdTokens[] = $tokenId;

        // Clear entity manager and reload.
        $this->getEntityManager()->clear();

        $reloaded = $this->getEntityManager()->find(GuestToken::class, $tokenId);

        $this->assertNotNull($reloaded);
        $this->assertEquals($tokenValue, $reloaded->getToken());
        $this->assertEquals('persist@example.com', $reloaded->getEmail());
    }

    /**
     * Test plugin sets created timestamp.
     */
    public function testSetsCreatedTimestamp(): void
    {
        $user = $this->createUser('timestamp@example.com', 'Timestamp User');
        $userEntity = $user->getEntity();

        $before = new \DateTime();
        $token = ($this->plugin)($userEntity);
        $after = new \DateTime();

        $created = $token->getCreated();
        $this->assertInstanceOf(\DateTime::class, $created);
        $this->assertGreaterThanOrEqual($before, $created);
        $this->assertLessThanOrEqual($after, $created);

        $this->createdTokens[] = $token->getId();
    }

    /**
     * Test plugin can handle new (not yet persisted) user.
     */
    public function testHandlesNewUser(): void
    {
        // Create a user entity without persisting.
        $userEntity = new \Omeka\Entity\User();
        $userEntity->setEmail('newuser@example.com');
        $userEntity->setName('New User');
        $userEntity->setRole('guest');
        $userEntity->setIsActive(true);
        $userEntity->setPassword('password123');

        $token = ($this->plugin)($userEntity);

        $this->assertNotNull($token->getId());
        $this->assertNotNull($userEntity->getId());
        $this->assertEquals($userEntity->getId(), $token->getUser()->getId());

        $this->createdTokens[] = $token->getId();
        $this->createdUsers[] = $userEntity->getId();
    }

    /**
     * Test plugin creates token with confirmed = false.
     */
    public function testTokenIsNotConfirmedByDefault(): void
    {
        $user = $this->createUser('notconfirmed@example.com', 'Not Confirmed User');
        $userEntity = $user->getEntity();

        $token = ($this->plugin)($userEntity);

        $this->assertFalse($token->isConfirmed());
        $this->createdTokens[] = $token->getId();
    }
}
