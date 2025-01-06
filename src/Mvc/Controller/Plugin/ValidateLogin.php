<?php declare(strict_types=1);

namespace Guest\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Guest\Entity\GuestToken;
use Laminas\Authentication\AuthenticationService;
use Laminas\EventManager\EventManager;
use Laminas\Form\Form;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Laminas\Session\Container as SessionContainer;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Messenger;
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
     * @var Messenger
     */
    protected $messenger;

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
        Messenger $messenger,
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
        $this->messenger = $messenger;
        $this->request = $request;
        $this->settings = $settings;
        $this->twoFactorLogin = $twoFactorLogin;
        $this->site = $site;
        $this->config = $config;
        $this->hasModuleUserNames = $hasModuleUserNames;
    }

    /**
     * Validate login via form or api, user, and new user token.
     *
     * @param Form|array Form (data from request) or checked data (api).
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
    public function __invoke($formOrData)
    {
        if ($formOrData instanceof Form) {
            $isApi = false;
            $result = $this->checkPostAndValidForm($formOrData);
            if ($result !== true) {
                $email = $this->request->getPost('email');
                if ($email) {
                    $formOrData->get('email')->setValue($email);
                }
                return $result;
            }
            $validatedData = $formOrData->getData();
            $email = $validatedData['email'];
            $password = $validatedData['password'];
        } else {
            $isApi = true;
            $email = $formOrData['email'];
            $password = $formOrData['password'];
        }

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

        $this->messenger->clear();

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
            return $this->hasModuleUserNames
                ? 'User name, email, or password is invalid' // @translate
                : 'Email or password invalid'; // @translate
        }

        return true;
    }
}
