<?php declare(strict_types=1);

namespace GuestTest\Controller;

use GuestTest\GuestTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Base test case for Guest module controller tests.
 *
 * Provides common setup/teardown and helper methods using GuestTestTrait.
 */
abstract class GuestControllerTestCase extends AbstractHttpControllerTestCase
{
    use GuestTestTrait;

    /**
     * @var \Omeka\Api\Representation\SiteRepresentation
     */
    protected $testSite;

    /**
     * @var \Omeka\Api\Representation\UserRepresentation
     */
    protected $testUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAdmin();
        $this->setupMockMailer();

        // Clean up any stale test resources from previous failed runs.
        $this->cleanupStaleTestResources();

        $this->testSite = $this->createSite('test', 'Test');
        $this->testUser = $this->createUser('test@test.fr', 'Tester', 'global_admin', 'test');
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Clean up stale test resources that may exist from previous failed runs.
     */
    protected function cleanupStaleTestResources(): void
    {
        $em = $this->getEntityManager();

        // Clean up stale 'test' site.
        try {
            $sites = $this->api()->search('sites', ['slug' => 'test'])->getContent();
            foreach ($sites as $site) {
                $this->api()->delete('sites', $site->id());
            }
        } catch (\Exception $e) {
            // Ignore.
        }

        // Clean up stale 'test@test.fr' user.
        try {
            $user = $em->getRepository('Omeka\Entity\User')
                ->findOneBy(['email' => 'test@test.fr']);
            if ($user) {
                // Also clean up associated guest tokens.
                $tokens = $em->getRepository('Guest\Entity\GuestToken')
                    ->findBy(['user' => $user]);
                foreach ($tokens as $token) {
                    $em->remove($token);
                }
                $em->flush();
                $this->api()->delete('users', $user->getId());
            }
        } catch (\Exception $e) {
            // Ignore.
        }
    }

    /**
     * Reset the application after a dispatch.
     *
     * Re-initializes the mock mailer after reset.
     */
    protected function resetApplication(): void
    {
        parent::resetApplication();
        $this->services = null;
        $this->setupMockMailer();
    }
}
