<?php declare(strict_types=1);

namespace GuestTest\Controller;

use GuestTest\Service\MockMailerFactory;
use OmekaTestHelper\Controller\OmekaControllerTestCase;

abstract class GuestControllerTestCase extends OmekaControllerTestCase
{
    protected $testSite;
    protected $testUser;

    public function setUp(): void
    {
        $this->loginAsAdmin();

        $this->setupMockMailer();

        $this->testSite = $this->createSite('test', 'Test');
        $this->testUser = $this->createTestUser();
    }

    public function tearDown(): void
    {
        $this->loginAsAdmin();
        $this->api()->delete('users', $this->testUser->id());
        $this->api()->delete('sites', $this->testSite->id());
    }

    protected function setupMockMailer(): void
    {
        $serviceLocator = $this->getServiceLocator();
        $config = $serviceLocator->get('Config');
        $config['service_manager']['factories']['Omeka\Mailer'] = 'GuestTest\Service\MockMailerFactory';
        $serviceLocator->setAllowOverride(true);
        $serviceLocator->setService('Config', $config);
        $serviceLocator->setFactory('Omeka\Mailer', new MockMailerFactory);
        $serviceLocator->setAllowOverride(false);
    }

    protected function createSite($slug, $title)
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => $title,
            'o:is_public' => '1',
        ]);
        return $response->getContent();
    }

    protected function createTestUser()
    {
        $response = $this->api()->create('users', [
            'o:email' => 'test@test.fr',
            'o:name' => 'Tester',
            'o:role' => 'global_admin',
            'o:is_active' => '1',
        ]);
        $user = $response->getContent();
        $userEntity = $user->getEntity();
        $userEntity->setPassword('test');
        $this->getEntityManager()->persist($userEntity);
        $this->getEntityManager()->flush();

        return $user;
    }

    protected function resetApplication(): void
    {
        parent::resetApplication();

        $this->setupMockMailer();
    }
}
