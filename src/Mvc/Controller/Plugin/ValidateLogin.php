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
use TwoFactorAuth\Mvc\Controller\Plugin\TwoFactorLogin;

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
     * @var TwoFactorLogin
     */
    protected $twoFactorLogin;

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
        ?TwoFactorLogin $twoFactorLogin,
        ?SiteRepresentation $site,
        array $config,
        bool $hasModuleUserNames
    ) {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->eventManager = $eventManager;
        $this->request = $request;
        $this->settings = $settings;
        $this->twoFactorLogin = $twoFactorLogin;
        $this->site = $site;
        $this->config = $config;
        $this->hasModuleUserNames = $hasModuleUserNames;
    }

    /**
     * Validate login form, user, and new user token.
     *
     * @return bool|null|int|string May be:
     * - null if internal error (cannot send mail),
     * - false if not a post or invalid (missing csrf, email, password),
     * - 0 for bad email or password,
     * - 1 if first step login is validated for a two-factor authentication,
     * - true if validated and session created,
     * - a message else.
     * The form may be updated.
     * Messages may be passed to Messenger for TwoFactorAuth.
     *
     * @todo Clarify output.
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

        $validatedData = $form->getData();
        $email = $validatedData['email'];
        $password = $validatedData['password'];

        // Manage the module TwoFactorAuth.
        $adapter = $this->authenticationService->getAdapter();
        if ($this->twoFactorLogin
            && $adapter instanceof \TwoFactorAuth\Authentication\Adapter\TokenAdapter
        ) {
            if ($this->twoFactorLogin->requireSecondFactor($email)) {
                $result = $this->twoFactorLogin->validateLoginStep1($email, $password);
                if ($result) {
                    $user = $this->twoFactorLogin->userFromEmail($email);
                    $result = $this->twoFactorLogin->prepareLoginStep2($user);
                    if (!$result) {
                        return null;
                    }
                    // Go to second step.
                    return 1;
                }
                return 0;
            }
            // Normal process without two-factor authentication.
            $adapter = $adapter->getRealAdapter();
            $this->authenticationService->setAdapter($adapter);
        }

        $sessionManager = SessionContainer::getDefaultManager();
        $sessionManager->regenerateId();

        // Process the login.
        $adapter
            ->setIdentity($email)
            ->setCredential($password);
        $result = $this->authenticationService->authenticate();
        if (!$result->isValid()) {
            // Check if the user is under moderation in order to add a message.
            if ($this->settings->get('guest_open') !== 'open') {
                /** @var \Omeka\Entity\User $user */
                $userRepository = $this->entityManager->getRepository(User::class);
                $user = $userRepository->findOneBy(['email' => $email]);
                if ($user) {
                    $guestToken = $this->entityManager->getRepository(GuestToken::class)
                        ->findOneBy(['email' => $email], ['id' => 'DESC']);
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

    /**
     * Validate login form, user, and new user token.
     *
     * @return bool|string False if not a post, true if validated, else a
     * message.
     */
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
