<?php declare(strict_types=1);

namespace Guest\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Guest\Entity\GuestToken;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\EventManager;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Http\Request;
use Laminas\Session\Container as SessionContainer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\User;
use Omeka\Settings\Settings;

class ValidateLogin extends AbstractPlugin
{
    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var SiteRepresentation|null
     */
    protected $site;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var bool
     */
    protected $hasModuleUserNames;

    public function __construct(
        AuthenticationService $authenticationService,
        EntityManager $entityManager,
        EventManager $eventManager,
        Request $request,
        Settings $settings,
        ?SiteRepresentation $site,
        array $config,
        bool $hasModuleUserNames
    ) {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->eventManager = $eventManager;
        $this->request = $request;
        $this->settings = $settings;
        $this->site = $site;
        $this->config = $config;
        $this->hasModuleUserNames = $hasModuleUserNames;
    }

    /**
     * Validate login form, user, and new user token.
     *
     * @return bool|string False if not a post, true if validated and session
     * created, a message else. The form may be updated.
     */
    public function __invoke(Form $form)
    {
        $result = $this->checkPostAndValidForm($form);
        if ($result !== true) {
            $email = $this->request->getPost('email');
            if ($email) {
                $form->get('email')->setValue($email);
            }
            return $result;
        }

        // Process the login.

        $validatedData = $form->getData();
        $sessionManager = SessionContainer::getDefaultManager();
        $sessionManager->regenerateId();

        $adapter = $this->authenticationService->getAdapter();
        $adapter->setIdentity($validatedData['email']);
        $adapter->setCredential($validatedData['password']);
        $result = $this->authenticationService->authenticate();
        if (!$result->isValid()) {
            // Check if the user is under moderation in order to add a message.
            if ($this->settings->get('guest_open') !== 'open') {
                /** @var \Omeka\Entity\User $user */
                $user = $this->entityManager->getRepository(User::class)->findOneBy([
                    'email' => $validatedData['email'],
                ]);
                if ($user) {
                    $guestToken = $this->entityManager->getRepository(GuestToken::class)
                        ->findOneBy(['email' => $validatedData['email']], ['id' => 'DESC']);
                    if (empty($guestToken) || $guestToken->isConfirmed()) {
                        if (!$user->isActive()) {
                            return 'Your account is under moderation for opening.'; // @translate
                        }
                    } else {
                        return 'Check your email to confirm your registration.'; // @translate
                    }
                }
            }
            return implode(';', $result->getMessages());
        }

        $this->eventManager
            ->trigger('user.login', $this->authenticationService->getIdentity());

        return true;
    }

    protected function checkPostAndValidForm(Form $form)
    {
        if (!$this->request->isPost()) {
            return false;
        }

        $postData = $this->request->getPost();
        $form->setData($postData);
        if (!$form->isValid()) {
            return $this->hasModuleUserName
                ? 'User name, email, or password is invalid' // @translate
                : 'Email or password invalid'; // @translate
        }

        return true;
    }
}
