<?php declare(strict_types=1);

namespace Guest\Controller;

use Common\Stdlib\PsrMessage;
use Doctrine\ORM\EntityManager;
use Guest\Entity\GuestToken;
use Laminas\Authentication\AuthenticationService;
use Laminas\Http\Response;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Math\Rand;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\Exception\RuntimeException;
use Laminas\Session\Container as SessionContainer;
use Laminas\View\Model\JsonModel;
use Omeka\Api\Adapter\SiteAdapter;
use Omeka\Api\Adapter\UserAdapter;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\SitePermission;
use Omeka\Entity\Site;
use Omeka\Entity\User;
use Omeka\Stdlib\Paginator;
use TwoFactorAuth\Form\TokenForm;

/**
 * Allow to manage "me" and auth actions via api.
 *
 * Replace ApiController with right jsend messages and JsonModel.
 * Auth adapter is the standard adapter, not key_identity/key_credential.
 *
 * @todo Add an option to use key_identity/key_credential with GuestApiController and deprecate ApiController?
 *
 * @see https://github.com/omniti-labs/jsend
 */
class GuestApiController extends AbstractActionController
{
    use TraitGuestController;

    const ERROR = 'error';
    const FAIL = 'fail';
    const SUCCESS = 'success';

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var AuthenticationService
     */
    protected $authenticationServiceSession;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var \Omeka\Stdlib\Paginator
     */
    protected $paginator;

    /**
     * @var SiteAdapter
     */
    protected $siteAdapter;

    /**
     * @var \Laminas\I18n\Translator\TranslatorInterface
     */
    protected $translator;

    /**
     * @var UserAdapter
     */
    protected $userAdapter;

    public function __construct(
        ApiManager $api,
        AuthenticationService $authenticationService,
        AuthenticationService $authenticationServiceSession,
        array $config,
        EntityManager $entityManager,
        Paginator $paginator,
        SiteAdapter $siteAdapter,
        TranslatorInterface $translator,
        UserAdapter $userAdapter
    ) {
        $this->api = $api;
        $this->authenticationService = $authenticationService;
        $this->authenticationServiceSession = $authenticationServiceSession;
        $this->config = $config;
        $this->entityManager = $entityManager;
        $this->paginator = $paginator;
        $this->siteAdapter = $siteAdapter;
        $this->translator = $translator;
        $this->userAdapter = $userAdapter;
    }

    /**
     * Get info about me (alias of /api/users/#id, except for failure).
     */
    public function meAction()
    {
        $user = $this->authenticationService->getIdentity();
        if (!$user) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('Unauthorized access.'), // @translate
            ], null, Response::STATUS_CODE_401);
        }
        $userRepr = $this->userAdapter->getRepresentation($user);
        return $this->jSend(self::SUCCESS, [
            'user' => $userRepr,
        ]);
    }

    /**
     * Login via api.
     *
     * Here, it's not the true api, so there may be credentials that are not checked.
     */
    public function loginAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        $user = $this->loggedUser();
        if ($user) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('User cannot login: already logged.'), // @translate
            ]);
        }

        /** @var \Laminas\Http\PhpEnvironment\Request $request */
        $data = $this->params()->fromPost() ?: [];

        if (empty($data['email'])) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Email is required.'), // @translate
            ]);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Invalid email.'), // @translate
            ]);
        }

        if (empty($data['password'])) {
            return $this->jSend(self::FAIL, [
                'password' => $this->translate('Password is required.'), // @translate
            ]);
        }

        // Process authentication via entity manager.
        /** @var \Omeka\Entity\User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $data['email'],
            'isActive' => true,
        ]);

        if (!$user) {
            // Slow down the process to avoid brute force.
            sleep(3);
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('Wrong email or password.'), // @translate
            ]);
        }

        if (!$user->verifyPassword($data['password'])) {
            // Slow down the process to avoid brute force.
            sleep(3);
            return $this->jSend(self::FAIL, [
                // Same message as above for security.
                'user' => $this->translate('Wrong email or password.'), // @translate
            ]);
        }

        $role = $user->getRole();
        $loginRoles = $this->settings()->get('guest_login_roles', []);
        if (!in_array('all', $loginRoles) && !in_array($role, $loginRoles)) {
            $message = new PsrMessage(
                'Role "{role]" is not allowed to login via api.', // @translate
                ['role' => $role]
            );
            return $this->jSend(self::FAIL, [
                'user' => $message->setTranslator($this->translator()),
            ]);
        }

        $result = $this->validateLogin([
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if ($result === null) {
            // Internal error (no mail sent).
            return $this->jSend(self::ERROR);
        } elseif ($result === false) {
            // Email or password error, so retry below.
            // Slow down the process to avoid brute force.
            sleep(3);
            return $this->jSend(self::FAIL, [
                'login' => $this->translate('Wrong email or password.'), // @translate
            ]);
        } elseif ($result === 0) {
            // Email or password error in 2FA. Sleep is already processed.
            return $this->jSend(self::FAIL, [
                'login' => $this->translate('Wrong email or password.'), // @translate
            ]);
        } elseif ($result === 1) {
            // Success login in first step in 2FA, so go to second step.
            return $this->jSend(self::SUCCESS, [
                'login' => null,
                'token_email' => null,
                'dialog' => $this->viewHelpers()->get('partial')('common/dialog/2fa-token', [
                    'form' => $this->getForm(TokenForm::class)->setAttribute('action', $this->url()->fromRoute('login')),
                ]),
            ]);
        } elseif (is_string($result)) {
            // Moderation or confirmation missing or other issue.
            return $this->jSend(self::FAIL, [
                'login' => $result,
            ]);
        } else {
            // Here, the user is authenticated.
            $user = $this->identity();
            $sessionToken = $this->prepareSessionToken($user);
            return $this->jSend(self::SUCCESS, [
                'login' => true,
                'user' => $this->userAdapter->getRepresentation($user),
                'session_token' => [
                    'o:user' => [
                        '@id' => $this->url()->fromRoute('api/default', ['resource' => 'users', 'id' => $user->getId()], ['force_canonical' => true]),
                        'o:id' => $user->getId(),
                    ],
                ] + $sessionToken,
            ]);
        }
    }

    public function logoutAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        /** @var \Omeka\Entity\User $user */
        $user = $this->authenticationService->getIdentity();
        if (!$user) {
            $user = $this->authenticationServiceSession->getIdentity();
            if (!$user) {
                return $this->jSend(self::FAIL, [
                    'user' => $this->translate('User not logged.'), // @translate
                ]);
            }
        }

        $this->removeSessionTokens($user);

        // TODO Use authentication chain.
        // In all cases, the logout is done on all authentication services.
        $this->authenticationService->clearIdentity();
        $this->authenticationServiceSession->clearIdentity();

        $sessionManager = SessionContainer::getDefaultManager();

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.logout');

        $sessionManager->destroy();

        $message = $this->translate('Successfully logout.'); // @translate
        return $this->jSend(self::SUCCESS, [
            'user' => null,
        ], $message);
    }

    public function sessionTokenAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        $user = $this->loggedUser();
        if (!$user) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('Unauthorized access.'), // @translate
            ], null, Response::STATUS_CODE_401);
        }
        $sessionToken = $this->prepareSessionToken($user);
        return $this->jSend(self::SUCCESS, [
            'login' => true,
            'session_token' => [
                'o:user' => [
                    '@id' => $this->url()->fromRoute('api/default', ['resource' => 'users', 'id' => $user->getId()], ['force_canonical' => true]),
                    'o:id' => $user->getId(),
                ],
            ] + $sessionToken,
        ]);
    }

    /**
     * @see \Guest\Controller\Site\GuestController::registerAction()
     */
    public function registerAction()
    {
        $returnError = $this->checkCors();
        if ($returnError) {
            return $returnError;
        }

        $settings = $this->settings();
        $apiOpenRegistration = $settings->get('guest_open');
        if ($apiOpenRegistration === 'closed') {
            return $this->jSend('fail', [
                'user' => $this->translate('Access forbidden.'), // @translate
            ], null, Response::STATUS_CODE_403);
        }

        if ($this->loggedUser()) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('User cannot register: already logged.'), // @translate
            ]);
        }

        // Unlike api post for creation, the registering creates the user and
        // sends an email with a token.

        // TODO Use validator from the user form?

        /** @var \Laminas\Http\PhpEnvironment\Request $request */
        $data = $this->params()->fromPost() ?: [];

        // When the data come from the user form, it can be checked directly.
        // Furthermore, some data should be reordered.
        if (isset($data['user-information']['o:email'])) {
            $form = $this->getUserForm();
            $form->setData($data);
            if (!$form->isValid()) {
                // TODO Add flat messages (common 3.4.65).
                return $this->jSend(self::FAIL, $form->getMessages());
            }
            $data = $form->getData();
        }
        $data['email'] ??= $data['user-information']['o:email'] ?? null;
        $data['username'] ??= $data['user-information']['o:name'] ?? null;
        $data['password'] ??= $data['change-password']['password-confirm']['password'] ?? null;

        $site = null;
        $siteEntity = null;
        $settings = $this->settings();
        if ($settings->get('guest_register_site')) {
            if (empty($data['site'])) {
                return $this->jSend(self::FAIL, [
                    'site' => $this->translate('A site is required to register.'), // @translate
                ]);
            }

            // The site may be private, so don't use api.
            $siteEntityRepository = $this->entityManager->getRepository(Site::class);
            $criteria = new \Doctrine\Common\Collections\Criteria();
            $criteria->where($criteria->expr()->eq(is_numeric($data['site']) ? 'id' : 'slug', $data['site']));
            $siteEntity = $siteEntityRepository->matching($criteria)->first();
            if (empty($siteEntity)) {
                return $this->jSend(self::FAIL, [
                    'site' => $this->translate('The site doesn’t exist.'), // @translate
                ]);
            }
            $site = $this->siteAdapter->getRepresentation($siteEntity);
        }

        if (!isset($data['email'])) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Email is required.'), // @translate
            ]);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Invalid email.'), // @translate
            ]);
        }

        if (empty($data['username'])) {
            $data['username'] = $data['email'];
        }

        if (!isset($data['password'])) {
            $data['password'] = null;
        }

        $emailIsAlwaysValid = $settings->get('guest_register_email_is_valid');

        $userInfo = [];
        $userInfo['o:email'] = $data['email'];
        $userInfo['o:name'] = $data['username'];
        // TODO Avoid to set the right to change role (fix core).
        $userInfo['o:role'] = \Guest\Permissions\Acl::ROLE_GUEST;
        $userInfo['o:is_active'] = false;

        // Before creation, check the email too to manage confirmation, rights
        // and module UserNames.
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $userInfo['o:email'],
        ]);
        if ($user) {
            $guestToken = $this->entityManager->getRepository(GuestToken::class)
                ->findOneBy(['email' => $userInfo['o:email']], ['id' => 'DESC']);
            if (empty($guestToken) || $guestToken->isConfirmed()) {
                return $this->jSend(self::FAIL, [
                    'user' => $this->translate('Already registered.'), // @translate
                ]);
            }

            // This is a second registration, but the token is not set, but
            // the option may have been updated.
            if ($guestToken && $emailIsAlwaysValid) {
                $guestToken->setConfirmed(true);
                $this->entityManager->persist($guestToken);
                $this->entityManager->flush();
                return $this->jSend(self::FAIL, [
                    'user' => $this->translate('Already registered.'), // @translate
                ]);
            }

            // TODO Check if the token is expired to ask a new one.
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('Check your email to confirm your registration.'), // @translate
            ]);
        }

        // Because creation of a username (module UserNames) by an anonymous
        // visitor is not possible, a check is done for duplicates first to
        // avoid issue later.
        if ($this->hasModuleUserNames()) {
            // The username is a required data and it must be valid.
            // Get the adapter through the services.
            $userNameAdapter = $this->api()->read('vocabularies', 1)->getContent()->getServiceLocator()
                ->get('Omeka\ApiAdapterManager')->get('usernames');
            $userName = new \UserNames\Entity\UserNames;
            $userName->setUserName($data['o-module-usernames:username'] ?? '');
            $errorStore = new \Omeka\Stdlib\ErrorStore;
            $userNameAdapter->validateEntity($userName, $errorStore);
            // Only the user name is validated here.
            $errors = $errorStore->getErrors();
            if (!empty($errors['o-module-usernames:username'])) {
                // TODO Return only the first error currently.
                return $this->jSend(self::FAIL, [
                    'user' => $this->translate(reset($errors['o-module-usernames:username'])),
                ]);
            }
            $userInfo['o-module-usernames:username'] = $data['o-module-usernames:username'];
        }

        // Check the creation of the user to manage the creation of usernames:
        // the exception occurs in api.create.post, so user is created.
        try {
            /** @var \Omeka\Entity\User $user */
            $user = $this->api()->create('users', $userInfo, [], ['responseContent' => 'resource'])->getContent();
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            // This is the exception thrown by the module UserNames, so the user
            // is created, but not the username.
            // Anonymous user cannot read User, so use entity manager.
            $user = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            // An error occurred in another module.
            if (!$user) {
                return $this->jSend(self::ERROR, null,
                    $this->translate('Unknown error before creation of user.') // @translate
                );
            }
            if ($this->hasModuleUserNames()) {
                // Check the user for security.
                // If existing, it will be related to a new version of module UserNames.
                $userNames = $this->api()->search('usernames', ['user' => $user->getId()])->getContent();
                if (!$userNames) {
                    // Create the username via the entity manager because the
                    // user is not logged, so no right.
                    $userName = new \UserNames\Entity\UserNames;
                    $userName->setUser($user);
                    $userName->setUserName($userInfo['o-module-usernames:username']);
                    $this->entityManager->persist($userName);
                    $this->entityManager->flush();
                }
            } else {
                // Issue in another module?
                // Log error, but continue registering (email is checked before,
                // so it is a new user in any case).
                $this->logger()->err(
                    'An error occurred after creation of the guest user: {exception}', // @translate
                    ['exception' => $e]
                );
            }
            // TODO Check for another exception at the same time…
        } catch (\Exception $e) {
            $this->logger()->err($e);
            $user = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            if (!$user) {
                return $this->jSend(self::ERROR, null,
                    $this->translate('Unknown error during creation of user.') // @translate
                );
            }
            // Issue in another module?
            // Log error, but continue registering (email is checked before,
            // so it is a new user in any case).
        }

        $user->setPassword($data['password']);
        // To create user with another role, don't use register, but /api/users.
        $user->setRole(\Guest\Permissions\Acl::ROLE_GUEST);
        // The account is active, but not confirmed, so login is not possible.
        // Guest user has no right to set active his account.
        // Except if the option "email is valid" is set.
        $isOpenRegister = $this->isOpenRegister();
        $user->setIsActive($isOpenRegister);

        $id = $user->getId();
        // For compatibility with user form.
        if (!empty($data['user-settings']) && is_array($data['user-settings'])) {
            $data['o:settings'] = isset($data['o:settings']) && is_array($data['o:settings'])
                ? array_merge($data['o:settings'], $data['user-settings'])
                : $data['user-settings'];
            unset($data['user-settings']);
        }
        if (!empty($data['o:settings']) && is_array($data['o:settings'])) {
            $userSettings = $this->userSettings();
            foreach ($data['o:settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        // Add the user as a viewer of the specified site.
        // TODO Add a check of the site.
        if ($siteEntity) {
            // A guest user cannot update site, so the entity manager is used.
            $sitePermission = new SitePermission;
            $sitePermission->setSite($siteEntity);
            $sitePermission->setUser($user);
            $sitePermission->setRole(SitePermission::ROLE_VIEWER);
            $siteEntity->getSitePermissions()->add($sitePermission);
            $this->entityManager->persist($siteEntity);
            $this->entityManager->flush();
            // $this->api->update('sites', $site->id(), [
            //     'o:site_permission' => [
            //         'o:user' => ['o:id' => $user->getId()],
            //         'o:role' => 'viewer',
            //     ],
            // ], [], ['isPartial' => true]);
        } else {
            // The site may be private.
            $site = $this->viewHelpers()->get('defaultSite')();
            $siteEntity = $site
                ? $this->entityManager->find(Site::class, $site->id())
                : null;
            // User is flushed when the guest user token is created.
            $this->entityManager->persist($user);
        }

        // Set the current site, disabled in api.
        // The site may be private, so check it.
        if ($site) {
            $this->getPluginManager()->get('currentSite')->setSite($site);
        }

        if ($emailIsAlwaysValid) {
            $this->entityManager->flush();
            $guestToken = null;
        } else {
            $guestToken = $this->createGuestToken($user);
        }
        // TODO Clarify process: why the confirmation texts are separated?
        $message = $this->prepareMessage('confirm-email', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'token' => $guestToken,
            'site' => $site,
        ]);
        $result = $this->sendEmail($user->getEmail(), $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            return $this->jSend(self::ERROR, null,
                $this->translate('An error occurred when the email was sent.') // @translate
            );
        }

        // In any case, warn the administrator that a new user is registering.
        $message = $this->prepareMessage('notify-registration', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'site' => $site,
        ]);
        $fromEmail = $this->settings()->get('administrator_email');
        $toEmails = $this->settings()->get('guest_notify_register') ?: $fromEmail;
        $result = $this->sendEmail($toEmails, $message['subject'], $message['body'], $user->getName());

        if ($emailIsAlwaysValid) {
            $message = $this->settings()->get('guest_message_confirm_register_site')
                ?: $this->translate('Thank you for registering. You can now log in and use the library.'); // @translate
        } elseif ($this->isOpenRegister()) {
            $message = $this->settings()->get('guest_message_confirm_register_site')
                ?: $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.'); // @translate
        } else {
            $message = $this->settings()->get('guest_message_confirm_register_site')
                ?: $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, a moderator will confirm registration.'); // @translate
        }

        return $this->jSend(self::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
            'redirect_url' => $site && $site->isPublic()
                ? $site->siteUrl()
                : $this->url()->fromRoute('top'),
        ], $message);
    }

    /**
     * @see \Omeka\Controller\ApiController::forgotPasswordAction()
     */
    public function forgotPasswordAction()
    {
        $user = $this->loggedUser();
        if ($user) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('A logged user cannot change the password with this method.'), // @translate
            ]);
        }

        $data = $this->params()->fromPost() ?: [];

        if (!isset($data['email'])) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Email is required.'), // @translate
            ]);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Invalid email.'), // @translate
            ]);
        }

        // Use entity manager, because anonymous user cannot read users.
        /** @var \Omeka\Entity\User $user */
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => $data['email'],
            ]);
        if (!$user) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Invalid email.'), // @translate
            ]);
        }

        if (!$user->isActive()) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('User is not active and cannot update password.'), // @translate
            ]);
        }

        $token = $data['token'] ?? null;

        // Send a token with a simple id.
        if (!$token) {
            // Adapted from \Omeka\Stdlib\Mailer::sendUserActivation()

            // Remove existing token before creating new one, there can be only one.
            $passwordCreation = $this->entityManager
                ->getRepository(\Omeka\Entity\PasswordCreation::class)
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $this->entityManager->remove($passwordCreation);
                $this->entityManager->flush();
            }
            /** @var \Omeka\Stdlib\Mailer $mailer */
            $mailer = $this->mailer();
            $passwordCreation = $mailer->getPasswordCreation($user, false);
            // The id is created automatically and cannot be changed via setId().
            // So create a unique random token automatically.
            while (true) {
                $newId = Rand::getString(8, '1234567890');
                $check = $this->entityManager->find(
                    \Omeka\Entity\PasswordCreation::class,
                    $newId
                );
                if (!$check) {
                    break;
                }
            }
            $sql = <<<'SQL'
                UPDATE `password_creation`
                SET `id` = :new_id
                WHERE `id` = :old_id;
                SQL;
            $this->entityManager->getConnection()->executeStatement($sql, [
                'old_id' => $passwordCreation->getId(),
                'new_id' => $newId,
            ]);

            // Send the token.
            $installationTitle = $mailer->getInstallationTitle();
            $subject = new PsrMessage(
                'User token for {site_title}', // @translate
                ['site_title' => $installationTitle]
            );
            $body = new PsrMessage(<<<'TXT'
                Greetings!
                
                To reset your password on {site_title}, fill this token in the app: {token}.
                
                Your activation link will expire on {date}.
                TXT, // @translate
                [
                    'site_title' => $installationTitle,
                    'token' => $newId,
                    // The default expiration time is too much long (two weeks), so
                    // limit it to one hour.
                    // $this->viewHelpers()->get('i18n')->dateFormat($passwordCreation->getExpiration(), 'medium', 'medium')
                    'date' => $this->viewHelpers()->get('i18n')->dateFormat(
                        $passwordCreation->getCreated()->add(new \DateInterval('PT1H')),
                        'medium',
                        'medium'
                    ),
                ]
            );

            $message = $mailer->createMessage();
            $message->addTo($user->getEmail(), $user->getName())
                ->setSubject($subject->setTranslator($this->translator())->translate())
                ->setBody($body->setTranslator($this->translator())->translate());
            $mailer->send($message);

            return $this->jSend(self::SUCCESS, [
                // Of course, don't send anything else here.
                'email' => $data['email'],
            ]);
        }

        // Check token.
        // See \Omeka\Controller\ApiController::createPasswordAction()
        /** @var \Omeka\Entity\PasswordCreation $passwordCreation */
        $passwordCreation = $this->entityManager->find(
            \Omeka\Entity\PasswordCreation::class,
            $token
        );

        if (!$passwordCreation) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('Invalid token.'), // @translate
            ]);
        }

        $userToken = $passwordCreation->getUser();
        if ($userToken->getId() !== $user->getId()) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('This token is invalid. Check your email.'), // @translate
            ]);
        }

        // The default expiration (deux weeks) is too much long for a
        // forgot-password process, so use 1 hour.
        if (new \DateTime > $passwordCreation->getCreated()->add(new \DateInterval('PT1H'))) {
            $this->entityManager->remove($passwordCreation);
            $this->entityManager->flush();
            return $this->jSend(self::FAIL, [
                'token' => $this->translate('Password token expired.'), // @translate
            ]);
        }

        if (!isset($data['password'])) {
            return $this->jSend(self::FAIL, [
                'token' => $this->translate('Password is required.'), // @translate
            ]);
        }

        // TODO Use Omeka checks for password.
        if (strlen($data['password']) < 6) {
            return $this->jSend(self::FAIL, [
                'token' => $this->translate('New password should have 6 characters or more.'), // @translate
            ]);
        }

        $user->setPassword($data['password']);
        if ($passwordCreation->activate()) {
            $user->setIsActive(true);
        }
        $this->entityManager->remove($passwordCreation);
        $this->entityManager->flush();

        return $this->jSend(self::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
        ]);
    }

    public function dialogAction()
    {
        $dialog = $this->params()->fromQuery('name');
        $dialogTemplates = [
            'login' => 'common/dialog/login',
            'register' => 'common/dialog/register',
            'forgot-password' => 'common/dialog/forgot-password',
            '2fa-token' => 'common/dialog/2fa-token',
        ];

        if (!isset($dialogTemplates[$dialog])) {
            return $this->jSend(self::FAIL, [
                'dialog' => $this->translate('This dialog is not managed.'), // @translate
            ]);
        }

        $hasForm = true;
        if ($dialog === 'login') {
            $hasForm = !$this->siteSettings()->get('guest_login_without_form');
        } elseif ($dialog === '2fa-token' && !$this->getPluginManager()->has('twoFactorLogin')) {
            return $this->jSend(self::FAIL, [
                'dialog' => $this->translate('This dialog requires module Two-Factor Authentication.'), // @translate
            ]);
        }

        if ($hasForm) {
            $dialogForms = [
                'login' => $this->hasModuleUserNames()
                    ? \UserNames\Form\LoginForm::class
                    : \Omeka\Form\LoginForm::class,
                'register' => null,
                'forgot-password' => \Omeka\Form\ForgotPasswordForm::class,
                '2fa-token' => \TwoFactorAuth\Form\TokenForm::class,
            ];
            try {
                $form = $dialog === 'register'
                    ? $this->getUserForm()
                    : $this->getForm($dialogForms[$dialog]);
            } catch (\Exception $e) {
                return $this->jSend(self::ERROR, null,
                    $this->translate('An error occurred when loading dialog.'), // @translate
                );
            }
            $action = $dialog === '2fa-token'
                ? $this->url()->fromRoute('login')
                : $this->url()->fromRoute('api/guest', ['action' => $dialog], true);
            $form->setAttribute('action', $action);
        }
        $args = [
            'form' => $form ?? null,
        ];

        $this->prepareSiteTemplates();

        $template = $dialogTemplates[$dialog];
        return $this->jSend(self::SUCCESS, [
            'dialog' => $this->viewHelpers()->get('partial')($template, $args),
        ]);
    }

    // TODO Confirmation through api, not via module guest user (but the email link is always a web page!)

    /**
     * Check cors and prepare the response.
     */
    protected function checkCors()
    {
        // Check cors if any.
        $cors = $this->settings()->get('guest_cors') ?: ['*'];
        if (in_array('*', $cors)) {
            $origin = '*';
        } else {
            /** @var \Laminas\Http\Header\Origin|false $origin */
            $origin = $this->getRequest()->getHeader('Origin');
            if ($origin) {
                $origin = $origin->getFieldValue();
            }
            if (!$origin
                || (!is_array($origin) && !in_array($origin, $cors))
                || (is_array($origin) && !array_intersect($origin, $cors))
            ) {
                return $this->jSend(self::FAIL, [
                    'user' => $this->translate('Access forbidden.'), // @translate
                ], null, Response::STATUS_CODE_403);
            }
        }

        // Add cors origin.
        $session = json_decode(json_encode($_SESSION), true);
        $this->getResponse()->getHeaders()
            ->addHeaderLine('Access-Control-Allow-Origin', $origin)
            // @link https://stackoverflow.com/questions/58270663/samesite-warning-chrome-77
            ->addHeaderLine('Set-Cookie',
                session_name() . '='
                . ($session['__ZF']['_VALID']['Laminas\Session\Validator\Id'] ?? '')
                . '; Path=/; HttpOnly; Secure; SameSite=None')
        ;
        return null;
    }

    /**
     * Check if a user is logged.
     *
     * This method simplifies derivative modules that use the same code.
     *
     * @return bool
     */
    protected function isUserLogged()
    {
        return $this->authenticationService->hasIdentity();
    }

    /**
     * Check if the registering is open or moderated.
     *
     *  @return bool True if open, false if moderated (or closed).
     */
    protected function isOpenRegister()
    {
        return $this->settings()->get('guest_open') === 'open';
    }

    /**
     * Check if a user is logged and return it.
     *
     * This method simplifies derivative modules that use the same code.
     *
     * @return User|null
     */
    protected function loggedUser()
    {
        $user = $this->authenticationService->getIdentity();
        if ($user && $this->settings()->get('guest_login_session')) {
            $userPass = $this->authenticationServiceSession->getIdentity();
            if ($user !== $userPass) {
                $storage = $this->authenticationServiceSession->getStorage();
                $storage->clear();
                $storage->write($user);
            }
        } else {
            $this->authenticationServiceSession->clearIdentity();
        }
        return $user;
    }

    /**
     * Update me is always a patch.
     *
     * @param bool $isUpdate Currently not used: always a partial patch.
     */
    protected function updatePatch(User $user, array $data, $isUpdate = false)
    {
        if (empty($data) || !array_filter($data)) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('Request is empty.'), // @translate
            ]);
        }

        if (isset($data['password']) || isset($data['new_password'])) {
            return $this->changePassword($user, $data);
        }

        // By exception, two common metadata can be without prefix.
        if (isset($data['name'])) {
            $data['o:name'] = $data['name'];
        }
        unset($data['name']);
        if (isset($data['email'])) {
            $data['o:email'] = $data['email'];
        }
        unset($data['email']);

        if (isset($data['o:email'])) {
            $settings = $this->settings();
            if ($settings->get('guest_register_site')) {
                $site = $this->userSites($user, true);
                if (empty($site)) {
                    return $this->jSend(self::FAIL, [
                        'email' => $this->translate('Email cannot be updated: the user is not related to a site.'), // @translate
                    ]);
                }
            } else {
                $site = $this->viewHelpers()->get('defaultSite')();
            }

            $this->getPluginManager()->get('currentSite')->setSite($site);
            return $this->changeEmail($user, $data);
        }

        // For security, keep only the updatable data.
        // TODO Check if acl forbids change of the role for guest and other public roles.
        $toPatch = array_intersect_key($data, [
            'o:name' => null,
            // 'o:email' => null,
            // 'password' => null,
            // 'new_password' => null,
        ]);
        if (count($data) !== count($toPatch)) {
            return $this->jSend(self::FAIL, [
                'user' => $this->translate('Your request contains metadata that cannot be updated.'), // @translate
            ]);
        }

        if (isset($data['o:name']) && empty($data['o:name'])) {
            return $this->jSend(self::FAIL, [
                'name' => $this->translate('The new name is empty.'), // @translate
            ]);
        }

        // Update me is always partial for security, else use standard api.
        $response = $this->api->update('users', $user->getId(), $toPatch, [], ['isPartial' => true]);
        return $this->jSend(self::SUCCESS, [
            'user' => $response->getContent(),
        ]);
    }

    protected function changePassword(User $user, array $data)
    {
        // TODO Remove limit to update password separately.
        if (count($data) > 2) {
            return $this->jSend(self::FAIL, [
                'password' => $this->translate('You cannot update password and another data in the same time.'), // @translate
            ]);
        }
        if (empty($data['password'])) {
            return $this->jSend(self::FAIL, [
                'password' => $this->translate('Existing password empty.'), // @translate
            ]);
        }
        if (empty($data['new_password'])) {
            return $this->jSend(self::FAIL, [
                'password' => $this->translate('New password empty.'), // @translate
            ]);
        }
        // TODO Use Omeka checks for password.
        if (strlen($data['new_password']) < 6) {
            return $this->jSend(self::FAIL, [
                'password' => $this->translate('New password should have 6 characters or more.'), // @translate
            ]);
        }
        if (!$user->verifyPassword($data['password'])) {
            // Security to avoid batch hack.
            sleep(1);
            return $this->jSend(self::FAIL, [
                'password' => $this->translate('Wrong password.'), // @translate
            ]);
        }

        $user->setPassword($data['new_password']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->jSend(self::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
        ], $this->translate('Password successfully changed')); // @translate
    }

    /**
     * Update email.
     *
     * @todo Factorize with Guest.
     */
    protected function changeEmail(User $user, array $data)
    {
        // TODO Remove limit to update email separately.
        if (count($data) > 1) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('You cannot update email and another data in the same time.'), // @translate
            ]);
        }
        if (empty($data['o:email'])) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('New email empty.'), // @translate
            ]);
        }
        $email = $data['o:email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = new PsrMessage(
                '"{email}" is not an email.', // @translate
                ['email' => $email]
            );
            return $this->jSend(self::FAIL, [
                'email' => $message->setTranslator($this->translator)
            ]);
        }

        if ($email === $user->getEmail()) {
            return $this->jSend(self::FAIL, [
                'email' => $this->translate('The new email is the same than the current one.'), // @translate
            ]);
        }

        try {
            $existUser = $this->api()->read('users', ['email' => $email])->getContent();
        } catch (\Exception $e) {
            $existUser = null;
        }
        if ($existUser) {
            // Avoid a hack of the database.
            sleep(1);
            $this->logger()->warn(
                'User #{user_id} wants to change email from "{email}" to "{email_2}", used by user #{user_id_2}.', // @translate
                ['user_id' => $user->getId(), 'email' => $user->getEmail(), 'email_2' => $email, 'user_id_2' => $existUser->id()]
            );
            $message = new PsrMessage(
                'The email "{email}" is not yours.', // @translate
                ['email' => $email]
            );
            return $this->jSend(self::FAIL, [
                'email' => $message->setTranslator($this->translator),
            ]);
        }

        // Add a second check for the email (needed for an unknown reason: cache?).
        /** @var \Omeka\Entity\User $user */
        $users = $this->entityManager->getRepository(User::class)->findBy([
            'email' => $email,
        ]);
        if (count($users)) {
            // Avoid a hack of the database.
            sleep(1);
            $this->logger()->warn(
                'User #{user_id} wants to change email from "{email}" to "{email_2}", used by user #{user_id_2} (second check).', // @translate
                ['user_id' => $user->getId(), 'email' => $user->getEmail(), 'email_2' => $email, 'user_id_2' => (reset($users))->getId()]
            );
            $message = new PsrMessage(
                'The email "{email}" is not yours.', // @translate
                ['email' => $email]
            );
            return $this->jSend(self::FAIL, [
                'email' => $message->setTranslator($this->translator),
            ]);
        }

        $site = $this->currentSite();

        $guestToken = $this->createGuestToken($user);
        $message = $this->prepareMessage('update-email', [
            'user_email' => $email,
            'user_name' => $user->getName(),
            'token' => $guestToken,
        ], $site);
        $result = $this->sendEmail($email, $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            $this->logger()->err('[GuestApi] An error occurred when the email was sent.'); // @translate
            return $this->jSend(self::ERROR, null,
                $this->translate('An error occurred when the email was sent.') // @translate,
            );
        }

        $message = new PsrMessage(
            'Check your email "{email}" to confirm the change.', // @translate
            ['email' => $email]
        );
        return $this->jSend(self::SUCCESS, [
            'user' => $this->userAdapter->getRepresentation($user),
        ], $message->setTranslator($this->translator));
    }

    /**
     * Create a new session token and get key_identity and key_credential.
     */
    protected function prepareSessionToken(User $user): array
    {
        $this->removeSessionTokens($user);

        // Create a new session token.
        $apiKey = new \Omeka\Entity\ApiKey;
        $apiKey->setId();
        $apiKey->setLabel('guest_session');
        $apiKey->setOwner($user);
        $apiKeyId = $apiKey->getId();
        $apiKeyCredential = $apiKey->setCredential();
        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        return [
            'key_identity' => $apiKeyId,
            'key_credential' => $apiKeyCredential,
        ];
    }

    protected function removeSessionTokens(User $user): void
    {
        // Remove all existing session tokens.
        $keys = $user->getKeys();
        foreach ($keys as $keyId => $key) {
            if ($key->getLabel() === 'guest_session') {
                $keys->remove($keyId);
            }
        }
        $this->entityManager->flush();
    }

    /**
     * Get the site to prepare theme of the site for the dialog template.
     *
     * @see \Omeka\Mvc\MvcListeners::preparePublicSite()
     */
    protected function prepareSiteTemplates(): void
    {
        $siteSlug = $this->params()->fromQuery('site_slug')
            ?: $this->viewHelpers()->get('defaultSite')('slug');
        if (!$siteSlug) {
            return;
        }

        try {
            $site = $this->api()->read('sites', ['slug' => $siteSlug])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $this->logger()->err(
                '[Guest] Site #{site_slug} is not available.', // @translate
                ['site_slug' => $siteSlug]
            );
            return;
        }

        // TODO It may be simpler if the route is a site one.
        /** @var \Laminas\Mvc\MvcEvent $mvcEvent */
        $mvcEvent = $site->getServiceLocator()->get('Application')->getMvcEvent();
        $routeMatch = $mvcEvent->getRouteMatch();
        $routeMatch
            ->setParam('__SITE__', true)
            ->setParam('site-slug', $siteSlug);
        $mvcListeners = new \Omeka\Mvc\MvcListeners();
        $mvcListeners->preparePublicSite($mvcEvent);
    }

    /**
     * Send output via json according to jSend.
     *
     * Notes:
     * - Unlike jSend, any status can have a main message and a code.
     * - For statuses fail and error, the error messages are taken from
     *   messenger messages when not set.
     *
     * @see https://github.com/omniti-labs/jsend
     *
     * @throws \Laminas\Mvc\Exception\RuntimeException
     * @deprecated Use \Common\Mvc\Controller\Plugin\JSend (since Common version 3.4.65).
     */
    protected function jSend(
        string $status,
        ?array $data = null,
        ?string $message = null,
        ?int $httpStatusCode = null,
        ?int $code = null
    ) {
        switch ($status) {
            case self::SUCCESS:
                $json = [
                    'status' => self::SUCCESS,
                    'data' => $data,
                ];
                if (isset($message) && strlen($message)) {
                    $json['message'] = $message;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                break;

            case self::FAIL:
                if (!$data) {
                    $message = $message
                        ?: $this->translatedMessages('error')
                        ?: $this->translate('Check your input for invalid data.'); // @translate
                    $data = ['fail' => $message];
                }
                $json = [
                    'status' => self::FAIL,
                    'data' => $data,
                ];
                if (isset($message) && strlen($message)) {
                    $json['message'] = $message;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                $httpStatusCode ??= Response::STATUS_CODE_400;
                break;

            case self::ERROR:
                $message = $message
                    ?: $this->translatedMessages('error')
                    ?: $this->translate('An internal error has occurred.'); // @translate
                $json = [
                    'status' => self::ERROR,
                    'message' => $message,
                ];
                if ($data) {
                    $json['data'] = $data;
                }
                if (isset($code)) {
                    $json['code'] = $code;
                }
                $httpStatusCode ??= Response::STATUS_CODE_500;
                break;

            default:
                throw new RuntimeException(sprintf('The status "%s" is not supported by jSend.', $status)); // @translate
        }

        if ($httpStatusCode) {
            /** @var \Laminas\Http\Response $response */
            $response = $this->getResponse();
            $response->setStatusCode($httpStatusCode);
        }

        return new JsonModel($json);
    }

    /**
     * @deprecated Use $this->viewHelpers()->get('messages')->getTranslatedMessages() (since Common version 3.4.65).
     */
    protected function translatedMessages(string $type, bool $asArray = false)
    {
        /** @var \Common\View\Helper\Messages $messages */
        $messages = $this->viewHelpers()->get('messages');
        if (method_exists($messages, 'getTranslatedMessages')) {
            $msgs = $messages->getTranslatedMessages();
        } else {
            $translate = $this->translate();
            $translator = $translate->getTranslator();
            $msgs = array_map(
                fn ($msg) => $msg instanceof TranslatorAwareInterface
                    ? $msg->setTranslator($translator)->translate()
                    : $translate($msg),
                $messages->get()
            );
        }

        $msgs = $msgs[$type] ?? [];
        return $asArray
            ? $msgs
            : implode("\n", $msgs);
    }
}
