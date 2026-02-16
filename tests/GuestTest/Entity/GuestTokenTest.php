<?php declare(strict_types=1);

namespace GuestTest\Entity;

use DateTime;
use Guest\Entity\GuestToken;
use GuestTest\GuestTestTrait;
use Omeka\Entity\User;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Unit tests for the GuestToken entity.
 */
class GuestTokenTest extends AbstractHttpControllerTestCase
{
    use GuestTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->cleanupStaleEntityTestResources();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Clean up stale test resources that may exist from previous failed runs.
     */
    protected function cleanupStaleEntityTestResources(): void
    {
        $em = $this->getEntityManager();

        // List of test emails that may be stale.
        $testEmails = [
            'persist@example.com',
            'cascade@example.com',
            'findbyemail@example.com',
            'findbytoken@example.com',
            'multitoken@example.com',
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
     * Test entity can be instantiated.
     */
    public function testCanInstantiate(): void
    {
        $token = new GuestToken();
        $this->assertInstanceOf(GuestToken::class, $token);
    }

    /**
     * Test default confirmed state is false.
     */
    public function testDefaultConfirmedIsFalse(): void
    {
        $token = new GuestToken();
        $this->assertFalse($token->isConfirmed());
    }

    /**
     * Test email getter and setter.
     */
    public function testEmailGetterSetter(): void
    {
        $token = new GuestToken();
        $email = 'test@example.com';

        $result = $token->setEmail($email);

        $this->assertSame($token, $result);
        $this->assertEquals($email, $token->getEmail());
    }

    /**
     * Test token getter and setter.
     */
    public function testTokenGetterSetter(): void
    {
        $guestToken = new GuestToken();
        $tokenValue = sha1('random_token_' . microtime());

        $result = $guestToken->setToken($tokenValue);

        $this->assertSame($guestToken, $result);
        $this->assertEquals($tokenValue, $guestToken->getToken());
    }

    /**
     * Test confirmed getter and setter.
     */
    public function testConfirmedGetterSetter(): void
    {
        $token = new GuestToken();

        $result = $token->setConfirmed(true);

        $this->assertSame($token, $result);
        $this->assertTrue($token->isConfirmed());

        $token->setConfirmed(false);
        $this->assertFalse($token->isConfirmed());
    }

    /**
     * Test confirmed setter casts to boolean.
     */
    public function testConfirmedCastsToBoolean(): void
    {
        $token = new GuestToken();

        $token->setConfirmed(1);
        $this->assertTrue($token->isConfirmed());

        $token->setConfirmed(0);
        $this->assertFalse($token->isConfirmed());

        $token->setConfirmed('yes');
        $this->assertTrue($token->isConfirmed());

        $token->setConfirmed('');
        $this->assertFalse($token->isConfirmed());
    }

    /**
     * Test created date getter and setter.
     */
    public function testCreatedGetterSetter(): void
    {
        $token = new GuestToken();
        $created = new DateTime('2024-01-15 10:30:00');

        $result = $token->setCreated($created);

        $this->assertSame($token, $result);
        $this->assertEquals($created, $token->getCreated());
    }

    /**
     * Test user getter and setter.
     */
    public function testUserGetterSetter(): void
    {
        $user = $this->createUser('tokentest@example.com', 'Token Test User');
        $userEntity = $user->getEntity();

        $token = new GuestToken();
        $result = $token->setUser($userEntity);

        $this->assertSame($token, $result);
        $this->assertInstanceOf(User::class, $token->getUser());
        $this->assertEquals($userEntity->getId(), $token->getUser()->getId());
    }

    /**
     * Test entity can be persisted to database.
     */
    public function testCanPersistToDatabase(): void
    {
        $user = $this->createUser('persist@example.com', 'Persist Test');
        $userEntity = $user->getEntity();
        $em = $this->getEntityManager();

        $token = new GuestToken();
        $token->setUser($userEntity);
        $token->setEmail('persist@example.com');
        $token->setToken(sha1('persist_test_token'));
        $token->setConfirmed(false);
        $token->setCreated(new DateTime());

        $em->persist($token);
        $em->flush();

        $this->assertNotNull($token->getId());
        $this->createdTokens[] = $token->getId();

        // Verify can be retrieved.
        $em->clear();
        $retrieved = $em->find(GuestToken::class, $token->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals('persist@example.com', $retrieved->getEmail());
        $this->assertFalse($retrieved->isConfirmed());
    }

    /**
     * Test token is deleted when user is deleted (CASCADE).
     */
    public function testTokenDeletedOnUserDelete(): void
    {
        $em = $this->getEntityManager();

        // Create user and token.
        $user = $this->createUser('cascade@example.com', 'Cascade Test');
        $userId = $user->id();
        $userEntity = $user->getEntity();

        $token = new GuestToken();
        $token->setUser($userEntity);
        $token->setEmail('cascade@example.com');
        $token->setToken(sha1('cascade_test_token'));
        $token->setConfirmed(false);
        $token->setCreated(new DateTime());

        $em->persist($token);
        $em->flush();

        $tokenId = $token->getId();
        $this->assertNotNull($tokenId);

        // Remove from tracking since it will be cascade-deleted.
        $this->createdTokens = array_filter(
            $this->createdTokens,
            fn($id) => $id !== $tokenId
        );

        // Delete user.
        $em->clear();
        $this->api()->delete('users', $userId);

        // Remove from tracking since we just deleted it.
        $this->createdUsers = array_filter(
            $this->createdUsers,
            fn($id) => $id !== $userId
        );

        // Verify token is also deleted.
        $em->clear();
        $retrieved = $em->find(GuestToken::class, $tokenId);
        $this->assertNull($retrieved);
    }

    /**
     * Test finding token by email.
     */
    public function testFindByEmail(): void
    {
        $user = $this->createUser('findbyemail@example.com', 'Find By Email');
        $userEntity = $user->getEntity();
        $em = $this->getEntityManager();

        $token = new GuestToken();
        $token->setUser($userEntity);
        $token->setEmail('findbyemail@example.com');
        $token->setToken(sha1('find_by_email_token'));
        $token->setConfirmed(true);
        $token->setCreated(new DateTime());

        $em->persist($token);
        $em->flush();
        $this->createdTokens[] = $token->getId();

        // Find by email.
        $repository = $em->getRepository(GuestToken::class);
        $found = $repository->findOneBy(['email' => 'findbyemail@example.com']);

        $this->assertNotNull($found);
        $this->assertEquals($token->getId(), $found->getId());
        $this->assertTrue($found->isConfirmed());
    }

    /**
     * Test finding token by token value.
     */
    public function testFindByToken(): void
    {
        $user = $this->createUser('findbytoken@example.com', 'Find By Token');
        $userEntity = $user->getEntity();
        $em = $this->getEntityManager();

        $tokenValue = sha1('unique_token_value_' . microtime());

        $token = new GuestToken();
        $token->setUser($userEntity);
        $token->setEmail('findbytoken@example.com');
        $token->setToken($tokenValue);
        $token->setConfirmed(false);
        $token->setCreated(new DateTime());

        $em->persist($token);
        $em->flush();
        $this->createdTokens[] = $token->getId();

        // Find by token.
        $repository = $em->getRepository(GuestToken::class);
        $found = $repository->findOneBy(['token' => $tokenValue]);

        $this->assertNotNull($found);
        $this->assertEquals($token->getId(), $found->getId());
        $this->assertEquals('findbytoken@example.com', $found->getEmail());
    }

    /**
     * Test multiple tokens for same user.
     */
    public function testMultipleTokensForSameUser(): void
    {
        $user = $this->createUser('multitokens@example.com', 'Multi Tokens');
        $userEntity = $user->getEntity();
        $em = $this->getEntityManager();

        // Create two tokens for the same user.
        $token1 = new GuestToken();
        $token1->setUser($userEntity);
        $token1->setEmail('multitokens@example.com');
        $token1->setToken(sha1('token1_' . microtime()));
        $token1->setConfirmed(false);
        $token1->setCreated(new DateTime());

        $token2 = new GuestToken();
        $token2->setUser($userEntity);
        $token2->setEmail('multitokens_newmail@example.com');
        $token2->setToken(sha1('token2_' . microtime()));
        $token2->setConfirmed(true);
        $token2->setCreated(new DateTime());

        $em->persist($token1);
        $em->persist($token2);
        $em->flush();

        $this->createdTokens[] = $token1->getId();
        $this->createdTokens[] = $token2->getId();

        // Find all tokens for this user.
        $repository = $em->getRepository(GuestToken::class);
        $tokens = $repository->findBy(['user' => $userEntity]);

        $this->assertCount(2, $tokens);
    }
}
