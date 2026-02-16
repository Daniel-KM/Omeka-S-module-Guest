<?php declare(strict_types=1);

namespace Guest\Controller\Site;

use Common\Stdlib\PsrMessage;
use Guest\Entity\GuestToken;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\Site;
use Omeka\Entity\SitePermission;
use Omeka\Entity\User;
use Omeka\Form\ForgotPasswordForm;
use Omeka\Form\LoginForm;
use TwoFactorAuth\Form\TokenForm;

/**
 * Manage anonymous visitor pages.
 */
class AnonymousController extends AbstractGuestController
{
    public function loginAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        /**
         * @var \Laminas\Http\PhpEnvironment\Request $request
         */

        $site = $this->currentSite();
        $settings = $this->settings();
        $siteSettings = $this->siteSettings();

        // The process is slightly different from module TwoFactorAuth, because
        // there is no route for login-token.

        // Further, the ajax for 2fa-login is managed by module TwoFactorAuth.

        // The site setting may be overridden with a site block, so check if a
        // block has such option.
        $loginWithoutForm = $this->isLoginWIthoutForm();

        // The TokenForm returns to the login action, so check it when needed.
        $request = $this->getRequest();
        $isPost = $request->isPost();
        if (!$loginWithoutForm
            && $isPost
            && ($request->getPost('token_email') || $request->getPost('submit_token'))
        ) {
            return $this->loginToken();
        }

        if (!$isPost && $request->getQuery('resend_token')) {
            return $this->resendToken();
        }

        /** @var LoginForm $form */
        $form = $loginWithoutForm
            ? null
            : $this->getForm($this->hasModuleUserNames() ? \UserNames\Form\LoginForm::class : LoginForm::class);


        if ($siteSettings->get('guest_login_with_register')
            && $settings->get('guest_open', 'moderate') !== 'closed'
        ) {
            // Needed to prepare the form.
            $roleDefault = $this->getDefaultRole();
            $user = new User();
            $user->setRole($roleDefault);
            $formRegister = $this->getUserForm($user, 'register');
            $urlRegister = $this->url()->fromRoute('site/guest/anonymous', [
                'site-slug' => $this->currentSite()->slug(),
                'controller' => \Guest\Controller\Site\AnonymousController::class,
                'action' => 'register',
            ]);
            $formRegister->setAttribute('action', $urlRegister);
        }

        $view = new ViewModel([
            'site' => $site,
            'form' => $form,
            'formRegister' => $formRegister ?? null,
            'formToken' => null,
            'htmlBeforeLogin' => $siteSettings->get('guest_login_html_before') ?: null,
            'htmlAfterLogin' => $siteSettings->get('guest_login_html_after') ?: null,
            'htmlBeforeRegister' => $siteSettings->get('guest_register_html_before') ?: null,
            'htmlAfterRegister' => $siteSettings->get('guest_register_html_after') ?: null,
        ]);

        if (!$loginWithoutForm
            && $settings->get('twofactorauth_use_dialog')
            && $this->getPluginManager()->has('twoFactorLogin')
        ) {
            // For ajax, use standard action.
            $form->setAttribute('action', $this->url()->fromRoute('login'));
            $view
                ->setVariable('formToken', $this->getForm(TokenForm::class)->setAttribute('action', $this->url()->fromRoute('login')));
        }

        if ($form) {
            $result = $this->validateLogin($form);
            if ($result === null) {
                // Internal error (no mail sent).
                return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], true);
            } elseif ($result === false) {
                // Email or password error, so retry.
                // Slow down the process to avoid brute force.
                sleep(1);
                return $view;
            } elseif ($result === 0) {
                // Email or password error in 2FA. Sleep is already processed.
                return $view;
            } elseif ($result === 1) {
                // Success login in first step in 2FA, so go to second step.
                // Here, there is no ajax.
                return $view
                    ->setVariable('formToken', $this->getForm(TokenForm::class))
                    ->setTemplate('guest/site/anonymous/login-token');
            } elseif (is_string($result)) {
                // Moderation or confirmation missing or other issue.
                $this->messenger()->addError($result);
                return $view;
            }
            // Here, the user is authenticated.
        } elseif (!$this->request->isPost()) {
            // Manage login without form.
            return $view;
        }

        return $this->redirectToAdminOrSite();
    }

    /**
     * @see \Guest\Controller\Site\AnonymousController::loginToken()
     * @see \Guest\Site\BlockLayout\Login::loginToken()
     * @see \TwoFactorAuth\Controller\LoginController::loginTokenAction()
     */
    protected function loginToken()
    {
        // Check if the first step was just processed.
        $isFirst = (bool) $this->getRequest()->getMetadata('first');

        // Ajax is managed by module TwoFactorAuth.

        if (!$isFirst && $this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost();
            $form = $this->getForm(TokenForm::class);
            $form->setData($data);
            if ($form->isValid()) {
                /**
                 * @var \Laminas\Http\PhpEnvironment\Request $request
                 * @var \TwoFactorAuth\Mvc\Controller\Plugin\TwoFactorLogin $twoFactorLogin
                 */
                $validatedData = $form->getData();
                $twoFactorLogin = $this->twoFactorLogin();
                $result = $twoFactorLogin->validateLoginStep2($validatedData['token_email']);
                if ($result === null) {
                    // Internal error (no mail sent).
                    return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], true);
                } elseif ($result) {
                    return $this->redirectToAdminOrSite();
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel([
            'site' => $this->currentSite(),
            'formToken' => $this->getForm(TokenForm::class),
            'formRegister' => null,
        ]);
        return $view
            ->setTemplate('guest/site/anonymous/login-token');
    }

    /**
     * Adapted:
     * @see \Guest\Controller\Site\AnonymousController::resendToken();
     * @see \Guest\Site\BlockLayout\Login::resendToken()
     * @see \TwoFactorAuth\Controller\LoginController::resendTokenAction();
     */
    protected function resendToken()
    {
        $request = $this->getRequest();
        $codeKey = $request->getQuery('resend_token');
        if ($codeKey) {
            /** @var \TwoFactorAuth\Mvc\Controller\Plugin\TwoFactorLogin $twoFactorLogin */
            $twoFactorLogin = $this->twoFactorLogin();
            $result = $twoFactorLogin->resendToken();
        } else {
            $result = false;
        }

        // Ajax is managed by module TwoFactorAuth.

        $result
            ? $this->messenger()->addSuccess('A new code was resent.') // @translate
            : $this->messenger()->addError('Unable to send email.'); // @translate

        $view = new ViewModel([
            'site' => $this->currentSite(),
            'formToken' => $this->getForm(TokenForm::class),
            'formRegister' => null,
        ]);
        return $view
            ->setTemplate('guest/site/anonymous/login-token');
    }

    public function registerAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $site = $this->currentSite();
        $settings = $this->settings();
        $siteSettings = $this->siteSettings();

        // To create user with admin role, don't use register, but /api/users.
        // Anyway, there should not be a lot of admins, so they should be
        // managed manually.
        $roleDefault = $this->getDefaultRole();

        $user = new User();
        $user->setRole($roleDefault);
        $form = $this->getUserForm($user, 'register');

        $view = new ViewModel([
            'site' => $site,
            'form' => $form,
            'htmlBeforeRegister' => $siteSettings->get('guest_register_html_before') ?: null,
            'htmlAfterRegister' => $siteSettings->get('guest_register_html_after') ?: null,
        ]);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        // TODO Add password required only for login.
        $values = $form->getData();

        // Extract password using helper (handles both old and new form structures).
        $password = $this->extractPasswordFromFormValues($values);
        if (empty($password)) {
            $this->messenger()->addError('A password must be set.'); // @translate
            return $view;
        }

        $roleUser = $this->isAllowedRole($values['user-information']['o:role'] ?? null, 'register')
            ? $values['user-information']['o:role']
            : $roleDefault;

        $userInfo = $values['user-information'];
        // TODO Avoid to set the right to change role (fix core).
        $userInfo['o:role'] = $roleUser;
        $userInfo['o:is_active'] = false;

        // Before creation, check the email too to manage confirmation, rights
        // and module UserNames.
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getEntityManager();
        $user = $entityManager->getRepository(User::class)->findOneBy([
            'email' => $userInfo['o:email'],
        ]);
        if ($user) {
            $guestToken = $entityManager->getRepository(GuestToken::class)
                ->findOneBy(['email' => $userInfo['o:email']], ['id' => 'DESC']);
            if (empty($guestToken) || $guestToken->isConfirmed()) {
                $this->messenger()->addError('Already registered.'); // @translate
            } else {
                // TODO Check if the token is expired to ask a new one.
                $this->messenger()->addError('Check your email to confirm your registration.'); // @translate
            }
            return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], true);
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
            $userName->setUserName($userInfo['o-module-usernames:username'] ?? '');
            $errorStore = new \Omeka\Stdlib\ErrorStore;
            $userNameAdapter->validateEntity($userName, $errorStore);
            // Only the user name is validated here.
            $errors = $errorStore->getErrors();
            if (!empty($errors['o-module-usernames:username'])) {
                foreach ($errors['o-module-usernames:username'] as $message) {
                    $this->messenger()->addError($message);
                }
                return $view;
            }
        }

        // Check the creation of the user to manage the creation of usernames:
        // the exception occurs in api.create.post, so user is created.
        try {
            /** @var \Omeka\Entity\User $user */
            $userResponse = $this->api()->create('users', $userInfo, [], ['responseContent' => 'resource']);
            if (!$userResponse) {
                throw new \Exception();
            }
            $user = $userResponse->getContent();
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            // This is the exception thrown by the module UserNames, so the user
            // is created, but not the username.
            // Anonymous user cannot read User, so use entity manager.
            $user = $entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            // An error occurred in another module.
            if (!$user) {
                $this->messenger()->addError('Unknown error before creation of user.'); // @translate
                return $view;
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
                    $entityManager->persist($userName);
                    $entityManager->flush();
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
            $user = $entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            if (!$user) {
                $this->messenger()->addError('Unknown error during creation of user.'); // @translate
                return $view;
            }
            // Issue in another module?
            // Log error, but continue registering (email is checked before,
            // so it is a new user in any case).
        }

        $user->setPassword($password);
        $user->setRole($roleUser);
        // The account is active, but not confirmed, so login is not possible.
        // Guest user has no right to set active his account.
        // Except if the option "email is valid" is set.
        $isOpenRegister = $this->isOpenRegister();
        $user->setIsActive($isOpenRegister);

        // Add guest user to default sites.
        $this->addUserToDefaultSites($user);

        $entityManager->flush();

        // Process user settings from form values.
        $this->processUserSettings($user, $values);

        // Save the site on which the user registered.
        $this->userSettings()->set('guest_site', $this->currentSite()->id(), $user->getId());

        // Send confirmation email to user.
        $guestToken = $this->createGuestToken($user);
        $result = $this->sendRegistrationConfirmationEmail($user, $guestToken, $site);
        if (!$result) {
            $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[Guest] An error occurred when the email was sent.'); // @translate
            return $view;
        }

        // Send notification to administrators about new registration.
        $message = $this->prepareMessage('notify-registration', [
            'user_email' => $user->getEmail(),
            'user_name' => $user->getName(),
            'site' => $site,
        ]);
        $toEmails = $settings->get('guest_notify_register') ?: null;
        $this->sendEmail($message['body'], $message['subject'], $toEmails);

        $message = $this->isOpenRegister()
            ? $this->getOption('guest_message_confirm_register_site')
            : $this->getOption('guest_message_confirm_register_moderate_site');
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], [], true);
    }

    public function confirmAction()
    {
        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();
        $guestToken = $entityManager->getRepository(GuestToken::class)->findOneBy(['token' => $token]);
        if (empty($guestToken)) {
            $this->messenger()->addError($this->translate('Invalid token stop')); // @translate
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $guestToken->setConfirmed(true);
        $entityManager->persist($guestToken);
        $user = $entityManager->find(User::class, $guestToken->getUser()->getId());

        $isOpenRegister = $this->isOpenRegister();

        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setIsActive($isOpenRegister);
        $entityManager->persist($user);
        $entityManager->flush();

        $currentSite = $this->currentSite();
        $siteTitle = $currentSite->title();
        if ($isOpenRegister) {
            $body = new PsrMessage(
                'Thanks for joining {site_title}! You can now log in using the password you chose.', // @translate
                ['site_title' => $siteTitle]
            );
            $this->messenger()->addSuccess($body);
            $redirectUrl = $this->url()->fromRoute('site/guest/anonymous', [
                'site-slug' => $currentSite->slug(),
                'action' => 'login',
            ]);
            return $this->redirect()->toUrl($redirectUrl);
        }

        $body = new PsrMessage(
            'Thanks for joining {site_title}! Your registration is under moderation. See you soon!', // @translate
            ['site_title' => $siteTitle]
        );
        $this->messenger()->addSuccess($body);
        $redirectUrl = $currentSite->url();
        return $this->redirect()->toUrl($redirectUrl);
    }

    public function confirmEmailAction()
    {
        return $this->confirmEmail(true);
    }

    public function validateEmailAction()
    {
        return $this->confirmEmail(false);
    }

    protected function confirmEmail(bool $isCreate = true): \Laminas\Http\Response
    {
        $token = $this->params()->fromQuery('token');
        $entityManager = $this->getEntityManager();

        $siteTitle = $this->currentSite()->title();

        $guestToken = $entityManager->getRepository(GuestToken::class)->findOneBy(['token' => $token]);
        if (empty($guestToken)) {
            $message = new PsrMessage(
                'Invalid token: your email was not confirmed for {site_title}.', // @translate
                ['site_title' => $siteTitle]
            );

            $this->messenger()->addError($message); // @translate
            if ($this->isUserLogged()) {
                $redirectUrl = $this->url()->fromRoute('site/guest/guest', [
                    'site-slug' => $this->currentSite()->slug(),
                    'action' => 'update-email',
                ]);
            } else {
                $redirectUrl = $this->url()->fromRoute('site/guest/anonymous', [
                    'site-slug' => $this->currentSite()->slug(),
                    'action' => 'login',
                ]);
            }
            return $this->redirect()->toUrl($redirectUrl);
        }

        $guestToken->setConfirmed(true);
        $entityManager->persist($guestToken);

        // Validate all tokens with the same email to avoid issues when the
        // email is checked after login.
        $email = $guestToken->getEmail();
        $guestTokens = $entityManager->getRepository(GuestToken::class)->findBy(['email' => $email]);
        foreach ($guestTokens as $gToken) {
            $gToken->setConfirmed(true);
            $entityManager->persist($gToken);
        }

        $user = $entityManager->find(User::class, $guestToken->getUser()->getId());
        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setEmail($email);
        $entityManager->persist($user);
        $entityManager->flush();

        // The message is not the same for a new user or an existing user.
        // Furthermore, a notification should be sent for information and
        // moderation.
        if ($isCreate) {
            // Send notification.
            $emails = $this->getOption('guest_notify_register') ?: [];
            if ($emails) {
                $message = new PsrMessage(
                    'A new user is registering and has confirmed email: {user_email} ({url}).', // @translate
                    [
                        'user_email' => $user->getEmail(),
                        'url' => $this->url()->fromRoute('admin/id', ['controller' => 'user', 'id' => $user->getId()], ['force_canonical' => true]),
                    ]
                );
                $result = $this->sendEmail($message, $this->translate('[Omeka Guest] New registration'), $emails); // @translate
                if (!$result) {
                    $message = new PsrMessage('An error occurred when the notification email was sent.'); // @translate
                    $this->messenger()->addError($message);
                    $this->logger()->err('[Guest] An error occurred when the notification email was sent.'); // @translate
                }
            }
            // Display the confirmation message in all cases.
            $message = new PsrMessage(
                $this->getOption('guest_message_confirm_email_site'),
                ['user_email' => $email, 'site_title' => $siteTitle]
            );
            $this->messenger()->addSuccess($message);
        } else {
            $message = new PsrMessage(
                'Your email "{user_email}" is confirmed for {site_title}.', // @translate
                ['user_email' => $email, 'site_title' => $siteTitle]
            );
            $this->messenger()->addSuccess($message);
        }

        if ($this->isUserLogged()) {
            $redirectUrl = $this->url()->fromRoute('site/guest', [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'me',
            ]);
        } else {
            $redirectUrl = $this->url()->fromRoute('site/guest/anonymous', [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'login',
            ]);
        }
        return $this->redirect()->toUrl($redirectUrl);
    }

    public function forgotPasswordAction()
    {
        if ($this->isUserLogged()) {
            return $this->redirectToAdminOrSite();
        }

        $site = $this->currentSite();

        $form = $this->getForm(ForgotPasswordForm::class);

        $view = new ViewModel([
            'site' => $site,
            'form' => $form,
        ]);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data = $this->getRequest()->getPost();
        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addError('Activation unsuccessful'); // @translate
            return $view;
        }

        $entityManager = $this->getEntityManager();
        $user = $entityManager->getRepository(User::class)
            ->findOneBy([
                'email' => $data['email'],
                'isActive' => true,
            ]);
        if ($user) {
            $entityManager->persist($user);
            $passwordCreation = $entityManager
                ->getRepository(\Omeka\Entity\PasswordCreation::class)
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $entityManager->remove($passwordCreation);
                $entityManager->flush();
            }
            $this->mailer()->sendResetPassword($user);
        }

        $this->messenger()->addSuccess('Check your email for instructions on how to reset your password'); // @translate

        // Bypass settings.
        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        $siteSlug = $this->params('site-slug');
        return $siteSlug && !$this->params('outside')
            ? $this->redirect()->toRoute('site', ['site-slug' => $siteSlug])
            : $this->redirect()->toRoute('top');
    }

    public function staleTokenAction(): void
    {
        $auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
        $auth->clearIdentity();
    }

    public function authErrorAction()
    {
        return new ViewModel([
            'site' => $this->currentSite(),
        ]);
    }

    protected function checkPostAndValidForm(\Laminas\Form\Form $form): bool
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        $postData = $this->params()->fromPost();
        $form->setData($postData);
        if ($form->isValid()) {
            return true;
        }

        // For translations.
        // TODO Get all laminas messages in omeka.
        $messages = [
            "Value is required and can't be empty", // @translate
        ];

        /** @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger */
        $messenger = $this->messenger();
        $messages = $form->getMessages();

        // Check if the issue is password related, in which case simplify
        // the message for security.
        if (!empty($messages['change-password'])) {
            empty($this->hasModuleUserName)
               ? $this->messenger()->addError('Email or password invalid') // @translate
                : $this->messenger()->addError('User name, email, or password is invalid'); // @translate
            unset($messages['change-password']);
        }

        if (!empty($messages)) {
            $messenger->addErrors($messages);
        }

        return false;
    }
}
